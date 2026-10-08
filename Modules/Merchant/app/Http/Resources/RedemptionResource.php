<?php

namespace Modules\Merchant\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Merchant\Models\Redemption;

/**
 * A member's own redemption (the receipt the shop can look at). No payout amount.
 *
 * @mixin Redemption
 */
class RedemptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'status' => $this->status->value,
            'merchant' => $this->merchant->name,
            'branch' => $this->branch->name,
            'reward' => $this->reward_name,
            'points' => $this->points,
            'redeemed_at' => $this->redeemed_at->toIso8601String(),
            'reversed_at' => $this->reversed_at?->toIso8601String(),
        ];
    }
}
