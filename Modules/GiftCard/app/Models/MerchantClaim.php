<?php

namespace Modules\GiftCard\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\GiftCard\Enums\ClaimStatus;
use Modules\Merchant\Models\Merchant;

/**
 * A merchant asks to be paid for the gift cards used at its branches (all its unclaimed used
 * cards up to a day). Our staff pay it or reject it.
 *
 * @property ClaimStatus $status
 */
class MerchantClaim extends Model
{
    protected $fillable = ['reference', 'merchant_id', 'status', 'cards_count', 'amount', 'up_to', 'note',
        'created_by', 'paid_on', 'payment_reference', 'decided_by', 'decided_at', 'reject_reason'];

    protected function casts(): array
    {
        return [
            'status' => ClaimStatus::class,
            'cards_count' => 'integer',
            'amount' => 'decimal:2',
            'up_to' => 'immutable_date',
            'paid_on' => 'immutable_date',
            'decided_at' => 'immutable_datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /** The cards in this claim (released again when it is rejected or deleted). */
    public function exchanges(): HasMany
    {
        return $this->hasMany(GiftCardExchange::class, 'claim_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isOpen(): bool
    {
        return $this->status === ClaimStatus::Submitted;
    }

    /** What this user may see: our staff everything, a merchant's staff their own merchant. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->when($user->isMerchantUser(), fn (Builder $q) => $q->where('merchant_id', $user->merchant_id));
    }
}
