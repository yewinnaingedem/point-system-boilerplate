<?php

namespace Modules\GiftCard\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\GiftCard\Models\GiftCardExchange;

/** @mixin GiftCardExchange */
class ExchangeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'gift_card' => $this->giftCard->name,
            'for_merchant' => $this->giftCard->merchant?->name,                      // where it can be used; null = any partner shop
            'points' => $this->points,
            'value' => $this->face_value,
            'code' => $this->code,                                    // only once issued
            'expires_at' => $this->expires_at?->toIso8601String(),
            'verify_before' => $this->verification_expires_at?->toIso8601String(), // pending only
            'issued_at' => $this->issued_at?->toIso8601String(),
            'used_at' => $this->used_at?->toIso8601String(),                       // used only
            'used_at_merchant' => $this->whenLoaded('merchant', fn () => $this->merchant?->name),
            'used_at_branch' => $this->whenLoaded('branch', fn () => $this->branch?->name),
        ];
    }
}
