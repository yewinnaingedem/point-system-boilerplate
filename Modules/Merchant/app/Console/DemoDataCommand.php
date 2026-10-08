<?php

namespace Modules\Merchant\Console;

use App\Enums\SystemRole;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Customer\Models\Customer;
use Modules\Loyalty\Enums\PointTransactionType;
use Modules\Loyalty\Services\PointWallet;
use Modules\Loyalty\Services\TierQualificationEngine;
use Modules\Merchant\Exceptions\RedemptionRejected;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantBranch;
use Modules\Merchant\Models\MerchantReward;
use Modules\Merchant\Models\Redemption;
use Modules\Merchant\Services\MerchantService;
use Modules\Merchant\Services\RedemptionService;

/**
 * Sample merchants, customers, purchases and redemptions for trying the screens out.
 *
 * Everything goes through the real services (MerchantService, TierQualificationEngine,
 * PointWallet, RedemptionService) with the clock set to each event's date, so balances,
 * histories, tiers and payouts are as consistent as real usage. Demo rows are tagged
 * (merchant notes, customer partner ids demo-N) and `--remove` deletes exactly those.
 */
class DemoDataCommand extends Command
{
    protected $signature = 'merchant:demo-data
        {--remove : Delete the demo data instead of creating it}
        {--force : Allow running in production}';

    protected $description = 'Create (or --remove) sample merchants, customers, points and redemptions';

    public const MERCHANT_TAG = 'Demo data (merchant:demo-data)';

    public const MEMBER_DOMAIN = 'demo.pos.test';

    /** Partner ids of demo customers: demo-1, demo-2, ... */
    public const CUSTOMER_PREFIX = 'demo-';

    /** One point per this much spent (MMK). The Sales module will own this rule later. */
    private const SPEND_PER_POINT = 1000;

    private const DAYS_OF_HISTORY = 90;

    private const MERCHANTS = [
        ['KFC', 'Daw Thida', '09 420 111 222', 10, true,
            ['Junction City', 'Myay Ni Gone', 'Hledan Centre', 'Mandalay 78th Street'],
            [['Pepsi (medium)', 150, null], ['Zinger Burger', 500, null], ['2-piece Chicken Meal', 800, null], ['Family Bucket', 2000, 18000]]],
        ['Cafe Amazon', 'U Min Htet', '09 250 333 444', 8, true,
            ['Sule Square', 'Taw Win Center'],
            [['Croissant', 200, null], ['Iced Latte', 300, null], ['Coffee + Cake Set', 650, 5500]]],
        ['City Mart', 'Daw Nwe Ni', '09 777 555 666', 5, true,
            ['Pearl Condo', 'Junction Square', 'Thamada'],
            [['5,000 Ks voucher', 1000, 5000], ['10,000 Ks voucher', 1900, 10000]]],
        ['Lotteria', 'U Kyaw Soe', '09 450 777 888', 9, false,
            ['Bogyoke Market'],
            [['Burger Set', 700, null]]],
    ];

    private const MEMBERS = ['Aung Aung', 'Su Su Hlaing', 'Kyaw Zin Htet', 'Hnin Wai', 'Thura Naing', 'May Thu Kyaw', 'Zaw Min Oo', 'Ei Phyu Sin'];

    public function handle(
        MerchantService $merchants,
        TierQualificationEngine $tiers,
        PointWallet $wallet,
        RedemptionService $redemptions,
    ): int {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('This is a production environment. Re-run with --force if you really want demo data here.');

            return self::FAILURE;
        }

        if ($this->option('remove')) {
            return $this->remove();
        }

        if (Merchant::query()->where('notes', self::MERCHANT_TAG)->exists()) {
            $this->warn('Demo data already exists. Run with --remove first to recreate it.');

            return self::FAILURE;
        }

        mt_srand(2026); // same story every run
        $start = CarbonImmutable::now()->subDays(self::DAYS_OF_HISTORY)->setTime(10, 0);

