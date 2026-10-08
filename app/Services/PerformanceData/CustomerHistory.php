<?php

namespace App\Services\PerformanceData;

use Carbon\CarbonImmutable;
use Modules\GiftCard\Models\GiftCardExchange;
use Modules\Loyalty\Enums\TierLevel;
use Modules\Loyalty\Enums\TierTransition;
use Modules\Loyalty\Support\Amount;
use Modules\Loyalty\Support\TierDecision;
use Modules\Loyalty\Support\TierLadder;
use Modules\Loyalty\Support\TierState;
use Modules\Loyalty\Support\TierStateMachine;
use Modules\Merchant\Models\Redemption;

/**
 * Plays one customer's life (enrolment, purchases, redemptions, gift cards, adjustments,
 * reversals, expiry) in memory and hands the resulting rows to the RowBuffer.
 *
 * It follows the same rules as the real services, so the data is what months of real use
 * would leave behind:
 *  - tiers: the real TierStateMachine decides every step; the hourly evaluation runs at each
 *    status's next_evaluation_at (cycle end or guarantee expiry), as TierQualificationEngine does;
 *  - points: every credit is a lot (PointExpiryPolicy dates), debits take the soonest-expiring
 *    lots and record usages, reversals put points back into the same lots, due lots expire in the
 *    00:10 sweep (or lazily at a debit), and the monthly summary is kept with every ledger row.
 *
 * So the books agree afterwards: balance = ledger sum = remaining in lots.
 * Times are Unix timestamps internally (millions of rows: Carbon only where the state machine needs it).
 */
final class CustomerHistory
{
    public const CUSTOMER_PREFIX = 'perf-';

    public const EMAIL_DOMAIN = 'perf.pos.test';

    /** One point per this much spent (MMK), as in merchant:demo-data. */
    private const SPEND_PER_POINT = 1000;

    /** loyalty:expire-points runs at 00:10. */
    private const SWEEP_DELAY = 600;

    private const DAY = 86400;

    /** [weight %, smallest sale, largest sale, sales per month] */
    private const PROFILES = [
        [40, 10000, 40000, 0.5],     // occasional
        [35, 20000, 100000, 1.0],    // regular
        [20, 80000, 300000, 2.0],    // big spender
        [5, 250000, 800000, 3.0],    // whale: Platinum / Diamond
    ];

    private const MAX_SALES = 80;

    private const FIRST_NAMES = ['Aung', 'Su', 'Kyaw', 'Hnin', 'Thura', 'May', 'Zaw', 'Ei', 'Myo', 'Nay', 'Thandar', 'Htet',
        'Khin', 'Min', 'Phyo', 'Yadanar', 'Win', 'Thiri', 'Ko', 'Moe', 'Pyae', 'Wai', 'Zin', 'Chit'];

    private const LAST_NAMES = ['Aung', 'Hlaing', 'Htet', 'Wai', 'Naing', 'Kyaw', 'Oo', 'Sin', 'Myint', 'Thu', 'Zaw', 'Lwin',
        'Soe', 'Tun', 'Win', 'Maung', 'Nyein', 'Phyo', 'San', 'Thant'];

    private const GIFT_CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private readonly string $redemptionMorph;

    private readonly string $exchangeMorph;

    // ---- state of the customer being simulated ----
    private int $customerId;

    private int $enrolledAt;

    private TierState $state;

    private int $cycleStart;

    private int $cycleEnd;

    private int $nextEvaluation;

    private int $lastEvaluated;

    /** @var array<int, array{end: int, total: string, count: int, created: int, updated: int}> keyed by cycle start */
    private array $cycles;

    private int $balance;

    private ?int $accountCreated;

    /** @var array<int, array{points: int, remaining: int, earned: int, expires: ?int, tx: int}> keyed by lot id */
    private array $lots;

    /** @var array<int, list<array{0: int, 1: int}>> debit tx id => [[lot id, points], ...] */
    private array $usages;

    /** @var array<string, array<string, int>> period => summary columns */
    private array $summaries;

