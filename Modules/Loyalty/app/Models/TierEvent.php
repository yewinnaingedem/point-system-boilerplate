<?php

namespace Modules\Loyalty\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Customer\Models\Customer;
use Modules\Loyalty\Enums\TierLevel;
use Modules\Loyalty\Enums\TierTransition;

/**
 * One recorded transition of the tier state machine: a customer's tier history.
 *
 * @property TierTransition $transition
 * @property TierLevel|null $from_tier
 * @property TierLevel $to_tier
 */
class TierEvent extends Model
{
    protected $table = 'loyalty_tier_events';

    protected $fillable = [
        'customer_id', 'transition', 'from_tier', 'to_tier', 'cycle_spent', 'guarantee_expires_at', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'transition' => TierTransition::class,
            'from_tier' => TierLevel::class,
            'to_tier' => TierLevel::class,
            'cycle_spent' => 'decimal:2',
            'guarantee_expires_at' => 'immutable_datetime',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
