<?php

namespace Modules\Loyalty\Support;

use Carbon\CarbonImmutable;
use Modules\Loyalty\Enums\TierTransition;

/**
 * The tier state machine. Pure: no database, no clock; the engine supplies the state, the
 * configuration snapshot and the moment, and persists the decision.
 *
 * Invariant kept after every step:
 *
 *     tier = max( tier qualified by current-cycle spending,
 *                 current tier, while its guarantee is still running )
 *
 * Transitions, checked in this order:
 *
 *   Upgraded     qualified tier > current tier. Immediate; guarantee = at + new tier's months.
 *   Requalified  spending crossed the current tier's own threshold in this step, so the member
 *                earned it again this cycle; guarantee extended (never shortened).
 *   Demoted      qualified tier < current tier and the guarantee is not running at `at`.
 *                Target is the qualified tier (not simply one step down), which then gets a
 *                fresh guarantee because it was earned in this cycle. Silver gets none.
 *   Protected    qualified tier < current tier at a cycle rollover, but the guarantee holds.
 *                The tier and guarantee stay; the step is only recorded.
 *   Unchanged    anything else.
 *
 * A demotion can therefore happen at a rollover or mid-cycle when a guarantee runs out while
 * this cycle's spending is short, never while a guarantee is running. An upgrade never
 * waits for a rollover.
 */
final class TierStateMachine
{
    /**
     * @param  string  $spentBefore  cycle spending before this step (equal to $spentAfter for a pure evaluation)
     * @param  string  $spentAfter  cycle spending after this step
     * @param  bool  $rolledOver  a new cycle began in this step
     */
    public function decide(
        TierState $state,
        TierLadder $ladder,
        string $spentBefore,
        string $spentAfter,
        bool $rolledOver,
        CarbonImmutable $at,
    ): TierDecision {
        $current = $state->tier;
        $qualified = $ladder->qualifiedFor($spentAfter);

        if ($qualified->isAbove($current)) {
            return new TierDecision(
                TierTransition::Upgraded,
                $state,
                new TierState($qualified, $ladder->guaranteeUntil($qualified, $at)),
            );
        }

        if ($qualified === $current && $this->crossedThreshold($ladder->threshold($current), $spentBefore, $spentAfter)) {
            $extended = $this->later($state->guaranteeExpiresAt, $ladder->guaranteeUntil($current, $at));

            return $extended == $state->guaranteeExpiresAt
                ? TierDecision::unchanged($state)
                : new TierDecision(TierTransition::Requalified, $state, new TierState($current, $extended));
        }

        if ($current->isAbove($qualified)) {
            if (! $state->isGuaranteedAt($at)) {
                return new TierDecision(
                    TierTransition::Demoted,
                    $state,
                    new TierState($qualified, $ladder->guaranteeUntil($qualified, $at)),
                );
            }

            if ($rolledOver) {
                return new TierDecision(TierTransition::Protected, $state, $state);
            }
        }

        return TierDecision::unchanged($state);
    }

    /** True only for the step that takes spending from below the threshold to at/above it. */
    private function crossedThreshold(string $threshold, string $before, string $after): bool
    {
        return Amount::isPositive($threshold)
            && Amount::compare($before, $threshold) < 0
            && Amount::compare($after, $threshold) >= 0;
    }

    private function later(?CarbonImmutable $a, ?CarbonImmutable $b): ?CarbonImmutable
    {
        if ($a === null || $b === null) {
            return $a ?? $b;
        }

        return $a->greaterThan($b) ? $a : $b;
    }
}
