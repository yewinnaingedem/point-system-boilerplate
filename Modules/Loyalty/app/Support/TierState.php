<?php

namespace Modules\Loyalty\Support;

use Carbon\CarbonImmutable;
use Modules\Loyalty\Enums\TierLevel;

/**
 * The part of a member's status the state machine decides on.
 */
final class TierState
{
    public function __construct(
        public readonly TierLevel $tier,
        public readonly ?CarbonImmutable $guaranteeExpiresAt,
    ) {}

    /** Guarantee holds while now < guarantee_expires_at (the expiry instant itself is unprotected). */
    public function isGuaranteedAt(CarbonImmutable $at): bool
    {
        return $this->guaranteeExpiresAt !== null && $at->lessThan($this->guaranteeExpiresAt);
    }
}
