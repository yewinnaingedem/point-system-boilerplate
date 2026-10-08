<?php

namespace Modules\Customer\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Loyalty\Models\TierEvent;

/**
 * One entry of a customer's tier history.
 *
 * @mixin TierEvent
 */
class TierEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'transition' => $this->transition->value,
            'from' => $this->from_tier?->value,
            'to' => $this->to_tier->value,
            'cycle_spent' => $this->cycle_spent,
            'guarantee_expires_at' => $this->guarantee_expires_at?->toIso8601String(),
            'at' => $this->occurred_at->toIso8601String(),
        ];
    }
}
