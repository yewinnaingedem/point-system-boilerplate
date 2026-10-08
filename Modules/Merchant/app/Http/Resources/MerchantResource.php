<?php

namespace Modules\Merchant\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Merchant\Models\Merchant;

/**
 * A merchant as the member app sees it: active branches and rewards only. Never contains
 * branch codes or payout amounts (what we pay the merchant is not the member's business).
 *
 * @mixin Merchant
 */
class MerchantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'branches' => $this->branches->map(fn ($branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'address' => $branch->address,
                'phone' => $branch->phone,
            ])->values(),
            'rewards' => $this->rewards->map(fn ($reward) => [
                'id' => $reward->id,
                'name' => $reward->name,
                'description' => $reward->description,
                'points' => $reward->points_cost,
            ])->values(),
        ];
    }
}
