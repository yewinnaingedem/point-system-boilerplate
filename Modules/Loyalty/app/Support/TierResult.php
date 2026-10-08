<?php

namespace Modules\Loyalty\Support;

use Carbon\CarbonImmutable;
use Modules\Loyalty\Enums\TierLevel;
use Modules\Loyalty\Enums\TierTransition;

/**
 * What the engine returns to callers (e.g. a sales module showing the tier on a receipt).
 */
final class TierResult
{
    public function __construct(
        public readonly int $userId,
        public readonly TierLevel $tier,
        public readonly TierTransition $transition,
        public readonly ?CarbonImmutable $guaranteeExpiresAt,
        public readonly CycleWindow $cycle,
        public readonly string $cycleSpent,
    ) {}
}
