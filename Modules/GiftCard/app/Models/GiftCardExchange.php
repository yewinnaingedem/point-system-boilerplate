<?php

namespace Modules\GiftCard\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Customer\Models\Customer;
use Modules\GiftCard\Enums\ExchangeStatus;

/**
 * @property ExchangeStatus $status
 * @property string|null $code
 */
class GiftCardExchange extends Model
{
    protected $fillable = ['gift_card_id', 'customer_id', 'status', 'points', 'face_value', 'code', 'verification_hash',
        'verification_expires_at', 'verification_attempts', 'issued_at', 'expires_at', 'cancelled_at', 'cancelled_by', 'cancel_reason'];

    protected $hidden = ['verification_hash'];

    protected function casts(): array
    {
        return [
            'status' => ExchangeStatus::class,
            'points' => 'integer',
            'face_value' => 'decimal:2',
            'verification_attempts' => 'integer',
            'verification_expires_at' => 'immutable_datetime',
            'issued_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