    /** @var array<int, array<string, mixed>> keyed by redemption id */
    private array $redemptions;

    /** @var array<int, array<string, mixed>> keyed by exchange id */
    private array $exchanges;

    /** @var array<int, array{id: int, tx: int, points: int}> event key => what a reversal undoes */
    private array $made;

    /** @var array<string, int> */
    private array $stats = ['sales' => 0, 'redemptions' => 0, 'refused' => 0, 'reversed' => 0, 'gift_cards' => 0, 'cancelled' => 0, 'adjustments' => 0];

    /**
     * @param  list<array{id: int, merchant_id: int, label: string, rewards: list<array{id: int, name: string, points: int, payout: string}>}>  $branches
     * @param  list<array{id: int, name: string, points: int, face_value: string, min_rank: ?int, valid_days: ?int}>  $giftCards
     */
    public function __construct(
        private readonly RowBuffer $rows,
        private readonly IdSequence $ids,
        private readonly TierStateMachine $machine,
        private readonly TierLadder $ladder,
        private readonly int $cycleMonths,
        private readonly int $expiryMonths,
        private readonly int $cutoffDay,
        private readonly array $branches,
        private readonly array $giftCards,
        private readonly ?int $adminId,
        private readonly int $now,
        private readonly int $historyDays,
    ) {
        $this->redemptionMorph = (new Redemption)->getMorphClass();
        $this->exchangeMorph = (new GiftCardExchange)->getMorphClass();
    }

    /** Simulate customer number $n and buffer all of their rows. */
    public function simulate(int $n): void
    {
        $this->reset($this->ids->next('customers'), $this->now - mt_rand(self::DAY, $this->historyDays * self::DAY));
        $this->rows->add('customers', $this->customerRow($n));
        $this->enrol();

        foreach ($this->timeline() as $key => $event) {
            $this->advanceTo($event['at']);
            match ($event['type']) {
                'sale' => $this->sale($event['at'], $event['amount']),
                'redeem' => $this->redeem($key, $event['at'], $event['branch'], $event['reward']),
                'reverse' => $this->reverse($event['of'], $event['at']),
                'gift' => $this->exchange($key, $event['at'], $event['card']),
                'cancel' => $this->cancel($event['of'], $event['at']),
                'adjust' => $this->adjust($event['at'], $event['points']),
            };
        }

        $this->advanceTo($this->now);
        $this->finish();
    }

    /** @return array<string, int> */
    public function stats(): array
    {
        return $this->stats;
    }

    private function reset(int $customerId, int $enrolledAt): void
    {
        $this->customerId = $customerId;
        $this->enrolledAt = $enrolledAt;
        $this->cycles = $this->lots = $this->usages = $this->summaries = $this->redemptions = $this->exchanges = $this->made = [];
        $this->balance = 0;
        $this->accountCreated = null;
    }

    /** @return array<string, mixed> */
    private function customerRow(int $n): array
    {
        $first = self::FIRST_NAMES[mt_rand(0, count(self::FIRST_NAMES) - 1)];
        $last = self::LAST_NAMES[mt_rand(0, count(self::LAST_NAMES) - 1)];
        $lastLogin = mt_rand(1, 10) === 1 ? null : mt_rand($this->enrolledAt, $this->now);

        return [
            'id' => $this->customerId,
            'external_id' => self::CUSTOMER_PREFIX.$n,
            'name' => "{$first} {$last}",
            'email' => mt_rand(1, 20) === 1 ? null : strtolower("{$first}.{$last}.{$n}@").self::EMAIL_DOMAIN,
            'phone' => sprintf('09%09d', $n),
            'is_active' => mt_rand(1, 100) > 3,
            'last_login_at' => $this->time($lastLogin),
            'created_at' => $this->time($this->enrolledAt),
            'updated_at' => $this->time($lastLogin ?? $this->enrolledAt),
        ];
    }

