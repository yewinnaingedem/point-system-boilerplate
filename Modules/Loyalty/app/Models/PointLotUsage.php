<?php

namespace Modules\Loyalty\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** How many points one debit took from one lot. */
class PointLotUsage extends Model
{
    protected $table = 'loyalty_point_lot_usages';

    protected $fillable = ['transaction_id', 'lot_id', 'points'];

    protected function casts(): array
    {
        return ['points' => 'integer'];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(PointLot::class);
    }
}
