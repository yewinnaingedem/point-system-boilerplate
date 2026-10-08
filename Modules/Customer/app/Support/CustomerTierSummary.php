<?php

namespace Modules\Customer\Support;

use Modules\Customer\Models\Customer;
use Modules\Loyalty\Enums\TierLevel;
use Modules\Loyalty\Models\CycleSpending;
use Modules\Loyalty\Models\LoyaltyTier;
use Modules\Loyalty\Services\TierConfigService;
use Modules\Loyalty\Services\TierQualificationEngine;
use Modules\Loyalty\Support\Amount;

/**
 * Where a customer stands: tier, guarantee, this cycle's spending and what the next tier needs.
 * Evaluates first, so an ended cycle or an expired guarantee is reflected before it is shown.
 */
class CustomerTierSummary
{
    public function __construct(
        private readonly TierQualificationEngine $engine,
        private readonly TierConfigService $config,
    ) {}

    /**
     * @return array<string, mixed>|null null for a customer who was never enrolled
     */
    public function for(Customer $customer): ?array
    {
        $result = $this->engine->evaluateUserTierStatus($customer->id);
        if ($result === null) {
            return null;
        }

        $tiers = $this->config->tiers()->keyBy(fn (LoyaltyTier $tier) => $tier->tier_level->value);
        $ladder = $this->config->ladder();
        $next = collect(TierLevel::cases())->first(fn (TierLevel $level) => $level->isAbove($result->tier) && $ladder->isConfigured($level));
        $spent = CycleSpending::query()->where('customer_id', $customer->id)
            ->where('cycle_start', $result->cycle->start->toDateTimeString())->value('total_spent') ?? Amount::ZERO;

        return [
            'level' => $result->tier,
            'label' => $result->tier->label(),
            'color' => $tiers->get($result->tier->value)?->color,
            'guarantee_expires_at' => $result->guaranteeExpiresAt,
            'cycle_start' => $result->cycle->start,
            'cycle_end' => $result->cycle->end,
            'cycle_spent' => Amount::of($spent),
            'next' => $next === null ? null : [
                'level' => $next,
                'label' => $next->label(),
                'threshold' => $ladder->threshold($next),
                'remaining' => max(0, (float) Amount::subtract($ladder->threshold($next), Amount::of($spent))),
            ],
        ];
    }
}
