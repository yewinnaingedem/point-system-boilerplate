<?php

namespace App\Console\Commands;

use App\Enums\SystemRole;
use App\Models\User;
use App\Services\PerformanceData\CustomerHistory;
use App\Services\PerformanceData\IdSequence;
use App\Services\PerformanceData\RowBuffer;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Modules\Loyalty\Enums\TierLevel;
use Modules\Loyalty\Services\TierConfigService;
use Modules\Loyalty\Support\PointExpiryPolicy;
use Modules\Loyalty\Support\TierStateMachine;
use Modules\Merchant\Support\BranchCodeGenerator;
use Spatie\Permission\Models\Role;

/**
 * Bulk data for performance testing: 100k customers (by default) with a year of purchases,
 * tiers, points with expiry, merchant redemptions, gift card exchanges and reversals, plus
 * staff users, merchants, branches, rewards and gift cards.
 *
 * Rows are simulated in PHP with the app's own rules (see CustomerHistory) and written with
 * multi-row INSERTs, so it takes minutes, not the days the real services would need, and the
 * books still agree. Every row is tagged (customer ids perf-N, @perf.pos.test emails, merchant
 * notes, gift card descriptions) and `--remove` deletes exactly that data.
 */
class SeedPerformanceData extends Command
{
    protected $signature = 'perf:seed
        {--customers=100000 : Customers to create}
        {--users=1000 : Staff users to create}
        {--merchants=200 : Merchants (5 branches and 4 rewards each)}
        {--gift-cards=30 : Gift card types}
        {--days=365 : Days of history}
        {--chunk=500 : Customers per database transaction}
        {--seed=2026 : Random seed (same data every run)}
        {--remove : Delete the performance data instead of creating it}
        {--force : Allow running in production}';

    protected $description = 'Create (or --remove) bulk customers, points, tiers, merchants, redemptions and gift cards for performance testing';

    public const TAG = 'Performance data (perf:seed)';

    private const BRANCHES_PER_MERCHANT = 5;

    private const REWARDS_PER_MERCHANT = 4;

    private const DELETE_CHUNK = 1000;

    private const SHOPS = ['KFC', 'Cafe Amazon', 'City Mart', 'Lotteria', 'Yoma Bakery', 'Shwe Pu Zun', 'Ocean', 'Capital',
        'Sein Gay Har', 'Seasons Bakery', 'Burger King', 'Pizza Hut', 'Gloria Jean\'s', 'Parisian', 'Marketplace'];

    private const PLACES = ['Junction City', 'Myay Ni Gone', 'Hledan', 'Sule Square', 'Taw Win', 'Pearl Condo', 'Thamada',
        'Bogyoke', 'Kamayut', 'Sanchaung', 'Bahan', 'Tamwe', 'Insein', 'Mandalay 78th', 'Naypyitaw'];

    private const REWARDS = [['Soft drink', 60], ['Snack', 150], ['Meal set', 400], ['5,000 Ks voucher', 600],
        ['Family set', 1200], ['20,000 Ks voucher', 2000]];

    public function __construct(private readonly ConnectionInterface $db)
    {
        parent::__construct();
    }

    public function handle(TierConfigService $tiers, PointExpiryPolicy $expiry, TierStateMachine $machine, BranchCodeGenerator $codes): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('This is a production environment. Re-run with --force if you really want performance data here.');

