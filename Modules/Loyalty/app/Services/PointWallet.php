<?php

namespace Modules\Loyalty\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Modules\Loyalty\Enums\PointTransactionType;
use Modules\Loyalty\Exceptions\InsufficientPoints;
use Modules\Loyalty\Models\PointAccount;
use Modules\Loyalty\Models\PointLot;
use Modules\Loyalty\Models\PointLotUsage;
use Modules\Loyalty\Models\PointSummary;
use Modules\Loyalty\Models\PointTransaction;
use Modules\Loyalty\Support\PointExpiryPolicy;

/**
 * Customers' points. Every change locks the customer's account row (SELECT ... FOR UPDATE)
 * and, in the same DB transaction, writes the ledger row, the lots, and the monthly summary, so
 * concurrent redemptions can't spend the same points twice and the books always agree:
 *
 *   account.balance = sum of remaining in unexpired lots = sum of the ledger
 *
 * Credits create a lot with an expiry date (PointExpiryPolicy). Debits first expire what is
 * due, then take from the lots that expire soonest (FIFO by expiry) and remember which lots they
 * used, so a reversal returns points to those same lots with their original expiry.
 *
 * Safe to call inside a caller's transaction (e.g. a redemption): it then joins it, and a
 * rollback there undoes the points change too.
 */
