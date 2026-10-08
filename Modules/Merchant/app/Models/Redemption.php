<?php

namespace Modules\Merchant\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Customer\Models\Customer;
use Modules\Merchant\Enums\RedemptionStatus;

/**
 * A reward handed over at a branch. Written by RedemptionService only.
 *
 * @property string $reference
 * @property RedemptionStatus $status
 * @property int $points
 * @property string $payout_amount
 * @property int|null $settlement_id set by the (future) payout module
 */
class Redemption extends Model
{
    protected $table = 'merchant_redemptions';

    protected $fillable = [
        'reference', 'customer_id', 'merchant_id', 'branch_id', 'reward_id', 'reward_name', 'points',
        'payout_amount', 'status', 'settlement_id', 'request_id', 'redeemed_at', 'reversed_at',
        'reversed_by', 'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => RedemptionStatus::class,
            'points' => 'integer',
            'payout_amount' => 'decimal:2',
            'redeemed_at' => 'immutable_datetime',
            'reversed_at' => 'immutable_datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(MerchantBranch::class, 'branch_id');
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(MerchantReward::class, 'reward_id');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    /** Completed and not yet in a settlement: what we still owe the merchant. */
    public function scopeUnsettled(Builder $query): void
    {
        $query->where('status', RedemptionStatus::Completed)->whereNull('settlement_id');
    }

    public function isReversible(): bool
    {
        return $this->status === RedemptionStatus::Completed && $this->settlement_id === null && $this->customer_id !== null;
    }
}
