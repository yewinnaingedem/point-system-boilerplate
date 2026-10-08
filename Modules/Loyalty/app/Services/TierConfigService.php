<?php

namespace Modules\Loyalty\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\AppSetting\Services\SettingService;
use Modules\Loyalty\Models\LoyaltyTier;
use Modules\Loyalty\Models\MemberTierStatus;
use Modules\Loyalty\Support\TierLadder;

/**
 * Reads and saves the dynamic configuration: thresholds and guarantees per tier
 * (loyalty_tiers) and the cycle length (setting `loyalty_cycle_months`).
 */
class TierConfigService
{
    public const CYCLE_SETTING = 'loyalty_cycle_months';

    private const DEFAULT_CYCLE_MONTHS = 1;

    public function __construct(private readonly SettingService $settings) {}

    public function ladder(): TierLadder
    {
        return TierLadder::fromModels($this->tiers());
    }

    public function cycleMonths(): int
    {
        return max(1, (int) $this->settings->get(self::CYCLE_SETTING, self::DEFAULT_CYCLE_MONTHS));
    }

    /**
     * @return Collection<int, LoyaltyTier> ordered lowest tier first
     */
    public function tiers(): Collection
    {
        return LoyaltyTier::query()->get()->sortBy(fn (LoyaltyTier $tier) => $tier->tier_level->rank())->values();
    }

    /**
     * Save one tier. The base tier keeps threshold 0 and no guarantee; only its colour changes.
     * New values apply to the next transaction or evaluation; tiers and guarantees already
     * given are not recalculated.
     *
     * @param  array{spending_threshold?: string|int|float, guarantee_months?: int, color: string}  $values
     */
    public function updateTier(LoyaltyTier $tier, array $values): void
    {
        $tier->color = strtolower($values['color']);

        if (! $tier->tier_level->isBase()) {
            $tier->spending_threshold = $values['spending_threshold'];
            $tier->guarantee_months = (int) $values['guarantee_months'];
        }

        $tier->save();
    }

    /**
     * Members currently holding each tier.
     *
     * @return array<string, int> keyed by TierLevel value
     */
    public function memberCounts(): array
    {
        return MemberTierStatus::query()
            ->selectRaw('current_tier, count(*) as members')
            ->groupBy('current_tier')
            ->pluck('members', 'current_tier')
            ->map(fn ($count) => (int) $count)
            ->all();
    }
}