            return self::FAILURE;
        }

        if ($this->option('remove')) {
            return $this->remove();
        }

        if ($this->db->table('customers')->where('external_id', 'like', CustomerHistory::CUSTOMER_PREFIX.'%')->exists()) {
            $this->warn('Performance data already exists. Run with --remove first to recreate it.');

            return self::FAILURE;
        }

        mt_srand((int) $this->option('seed'));
        $started = microtime(true);
        $rows = new RowBuffer($this->db);
        $ids = new IdSequence($this->db);
        $now = time();

        $this->components->task('Staff users, merchants, branches, rewards, gift cards', function () use ($rows, $ids, $codes, $now, &$catalogue) {
            $this->db->transaction(function () use ($rows, $ids, $codes, $now, &$catalogue) {
                $this->staff($rows, $ids, (int) $this->option('users'), $now);
                $catalogue = [
                    'branches' => $this->merchants($rows, $ids, $codes, (int) $this->option('merchants'), $now),
                    'gift_cards' => $this->giftCards($rows, $ids, (int) $this->option('gift-cards'), $now),
                ];
                $rows->flush();
            });
        });

        $history = new CustomerHistory(
            $rows, $ids, $machine, $tiers->ladder(), $tiers->cycleMonths(), $expiry->months(), $expiry->cutoffDay(),
            $catalogue['branches'], $catalogue['gift_cards'], $this->adminId(), $now, max(1, (int) $this->option('days')),
        );

        $total = (int) $this->option('customers');
        $chunk = max(1, (int) $this->option('chunk'));
        $this->info("Simulating {$total} customers (loyalty cycle {$tiers->cycleMonths()} month(s), points expire after "
            .($expiry->months() ?: 'never').' month(s), cutoff day '.$expiry->cutoffDay().')');
        $bar = $this->output->createProgressBar($total);

        for ($n = 1; $n <= $total; $n += $chunk) {
            $this->db->transaction(function () use ($history, $rows, $n, $chunk, $total) {
                for ($i = $n; $i < min($n + $chunk, $total + 1); $i++) {
                    $history->simulate($i);
                }
                $rows->flush();
            });
            $bar->advance(min($chunk, $total - $n + 1));
        }
        $bar->finish();
        $this->newLine(2);

        $this->deactivateSome();
        $this->report($rows->written(), $history->stats(), microtime(true) - $started);

        return self::SUCCESS;
    }

    private function staff(RowBuffer $rows, IdSequence $ids, int $count, int $now): void
    {
        $password = Hash::make('password'); // once: hashing 1,000 times would take a minute
        $roles = Role::query()->whereIn('name', [SystemRole::Manager->value, SystemRole::Cashier->value])->pluck('id', 'name');

        for ($n = 1; $n <= $count; $n++) {
            $id = $ids->next('users');
            $created = mt_rand($now - 365 * 86400, $now);
            $rows->add('users', [
                'id' => $id,
                'name' => "Staff {$n}",
                'email' => "staff{$n}@".CustomerHistory::EMAIL_DOMAIN,
                'phone' => sprintf('07%09d', $n),
                'is_active' => mt_rand(1, 100) > 5,
                'last_login_at' => mt_rand(1, 4) === 1 ? null : date('Y-m-d H:i:s', mt_rand($created, $now)),
                'password' => $password,
                'created_at' => date('Y-m-d H:i:s', $created),
                'updated_at' => date('Y-m-d H:i:s', $created),
                'deleted_at' => mt_rand(1, 50) === 1 ? date('Y-m-d H:i:s', mt_rand($created, $now)) : null,
            ]);

            $role = $roles[$n % 10 === 0 ? SystemRole::Manager->value : SystemRole::Cashier->value] ?? null;
            if ($role !== null) {
                $rows->add('model_has_roles', ['role_id' => $role, 'model_type' => (new User)->getMorphClass(), 'model_id' => $id]);
            }
        }
    }

    /** @return list<array<string, mixed>> branches with their merchant's rewards, for the simulation */
    private function merchants(RowBuffer $rows, IdSequence $ids, BranchCodeGenerator $codes, int $count, int $now): array
    {
        $branches = [];
        $stamp = date('Y-m-d H:i:s', $now - 400 * 86400);

        for ($n = 1; $n <= $count; $n++) {
            $id = $ids->next('merchants');
            $name = self::SHOPS[$n % count(self::SHOPS)].' #'.$n;
            $rate = (string) mt_rand(4, 12);
            $rows->add('merchants', [
                'id' => $id, 'name' => $name, 'contact_person' => "Manager {$n}", 'phone' => sprintf('09 %03d %03d %03d', $n % 1000, mt_rand(0, 999), mt_rand(0, 999)),
                'email' => "merchant{$n}@".CustomerHistory::EMAIL_DOMAIN, 'address' => 'Yangon', 'settlement_rate' => $rate,
                'notes' => self::TAG, 'is_active' => true, 'created_at' => $stamp, 'updated_at' => $stamp,
            ]);

            $rewards = [];
            foreach (array_rand(self::REWARDS, self::REWARDS_PER_MERCHANT) as $r) {
                [$rewardName, $points] = self::REWARDS[$r];
                $payout = mt_rand(0, 1) ? null : (string) ($points * 9);
                $rewardId = $ids->next('merchant_rewards');
                $rows->add('merchant_rewards', [
                    'id' => $rewardId, 'merchant_id' => $id, 'name' => $rewardName, 'description' => null, 'points_cost' => $points,
                    'payout_amount' => $payout, 'is_active' => true, 'created_at' => $stamp, 'updated_at' => $stamp,
                ]);
                $rewards[] = ['id' => $rewardId, 'name' => $rewardName, 'points' => $points,
                    'payout' => $payout !== null ? bcadd($payout, '0', 2) : bcmul((string) $points, $rate, 2)];
            }

            for ($b = 1; $b <= self::BRANCHES_PER_MERCHANT; $b++) {
                $branchId = $ids->next('merchant_branches');
                $place = self::PLACES[mt_rand(0, count(self::PLACES) - 1)];
                $rows->add('merchant_branches', [
                    'id' => $branchId, 'merchant_id' => $id, 'name' => "{$place} {$b}", 'phone' => null, 'address' => "{$place}, Yangon",
                    'code' => Crypt::encryptString($codes->generate()), 'code_changed_at' => $stamp,
                    'is_active' => true, 'created_at' => $stamp, 'updated_at' => $stamp,
                ]);
                $branches[] = ['id' => $branchId, 'merchant_id' => $id, 'label' => "{$name} {$place} {$b}", 'rewards' => $rewards];
            }
        }

        return $branches;
    }

    /** @return list<array<string, mixed>> */
    private function giftCards(RowBuffer $rows, IdSequence $ids, int $count, int $now): array
    {
        $cards = [];
        $stamp = date('Y-m-d H:i:s', $now - 400 * 86400);
        $tiers = [null, null, TierLevel::Gold, TierLevel::Platinum, TierLevel::Diamond];

        for ($n = 1; $n <= $count; $n++) {
            $id = $ids->next('gift_cards');
            $points = [100, 250, 500, 1000, 2500][$n % 5];
            $tier = $tiers[mt_rand(0, count($tiers) - 1)];
            $validDays = [null, 30, 90, 365][mt_rand(0, 3)];
            $face = (string) ($points * 10);
            $rows->add('gift_cards', [
                'id' => $id, 'name' => number_format($points * 10).' Ks gift card #'.$n, 'description' => self::TAG,
                'points_cost' => $points, 'face_value' => $face, 'min_tier' => $tier?->value,
                'stock' => null, 'per_customer_limit' => null, 'requires_verification' => mt_rand(1, 4) === 1,
                'valid_days' => $validDays, 'is_active' => true, 'created_at' => $stamp, 'updated_at' => $stamp,
            ]);
            $cards[] = ['id' => $id, 'name' => number_format($points * 10).' Ks gift card #'.$n, 'points' => $points,
                'face_value' => bcadd($face, '0', 2), 'min_rank' => $tier?->rank(), 'valid_days' => $validDays];
        }

        return $cards;
    }

    /** A few merchants and gift cards retired after collecting history, as in real use. */
    private function deactivateSome(): void
    {
        $this->db->table('merchants')->where('notes', self::TAG)->whereRaw('id % 20 = 0')->update(['is_active' => false]);
        $this->db->table('gift_cards')->where('description', self::TAG)->whereRaw('id % 10 = 0')->update(['is_active' => false]);
    }

    private function adminId(): ?int
    {
        return User::role(SystemRole::Administrator->value)->orderBy('id')->value('id');
    }

    /**
     * @param  array<string, int>  $written
     * @param  array<string, int>  $stats
     */
    private function report(array $written, array $stats, float $seconds): void
    {
        ksort($written);
        $this->table(['Table', 'Rows'], array_map(fn ($table, $count) => [$table, number_format($count)], array_keys($written), $written));
        $this->table(['Simulated', 'Count'], array_map(fn ($what, $count) => [$what, number_format($count)], array_keys($stats), $stats));
        $this->info(sprintf('Done in %.1f s (%s rows). Staff users sign in with staffN@%s / password.',
            $seconds, number_format(array_sum($written)), CustomerHistory::EMAIL_DOMAIN));
        $this->info('Remove with: php artisan perf:seed --remove');
    }

    private function remove(): int
    {
        $started = microtime(true);
        $merchants = $this->db->table('merchants')->where('notes', self::TAG)->select('id');
        $cards = $this->db->table('gift_cards')->where('description', self::TAG)->select('id');
        $customers = $this->db->table('customers')->where('external_id', 'like', CustomerHistory::CUSTOMER_PREFIX.'%');
        $users = $this->db->table('users')->where('email', 'like', '%@'.CustomerHistory::EMAIL_DOMAIN);

        $deleted = [
            // Redemptions and exchanges first: merchants and gift cards refuse to go while they exist.
            'merchant_redemptions' => $this->deleteInChunks($this->db->table('merchant_redemptions')->whereIn('merchant_id', $merchants)),
            'gift_card_exchanges' => $this->deleteInChunks($this->db->table('gift_card_exchanges')->whereIn('gift_card_id', $cards)),
            // Points, lots, summaries, tiers, spending and history cascade with the customer.
            'customers' => $this->deleteInChunks($customers, function (array $chunk) {
                $this->db->table('personal_access_tokens')->where('tokenable_type', 'like', '%Customer')->whereIn('tokenable_id', $chunk)->delete();
            }),
            'merchants' => $this->db->table('merchants')->where('notes', self::TAG)->delete(), // branches, rewards cascade
            'gift_cards' => $this->db->table('gift_cards')->where('description', self::TAG)->delete(),
            'users' => $this->deleteInChunks($users, function (array $chunk) {
                $this->db->table('model_has_roles')->where('model_type', (new User)->getMorphClass())->whereIn('model_id', $chunk)->delete();
                $this->db->table('model_has_permissions')->where('model_type', (new User)->getMorphClass())->whereIn('model_id', $chunk)->delete();
                $this->db->table('personal_access_tokens')->where('tokenable_type', (new User)->getMorphClass())->whereIn('tokenable_id', $chunk)->delete();
                $this->db->table('sessions')->whereIn('user_id', $chunk)->delete();
            }),
        ];

        $this->table(['Removed', 'Rows'], array_map(fn ($table, $count) => [$table, number_format($count)], array_keys($deleted), $deleted));
        $this->info(sprintf('Done in %.1f s.', microtime(true) - $started));

        return self::SUCCESS;
    }

    /** Delete matching rows a chunk of ids at a time, so cascades never run as one huge transaction. */
    private function deleteInChunks($query, ?callable $before = null): int
    {
        $deleted = 0;
        $table = $query->from;

        while (($chunk = (clone $query)->orderBy('id')->limit(self::DELETE_CHUNK)->pluck('id')->all()) !== []) {
            $this->db->transaction(function () use ($table, $chunk, $before, &$deleted) {
                if ($before !== null) {
                    $before($chunk);
                }
                $deleted += $this->db->table($table)->whereIn('id', $chunk)->delete();
            });
        }

        return $deleted;
    }
}
