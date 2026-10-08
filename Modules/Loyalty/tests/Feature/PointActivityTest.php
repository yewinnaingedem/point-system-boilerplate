<?php

namespace Modules\Loyalty\Tests\Feature;

use App\Enums\SystemRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Customer\Models\Customer;
use Modules\Loyalty\Enums\PointTransactionType;
use Modules\Loyalty\Services\PointWallet;
use Tests\TestCase;

class PointActivityTest extends TestCase
{
    use RefreshDatabase;

    private const EARNERS = ['customer', 'earned_points', 'award_count', 'last_award', 'actions'];

    private const MOVEMENTS = ['date', 'customer', 'type', 'points', 'balance_after', 'reference', 'note'];

    private Customer $aye;

    private Customer $bo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $wallet = app(PointWallet::class);
        $this->aye = Customer::factory()->create(['name' => 'Aye Aye']);
        $this->bo = Customer::factory()->create(['name' => 'Bo Bo']);

        $this->travelTo(CarbonImmutable::parse('2027-03-09 10:00'));          // yesterday
        $wallet->credit($this->bo->id, 500, PointTransactionType::Earn, reference: 'ORDER-1');

        $this->travelTo(CarbonImmutable::parse('2027-03-10 09:00'));          // today
        $wallet->credit($this->aye->id, 100, PointTransactionType::Earn, reference: 'ORDER-2');
        $wallet->credit($this->aye->id, 150, PointTransactionType::Earn, reference: 'ORDER-3');
        $wallet->credit($this->bo->id, 40, PointTransactionType::Earn, reference: 'ORDER-4');
        $wallet->debit($this->bo->id, 200, PointTransactionType::Redeem);

        $this->actingAs($this->userWithRole(SystemRole::Administrator));
    }

    public function test_page_shows_todays_figures(): void
    {
        $this->get(route('admin.loyalty.points-activity.index'))->assertOk()
            ->assertSeeInOrder(['Earned today', '290', 'Customers who earned today', '2', 'Redeemed today', '200']);
    }

    public function test_customers_who_earned_today_one_row_each_biggest_first(): void
    {
        $sort = ['columns' => [['data' => 'customer'], ['data' => 'earned_points', 'name' => 'earned', 'orderable' => 'true']],
            'order' => [['column' => 1, 'dir' => 'desc']]];
        $rows = $this->dataTable(route('admin.loyalty.points-activity.earners'), [], ['period' => 'today'] + $sort)->assertOk()->json('data');

        $this->assertCount(2, $rows);
        $this->assertStringContainsString('Aye Aye', $rows[0]['customer']);
        $this->assertSame(['+250', '2'], [$rows[0]['earned_points'], $rows[0]['award_count']]);
        $this->assertSame('+40', $rows[1]['earned_points']);

        $week = $this->dataTable(route('admin.loyalty.points-activity.earners'), self::EARNERS, ['period' => 'week'])->json('data');
        $bo = collect($week)->first(fn ($row) => str_contains($row['customer'], 'Bo Bo'));
        $this->assertSame('+540', $bo['earned_points']);
    }

    public function test_movements_filter_by_period_type_dates_and_search(): void
    {
        $url = route('admin.loyalty.points-activity.transactions');

        $this->dataTable($url, self::MOVEMENTS, ['period' => 'today'])->assertJsonPath('recordsFiltered', 4);
        $this->dataTable($url, self::MOVEMENTS, ['period' => 'today', 'type' => 'earn'])->assertJsonPath('recordsFiltered', 3);
        $this->dataTable($url, self::MOVEMENTS, ['period' => 'yesterday'])->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.reference', 'ORDER-1');
        $this->dataTable($url, self::MOVEMENTS, ['period' => 'custom', 'from' => '2027-03-09', 'to' => '2027-03-10'])
            ->assertJsonPath('recordsFiltered', 5);
        $this->dataTable($url, self::MOVEMENTS, ['period' => 'all'], 'Aye')->assertJsonPath('recordsFiltered', 2);
        $this->dataTable($url, self::MOVEMENTS, ['period' => 'all'], 'ORDER-4')->assertJsonPath('recordsFiltered', 1);
        $this->dataTable($url, self::MOVEMENTS, ['period' => 'custom', 'from' => '2027-03-10', 'to' => '2027-03-01'])->assertUnprocessable();
    }

    public function test_a_role_without_permission_gets_403(): void
    {
        $this->actingAs($this->userWithRole(SystemRole::Cashier));

        $this->get(route('admin.loyalty.points-activity.index'))->assertForbidden();
        $this->dataTable(route('admin.loyalty.points-activity.transactions'), self::MOVEMENTS)->assertForbidden();
    }
}
