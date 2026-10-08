<?php

namespace Modules\Loyalty\Services;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Loyalty\Enums\TierLevel;
use Modules\Loyalty\Enums\TierTransition;
use Modules\Loyalty\Events\TierChanged;
use Modules\Loyalty\Exceptions\TransactionOutsideActiveCycle;
use Modules\Loyalty\Models\CycleSpending;
use Modules\Loyalty\Models\MemberTierStatus;
use Modules\Loyalty\Models\TierEvent;
use Modules\Loyalty\Support\Amount;
use Modules\Loyalty\Support\CycleWindow;
use Modules\Loyalty\Support\TierDecision;
use Modules\Loyalty\Support\TierResult;
use Modules\Loyalty\Support\TierState;
use Modules\Loyalty\Support\TierStateMachine;

/**
 * Tier qualification: keeps each customer's cycle spending aggregate and tier status, and
 * runs the TierStateMachine on every change.
 *
 * Concurrency: every write happens in one DB transaction that first takes a row lock
 * (SELECT ... FOR UPDATE) on the customer's status row. Two tills ringing up the same customer
 * at once are therefore applied one after the other, each seeing the other's total.
 * Different members never block each other. The aggregate itself is changed with an atomic
 * `UPDATE ... SET total_spent = total_spent + ?`, never read-modify-written in PHP.
 *
 * Cost per call is constant: a handful of single-row statements on unique keys, no SUM over
 * sales, regardless of how many transactions a customer has.
 */
