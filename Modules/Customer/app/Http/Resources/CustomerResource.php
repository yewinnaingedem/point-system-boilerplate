<?php

namespace Modules\Customer\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customer\Models\Customer;
use Modules\Customer\Support\CustomerTierSummary;
use Modules\Loyalty\Services\PointWallet;

/**
 * The signed-in customer as their app sees them. Explicit fields only.
 *
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tier = app(CustomerTierSummary::class)->for($this->resource);

        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'points' => app(PointWallet::class)->balance($this->id),
            'tier' => $tier === null ? null : [
                'level' => $tier['level']->value,
                'label' => $tier['label'],
                'color' => $tier['color'],
                'guarantee_expires_at' => $tier['guarantee_expires_at']?->toIso8601String(),
                'cycle' => [
                    'start' => $tier['cycle_start']->toIso8601String(),
                    'end' => $tier['cycle_end']->toIso8601String(),
                    'spent' => $tier['cycle_spent'],
                ],
                'next' => $tier['next'] === null ? null : [
                    'level' => $tier['next']['level']->value,
                    'label' => $tier['next']['label'],
                    'threshold' => $tier['next']['threshold'],
                    'remaining' => $tier['next']['remaining'],
                ],
            ],
        ];
    }
}