class PointWallet
{
    /** Ledger types that create a lot when they add points. */
    private const LOT_CREATING = [PointTransactionType::Earn, PointTransactionType::Adjust];

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly PointExpiryPolicy $expiry,
    ) {}

    /** Spendable points now. Expires anything due first, so the figure is never stale. */
    public function balance(int $customerId): int
    {
        if (PointLot::query()->where('customer_id', $customerId)->due(CarbonImmutable::now())->exists()) {
            $this->expireDue($customerId);
        }

        return (int) PointAccount::query()->where('customer_id', $customerId)->value('balance');
    }

    /**
     * Add points (earn or positive adjustment): a new lot that expires per the settings.
     *
     * @param  string|null  $reference  caller's idempotency key, unique across the ledger
     */
    public function credit(int $customerId, int $points, PointTransactionType $type, ?Model $source = null, ?string $note = null, ?int $actorId = null, ?string $reference = null): PointTransaction
    {
        $this->guardPositive($points);
        if (! in_array($type, self::LOT_CREATING, true)) {
            throw new InvalidArgumentException("Use restore() for {$type->value}: it returns points to their original lots.");
        }
        $this->ensureAccount($customerId);

        return $this->db->transaction(function () use ($customerId, $points, $type, $source, $note, $actorId, $reference) {
            $account = $this->lockAccount($customerId);
            $now = CarbonImmutable::now();

            $tx = $this->record($account, $points, $type, $source, $note, $actorId, $reference);
            PointLot::query()->create([
                'customer_id' => $customerId,
                'transaction_id' => $tx->id,
                'points' => $points,
                'remaining' => $points,
                'earned_at' => $now,
                'expires_at' => $this->expiry->expiresAt($now),
            ]);

            return $tx;
        });
    }

    /**
     * Take points (redeem or negative adjustment) from the lots that expire soonest.
     *
     * @throws InsufficientPoints
     */
    public function debit(int $customerId, int $points, PointTransactionType $type, ?Model $source = null, ?string $note = null, ?int $actorId = null): PointTransaction
    {
        $this->guardPositive($points);
        $this->ensureAccount($customerId);

        return $this->db->transaction(function () use ($customerId, $points, $type, $source, $note, $actorId) {
            $account = $this->lockAccount($customerId);
            $this->expireLocked($account, CarbonImmutable::now());

            if ($account->balance < $points) {
                throw new InsufficientPoints($account->balance, $points);
            }

            $tx = $this->record($account, -$points, $type, $source, $note, $actorId);
            $this->consume($tx, PointLot::query()->where('customer_id', $customerId)->spendable(CarbonImmutable::now())->get(), $points);

            return $tx;
        });
    }

    /**
     * Undo a debit (e.g. a reversed redemption): its points go back to the lots they were taken
     * from. Points whose lot has expired in the meantime expire again straight away.
     */
    public function restore(PointTransaction $debit, ?Model $source = null, ?string $note = null, ?int $actorId = null): PointTransaction
    {
        if ($debit->points >= 0) {
            throw new InvalidArgumentException('Only a debit can be restored.');
        }

        return $this->db->transaction(function () use ($debit, $source, $note, $actorId) {
            $account = $this->lockAccount($debit->customer_id);
            $usages = PointLotUsage::query()->with('lot')->where('transaction_id', $debit->id)->get();

            foreach ($usages as $usage) {
                $usage->lot->increment('remaining', $usage->points);
            }
            $tx = $this->record($account, -$debit->points, PointTransactionType::Reversal, $source, $note, $actorId);
            $this->expireLocked($account, CarbonImmutable::now());

            return $tx;
        });
    }

    /**
     * Manual correction by an administrator: positive adds (a new lot), negative removes.
     *
     * @throws InsufficientPoints
     */
    public function adjust(int $customerId, int $points, string $note, int $actorId): PointTransaction
    {
        return $points >= 0
            ? $this->credit($customerId, $points, PointTransactionType::Adjust, null, $note, $actorId)
            : $this->debit($customerId, -$points, PointTransactionType::Adjust, null, $note, $actorId);
    }

    /** Expire this customer's lots that are past their date. Null when nothing was due. */
    public function expireDue(int $customerId, ?CarbonImmutable $at = null): ?PointTransaction
    {
        $this->ensureAccount($customerId);

        return $this->db->transaction(fn () => $this->expireLocked($this->lockAccount($customerId), $at ?? CarbonImmutable::now()));
    }

    /**
     * Customers that have points past their expiry, for the daily sweep.
     *
     * @return Collection<int, int>
     */
    public function customersWithDuePoints(CarbonImmutable $at): Collection
    {
        return PointLot::query()->due($at)->distinct()->orderBy('customer_id')->pluck('customer_id');
    }

    private function expireLocked(PointAccount $account, CarbonImmutable $at): ?PointTransaction
    {
        $due = PointLot::query()->where('customer_id', $account->customer_id)->due($at)->orderBy('expires_at')->orderBy('id')->get();
        $total = (int) $due->sum('remaining');
        if ($total === 0) {
            return null;
        }

        $tx = $this->record($account, -$total, PointTransactionType::Expire, null,
            trans_choice('{1} :count point expired|[2,*] :count points expired', $total));
        $this->consume($tx, $due, $total);

        return $tx;
    }

    /** Take $points from $lots in order, recording which lot gave how many. */
    private function consume(PointTransaction $tx, Collection $lots, int $points): void
    {
        $left = $points;
        foreach ($lots as $lot) {
            if ($left === 0) {
                break;
            }
            $take = min($left, $lot->remaining);
            $lot->decrement('remaining', $take);
            PointLotUsage::query()->create(['transaction_id' => $tx->id, 'lot_id' => $lot->id, 'points' => $take]);
            $left -= $take;
        }

        if ($left !== 0) {
            // Balance and lots disagree: never silently spend points that don't exist.
            throw new \LogicException("Point lots of customer {$tx->customer_id} are {$left} short of the balance.");
        }
    }

    /** Ledger row + cached balance + monthly summary, all under the account lock. */
    private function record(PointAccount $account, int $delta, PointTransactionType $type, ?Model $source, ?string $note, ?int $actorId = null, ?string $reference = null): PointTransaction
    {
        $account->balance += $delta;
        $account->save();

        $tx = PointTransaction::query()->create([
            'customer_id' => $account->customer_id,
            'type' => $type,
            'points' => $delta,
            'balance_after' => $account->balance,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'note' => $note,
            'reference' => $reference,
            'created_by' => $actorId,
        ]);

        $this->summarise($account->customer_id, $type, $delta, CarbonImmutable::now());

        return $tx;
    }

    private function summarise(int $customerId, PointTransactionType $type, int $delta, CarbonImmutable $at): void
    {
        $column = match ($type) {
            PointTransactionType::Earn => 'earned',
            PointTransactionType::Redeem => 'redeemed',
            PointTransactionType::Reversal => 'reversed',
            PointTransactionType::Expire => 'expired',
            PointTransactionType::Adjust => $delta >= 0 ? 'adjusted_in' : 'adjusted_out',
        };
        $period = $at->setTimezone(config('app.timezone'))->startOfMonth()->toDateString();

        // Only this customer's writes touch this row, and they are serialised by the account lock.
        PointSummary::query()->insertOrIgnore([
            'customer_id' => $customerId, 'period' => $period,
            'created_at' => $at, 'updated_at' => $at,
        ]);
        PointSummary::query()->where('customer_id', $customerId)->where('period', $period)->increment($column, abs($delta));
    }

    private function lockAccount(int $customerId): PointAccount
    {
        return PointAccount::query()->where('customer_id', $customerId)->lockForUpdate()->firstOrFail();
    }

    /**
     * Create an empty account if the customer has none. Done as its own statement before the
     * locking transaction: on MySQL an INSERT IGNORE on an existing key inside it takes a
     * shared lock that deadlocks with FOR UPDATE (see TierQualificationEngine::enrolIfNew).
     */
    private function ensureAccount(int $customerId): void
    {
        if (PointAccount::query()->where('customer_id', $customerId)->exists()) {
            return;
        }

        $now = now();
        PointAccount::query()->insertOrIgnore(['customer_id' => $customerId, 'balance' => 0, 'created_at' => $now, 'updated_at' => $now]);
    }

    private function guardPositive(int $points): void
    {
        if ($points <= 0) {
            throw new InvalidArgumentException('Points must be a positive whole number.');
        }
    }
}