    /**
     * Purchases, redemption attempts, gift card exchanges, adjustments and the reversals of
     * some of them, oldest first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function timeline(): array
    {
        [, $min, $max, $perMonth] = $this->profile();
        $months = ($this->now - $this->enrolledAt) / (30 * self::DAY);
        $sales = min(self::MAX_SALES, (int) round($months * $perMonth * mt_rand(50, 150) / 100));
        $events = [];

        for ($i = 0; $i < $sales; $i++) {
            $events[] = ['type' => 'sale', 'at' => mt_rand($this->enrolledAt + 60, $this->now - 60),
                'amount' => (int) (round(mt_rand($min, $max) / 500) * 500)];
        }

        $later = min($this->enrolledAt + 14 * self::DAY, $this->now - self::DAY);
        $attempts = $this->branches === [] ? 0 : mt_rand(0, 1 + intdiv($sales, 3));
        for ($i = 0; $i < $attempts; $i++) {
            $branch = $this->branches[mt_rand(0, count($this->branches) - 1)];
            $events[] = ['type' => 'redeem', 'at' => mt_rand($later, $this->now - 60), 'branch' => $branch,
                'reward' => $branch['rewards'][mt_rand(0, count($branch['rewards']) - 1)]];
        }

        if ($this->giftCards !== [] && mt_rand(1, 100) <= 20) {
            $events[] = ['type' => 'gift', 'at' => mt_rand($later, $this->now - 60),
                'card' => $this->giftCards[mt_rand(0, count($this->giftCards) - 1)]];
        }

        if (mt_rand(1, 100) <= 2) {
            $events[] = ['type' => 'adjust', 'at' => mt_rand($this->enrolledAt + 60, $this->now - 60), 'points' => mt_rand(1, 50) * 10];
        }

        // Some redemptions are reversed and some gift cards cancelled by staff a little later.
        foreach ($events as $key => $event) {
            $undo = ['redeem' => ['reverse', 3], 'gift' => ['cancel', 5]][$event['type']] ?? null;
            if ($undo !== null && mt_rand(1, 100) <= $undo[1]) {
                $at = $event['at'] + mt_rand(3600, 2 * self::DAY);
                if ($at < $this->now) {
                    $events[] = ['type' => $undo[0], 'at' => $at, 'of' => $key];
                }
            }
        }

        uasort($events, fn (array $a, array $b) => $a['at'] <=> $b['at']);

        return $events;
    }

    /** @return array{0: int, 1: int, 2: int, 3: float} */
    private function profile(): array
    {
        $roll = mt_rand(1, 100);
        foreach (self::PROFILES as $profile) {
            $roll -= $profile[0];
            if ($roll <= 0) {
                return $profile;
            }
        }

        return self::PROFILES[0];
    }

    // ---- tiers ----

    private function enrol(): void
    {
        $this->cycleStart = $this->monthStart($this->enrolledAt);
        $this->cycleEnd = $this->addMonths($this->cycleStart, $this->cycleMonths);
        $this->state = new TierState(TierLevel::base(), null);
        $this->tierEvent(TierTransition::Enrolled, null, Amount::ZERO, $this->enrolledAt);
        $this->lastEvaluated = $this->enrolledAt;
        $this->nextEvaluation = $this->cycleEnd;
    }

    /** Catch up with what the scheduler would have done before $at: tier evaluations and point expiry. */
    private function advanceTo(int $at): void
    {
        while ($this->nextEvaluation <= $at) {
            $moment = $this->nextEvaluation;
            $rolled = $this->rollCycle($moment);
            $spent = $this->cycleSpent();
            $this->applyTier($this->machine->decide($this->state, $this->ladder, $spent, $spent, $rolled, $this->carbon($moment)), $spent, $moment);
        }

        $this->expireUpTo($at);
    }

    private function sale(int $at, int $amount): void
    {
        $rolled = $this->rollCycle($at);
        $before = $this->cycleSpent();
        $after = bcadd($before, (string) $amount, 2);

        $this->cycles[$this->cycleStart] ??= ['end' => $this->cycleEnd, 'total' => Amount::ZERO, 'count' => 0, 'created' => $at, 'updated' => $at];
        $this->cycles[$this->cycleStart]['total'] = $after;
        $this->cycles[$this->cycleStart]['count']++;
        $this->cycles[$this->cycleStart]['updated'] = $at;

        $this->applyTier($this->machine->decide($this->state, $this->ladder, $before, $after, $rolled, $this->carbon($at)), $after, $at);

        $points = intdiv($amount, self::SPEND_PER_POINT);
        if ($points > 0) {
            $this->credit('earn', $points, $at, 'Purchase INV-'.date('ymd', $at).'-'.$this->customerId, null, true);
        }
        $this->stats['sales']++;
    }

