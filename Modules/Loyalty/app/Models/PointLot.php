<?php

namespace Modules\Loyalty\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Customer\Models\Customer;

/**
 * Points from one credit, with what is left of them and when they expire.
 *
 * @property int $customer_id
 * @property int $points
 * @property int $remaining
 * @property CarbonImmutable $earned_at
 * @property CarbonImmutable|null $expires_at exclusive; null = never
 */
class PointLot extends Model
{
    protected $table = 'loyalty_point_lots';

    protected $fillable = ['customer_id', 'transaction_id', 'points', 'remaining', 'earned_at', 'expires_at'];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'remaining' => 'integer',
            'earned_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PointTransaction::class);
    }

    /** Lots with points left that can still be spent at $at, soonest expiry first (spend order). */
    public function scopeSpendable(Builder $query, \DateTimeInterface $at): void
    {
        $query->where('remaining', '>', 0)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $at))
            ->orderByRaw('expires_at is null')->orderBy('expires_at')->orderBy('id');
    }

    /** Lots with points left whose expiry has passed. */
    public function scopeDue(Builder $query, \DateTimeInterface $at): void
    {
        $query->where('remaining', '>', 0)->whereNotNull('expires_at')->where('expires_at', '<=', $at);
    }
}
