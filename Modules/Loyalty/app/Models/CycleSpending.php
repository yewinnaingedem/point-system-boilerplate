<?php

namespace Modules\Loyalty\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Aggregated spending of one member in one cycle. The id is the cycle id.
 * total_spent is only changed with atomic increments, never recomputed from sales.
 *
 * @property int $customer_id
 * @property CarbonImmutable $cycle_start
 * @property CarbonImmutable $cycle_end
 * @property string $total_spent
 * @property int $transaction_count
 */
class CycleSpending extends Model
{
    protected $table = 'loyalty_cycle_spendings';

    protected $fillable = ['customer_id', 'cycle_start', 'cycle_end', 'total_spent', 'transaction_count'];

    protected function casts(): array
    {
        return [
            'cycle_start' => 'immutable_datetime',
            'cycle_end' => 'immutable_datetime',
            'total_spent' => 'decimal:2',
            'transaction_count' => 'integer',
        ];
    }
}
