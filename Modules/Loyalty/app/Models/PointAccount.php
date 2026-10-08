<?php

namespace Modules\Loyalty\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Customer\Models\Customer;

/**
 * A customer's points balance. Changed only by PointWallet, under a row lock.
 *
 * @property int $customer_id
 * @property int $balance
 */
class PointAccount extends Model
{
    protected $table = 'loyalty_point_accounts';

    protected $fillable = ['customer_id', 'balance'];

    protected function casts(): array
    {
        return ['balance' => 'integer'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
