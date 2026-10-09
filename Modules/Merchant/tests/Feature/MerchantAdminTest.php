<?php

namespace Modules\Merchant\Tests\Feature;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Customer\Models\Customer;
use Modules\Loyalty\Enums\PointTransactionType;
use Modules\Loyalty\Services\PointWallet;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantBranch;
use Modules\Merchant\Models\MerchantReward;
use Modules\Merchant\Services\MerchantService;
use Modules\Merchant\Services\RedemptionService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MerchantAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->admin = $this->userWithRole(SystemRole::Administrator);
    }

    public function test_admin_creates_a_merchant_with_branches(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('admin.merchants.store'), [
            'name' => 'KFC', 'phone' => '091234', 'settlement_rate' => '12.5', 'is_active' => '1',
        ])->assertRedirect()->assertSessionHas('success');
        $kfc = Merchant::query()->where('name', 'KFC')->sole();
        $this->assertSame('12.5000', $kfc->settlement_rate);

        $this->post(route('admin.merchants.branches.store', $kfc), ['name' => 'Junction Square', 'is_active' => '1'])
            ->assertRedirect(route('admin.merchants.show', $kfc));
        $branch = $kfc->branches()->sole();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $branch->code);
        $this->assertStringContainsString($branch->code, session('success'), 'the new code is shown to the admin');
        $this->assertNotSame($branch->code, $branch->getRawOriginal('code'), 'stored encrypted');

        foreach ([route('admin.merchants.index'), route('admin.merchants.create'), route('admin.merchants.show', $kfc),
            route('admin.merchants.edit', $kfc), route('admin.merchants.branches.create', $kfc), route('admin.merchants.branches.edit', $branch)] as $page) {
            $this->get($page)->assertOk();
        }

        $merchants = $this->dataTable(route('admin.merchants.data'), ['id', 'merchant', 'branches', 'rate', 'status', 'actions'])->json('data');
        $this->assertSame('1', $merchants[0]['branches']);
        $this->assertArrayNotHasKey('rewards', $merchants[0]);
        $this->get(route('admin.merchants.show', $kfc))->assertOk()->assertDontSee('rewards-table');
    }

    public function test_branch_code_can_be_replaced_and_is_hidden_without_permission(): void
    {
        [$kfc, $branch] = $this->kfcWithBranch();
        $old = $branch->code;

        $this->actingAs($this->admin)->post(route('admin.merchants.branches.code', $branch))->assertRedirect();
        $this->assertNotSame($old, $branch->fresh()->code);

        $viewer = User::factory()->create();
        $viewer->assignRole(Role::create(['name' => 'Merchant viewer'])->givePermissionTo('view-merchant'));
        $rows = $this->actingAs($viewer)->dataTable(route('admin.merchants.branches.data', $kfc), ['branch', 'code', 'status', 'actions'])->json('data');
        $this->assertStringNotContainsString($branch->fresh()->code, json_encode($rows));
        $this->actingAs($viewer)->post(route('admin.merchants.branches.code', $branch))->assertForbidden();
    }

    public function test_merchant_with_redemptions_can_only_be_deactivated(): void
    {
        [$kfc, $branch] = $this->kfcWithBranch();
        $reward = app(MerchantService::class)->saveReward($kfc, new MerchantReward, ['name' => 'Fries', 'points_cost' => 100, 'is_active' => true]);
        $member = Customer::factory()->create();
        app(PointWallet::class)->credit($member->id, 100, PointTransactionType::Earn);
        app(RedemptionService::class)->redeem($member, $branch->id, $reward->id, $branch->code);

        $this->actingAs($this->admin)->delete(route('admin.merchants.destroy', $kfc))->assertSessionHas('error');
        $this->actingAs($this->admin)->delete(route('admin.merchants.branches.destroy', $branch))->assertSessionHas('error');
        $this->assertModelExists($kfc);

        $empty = Merchant::query()->create(['name' => 'Empty', 'settlement_rate' => 0]);
        $this->actingAs($this->admin)->delete(route('admin.merchants.destroy', $empty))->assertRedirect(route('admin.merchants.index'));
        $this->assertModelMissing($empty);
    }

    public function test_a_role_without_permission_gets_403(): void
    {
        [$kfc] = $this->kfcWithBranch();
        $cashier = $this->userWithRole(SystemRole::Cashier);
        $this->actingAs($cashier);

        $this->get(route('admin.merchants.index'))->assertForbidden();
        $this->get(route('admin.merchants.show', $kfc))->assertForbidden();
        $this->post(route('admin.merchants.store'), ['name' => 'X', 'settlement_rate' => 1])->assertForbidden();
        $this->dataTable(route('admin.merchants.data'), ['id'])->assertForbidden();
    }

    /** @return array{0: Merchant, 1: MerchantBranch} */
    private function kfcWithBranch(): array
    {
        $kfc = Merchant::query()->create(['name' => 'KFC', 'settlement_rate' => 10]);

        return [$kfc, app(MerchantService::class)->createBranch($kfc, ['name' => 'Junction Square', 'is_active' => true])];
    }
}