    private function rollCycle(int $at): bool
    {
        if ($at < $this->cycleEnd) {
            return false;
        }
        while ($at >= $this->cycleEnd) {
            $this->cycleStart = $this->cycleEnd;
            $this->cycleEnd = $this->addMonths($this->cycleStart, $this->cycleMonths);
        }

        return true;
    }

    private function cycleSpent(): string
    {
        return $this->cycles[$this->cycleStart]['total'] ?? Amount::ZERO;
    }

    private function applyTier(TierDecision $decision, string $spent, int $at): void
    {
        $this->state = $decision->to;
        $this->lastEvaluated = $at;

        $guarantee = $this->state->guaranteeExpiresAt?->getTimestamp();
        $this->nextEvaluation = $guarantee !== null && $guarantee > $at && $guarantee < $this->cycleEnd ? $guarantee : $this->cycleEnd;

        if ($decision->transition->isRecorded()) {
            $this->tierEvent($decision->transition, $decision->from->tier, $spent, $at);
        }
    }

    private function tierEvent(TierTransition $transition, ?TierLevel $from, string $spent, int $at): void
    {
        $this->rows->add('loyalty_tier_events', [
            'customer_id' => $this->customerId,
            'transition' => $transition->value,
            'from_tier' => $from?->value,
            'to_tier' => $this->state->tier->value,
            'cycle_spent' => $spent,
            'guarantee_expires_at' => $this->time($this->state->guaranteeExpiresAt?->getTimestamp()),
            'occurred_at' => $this->time($at),
            'created_at' => $this->time($at),
            'updated_at' => $this->time($at),
        ]);
    }

    // ---- points ----

    private function credit(string $type, int $points, int $at, string $note, ?int $actor, bool $withReference): void
    {
        $tx = $this->ledger($type, $points, $at, null, null, $note, $actor, $withReference);
        $this->lots[$this->ids->next('loyalty_point_lots')] = [
            'points' => $points, 'remaining' => $points, 'earned' => $at, 'expires' => $this->expiresAt($at), 'tx' => $tx,
        ];
    }

    /** Take $points from the soonest-expiring lots. Null when the balance is short (the app refuses). */
    private function debit(int $points, int $at, string $sourceType, int $sourceId, string $note): ?int
    {
        if ($this->balance < $points) {
            return null;
        }

        $tx = $this->ledger('redeem', -$points, $at, $sourceType, $sourceId, $note, null, false);
        $this->consume($tx, $this->spendableLots(), $points, $at);

        return $tx;
    }

    /** A reversed debit: its points go back into the lots they came from, then anything past due expires. */
    private function restore(int $debitTx, int $points, int $at, string $sourceType, int $sourceId, string $note): void
    {
        foreach ($this->usages[$debitTx] ?? [] as [$lotId, $taken]) {
            $this->lots[$lotId]['remaining'] += $taken;
        }
        $this->ledger('reversal', $points, $at, $sourceType, $sourceId, $note, $this->adminId, false);
        $this->expireAt($at);
    }

    private function expireUpTo(int $at): void
    {
        while (($soonest = $this->soonestDue($at)) !== null) {
            $this->expireAt(min($soonest + self::SWEEP_DELAY, $at));
        }
    }

    private function soonestDue(int $at): ?int
    {
        $soonest = null;
        foreach ($this->lots as $lot) {
            if ($lot['remaining'] > 0 && $lot['expires'] !== null && $lot['expires'] <= $at && ($soonest === null || $lot['expires'] < $soonest)) {
                $soonest = $lot['expires'];
            }
        }

        return $soonest;
    }

