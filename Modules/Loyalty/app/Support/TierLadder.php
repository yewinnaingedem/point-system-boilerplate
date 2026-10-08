<?php

namespace Modules\Loyalty\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\Loyalty\Enums\TierLevel;
use Modules\Loyalty\Models\LoyaltyTier;

/**
 * Immutable snapshot of the tier configuration used for one decision, so a concurrent
 * edit on the settings screen can't change thresholds halfway through an evaluation.
 */
final class TierLadder
{
    /**
     * @param  array<string, array{threshold: string, guarantee_months: int}>  $tiers  keyed by TierLevel value
     */
    private function __construct(private readonly array $tiers) {}

    /**
     * @param  Collection<int, LoyaltyTier>  $rows
     */
    public static function fromModels(Collection $rows): self
    {
        $tiers = [];
        foreach ($rows as $row) {
            $tiers[$row->tier_level->value] = [
                'threshold' => Amount::of($row->spending_threshold),
                'guarantee_months' => $row->tier_level->isBase() ? 0 : $row->guarantee_months,
            ];
        }

        return new self($tiers);
    }

    /**
     * @param  array<string, array{0: string|int, 1: int}>  $tiers  level value => [threshold, guarantee months]
     */
    public static function fromArray(array $tiers): self
    {
        return new self(array_map(
            fn (array $tier) => ['threshold' => Amount::of($tier[0]), 'guarantee_months' => $tier[1]],
            $tiers,
        ));
    }

    /**
     * Whether this level can be reached at all. A level without a configuration row is
     * treated as switched off; the base tier is always available.
     */
    public function isConfigured(TierLevel $level): bool
    {
        return $level->isBase() || isset($this->tiers[$level->value]);
    }

    public function threshold(TierLevel $level): string
    {
        return $level->isBase() ? Amount::ZERO : ($this->tiers[$level->value]['threshold'] ?? Amount::ZERO);
    }

    public function guaranteeMonths(TierLevel $level): int
    {
        return $level->isBase() ? 0 : ($this->tiers[$level->value]['guarantee_months'] ?? 0);
    }

    /** The highest tier whose threshold $spent reaches. */
    public function qualifiedFor(string $spent): TierLevel
    {
        $qualified = TierLevel::base();

        foreach (TierLevel::cases() as $level) {
            if ($this->isConfigured($level) && Amount::compare($spent, $this->threshold($level)) >= 0 && $level->isAbove($qualified)) {
                $qualified = $level;
            }
        }

        return $qualified;
    }

    /** When a guarantee for $level granted at $at runs out; null when the tier has none. */
    public function guaranteeUntil(TierLevel $level, CarbonImmutable $at): ?CarbonImmutable
    {
        $months = $this->guaranteeMonths($level);

        return $months > 0 ? $at->addMonthsNoOverflow($months) : null;
    }
}
