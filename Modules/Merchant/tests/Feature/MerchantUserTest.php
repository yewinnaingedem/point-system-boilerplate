<?php

namespace Modules\Merchant\Tests\Feature;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\GiftCard\Database\Seeders\GiftCardDatabaseSeeder;
use Modules\GiftCard\Models\GiftCard;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantBranch;
use Modules\Merchant\Services\MerchantService;
use Tests\TestCase;

/**
 * A merchant's own staff (users.merchant_id + the Merchant role) only reach their own shop.
 */
class MerchantUserTest extends TestCase
{
    use RefreshDatabase;

    private Merchant $kfc;

    private Merchant $pizza;

    private MerchantBranch $pizzaBranch;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->kfc = Merchant::query()->create(['name' => 'KFC', 'settlement_rate' => 10, 'notes' => 'Internal: pays late']);
        $this->pizza = Merchant::query()->create(['name' => 'Pizza', 'settlement_rate' => 5]);
        $this->pizzaBranch = app(MerchantService::class)->createBranch($this->pizza, ['name' => 'Hledan', 'is_active' => true]);
        $this->manager = $this->userWithRole(GiftCardDatabaseSeeder::MERCHANT_ROLE, ['merchant_id' => $this->kfc->id]);
    }

    public function test_a_merchant_user_sees_and_edits_only_their_own_shop(): void
    {
        $this->actingAs($this->manager);

        $this->get(route('admin.merchants.index'))->assertRedirect(route('admin.merchants.show', $this->kfc));
        $this->get(route('admin.merchants.show', $this->kfc))->assertOk()
            ->assertSee('Edit shop details')->assertDontSee('Internal: pays late')->assertDontSee('Payout per point');
        $this->get(route('admin.merchants.show', $this->pizza))->assertForbidden();
        $this->get(route('admin.merchants.edit', $this->pizza))->assertForbidden();
        $this->get(route('admin.merchants.branches.edit', $this->pizzaBranch))->assertForbidden();
        $this->delete(route('admin.merchants.branches.destroy', $this->pizzaBranch))->assertForbidden();
        $this->get(route('admin.merchants.create'))->assertForbidden();
        $this->delete(route('admin.merchants.destroy', $this->kfc))->assertForbidden();

        // Contact details yes; payout rate, status and our notes are ignored.
        $this->put(route('admin.merchants.update', $this->kfc), ['name' => 'KFC Myanmar', 'phone' => '0911', 'settlement_rate' => 999, 'is_active' => 0, 'notes' => 'hi'])
            ->assertRedirect();
        $kfc = $this->kfc->fresh();
        $this->assertSame(['KFC Myanmar', '0911', '10.0000', true, 'Internal: pays late'], [$kfc->name, $kfc->phone, $kfc->settlement_rate, $kfc->is_active, $kfc->notes]);

        // Their own branches: add, deactivate.
        $this->post(route('admin.merchants.branches.store', $this->kfc), ['name' => 'Junction City', 'is_active' => '1'])->assertRedirect();
        $branch = $this->kfc->branches()->sole();
        $this->put(route('admin.merchants.branches.update', $branch), ['name' => 'Junction City', 'is_active' => '0'])->assertRedirect();
        $this->assertFalse($branch->fresh()->is_active);
        $this->post(route('admin.merchants.branches.code', $branch))->assertRedirect();

        // Their gift cards are listed, read-only.
        GiftCard::query()->create(['merchant_id' => $this->kfc->id, 'name' => 'KFC voucher', 'points_cost' => 100, 'face_value' => 5000, 'is_active' => true]);
        $this->get(route('admin.merchants.show', $this->kfc))->assertOk()->assertSee('KFC voucher')->assertDontSee('New gift card')
            ->assertDontSee(route('admin.gift-cards.index'));

        // Nothing else of ours.
        $this->get('/admin/customers')->assertForbidden();
        $this->get('/admin/access/users')->assertForbidden();
        $this->get('/admin/gift-cards/exchanges')->assertForbidden();
        $this->get('/admin/settings/general')->assertForbidden();
    }

    public function test_only_non_administrators_can_belong_to_a_merchant(): void
    {
        $admin = User::query()->where('email', config('access.admin.email'))->sole();
        $this->actingAs($admin)->get(route('admin.access.users.create'))->assertOk()->assertSee('None (our staff)')->assertSee('KFC');

        $form = ['name' => 'KFC cashier', 'email' => 'cashier@kfc.test', 'password' => 'Secret-pass-123', 'password_confirmation' => 'Secret-pass-123', 'is_active' => '1'];
        $this->post(route('admin.access.users.store'), $form + ['roles' => [SystemRole::Administrator->value], 'merchant_id' => $this->kfc->id])
            ->assertSessionHasErrors('merchant_id');
        $this->post(route('admin.access.users.store'), $form + ['roles' => [GiftCardDatabaseSeeder::MERCHANT_ROLE], 'merchant_id' => $this->kfc->id])
            ->assertRedirect();
        $this->assertSame($this->kfc->id, User::query()->where('email', 'cashier@kfc.test')->sole()->merchant_id);
    }
}