    private function expireAt(int $at): void
    {
        $due = array_filter($this->lots, fn (array $lot) => $lot['remaining'] > 0 && $lot['expires'] !== null && $lot['expires'] <= $at);
        if ($due === []) {
            return;
        }
        uksort($due, fn (int $a, int $b) => [$due[$a]['expires'], $a] <=> [$due[$b]['expires'], $b]);

        $total = array_sum(array_column($due, 'remaining'));
        $note = $total === 1 ? '1 point expired' : "{$total} points expired";
        $tx = $this->ledger('expire', -$total, $at, null, null, $note, null, false);
        $this->consume($tx, array_keys($due), $total, $at);
    }

    /** @return list<int> lot ids in spending order: soonest expiry first, never-expiring last, then oldest */
    private function spendableLots(): array
    {
        $ids = array_keys(array_filter($this->lots, fn (array $lot) => $lot['remaining'] > 0));
        usort($ids, fn (int $a, int $b) => [$this->lots[$a]['expires'] ?? PHP_INT_MAX, $a] <=> [$this->lots[$b]['expires'] ?? PHP_INT_MAX, $b]);

        return $ids;
    }

    /** @param  list<int>  $lotIds */
    private function consume(int $tx, array $lotIds, int $points, int $at): void
    {
        foreach ($lotIds as $lotId) {
            if ($points === 0) {
                break;
            }
            $take = min($points, $this->lots[$lotId]['remaining']);
            $this->lots[$lotId]['remaining'] -= $take;
            $this->usages[$tx][] = [$lotId, $take];
            $this->rows->add('loyalty_point_lot_usages', [
                'transaction_id' => $tx, 'lot_id' => $lotId, 'points' => $take,
                'created_at' => $this->time($at), 'updated_at' => $this->time($at),
            ]);
            $points -= $take;
        }

        if ($points !== 0) {
            throw new \LogicException("Simulated lots of customer {$this->customerId} are {$points} short of the balance.");
        }
    }

    /** Ledger row + balance + monthly summary, like PointWallet::record(). */
    private function ledger(string $type, int $delta, int $at, ?string $sourceType, ?int $sourceId, string $note, ?int $actor, bool $withReference): int
    {
        $id = $this->ids->next('loyalty_point_transactions');
        $this->balance += $delta;
        $this->accountCreated ??= $at;

        $this->rows->add('loyalty_point_transactions', [
            'id' => $id,
            'customer_id' => $this->customerId,
            'type' => $type,
            'points' => $delta,
            'balance_after' => $this->balance,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'note' => $note,
            'reference' => $withReference ? "PERF-{$id}" : null,
            'created_by' => $actor,
            'created_at' => $this->time($at),
            'updated_at' => $this->time($at),
        ]);

        $column = match ($type) {
            'earn' => 'earned',
            'redeem' => 'redeemed',
            'reversal' => 'reversed',
            'expire' => 'expired',
            'adjust' => $delta >= 0 ? 'adjusted_in' : 'adjusted_out',
        };
        $period = date('Y-m-01', $at);
        $this->summaries[$period] ??= ['earned' => 0, 'redeemed' => 0, 'reversed' => 0, 'adjusted_in' => 0, 'adjusted_out' => 0, 'expired' => 0, 'created' => $at, 'updated' => $at];
        $this->summaries[$period][$column] += abs($delta);
        $this->summaries[$period]['updated'] = $at;

        return $id;
    }

    // ---- what customers and staff do ----

