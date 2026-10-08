<?php

namespace Modules\Loyalty\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Modules\AppSetting\Services\SettingService;
use Modules\AppSetting\Support\SettingCatalog;
use Modules\Customer\Models\Customer;
use Modules\Loyalty\Enums\TierLevel;
use Modules\Loyalty\Enums\TierTransition;
use Modules\Loyalty\Events\TierChanged;
use Modules\Loyalty\Exceptions\TransactionOutsideActiveCycle;
use Modules\Loyalty\Models\CycleSpending;
use Modules\Loyalty\Models\LoyaltyTier;
use Modules\Loyalty\Models\MemberTierStatus;
use Modules\Loyalty\Models\TierEvent;
use Modules\Loyalty\Services\TierQualificationEngine;
use Tests\TestCase;

/**
 * The engine against a real database, with the default tier configuration:
 * Gold 500,000 / Platinum 1,500,000 / Diamond 3,000,000, each guaranteed 3 months,
 * 1-month cycles.
 */
class TierQualificationEngineTest extends TestCase
{
    use RefreshDatabase;

    private TierQualificationEngine $engine;

    private Customer $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = $this->app->make(TierQualificationEngine::class);
        $this->member = Customer::factory()->create();
    }

    // (a) Immediate upgrade on spending

    public function test_member_is_upgraded_the_moment_cycle_spending_reaches_the_threshold(): void
    {
        Event::fake([TierChanged::class]);

        $first = $this->buy(300000, '2026-01-10 09:00');
        $this->assertSame(TierLevel::Silver, $first->tier);

        $second = $this->buy(200000, '2026-01-12 15:30');

        $this->assertSame(TierTransition::Upgraded, $second->transition);
        $this->assertSame(TierLevel::Gold, $second->tier);
        $this->assertEquals(CarbonImmutable::parse('2026-04-12 15:30'), $second->guaranteeExpiresAt);
        $this->assertSame('500000.00', $second->cycleSpent);

        $status = $this->memberStatus();
        $this->assertSame(TierLevel::Gold, $status->current_tier);
        $this->assertEquals(CarbonImmutable::parse('2026-01-01'), $status->current_cycle_start);
        $this->assertEquals(CarbonImmutable::parse('2026-02-01'), $status->current_cycle_end);

        $cycle = CycleSpending::query()->where('customer_id', $this->member->id)->sole();
        $this->assertSame('500000.00', $cycle->total_spent);
        $this->assertSame(2, $cycle->transaction_count);

        $this->assertSame(
            [TierTransition::Enrolled, TierTransition::Upgraded],
            TierEvent::query()->orderBy('id')->get()->pluck('transition')->all(),
        );
        Event::assertDispatched(TierChanged::class, fn (TierChanged $e) => $e->from === TierLevel::Silver && $e->to === TierLevel::Gold);
    }

    public function test_upgrade_counts_only_the_current_cycle(): void
    {
        $this->buy(400000, '2026-01-20');
        $result = $this->buy(400000, '2026-02-03');

        // 800,000 over two cycles, but only 400,000 in February.
        $this->assertSame(TierLevel::Silver, $result->tier);
        $this->assertSame('400000.00', $result->cycleSpent);
        $this->assertSame(2, CycleSpending::query()->count());
    }

    // (b) Cycle rollover with active guarantee protection

    public function test_tier_survives_rollovers_while_the_guarantee_is_running(): void
    {
        $this->buy(500000, '2026-01-12 15:30'); // Gold, guaranteed until 2026-04-12 15:30

        foreach (['2026-02-01 00:00', '2026-03-01 00:00', '2026-04-01 00:00'] as $rollover) {
            $result = $this->engine->evaluateUserTierStatus($this->member->id, CarbonImmutable::parse($rollover));

            $this->assertSame(TierTransition::Protected, $result->transition, $rollover);
            $this->assertSame(TierLevel::Gold, $result->tier, $rollover);
            $this->assertSame('0.00', $result->cycleSpent, $rollover);
            $this->assertEquals(CarbonImmutable::parse($rollover), $result->cycle->start);
        }

        $status = $this->memberStatus();
        $this->assertEquals(CarbonImmutable::parse('2026-04-12 15:30'), $status->guarantee_expires_at);
        // The guarantee ends before the April cycle does, so that is when to look again.
        $this->assertEquals(CarbonImmutable::parse('2026-04-12 15:30'), $status->next_evaluation_at);
    }

    public function test_a_sale_after_rollover_is_protected_and_starts_spending_from_zero(): void
    {
        $this->buy(500000, '2026-01-12');

        $result = $this->buy(1000, '2026-02-15');

        $this->assertSame(TierTransition::Protected, $result->transition);
        $this->assertSame(TierLevel::Gold, $result->tier);
        $this->assertSame('1000.00', $result->cycleSpent);
    }

    // (c) Expiry of the guarantee leading to demotion

    public function test_member_is_demoted_once_the_guarantee_has_expired_and_spending_is_short(): void
    {
        Event::fake([TierChanged::class]);
        $this->buy(500000, '2026-01-12 15:30');
        $this->buy(100000, '2026-04-05'); // April spending, below Gold

        $justBefore = $this->engine->evaluateUserTierStatus($this->member->id, CarbonImmutable::parse('2026-04-12 15:29:59'));
        $this->assertSame(TierLevel::Gold, $justBefore->tier);

        $result = $this->engine->evaluateUserTierStatus($this->member->id, CarbonImmutable::parse('2026-04-12 15:30'));

        $this->assertSame(TierTransition::Demoted, $result->transition);
        $this->assertSame(TierLevel::Silver, $result->tier);
        $this->assertNull($result->guaranteeExpiresAt);
        $this->assertSame(TierLevel::Silver, $this->memberStatus()->current_tier);
        $this->assertEquals(CarbonImmutable::parse('2026-05-01'), $this->memberStatus()->next_evaluation_at);
        Event::assertDispatched(TierChanged::class, fn (TierChanged $e) => $e->transition === TierTransition::Demoted);
    }

    public function test_demotion_stops_at_the_tier_the_current_cycle_still_qualifies_for(): void
    {
        $this->buy(1500000, '2026-01-10'); // Platinum until 2026-04-10
        $this->buy(600000, '2026-04-02');  // April alone is Gold level

        $result = $this->engine->evaluateUserTierStatus($this->member->id, CarbonImmutable::parse('2026-04-10'));

        $this->assertSame(TierTransition::Demoted, $result->transition);
        $this->assertSame(TierLevel::Gold, $result->tier);
        $this->assertEquals(CarbonImmutable::parse('2026-07-10'), $result->guaranteeExpiresAt);
    }

    public function test_requalifying_extends_the_guarantee_so_no_demotion_follows(): void
    {
        $this->buy(500000, '2026-01-12');                  // Gold until 2026-04-12
        $result = $this->buy(500000, '2026-03-20 11:00'); // Gold reached again in March

        $this->assertSame(TierTransition::Requalified, $result->transition);
        $this->assertEquals(CarbonImmutable::parse('2026-06-20 11:00'), $result->guaranteeExpiresAt);

        $later = $this->engine->evaluateUserTierStatus($this->member->id, CarbonImmutable::parse('2026-05-01'));
        $this->assertSame(TierLevel::Gold, $later->tier);
    }

    public function test_long_inactivity_rolls_over_once_and_demotes(): void
    {
        $this->buy(500000, '2026-01-12');

        $result = $this->engine->evaluateUserTierStatus($this->member->id, CarbonImmutable::parse('2026-09-15'));

        $this->assertSame(TierTransition::Demoted, $result->transition);
        $this->assertEquals(CarbonImmutable::parse('2026-09-01'), $result->cycle->start);
    }

    public function test_cycle_length_comes_from_settings(): void
    {
        app(SettingService::class)->saveGroup(app(SettingCatalog::class)->group('loyalty'), ['loyalty_cycle_months' => '3']);

        $this->buy(300000, '2026-01-20');
        $result = $this->buy(200000, '2026-03-31'); // still the same quarter

        $this->assertSame(TierLevel::Gold, $result->tier);
        $this->assertEquals(CarbonImmutable::parse('2026-04-01'), $result->cycle->end);
    }

    public function test_tier_configuration_is_read_from_the_database(): void
    {
        LoyaltyTier::query()->where('tier_level', 'gold')->update(['spending_threshold' => 100]);

        $this->assertSame(TierLevel::Gold, $this->buy(100, '2026-01-02')->tier);
    }

    public function test_transactions_before_the_active_cycle_are_rejected(): void
    {
        $this->buy(1000, '2026-03-02');

        $this->expectException(TransactionOutsideActiveCycle::class);
        $this->buy(1000, '2026-02-27');
    }

    public function test_amount_must_be_positive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->buy(0, '2026-01-02');
    }

    public function test_evaluating_someone_never_enrolled_returns_null(): void
    {
        $this->assertNull($this->engine->evaluateUserTierStatus($this->member->id));
    }

    public function test_scheduled_command_evaluates_only_members_that_are_due(): void
    {
        $quiet = Customer::factory()->create();
        $this->engine->processTransaction($quiet->id, 500000, CarbonImmutable::parse('2026-01-12'));
        $this->buy(1000, CarbonImmutable::now()->toDateTimeString());

        $this->artisan('loyalty:evaluate-tiers')->assertSuccessful();

        $quietStatus = MemberTierStatus::query()->where('customer_id', $quiet->id)->sole();
        $this->assertSame(TierLevel::Silver, $quietStatus->current_tier);
        $this->assertTrue($quietStatus->next_evaluation_at->isFuture());
        // The active member was not due, so nothing beyond enrolment was recorded.
        $this->assertSame(1, TierEvent::query()->where('customer_id', $this->member->id)->count());
    }

    private function buy(int|string $amount, string $at)
    {
        return $this->engine->processTransaction($this->member->id, $amount, CarbonImmutable::parse($at));
    }

    private function memberStatus(): MemberTierStatus
    {
        return MemberTierStatus::query()->where('customer_id', $this->member->id)->sole();
    }
}
