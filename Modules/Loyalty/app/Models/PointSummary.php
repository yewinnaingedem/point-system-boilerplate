<?php

namespace Modules\Loyalty\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One customer's points activity in one month. Kept in step with the ledger by PointWallet.
 *
 * @property CarbonImmutable $period first day of the month
 */
class PointSummary extends Model
{
    public const COLUMNS = ['earned', 'redeemed', 'reversed', 'adjusted_in', 'adjusted_out', 'expired'];

    protected $table = 'loyalty_point_summaries';

    protected $fillable = ['customer_id', 'period', ...self::COLUMNS];

    protected function casts(): array
    {
        return ['period' => 'immutable_date'] + array_fill_keys(self::COLUMNS, 'integer');
    }
}
