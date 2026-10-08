<?php

namespace Modules\Loyalty\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * A qualification cycle: the half-open interval [start, end).
 *
 * A member's first cycle starts on the 1st of the month they enrol in; each later cycle
 * starts where the previous one ended. Because every boundary falls on the 1st of a month,
 * adding months never drifts (no Jan 31 → Feb 28 → Mar 28 creep). The cycle length is read
 * at each rollover, so a changed setting applies from the next cycle on.
 */
final class CycleWindow
{
    public function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
    ) {
        if ($end->lessThanOrEqualTo($start)) {
            throw new InvalidArgumentException('A cycle must end after it starts.');
        }
    }

    public static function firstFor(CarbonImmutable $at, int $months): self
    {
        $start = $at->startOfMonth();

        return new self($start, $start->addMonthsNoOverflow(self::guardMonths($months)));
    }

    public function contains(CarbonImmutable $at): bool
    {
        return $at->greaterThanOrEqualTo($this->start) && $at->lessThan($this->end);
    }

    public function hasEndedBy(CarbonImmutable $at): bool
    {
        return $at->greaterThanOrEqualTo($this->end);
    }

    /**
     * The cycle containing $at, stepping forward over any cycles with no activity.
     * Returns $this when $at is still inside it.
     */
    public function rolledForwardTo(CarbonImmutable $at, int $months): self
    {
        $months = self::guardMonths($months);
        $window = $this;

        while ($window->hasEndedBy($at)) {
            $window = new self($window->end, $window->end->addMonthsNoOverflow($months));
        }

        return $window;
    }

    private static function guardMonths(int $months): int
    {
        if ($months < 1) {
            throw new InvalidArgumentException('A cycle lasts at least one month.');
        }

        return $months;
    }
}
