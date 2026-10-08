<?php

namespace Modules\Loyalty\Tests\Feature;

use App\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Customer\Models\Customer;
use Modules\Loyalty\Enums\PointTransactionType;
use Modules\Loyalty\Exceptions\InsufficientPoints;
use Modules\Loyalty\Models\PointTransaction;
use Modules\Loyalty\Services\PointWallet;
use Tests\TestCase;

class MemberPointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_keeps_balance_and_history_in_step(): void
    {
        $member = Customer::factory()->create();
        $wallet = app(PointWallet::class);

        $wallet->credit($member->id, 1000, PointTransactionType::Earn);
        $wallet->debit($member->id, 300, PointTransactionType::Redeem);

        $this->assertSame(700, $wallet->balance($member->id));
        $this->assertSame(700, (int) PointTransaction::query()->where('customer_id', $member->id)->sum('points'));
        $this->assertSame(700, PointTransaction::query()->latest('id')->first()->balance_after);

        $this->expectException(InsufficientPoints::class);
        $wallet->debit($member->id, 701, PointTransactionType::Redeem);
    }

    public function test_admin_adjusts_points_and_sees_the_history(): void
    {
        $this->seedAccess();
        $admin = $this->userWithRole(SystemRole::Administrator);
        $member = Customer::factory()->create(['email' => 'aye@pos.test', 'phone' => '0977']);
        $this->actingAs($admin);

        $this->post(route('admin.loyalty.points.store'), ['customer' => '0977', 'points' => 500, 'note' => 'Welcome bonus'])
            ->assertRedirect(route('admin.loyalty.points.show', $member))->assertSessionHas('success');
        $this->post(route('admin.loyalty.points.store'), ['customer' => 'aye@pos.test', 'points' => -900, 'note' => 'Too much'])
            ->assertSessionHasErrors('points');
        $this->post(route('admin.loyalty.points.store'), ['customer' => 'nobody@pos.test', 'points' => 5, 'note' => 'x'])
            ->assertSessionHasErrors('customer');

        $this->assertSame(500, app(PointWallet::class)->balance($member->id));
        foreach ([route('admin.loyalty.points.index'), route('admin.loyalty.points.create'), route('admin.loyalty.points.show', $member)] as $page) {
            $this->get($page)->assertOk();
        }
        $this->dataTable(route('admin.loyalty.points.data'), ['member', 'balance', 'updated', 'actions'])->assertJsonPath('recordsTotal', 1);
        $history = $this->dataTable(route('admin.loyalty.points.history', $member), ['date', 'type', 'points', 'balance_after', 'note', 'by'])->json('data.0');
        $this->assertSame('+500', $history['points']);
        $this->assertSame('Welcome bonus', $history['note']);
        $this->assertSame($admin->name, $history['by']);
    }

    public function test_balances_sort_by_balance(): void
    {
        $this->seedAccess();
        $wallet = app(PointWallet::class);
        foreach ([300, 900, 100] as $points) {
            $wallet->credit(Customer::factory()->create()->id, $points, PointTransactionType::Earn);
        }
        $columns = [['data' => 'member', 'name' => ''], ['data' => 'balance', 'name' => 'balance', 'orderable' => 'true']];

        $rows = $this->actingAs($this->userWithRole(SystemRole::Administrator))
            ->dataTable(route('admin.loyalty.points.data'), [], ['columns' => $columns, 'order' => [['column' => 1, 'dir' => 'desc']]])
            ->json('data');

        $this->assertSame(['900', '300', '100'], array_column($rows, 'balance'));
    }

    public function test_a_role_without_permission_gets_403(): void
    {
        $this->seedAccess();
        $this->actingAs($this->userWithRole(SystemRole::Cashier));

        $this->get(route('admin.loyalty.points.index'))->assertForbidden();
        $this->post(route('admin.loyalty.points.store'), ['customer' => 'x', 'points' => 1, 'note' => 'x'])->assertForbidden();
    }
}
