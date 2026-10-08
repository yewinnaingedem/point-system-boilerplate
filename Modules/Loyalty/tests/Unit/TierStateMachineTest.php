<?php

namespace Modules\Loyalty\Tests\Unit;

use Carbon\CarbonImmutable;
use Modules\Loyalty\Enums\TierLevel;
use Modules\Loyalty\Enums\TierTransition;
use Modules\Loyalty\Support\CycleWindow;
use Modules\Loyalty\Support\TierLadder;
use Modules\Loyalty\Support\TierState;
use Modules\Loyalty\Support\TierStateMachine;
use PHPUnit\Framework\TestCase;

/**
 * The pure transition rules, without a database.
 */
class TierStateMachineTest extends TestCase
{
    private TierStateMachine $machine;

    private TierLadder $ladder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->machine = new TierStateMachine;
        $this->ladder = TierLadder::fromArray([
            'silver' => [0, 0],
            'gold' => [500000, 3],
            'platinum' => [1500000, 3],
            'diamond' => [3000000, 6],
        ]);
    }

    public function test_reaching_a_threshold_upgrades_immediately_with_a_fresh_guarantee(): void
    {
        $at = CarbonImmutable::parse('2026-01-12 10:00');

        $decision = $this->decide(new TierState(TierLevel::Silver, null), '300000.00', '500000.00', false, $at);

        $this->assertSame(TierTransition::Upgraded, $decision->transition);
        $this->assertSame(TierLevel::Gold, $decision->to->tier);
        $this->assertEquals($at->addMonths(3), $decision->to->guaranteeExpiresAt);
    }

    public function test_one_large_sale_can_skip_tiers(): void
    {
        $decision = $this->decide(new TierState(TierLevel::Silver, null), '0.00', '3200000.00', false, CarbonImmutable::parse('2026-01-12'));

        $this->assertSame(TierLevel::Diamond, $decision->to->tier);
    }

    public function test_spending_just_below_a_threshold_does_not_upgrade(): void
    {
        $decision = $this->decide(new TierState(TierLevel::Silver, null), '0.00', '499999.99', false, CarbonImmutable::parse('2026-01-12'));

        $this->assertSame(TierTransition::Unchanged, $decision->transition);
    }

    public function test_rollover_below_threshold_keeps_the_tier_while_guaranteed(): void
    {
        $state = new TierState(TierLevel::Gold, CarbonImmutable::parse('2026-04-12 10:00'));

        $decision = $this->decide($state, '0.00', '0.00', true, CarbonImmutable::parse('2026-04-01'));

        $this->assertSame(TierTransition::Protected, $decision->transition);
        $this->assertSame(TierLevel::Gold, $decision->to->tier);
        $this->assertEquals($state->guaranteeExpiresAt, $decision->to->guaranteeExpiresAt);
    }

    public function test_the_guarantee_does_not_hold_at_its_expiry_instant(): void
    {
        $expiry = CarbonImmutable::parse('2026-04-12 10:00');

        $decision = $this->decide(new TierState(TierLevel::Gold, $expiry), '0.00', '0.00', false, $expiry);

        $this->assertSame(TierTransition::Demoted, $decision->transition);
        $this->assertSame(TierLevel::Silver, $decision->to->tier);
        $this->assertNull($decision->to->guaranteeExpiresAt);
    }

    public function test_demotion_lands_on_the_tier_this_cycle_qualifies_for(): void
    {
        $at = CarbonImmutable::parse('2026-05-01');
        $state = new TierState(TierLevel::Diamond, CarbonImmutable::parse('2026-04-20'));

        $decision = $this->decide($state, '700000.00', '700000.00', false, $at);

        $this->assertSame(TierTransition::Demoted, $decision->transition);
        $this->assertSame(TierLevel::Gold, $decision->to->tier);
        $this->assertEquals($at->addMonths(3), $decision->to->guaranteeExpiresAt);
    }

    public function test_expired_guarantee_is_harmless_while_spending_still_qualifies(): void
    {
        $state = new TierState(TierLevel::Gold, CarbonImmutable::parse('2026-03-01'));

        $decision = $this->decide($state, '600000.00', '600000.00', true, CarbonImmutable::parse('2026-04-01'));

        $this->assertSame(TierTransition::Unchanged, $decision->transition);
    }

    public function test_reaching_the_own_threshold_again_extends_the_guarantee(): void
    {
        $at = CarbonImmutable::parse('2026-03-05');
        $state = new TierState(TierLevel::Gold, CarbonImmutable::parse('2026-04-12'));

        $decision = $this->decide($state, '400000.00', '520000.00', false, $at);

        $this->assertSame(TierTransition::Requalified, $decision->transition);
        $this->assertEquals($at->addMonths(3), $decision->to->guaranteeExpiresAt);

        // Further sales in the same cycle don't keep pushing it out.
        $again = $this->decide($decision->to, '520000.00', '800000.00', false, $at->addDay());
        $this->assertSame(TierTransition::Unchanged, $again->transition);
    }

    public function test_a_guaranteed_member_is_never_demoted_mid_cycle(): void
    {
        $state = new TierState(TierLevel::Platinum, CarbonImmutable::parse('2026-06-01'));

        $decision = $this->decide($state, '100.00', '100.00', false, CarbonImmutable::parse('2026-05-10'));

        $this->assertSame(TierTransition::Unchanged, $decision->transition);
        $this->assertSame(TierLevel::Platinum, $decision->to->tier);
    }

    public function test_cycle_window_skips_empty_cycles_on_month_boundaries(): void
    {
        $first = CycleWindow::firstFor(CarbonImmutable::parse('2026-01-31 18:00'), 1);
        $this->assertEquals(CarbonImmutable::parse('2026-01-01'), $first->start);
        $this->assertEquals(CarbonImmutable::parse('2026-02-01'), $first->end);

        $later = $first->rolledForwardTo(CarbonImmutable::parse('2026-06-15'), 1);
        $this->assertEquals(CarbonImmutable::parse('2026-06-01'), $later->start);
        $this->assertEquals(CarbonImmutable::parse('2026-07-01'), $later->end);

        $this->assertSame($first, $first->rolledForwardTo(CarbonImmutable::parse('2026-01-31 23:59:59'), 1));
    }

    private function decide(TierState $state, string $before, string $after, bool $rolledOver, CarbonImmutable $at)
    {
        return $this->machine->decide($state, $this->ladder, $before, $after, $rolledOver, $at);
    }
}
