<?php

namespace Modules\Loyalty\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Customer\Models\Customer;
use Modules\Loyalty\Enums\TierLevel;

/**
 * The current state of one member in the tier state machine.
 * Written only by TierQualificationEngine, under a row lock.
 *
 * @property int $customer_id
 * @property TierLevel $current_tier
 * @property CarbonImmutable|null $guarantee_expires_at
 * @property CarbonImmutable $current_cycle_start
 * @property CarbonImmutable $current_cycle_end
 * @property CarbonImmutable $next_evaluation_at
 * @property CarbonImmutable|null $last_evaluated_at
 */
class MemberTierStatus extends Model
{
    protected $table = 'loyalty_member_statuses';

    protected $fillable = [
        'customer_id', 'current_tier', 'guarantee_expires_at', 'current_cycle_start',
        'current_cycle_end', 'next_evaluation_at', 'last_evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'current_tier' => TierLevel::class,
            'guarantee_expires_at' => 'immutable_datetime',
            'current_cycle_start' => 'immutable_datetime',
            'current_cycle_end' => 'immutable_datetime',
            'next_evaluation_at' => 'immutable_datetime',
            'last_evaluated_at' => 'immutable_datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Members whose cycle has ended or whose guarantee has run out by $at. */
    public function scopeDueForEvaluation(Builder $query, \DateTimeInterface $at): void
    {
        $query->where('next_evaluation_at', '<=', $at);
    }
}
