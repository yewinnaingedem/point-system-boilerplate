<?php

namespace Modules\Merchant\Services;

use App\Models\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Modules\Customer\Models\Customer;
use Modules\Loyalty\Enums\PointTransactionType;
use Modules\Loyalty\Exceptions\InsufficientPoints;
use Modules\Loyalty\Models\PointTransaction;
use Modules\Loyalty\Services\PointWallet;
use Modules\Merchant\Enums\RedemptionStatus;
use Modules\Merchant\Exceptions\RedemptionRejected;
use Modules\Merchant\Models\MerchantBranch;
use Modules\Merchant\Models\MerchantReward;
use Modules\Merchant\Models\Redemption;

/**
 * A customer spends points on a merchant reward at a branch; the branch's staff confirm it by
 * typing the branch's 6-digit code on the customer's app.
 *
 * Order of checks: branch, merchant and reward available -> not locked out -> code right ->
 * then, in one DB transaction, points taken (customer's account row locked) and the redemption
 * written with the payout owed to the merchant frozen at today's prices.
 */
class RedemptionService
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly PointWallet $wallet,
        private readonly RateLimiter $limiter,
    ) {}

    /**
     * @param  string|null  $requestId  the app's idempotency key: retrying the same request
     *                                  returns the first redemption instead of charging again
     *
     * @throws RedemptionRejected
     */
    public function redeem(Customer $customer, int $branchId, int $rewardId, string $code, ?string $requestId = null): Redemption
    {
        if ($requestId !== null && ($existing = $this->findByRequest($customer, $requestId))) {
            return $existing;
        }

        $branch = MerchantBranch::query()->with('merchant')->find($branchId);
        if (! $branch?->is_active || ! $branch->merchant->is_active) {
            throw RedemptionRejected::unavailable('branch_id', __('This shop is not taking redemptions.'));
        }

        $reward = MerchantReward::query()->whereKey($rewardId)->where('merchant_id', $branch->merchant_id)->first();
        if (! $reward?->is_active) {
            throw RedemptionRejected::unavailable('reward_id', __('This reward is not available at this shop.'));
        }

        $this->verifyCode($customer, $branch, $code);

        try {
            return $this->db->transaction(function () use ($customer, $branch, $reward, $requestId) {
                $redemption = Redemption::query()->create([
                    'reference' => $this->newReference(),
                    'customer_id' => $customer->id,
                    'merchant_id' => $branch->merchant_id,
                    'branch_id' => $branch->id,
                    'reward_id' => $reward->id,
                    'reward_name' => $reward->name,
                    'points' => $reward->points_cost,
                    'payout_amount' => $reward->payoutFor($branch->merchant),
                    'status' => RedemptionStatus::Completed,
                    'request_id' => $requestId,
                    'redeemed_at' => now(),
                ]);

                $this->wallet->debit($customer->id, $reward->points_cost, PointTransactionType::Redeem, $redemption,
                    __(':reward at :branch', ['reward' => $reward->name, 'branch' => "{$branch->merchant->name} {$branch->name}"]));

                return $redemption;
            });
        } catch (InsufficientPoints $e) {
            throw RedemptionRejected::unavailable('reward_id', __('Not enough points: you have :balance, this needs :cost.', [
                'balance' => number_format($e->balance), 'cost' => number_format($e->required),
            ]));
        } catch (UniqueConstraintViolationException $e) {
            // The same request arrived twice at once; the other one won and was committed.
            if ($requestId !== null && ($existing = $this->findByRequest($customer, $requestId))) {
                return $existing;
            }
            throw $e;
        }
    }

    /**
     * Undo a redemption that has not been settled: its points go back to the customer and it is
     * no longer owed to the merchant.
     *
     * @throws RedemptionRejected
     */
    public function reverse(Redemption $redemption, User $actor, string $reason): Redemption
    {
        return $this->db->transaction(function () use ($redemption, $actor, $reason) {
            $locked = Redemption::query()->whereKey($redemption->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isReversible()) {
                throw RedemptionRejected::unavailable('reason', __('Only completed, unsettled redemptions of an existing customer can be reversed.'));
            }

            $locked->update([
                'status' => RedemptionStatus::Reversed,
                'reversed_at' => now(),
                'reversed_by' => $actor->id,
                'reversal_reason' => $reason,
            ]);

            // Points go back to the lots they were taken from, with their original expiry.
            $debit = PointTransaction::query()->where('source_type', $locked->getMorphClass())->where('source_id', $locked->id)
                ->where('type', PointTransactionType::Redeem)->firstOrFail();
            $this->wallet->restore($debit, $locked,
                __('Reversed :reference: :reason', ['reference' => $locked->reference, 'reason' => $reason]), $actor->id);

            return $locked;
        });
    }

    /** Wrong codes count per customer per branch; after too many the customer waits. */
    private function verifyCode(Customer $customer, MerchantBranch $branch, string $code): void
    {
        $key = "merchant-code:{$branch->id}:{$customer->id}";
        $maxAttempts = config('merchant.code_max_attempts');

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            throw RedemptionRejected::lockedOut($this->limiter->availableIn($key));
        }

        if (! $branch->codeMatches($code)) {
            $this->limiter->hit($key, config('merchant.code_lockout_minutes') * 60);
            $left = $maxAttempts - $this->limiter->attempts($key);

            throw $left > 0 ? RedemptionRejected::wrongCode($left) : RedemptionRejected::lockedOut($this->limiter->availableIn($key));
        }

        $this->limiter->clear($key);
    }

    private function findByRequest(Customer $customer, string $requestId): ?Redemption
    {
        return Redemption::query()->where('customer_id', $customer->id)->where('request_id', $requestId)->first();
    }

    /** e.g. RDM-20261008-K7Q2XM: date for humans, random part so references can't be guessed. */
    private function newReference(): string
    {
        return 'RDM-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }
}