        try {
            Carbon::setTestNow($start);
            $shops = $this->createMerchants($merchants);
            $customers = $this->createCustomers($tiers);

            $stats = ['sales' => 0, 'redeemed' => 0, 'skipped' => 0];
            foreach ($this->timeline($customers, $shops, $start) as $event) {
                Carbon::setTestNow($event['at']);

                if ($event['type'] === 'sale') {
                    $tiers->processTransaction($event['customer']->id, $event['amount'], $event['at']);
                    $wallet->credit($event['customer']->id, intdiv($event['amount'], self::SPEND_PER_POINT), PointTransactionType::Earn,
                        null, "Demo purchase INV-{$event['at']->format('ymd')}-".Str::upper(Str::random(4)));
                    $stats['sales']++;

                    continue;
                }

                try {
                    $redemptions->redeem($event['customer'], $event['branch']->id, $event['reward']->id, $event['branch']->code);
                    $stats['redeemed']++;
                } catch (RedemptionRejected) {
                    $stats['skipped']++; // not enough points yet: a real customer would also be refused
                }
            }

            Carbon::setTestNow(CarbonImmutable::now()->subDays(2)->setTime(15, 0));
            $reversed = $this->reverseTwo($redemptions);
            $this->deactivateRetiredMerchants();
        } finally {
            Carbon::setTestNow();
        }

        // Bring every customer's tier up to today (cycle rollovers, expired guarantees).
        foreach ($customers as $customer) {
            $tiers->evaluateUserTierStatus($customer->id);
        }

        $this->table(['What', 'Count'], [
            ['Merchants (1 inactive)', count($shops)],
            ['Branches', collect($shops)->sum(fn ($shop) => count($shop['branches']))],
            ['Rewards', collect($shops)->sum(fn ($shop) => count($shop['rewards']))],
            ['Demo customers (start at Silver)', count($customers)],
            ['Purchases (earned points + tier spending)', $stats['sales']],
            ['Redemptions', $stats['redeemed']],
            ['  of which reversed by admin', $reversed],
            ['Redemptions refused (not enough points)', $stats['skipped']],
        ]);
        $this->info('Open Customers, Merchants, Redemptions, Customer Points and Loyalty > Tiers in the admin. Remove with: php artisan merchant:demo-data --remove');

