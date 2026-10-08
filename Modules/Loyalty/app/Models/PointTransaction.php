<?php

namespace Modules\Loyalty\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Customer\Models\Customer;
use Modules\Loyalty\Enums\PointTransactionType;

/**
 * One change to a customer's points (append-only). created_by is the staff user who made a
 * manual change, if any.
 *
 * @property PointTransactionType $type
 * @property int $points signed
 * @property int $balance_after
 */
class PointTransaction extends Model
{
    protected $table = 'loyalty_point_transactions';

    protected $fillable = ['customer_id', 'type', 'points', 'balance_after', 'source_type', 'source_id', 'note', 'reference', 'created_by'];

    protected function casts(): array
    {
        return [
            'type' => PointTransactionType::class,
            'points' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
