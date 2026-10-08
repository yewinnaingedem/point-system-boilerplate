<?php

namespace Modules\Merchant\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $merchant_id
 * @property int $points_cost
 * @property string|null $payout_amount null = points_cost x merchant settlement_rate
 */
class MerchantReward extends Model
{
    protected $fillable = ['merchant_id', 'name', 'description', 'points_cost', 'payout_amount', 'is_active'];

    protected function casts(): array
    {
        return [
            'points_cost' => 'integer',
            'payout_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** What the merchant is owed when this reward is redeemed. */
    public function payoutFor(Merchant $merchant): string
    {
        return $this->payout_amount !== null
            ? bcadd($this->payout_amount, '0', 2)
            : bcmul((string) $this->points_cost, $merchant->settlement_rate, 2);
    }
}