        return self::SUCCESS;
    }

    /**
     * Every merchant starts active so it collects history; retired ones are switched off at the end.
     *
     * @return list<array{merchant: Merchant, branches: list<MerchantBranch>, rewards: list<MerchantReward>}>
     */
    private function createMerchants(MerchantService $service): array
    {
        $shops = [];
        foreach (self::MERCHANTS as [$name, $contact, $phone, $rate, , $branchNames, $rewards]) {
            $merchant = $service->saveMerchant(new Merchant, [
                'name' => $name, 'contact_person' => $contact, 'phone' => $phone,
                'email' => Str::slug($name).'@'.self::MEMBER_DOMAIN, 'address' => 'Yangon',
                'settlement_rate' => $rate, 'notes' => self::MERCHANT_TAG, 'is_active' => true,
            ]);
            $branches = array_map(fn (string $branch) => $service->createBranch($merchant, [
                'name' => $branch, 'address' => "{$branch}, Yangon", 'phone' => $phone, 'is_active' => true,
            ]), $branchNames);
            $items = array_map(fn (array $reward) => $service->saveReward($merchant, new MerchantReward, [
                'name' => $reward[0], 'points_cost' => $reward[1], 'payout_amount' => $reward[2], 'is_active' => true,
            ]), $rewards);
            $shops[] = ['merchant' => $merchant, 'branches' => $branches, 'rewards' => $items];
        }

        return $shops;
    }

    /**
     * Customers as the partner project's sign-in would create them, enrolled at Silver.
     *
     * @return list<Customer>
     */
    private function createCustomers(TierQualificationEngine $tiers): array
    {
        return array_map(function (string $name, int $i) use ($tiers) {
            $customer = Customer::query()->create([
                'external_id' => self::CUSTOMER_PREFIX.($i + 1),
                'name' => $name,
                'email' => Str::slug($name, '.').'@'.self::MEMBER_DOMAIN,
                'phone' => sprintf('0990000%04d', $i + 1),
                'is_active' => true,
            ]);
            $tiers->enrol($customer->id);

            return $customer;
        }, self::MEMBERS, array_keys(self::MEMBERS));
    }

    /**
     * Purchases and redemptions over the last 90 days, oldest first. Big spenders shop more
     * often and buy more, so customers end up in different tiers.
     *
     * @return list<array<string, mixed>>
     */
    private function timeline(array $customers, array $shops, CarbonImmutable $start): array
    {
        $events = [];
        foreach ($customers as $i => $customer) {
            $profile = [[60000, 180000], [40000, 120000], [150000, 400000], [20000, 60000],
                [80000, 250000], [30000, 90000], [250000, 600000], [10000, 40000]][$i];
            $visits = 6 + ($i % 4) * 3;

            for ($v = 0; $v < $visits; $v++) {
                $at = $start->addMinutes(mt_rand(0, self::DAYS_OF_HISTORY * 24 * 60 - 60));
                $amount = (int) (round(mt_rand($profile[0], $profile[1]) / 500) * 500);
                $events[] = ['type' => 'sale', 'customer' => $customer, 'amount' => $amount, 'at' => $at];
            }
            for ($r = 0; $r < 2 + $i % 4; $r++) {
                $shop = $shops[mt_rand(0, count($shops) - 1)];
                $events[] = [
                    'type' => 'redeem', 'customer' => $customer,
                    'branch' => $shop['branches'][mt_rand(0, count($shop['branches']) - 1)],
                    'reward' => $shop['rewards'][mt_rand(0, count($shop['rewards']) - 1)],
                    'at' => $start->addDays(20)->addMinutes(mt_rand(0, (self::DAYS_OF_HISTORY - 21) * 24 * 60)),
                ];
            }
        }
        usort($events, fn ($a, $b) => $a['at'] <=> $b['at']);

        return $events;
    }

    /** An administrator reverses two redemptions (reward was not handed over). */
    private function reverseTwo(RedemptionService $service): int
    {
        $admin = User::role(SystemRole::Administrator->value)->orderBy('id')->first();
        if ($admin === null) {
            return 0;
        }

        $done = 0;
        foreach ($this->demoRedemptions()->latest('id')->take(2)->get() as $redemption) {
            $service->reverse($redemption, $admin, $done === 0 ? 'Reward out of stock' : 'Member changed their mind');
            $done++;
        }

        return $done;
    }

    /** Merchants marked inactive above keep their past redemptions but take no new ones. */
    private function deactivateRetiredMerchants(): void
    {
        $retired = array_column(array_filter(self::MERCHANTS, fn (array $m) => ! $m[4]), 0);
        Merchant::query()->where('notes', self::MERCHANT_TAG)->whereIn('name', $retired)->update(['is_active' => false]);
    }

    private function remove(): int
    {
        $merchantIds = Merchant::query()->where('notes', self::MERCHANT_TAG)->pluck('id');
        $customerIds = Customer::query()->where('external_id', 'like', self::CUSTOMER_PREFIX.'%')->pluck('id');

        DB::transaction(function () use ($merchantIds, $customerIds) {
            Redemption::query()->whereIn('merchant_id', $merchantIds)->delete();
            Merchant::query()->whereIn('id', $merchantIds)->delete(); // branches, rewards cascade
            Customer::query()->whereIn('id', $customerIds)->each(function (Customer $customer) {
                $customer->tokens()->delete();
                $customer->delete(); // points, tiers, spending, tier history cascade
            });
        });

        $this->info("Removed {$merchantIds->count()} demo merchants and {$customerIds->count()} demo customers with all their data.");

        return self::SUCCESS;
    }

    private function demoRedemptions()
    {
        return Redemption::query()->whereIn('merchant_id', Merchant::query()->where('notes', self::MERCHANT_TAG)->select('id'));
    }
}
