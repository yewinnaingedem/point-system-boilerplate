<?php

namespace Modules\GiftCard\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Customer\Models\Customer;
use Modules\GiftCard\Enums\ExchangeStatus;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantBranch;

/**
 * @property ExchangeStatus $status
 * @property string|null $code
 */
class GiftCardExchange extends Model
{
    protected $fillable = ['gift_card_id', 'customer_id', 'status', 'points', 'face_value', 'code', 'verification_hash',
        'verification_expires_at', 'verification_attempts', 'issued_at', 'expires_at', 'used_at', 'merchant_id', 'branch_id', 'payout_amount', 'cancelled_at', 'cancelled_by', 'cancel_reason'];

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
            'used_at' => 'immutable_datetime',
            'payout_amount' => 'decimal:2',
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

    /** Where it was used (status used). */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(MerchantBranch::class, 'branch_id');
    }

    /** The merchant's claim this used card is in, if any. */
    public function claim(): BelongsTo
    {
        return $this->belongsTo(MerchantClaim::class, 'claim_id');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
