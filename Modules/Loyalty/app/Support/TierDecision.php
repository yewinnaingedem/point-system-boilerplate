<?php

namespace Modules\Loyalty\Support;

use Modules\Loyalty\Enums\TierTransition;

/**
 * Outcome of one state machine step: the transition taken and the resulting state.
 */
final class TierDecision
{
    public function __construct(
        public readonly TierTransition $transition,
        public readonly TierState $from,
        public readonly TierState $to,
    ) {}

    public static function unchanged(TierState $state): self
    {
        return new self(TierTransition::Unchanged, $state, $state);
    }
}
