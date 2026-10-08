<?php

namespace Modules\GiftCard\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Loyalty\Enums\TierLevel;

/**
 * A gift card customers can get for points, with optional tier, stock and per-customer limits.
 *
 * @property int $points_cost
 * @property TierLevel|null $min_tier
 * @property int|null $stock null = unlimited
 * @property int|null $per_customer_limit null = no limit
 */
class GiftCard extends Model
{
    protected $fillable = ['name', 'description', 'points_cost', 'face_value', 'min_tier', 'stock',
        'per_customer_limit', 'requires_verification', 'valid_days', 'is_active'];

    protected function casts(): array
    {
        return [
            'points_cost' => 'integer',
            'face_value' => 'decimal:2',
            'min_tier' => TierLevel::class,
            'stock' => 'integer',
            'per_customer_limit' => 'integer',
            'requires_verification' => 'boolean',
            'valid_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function exchanges(): HasMany
    {
        return $this->hasMany(GiftCardExchange::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function isOutOfStock(): bool
    {
        return $this->stock !== null && $this->stock <= 0;
    }

    /** Whether a customer at $tier may have it (min tier or higher). */
    public function allowsTier(?TierLevel $tier): bool
    {
        return $this->min_tier === null || ($tier !== null && $tier->rank() >= $this->min_tier->rank());
    }
}