    /** @param  array<string, mixed>  $branch  @param  array<string, mixed>  $reward */
    private function redeem(int $key, int $at, array $branch, array $reward): void
    {
        $id = $this->ids->next('merchant_redemptions');
        $tx = $this->debit($reward['points'], $at, $this->redemptionMorph, $id, "{$reward['name']} at {$branch['label']}");
        if ($tx === null) {
            $this->stats['refused']++;

            return;
        }

        $this->redemptions[$id] = [
            'id' => $id,
            'reference' => 'RDM-'.date('Ymd', $at).'-P'.str_pad(strtoupper(base_convert((string) $id, 10, 36)), 5, '0', STR_PAD_LEFT),
            'customer_id' => $this->customerId,
            'merchant_id' => $branch['merchant_id'],
            'branch_id' => $branch['id'],
            'reward_id' => $reward['id'],
            'reward_name' => $reward['name'],
            'points' => $reward['points'],
            'payout_amount' => $reward['payout'],
            'status' => 'completed',
            'settlement_id' => null,
            'request_id' => null,
            'redeemed_at' => $this->time($at),
            'reversed_at' => null,
            'reversed_by' => null,
            'reversal_reason' => null,
            'created_at' => $this->time($at),
            'updated_at' => $this->time($at),
        ];
        $this->made[$key] = ['id' => $id, 'tx' => $tx, 'points' => $reward['points']];
        $this->stats['redemptions']++;
    }

    private function reverse(int $of, int $at): void
    {
        if (! isset($this->made[$of])) {
            return; // the redemption was refused
        }
        ['id' => $id, 'tx' => $tx, 'points' => $points] = $this->made[$of];
        $reference = $this->redemptions[$id]['reference'];

        $this->restore($tx, $points, $at, $this->redemptionMorph, $id, "Reversed {$reference}: Reward not handed over");
        $this->redemptions[$id] = [...$this->redemptions[$id], 'status' => 'reversed', 'reversed_at' => $this->time($at),
            'reversed_by' => $this->adminId, 'reversal_reason' => 'Reward not handed over', 'updated_at' => $this->time($at)];
        $this->stats['reversed']++;
    }

    /** @param  array<string, mixed>  $card */
    private function exchange(int $key, int $at, array $card): void
    {
        if ($card['min_rank'] !== null && $this->state->tier->rank() < $card['min_rank']) {
            return; // the app shows it as unavailable (reason "tier")
        }

        $id = $this->ids->next('gift_card_exchanges');
        $code = $this->giftCode($id);
        $tx = $this->debit($card['points'], $at, $this->exchangeMorph, $id, "Gift card {$card['name']} ({$code})");
        if ($tx === null) {
            return; // reason "not_enough_points": nothing is written
        }

        $this->exchanges[$id] = [
            'id' => $id,
            'gift_card_id' => $card['id'],
            'customer_id' => $this->customerId,
            'status' => 'issued',
            'points' => $card['points'],
            'face_value' => $card['face_value'],
            'code' => $code,
            'verification_hash' => null,
            'verification_expires_at' => null,
            'verification_attempts' => 0,
            'issued_at' => $this->time($at),
            'expires_at' => $card['valid_days'] ? date('Y-m-d 23:59:59', $at + $card['valid_days'] * self::DAY) : null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'cancel_reason' => null,
            'created_at' => $this->time($at),
            'updated_at' => $this->time($at),
        ];
        $this->made[$key] = ['id' => $id, 'tx' => $tx, 'points' => $card['points']];
        $this->stats['gift_cards']++;
    }

    private function cancel(int $of, int $at): void
    {
        if (! isset($this->made[$of])) {
            return;
        }
        ['id' => $id, 'tx' => $tx, 'points' => $points] = $this->made[$of];
        $code = $this->exchanges[$id]['code'];

        $this->restore($tx, $points, $at, $this->exchangeMorph, $id, "Gift card {$code} cancelled: Customer request");
        $this->exchanges[$id] = [...$this->exchanges[$id], 'status' => 'cancelled', 'cancelled_at' => $this->time($at),
            'cancelled_by' => $this->adminId, 'cancel_reason' => 'Customer request', 'updated_at' => $this->time($at)];
        $this->stats['cancelled']++;
    }

    private function adjust(int $at, int $points): void
    {
        $this->credit('adjust', $points, $at, 'Goodwill points', $this->adminId, false);
        $this->stats['adjustments']++;
    }