class TierQualificationEngine
{
    /** Retries when the database reports a deadlock or lock wait timeout. */
    private const TRANSACTION_ATTEMPTS = 3;

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TierConfigService $config,
        private readonly TierStateMachine $machine,
    ) {}

    /**
     * Count a completed sale towards the customer's current cycle and upgrade immediately if
     * a threshold is reached. Enrols the customer (base tier) on their first transaction.
     *
     * @throws InvalidArgumentException when the amount is not positive
     * @throws TransactionOutsideActiveCycle when the date is before the active cycle
     */
    public function processTransaction(int $customerId, string|int|float $amount, DateTimeInterface $transactionDate): TierResult
    {
        $amount = Amount::of($amount);
        if (! Amount::isPositive($amount)) {
            throw new InvalidArgumentException('A qualifying transaction amount must be greater than zero.');
        }

        $at = $this->moment($transactionDate);
        $this->enrolIfNew($customerId, $at);

        return $this->db->transaction(function () use ($customerId, $amount, $at) {
            $ladder = $this->config->ladder();
            $status = $this->lock($customerId, $at);

            if ($at->lessThan($status->current_cycle_start)) {
                throw TransactionOutsideActiveCycle::for($customerId, $at, $status->current_cycle_start);
            }

            $rolledOver = $this->rollCycleForward($status, $at);
            $spentAfter = $this->addSpending($status, $amount);
            $spentBefore = Amount::subtract($spentAfter, $amount);

            $decision = $this->machine->decide($this->stateOf($status), $ladder, $spentBefore, $spentAfter, $rolledOver, $at);

            return $this->apply($status, $decision, $spentAfter, $at);
        }, self::TRANSACTION_ATTEMPTS);
    }

    /**
     * Start a new customer at the base tier (Silver) now, with "enrolled" as the first entry of
     * their tier history. Safe to call again: an existing status is just evaluated.
     */
    public function enrol(int $customerId, ?DateTimeInterface $at = null): TierResult
    {
        $at = $this->moment($at ?? CarbonImmutable::now());
        $this->enrolIfNew($customerId, $at);

        return $this->evaluateUserTierStatus($customerId, $at);
    }

    /**
     * Bring a customer's status up to date at $at (default now): roll the cycle over if it has
     * ended, keep the tier while the guarantee runs, demote once it has expired and the
     * current cycle's spending is short. Returns null for someone who was never enrolled.
     * Safe to call any time and any number of times (idempotent for the same moment).
     */
    public function evaluateUserTierStatus(int $customerId, ?DateTimeInterface $at = null): ?TierResult
    {
        $at = $this->moment($at ?? CarbonImmutable::now());

        return $this->db->transaction(function () use ($customerId, $at) {
            $status = $this->lock($customerId, $at);
            if ($status === null) {
                return null;
            }

            // A late scheduler run must not evaluate a moment the status has already passed.
            $at = $status->last_evaluated_at?->greaterThan($at) ? $status->last_evaluated_at : $at;

            $ladder = $this->config->ladder();
            $rolledOver = $this->rollCycleForward($status, $at);
            $spent = $this->currentSpending($status);

            $decision = $this->machine->decide($this->stateOf($status), $ladder, $spent, $spent, $rolledOver, $at);

            return $this->apply($status, $decision, $spent, $at);
        }, self::TRANSACTION_ATTEMPTS);
    }

    /**
     * Take the customer's row lock (SELECT ... FOR UPDATE). This is the only lock taken for
     * an existing member, so concurrent sales for them queue here instead of deadlocking.
     * The first holder after enrolment records the Enrolled event.
     */
    private function lock(int $customerId, CarbonImmutable $at): ?MemberTierStatus
    {
        $status = MemberTierStatus::query()->where('customer_id', $customerId)->lockForUpdate()->first();

        if ($status !== null && $status->last_evaluated_at === null) {
            $this->record($status, TierTransition::Enrolled, null, Amount::ZERO, $at);
        }

        return $status;
    }

    /**
     * Create the status row (base tier, first cycle) for a customer seen for the first time.
     *
     * Runs as its own statement before the locking transaction: on MySQL, INSERT IGNORE on an
     * existing key takes a shared lock, and several sessions upgrading that shared lock to
     * FOR UPDATE deadlock each other. Existing members skip the insert entirely; for two
     * simultaneous first sales one insert wins and the other is ignored. If the sale's own
     * transaction later rolls back, an empty base-tier row remains, which is harmless.
     */
    private function enrolIfNew(int $customerId, CarbonImmutable $at): void
    {
        if (MemberTierStatus::query()->where('customer_id', $customerId)->exists()) {
            return;
        }

        $window = CycleWindow::firstFor($at, $this->config->cycleMonths());
        $now = CarbonImmutable::now()->toDateTimeString();

        MemberTierStatus::query()->insertOrIgnore([
            'customer_id' => $customerId,
            'current_tier' => TierLevel::base()->value,
            'guarantee_expires_at' => null,
            'current_cycle_start' => $window->start->toDateTimeString(),
            'current_cycle_end' => $window->end->toDateTimeString(),
            'next_evaluation_at' => $window->end->toDateTimeString(),
            'last_evaluated_at' => null, // marks "not yet locked": see lock()
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Move the status to the cycle containing $at. Spending of the new cycle starts at zero
     * (its aggregate row is created on its first transaction); old rows stay as history.
     */
    private function rollCycleForward(MemberTierStatus $status, CarbonImmutable $at): bool
    {
        $window = $this->windowOf($status);
        if (! $window->hasEndedBy($at)) {
            return false;
        }

        $next = $window->rolledForwardTo($at, $this->config->cycleMonths());
        $status->current_cycle_start = $next->start;
        $status->current_cycle_end = $next->end;

        return true;
    }

    /** Atomically add to the current cycle's aggregate; returns the new total. */
    private function addSpending(MemberTierStatus $status, string $amount): string
    {
        $key = ['customer_id' => $status->customer_id, 'cycle_start' => $status->current_cycle_start->toDateTimeString()];
        $now = CarbonImmutable::now()->toDateTimeString();

        CycleSpending::query()->insertOrIgnore([
            ...$key,
            'cycle_end' => $status->current_cycle_end->toDateTimeString(),
            'total_spent' => Amount::ZERO,
            'transaction_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $row = CycleSpending::query()->where($key);
        $row->incrementEach(['total_spent' => $amount, 'transaction_count' => 1]);

        return Amount::of($row->value('total_spent'));
    }

    private function currentSpending(MemberTierStatus $status): string
    {
        $spent = CycleSpending::query()
            ->where('customer_id', $status->customer_id)
            ->where('cycle_start', $status->current_cycle_start->toDateTimeString())
            ->value('total_spent');

        return Amount::of($spent ?? Amount::ZERO);
    }

    private function apply(MemberTierStatus $status, TierDecision $decision, string $cycleSpent, CarbonImmutable $at): TierResult
    {
        $to = $decision->to;

        $status->current_tier = $to->tier;
        $status->guarantee_expires_at = $to->guaranteeExpiresAt;
        $status->last_evaluated_at = $at;
        $status->next_evaluation_at = $this->nextEvaluation($status, $at);
        $status->save();

        if ($decision->transition->isRecorded()) {
            $this->record($status, $decision->transition, $decision->from->tier, $cycleSpent, $at);
        }

        if ($decision->transition->changesTier()) {
            TierChanged::dispatch($status->customer_id, $decision->transition, $decision->from->tier, $to->tier, $at);
        }

        return new TierResult(
            $status->customer_id,
            $to->tier,
            $decision->transition,
            $to->guaranteeExpiresAt,
            $this->windowOf($status),
            $cycleSpent,
        );
    }

    /** The next moment the status can change without a sale: cycle end, or an earlier guarantee expiry. */
    private function nextEvaluation(MemberTierStatus $status, CarbonImmutable $at): CarbonImmutable
    {
        $guarantee = $status->guarantee_expires_at;

        return $guarantee !== null && $guarantee->greaterThan($at) && $guarantee->lessThan($status->current_cycle_end)
            ? $guarantee
            : $status->current_cycle_end;
    }

    private function record(MemberTierStatus $status, TierTransition $transition, ?TierLevel $from, string $cycleSpent, CarbonImmutable $at): void
    {
        TierEvent::query()->create([
            'customer_id' => $status->customer_id,
            'transition' => $transition,
            'from_tier' => $from,
            'to_tier' => $status->current_tier,
            'cycle_spent' => $cycleSpent,
            'guarantee_expires_at' => $status->guarantee_expires_at,
            'occurred_at' => $at,
        ]);
    }

    private function stateOf(MemberTierStatus $status): TierState
    {
        return new TierState($status->current_tier, $status->guarantee_expires_at);
    }

    private function windowOf(MemberTierStatus $status): CycleWindow
    {
        return new CycleWindow($status->current_cycle_start, $status->current_cycle_end);
    }

    /** All cycle arithmetic happens in the store's time zone (cycles start at local midnight). */
    private function moment(DateTimeInterface $at): CarbonImmutable
    {
        return CarbonImmutable::instance($at)->setTimezone(config('app.timezone'));
    }
}
