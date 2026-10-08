<?php

namespace Modules\Partner\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Modules\Customer\Models\Customer;
use Modules\Customer\Services\CustomerDirectory;
use Modules\Loyalty\Enums\PointTransactionType;
use Modules\Loyalty\Models\PointLot;
use Modules\Loyalty\Models\PointTransaction;
use Modules\Loyalty\Services\PointWallet;
use Modules\Loyalty\Services\TierQualificationEngine;
use Modules\Partner\Exceptions\PointAwardConflict;

/**
 * The partner project awards points for something that happened there (usually an order).
 *
 * - `reference` (e.g. the order number) makes it idempotent: sending the same award again
 *   returns the first one instead of paying twice; the same reference with a different customer
 *   or amount is a conflict.
 * - The customer is created (and enrolled at Silver) if they never signed in here yet.
 * - Optional `spent_amount` also counts towards their tier, in the same DB transaction, so the
 *   points and the tier spending are recorded together or not at all.
 */
class PointAwardService
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly CustomerDirectory $customers,
        private readonly PointWallet $wallet,
        private readonly TierQualificationEngine $tiers,
    ) {}

    /**
     * @param  array{external_id: string, name: string, email?: ?string, phone?: ?string}  $profile
     * @return array{transaction: PointTransaction, customer: Customer, replayed: bool}
     *
     * @throws PointAwardConflict
     */
    public function award(array $profile, int $points, string $reference, ?string $note = null, ?string $spentAmount = null): array
    {
        if ($existing = $this->replay($profile['external_id'], $points, $reference)) {
            return $existing;
        }

        [$customer] = $this->customers->upsert($profile);
        if (! $customer->is_active) {
            throw ValidationException::withMessages(['customer.external_id' => __('This customer is deactivated and can\'t receive points.')]);
        }

        try {
            $transaction = $this->db->transaction(function () use ($customer, $points, $reference, $note, $spentAmount) {
                $transaction = $this->wallet->credit($customer->id, $points, PointTransactionType::Earn, null, $note, null, $reference);
                if ($spentAmount !== null) {
                    $this->tiers->processTransaction($customer->id, $spentAmount, now());
                }

                return $transaction;
            });
        } catch (UniqueConstraintViolationException $e) {
            // The same award arrived twice at once; the other request won.
            return $this->replay($profile['external_id'], $points, $reference) ?? throw $e;
        }

        return ['transaction' => $transaction, 'customer' => $customer, 'replayed' => false];
    }

    /** The lot (with its expiry) that an award created. */
    public function lotOf(PointTransaction $transaction): ?PointLot
    {
        return PointLot::query()->where('transaction_id', $transaction->id)->first();
    }

    /**
     * @return array{transaction: PointTransaction, customer: Customer, replayed: bool}|null
     */
    private function replay(string $externalId, int $points, string $reference): ?array
    {
        $transaction = PointTransaction::query()->where('reference', $reference)->first();
        if ($transaction === null) {
            return null;
        }

        $customer = Customer::query()->find($transaction->customer_id);
        if ($customer?->external_id !== $externalId || $transaction->points !== $points || $transaction->type !== PointTransactionType::Earn) {
            throw new PointAwardConflict("Reference {$reference} was already used for a different award.");
        }

        return ['transaction' => $transaction, 'customer' => $customer, 'replayed' => true];
    }
}
