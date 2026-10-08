<?php

namespace Modules\Merchant\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $name
 * @property string $settlement_rate amount paid per point
 * @property bool $is_active
 */
class Merchant extends Model
{
    protected $fillable = ['name', 'contact_person', 'phone', 'email', 'address', 'settlement_rate', 'notes', 'is_active'];

    protected function casts(): array
    {
        return [
            'settlement_rate' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(MerchantBranch::class);
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(MerchantReward::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(Redemption::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Prefix search on name, like the users list. */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $query->when($term, fn (Builder $q) => $q->where(fn (Builder $q) => $q
            ->where('name', 'like', "{$term}%")
            ->orWhere('phone', 'like', "{$term}%")
            ->orWhere('email', 'like', "{$term}%")));
    }
}
