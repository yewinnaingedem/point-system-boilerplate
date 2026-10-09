<?php

namespace Modules\Loyalty\Tests\Feature;

use App\Enums\SystemRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AppSetting\Services\SettingService;
use Modules\AppSetting\Support\SettingCatalog;
use Modules\Customer\Models\Customer;
use Modules\Loyalty\Enums\PointTransactionType;
use Modules\Loyalty\Services\PointWallet;
use Tests\TestCase;

/**
 * Points → Earned Points: all customers' credits of points, with filters.
 */
class EarnedPointsTest extends TestCase
{
    use RefreshDatabase;

    private const COLUMNS = ['earned_at', 'customer', 'points', 'remaining', 'status', 'expires_at', 'source', 'reference', 'note'];

    private Customer $aung;

    private Customer $mya;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        app(SettingService::class)->saveGroup(app(SettingCatalog::class)->group('loyalty'), [
            'loyalty_cycle_months' => '1', 'point_expiry_months' => '2', 'point_expiry_cutoff_day' => '15',
        ]);
        $wallet = app(PointWallet::class);
        $this->aung = Customer::factory()->create(['name' => 'Aung Aung', 'external_id' => 'shop-7']);
        $this->mya = Customer::factory()->create(['name' => 'Mya Mya']);

        // Nov 10: Aung earns 100 (expires end of Dec) → fully expired by Jan 20.
        $this->travelTo(CarbonImmutable::parse('2026-11-10 10:00'));
        $wallet->credit($this->aung->id, 100, PointTransactionType::Earn, null, null, null, 'ORDER-OLD');
        // Jan 5: Aung earns 300 (ORDER-1); Jan 6: Mya gets a 50 manual adjustment.
        $this->travelTo(CarbonImmutable::parse('2027-01-05 10:00'));
        $wallet->credit($this->aung->id, 300, PointTransactionType::Earn, null, 'Purchase', null, 'ORDER-1');
        $this->travelTo(CarbonImmutable::parse('2027-01-06 10:00'));
        $wallet->credit($this->mya->id, 50, PointTransactionType::Adjust, null, 'Birthday');
        // Jan 20: Aung spends 120 → the old lot already expired (end of Dec), so ORDER-1 is partly used (180 left).
        $this->travelTo(CarbonImmutable::parse('2027-01-20 10:00'));
        $wallet->debit($this->aung->id, 120, PointTransactionType::Redeem);

        $this->actingAs($this->userWithRole(SystemRole::Administrator));
    }

    public function test_the_page_lists_every_customers_points_with_what_is_left(): void
    {
        $this->get(route('admin.loyalty.points-earned.index'))->assertOk()
            ->assertSee('Earned this month')->assertSee('350');  // 300 + 50 in January

        $rows = $this->rows(['period' => 'all']);
        $this->assertCount(3, $rows);
        $order1 = collect($rows)->first(fn ($r) => $r['reference'] === 'ORDER-1');
        $this->assertSame('180', $order1['remaining']);
        $this->assertStringContainsString('Partly used', $order1['status']);
        // Earned Jan 5 (on/before the 15th, 2 months): January + February count → gone after Feb 28.
        $this->assertStringContainsString(CarbonImmutable::parse('2027-02-28')->format(setting('date_format')), $order1['expires_at']);
        $this->assertStringContainsString('Expired', collect($rows)->firstWhere('reference', 'ORDER-OLD')['status']);
    }

    public function test_filters_period_source_status_customer_and_expiry(): void
    {
        $this->assertCount(2, $this->rows(['period' => 'month']));                                // January only
        $this->assertCount(1, $this->rows(['period' => 'all', 'source' => 'adjust']));            // Mya's adjustment
        $this->assertCount(1, $this->rows(['period' => 'all', 'status' => 'expired']));
        $this->assertCount(1, $this->rows(['period' => 'all', 'status' => 'unused']));            // Mya's 50
        $this->assertCount(2, $this->rows(['period' => 'all'], 'Aung'));                          // by name
        $this->assertCount(2, $this->rows(['period' => 'all'], 'shop-7'));                        // by customer id
        $this->assertCount(1, $this->rows(['period' => 'all'], 'ORDER-1'));                       // by order reference
        $this->assertCount(0, $this->rows(['period' => 'all', 'expiring' => '30']));              // Jan 20: end of Feb is 40 days away

        $this->travelTo(CarbonImmutable::parse('2027-02-10 10:00'));
        $this->assertCount(2, $this->rows(['period' => 'all', 'expiring' => '30']));              // ORDER-1 and Mya's 50, both end of Feb
    }

    public function test_sorting_by_points_works(): void
    {
        $sortable = array_map(fn ($c) => ['data' => $c, 'name' => $c === 'points' ? 'loyalty_point_lots.points' : '', 'orderable' => $c === 'points' ? 'true' : 'false'], self::COLUMNS);
        $rows = $this->dataTable(route('admin.loyalty.points-earned.data'), [], ['period' => 'all', 'columns' => $sortable, 'order' => [['column' => 2, 'dir' => 'desc']]])->json('data');

        $this->assertSame(['300', '100', '50'], array_map(fn ($r) => strip_tags(str_replace('+', '', $r['points'])), $rows));
    }

    public function test_role_without_permission_gets_403(): void
    {
        $this->actingAs($this->userWithRole(SystemRole::Cashier));

        $this->get(route('admin.loyalty.points-earned.index'))->assertForbidden();
        $this->dataTable(route('admin.loyalty.points-earned.data'), self::COLUMNS)->assertForbidden();
    }

    /** @return list<array<string, string>> */
    private function rows(array $params, ?string $search = null): array
    {
        return $this->dataTable(route('admin.loyalty.points-earned.data'), self::COLUMNS, $params + ['length' => 100], $search)->assertOk()->json('data');
    }
}