    /** GC-XXXX-XXXX-XXXX: the exchange id in the first six characters keeps codes unique, the rest is random. */
    private function giftCode(int $id): string
    {
        $base = strlen(self::GIFT_CODE_ALPHABET);
        $chars = '';
        for ($i = 0, $n = $id; $i < 6; $i++, $n = intdiv($n, $base)) {
            $chars .= self::GIFT_CODE_ALPHABET[$n % $base];
        }
        for ($i = 0; $i < 6; $i++) {
            $chars .= self::GIFT_CODE_ALPHABET[mt_rand(0, $base - 1)];
        }

        return 'GC-'.implode('-', str_split($chars, 4));
    }

    // ---- final state ----

    private function finish(): void
    {
        $this->rows->add('loyalty_member_statuses', [
            'customer_id' => $this->customerId,
            'current_tier' => $this->state->tier->value,
            'guarantee_expires_at' => $this->time($this->state->guaranteeExpiresAt?->getTimestamp()),
            'current_cycle_start' => $this->time($this->cycleStart),
            'current_cycle_end' => $this->time($this->cycleEnd),
            'next_evaluation_at' => $this->time($this->nextEvaluation),
            'last_evaluated_at' => $this->time($this->lastEvaluated),
            'created_at' => $this->time($this->enrolledAt),
            'updated_at' => $this->time($this->lastEvaluated),
        ]);

        foreach ($this->cycles as $start => $cycle) {
            $this->rows->add('loyalty_cycle_spendings', [
                'customer_id' => $this->customerId, 'cycle_start' => $this->time($start), 'cycle_end' => $this->time($cycle['end']),
                'total_spent' => $cycle['total'], 'transaction_count' => $cycle['count'],
                'created_at' => $this->time($cycle['created']), 'updated_at' => $this->time($cycle['updated']),
            ]);
        }

        if ($this->accountCreated !== null) {
            $this->rows->add('loyalty_point_accounts', [
                'customer_id' => $this->customerId, 'balance' => $this->balance,
                'created_at' => $this->time($this->accountCreated), 'updated_at' => $this->time($this->now),
            ]);
        }

        foreach ($this->lots as $id => $lot) {
            $this->rows->add('loyalty_point_lots', [
                'id' => $id, 'customer_id' => $this->customerId, 'transaction_id' => $lot['tx'],
                'points' => $lot['points'], 'remaining' => $lot['remaining'],
                'earned_at' => $this->time($lot['earned']), 'expires_at' => $this->time($lot['expires']),
                'created_at' => $this->time($lot['earned']), 'updated_at' => $this->time($lot['earned']),
            ]);
        }

        foreach ($this->summaries as $period => $summary) {
            ['created' => $created, 'updated' => $updated] = $summary;
            unset($summary['created'], $summary['updated']);
            $this->rows->add('loyalty_point_summaries', [
                'customer_id' => $this->customerId, 'period' => $period, ...$summary,
                'created_at' => $this->time($created), 'updated_at' => $this->time($updated),
            ]);
        }

        $this->rows->addMany('merchant_redemptions', array_values($this->redemptions));
        $this->rows->addMany('gift_card_exchanges', array_values($this->exchanges));
    }

    // ---- time helpers (app timezone = PHP default timezone, set by Laravel) ----

    /** Exclusive expiry of points earned at $at, as PointExpiryPolicy::expiresAt(). */
    private function expiresAt(int $at): ?int
    {
        if ($this->expiryMonths === 0) {
            return null;
        }
        $firstCounted = (int) date('j', $at) <= $this->cutoffDay ? 0 : 1;

        return mktime(0, 0, 0, (int) date('n', $at) + $firstCounted + $this->expiryMonths, 1, (int) date('Y', $at));
    }

    private function monthStart(int $at): int
    {
        return mktime(0, 0, 0, (int) date('n', $at), 1, (int) date('Y', $at));
    }

    private function addMonths(int $monthStart, int $months): int
    {
        return mktime(0, 0, 0, (int) date('n', $monthStart) + $months, 1, (int) date('Y', $monthStart));
    }

    private function carbon(int $at): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestamp($at, date_default_timezone_get());
    }

    private function time(?int $at): ?string
    {
        return $at === null ? null : date('Y-m-d H:i:s', $at);
    }
}
