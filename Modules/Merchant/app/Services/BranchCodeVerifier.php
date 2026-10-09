<?php

namespace Modules\Merchant\Services;

use Illuminate\Cache\RateLimiter;
use Modules\Customer\Models\Customer;
use Modules\Merchant\Exceptions\RedemptionRejected;
use Modules\Merchant\Models\MerchantBranch;

/**
 * "Is this customer really at this shop?" Shop staff type the branch's 6-digit code on the
 * customer's app. Used by reward redemptions and by gift cards used at a branch.
 *
 * Wrong codes are counted per customer per branch: after `code_max_attempts` the customer is
 * locked out of that branch for `code_lockout_minutes`.
 */
class BranchCodeVerifier
{
    public function __construct(private readonly RateLimiter $limiter) {}

    /** The branch, if it and its merchant are active. */
    public function availableBranch(int $branchId): MerchantBranch
    {
        $branch = MerchantBranch::query()->with('merchant')->find($branchId);
        if (! $branch?->is_active || ! $branch->merchant->is_active) {
            throw RedemptionRejected::unavailable('branch_id', __('This shop is not taking redemptions.'));
        }

        return $branch;
    }

    /** @throws RedemptionRejected wrong code (with tries left) or locked out (with retryAfter) */
    public function verify(Customer $customer, MerchantBranch $branch, string $code): void
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
}
