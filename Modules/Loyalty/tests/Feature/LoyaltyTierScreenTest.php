<?php

namespace Modules\Loyalty\Tests\Feature;

use App\Enums\SystemRole;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Customer\Models\Customer;
use Modules\Loyalty\Models\LoyaltyTier;
use Modules\Loyalty\Services\TierQualificationEngine;
use Tests\TestCase;

class LoyaltyTierScreenTest extends TestCase
{
    use RefreshDatabase;

    private const COLUMNS = ['tier', 'threshold', 'guarantee', 'members', 'color', 'actions'];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->admin = $this->userWithRole(SystemRole::Administrator);
    }

    public function test_table_lists_tiers_in_order_with_their_colour_and_member_count(): void
    {
        $member = Customer::factory()->create();
        app(TierQualificationEngine::class)->processTransaction($member->id, 600000, CarbonImmutable::now());

        $this->actingAs($this->admin)->get(route('admin.loyalty.tiers.index'))->assertOk()->assertSee('How tiers work');
        $rows = $this->dataTable(route('admin.loyalty.tiers.data'), self::COLUMNS)->assertOk()->json('data');

        $this->assertCount(4, $rows);
        $this->assertSame(self::COLUMNS, array_keys($rows[0]));
        $this->assertStringContainsString('Silver', $rows[0]['tier']);
        $this->assertStringContainsString('Diamond', $rows[3]['tier']);
        $this->assertStringContainsString('background-color: #d4a017', $rows[1]['tier']);
        $this->assertSame('1', $rows[1]['members']); // the member reached Gold
        $this->assertStringContainsString(route('admin.loyalty.tiers.edit', 'gold'), $rows[1]['actions']);
    }

    public function test_admin_edits_a_tier(): void
    {
        $this->actingAs($this->admin)->get(route('admin.loyalty.tiers.edit', 'gold'))->assertOk();

        $this->put(route('admin.loyalty.tiers.update', 'gold'), [
            'spending_threshold' => '400000.50',
            'guarantee_months' => 2,
            'color' => '#AA8800',
        ])->assertRedirect(route('admin.loyalty.tiers.index'))->assertSessionHas('success');

        $gold = LoyaltyTier::query()->where('tier_level', 'gold')->sole();
        $this->assertSame('400000.50', $gold->spending_threshold);
        $this->assertSame(2, $gold->guarantee_months);
        $this->assertSame('#aa8800', $gold->color);
    }

    public function test_base_tier_only_changes_colour(): void
    {
        $this->actingAs($this->admin)->put(route('admin.loyalty.tiers.update', 'silver'), [
            'spending_threshold' => '999',
            'guarantee_months' => 6,
            'color' => '#123456',
        ])->assertRedirect();

        $silver = LoyaltyTier::query()->where('tier_level', 'silver')->sole();
        $this->assertSame('0.00', $silver->spending_threshold);
        $this->assertSame(0, $silver->guarantee_months);
        $this->assertSame('#123456', $silver->color);
    }

    public function test_threshold_must_stay_between_neighbouring_tiers(): void
    {
        $this->actingAs($this->admin);
        $update = fn (string $tier, string $threshold) => $this->put(route('admin.loyalty.tiers.update', $tier), [
            'spending_threshold' => $threshold, 'guarantee_months' => 3, 'color' => '#336699',
        ]);

        $update('platinum', '500000')->assertSessionHasErrors('spending_threshold');  // not above Gold
        $update('platinum', '3000000')->assertSessionHasErrors('spending_threshold'); // not below Diamond
        $update('diamond', '1000000')->assertSessionHasErrors('spending_threshold');  // below Platinum

        $this->assertSame('1500000.00', LoyaltyTier::query()->where('tier_level', 'platinum')->value('spending_threshold'));
    }

    public function test_colour_must_be_a_hex_code(): void
    {
        $this->actingAs($this->admin)->put(route('admin.loyalty.tiers.update', 'gold'), [
            'spending_threshold' => '500000', 'guarantee_months' => 3, 'color' => 'red;background:url(x)',
        ])->assertSessionHasErrors('color');
    }

    public function test_a_role_without_permission_gets_403(): void
    {
        $cashier = $this->userWithRole(SystemRole::Cashier);
        $this->actingAs($cashier);

        $this->get(route('admin.loyalty.tiers.index'))->assertForbidden();
        $this->dataTable(route('admin.loyalty.tiers.data'), self::COLUMNS)->assertForbidden();
        $this->get(route('admin.loyalty.tiers.edit', 'gold'))->assertForbidden();
        $this->put(route('admin.loyalty.tiers.update', 'gold'), ['color' => '#000000'])->assertForbidden();
    }
}
