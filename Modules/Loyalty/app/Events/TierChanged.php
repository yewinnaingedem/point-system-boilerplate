<?php

namespace Modules\Loyalty\Events;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\Loyalty\Enums\TierLevel;
use Modules\Loyalty\Enums\TierTransition;

/**
 * A member moved up or down a tier. Dispatched only after the transaction commits, so a
 * listener (notification, receipt message) never sees a change that was rolled back.
 */
class TierChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
        public readonly TierTransition $transition,
        public readonly TierLevel $from,
        public readonly TierLevel $to,
        public readonly CarbonImmutable $occurredAt,
    ) {}
}
