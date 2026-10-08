<?php

namespace Modules\Loyalty\Enums;

/**
 * Every outcome of the tier state machine. All but Unchanged are written to
 * loyalty_tier_events.
 */
enum TierTransition: string
{
    /** First transaction: the member gets a status row at the base tier. */
    case Enrolled = 'enrolled';

    /** Cycle spending reached a higher tier's threshold. */
    case Upgraded = 'upgraded';

    /** Cycle spending reached the member's own tier threshold again: guarantee extended. */
    case Requalified = 'requalified';

    /** Cycle rolled over below the tier's threshold, but the guarantee kept the tier. */
    case Protected = 'protected';

    /** Guarantee expired and cycle spending no longer qualifies for the tier. */
    case Demoted = 'demoted';

    case Unchanged = 'unchanged';

    public function changesTier(): bool
    {
        return in_array($this, [self::Upgraded, self::Demoted], true);
    }

    public function isRecorded(): bool
    {
        return $this !== self::Unchanged;
    }
}
