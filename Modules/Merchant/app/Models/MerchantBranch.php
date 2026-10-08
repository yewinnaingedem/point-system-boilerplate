<?php

namespace Modules\Merchant\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $merchant_id
 * @property string $name
 * @property string $code 6 digits, encrypted at rest; never in API responses
 * @property bool $is_active
 */
class MerchantBranch extends Model
{
    protected $fillable = ['merchant_id', 'name', 'phone', 'address', 'is_active'];

    protected $hidden = ['code'];

    protected function casts(): array
    {
        return [
            'code' => 'encrypted',
            'code_changed_at' => 'immutable_datetime',
            'is_active' => 'boolean',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(Redemption::class, 'branch_id');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Constant-time comparison, so response timing gives nothing away. */
    public function codeMatches(string $code): bool
    {
        return hash_equals($this->code, $code);
    }
}
