<?php

namespace Modules\GiftCard\Tests\Feature;

use App\Enums\SystemRole;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Customer\Models\Customer;
use Modules\GiftCard\Database\Seeders\GiftCardDatabaseSeeder;
use Modules\GiftCard\Enums\ClaimStatus;
use Modules\GiftCard\Models\GiftCard;
use Modules\GiftCard\Models\GiftCardExchange;
use Modules\GiftCard\Models\MerchantClaim;
use Modules\GiftCard\Services\GiftCardExchangeService;
use Modules\GiftCard\Services\MerchantClaimService;
use Modules\Loyalty\Enums\PointTransactionType;
use Modules\Loyalty\Services\PointWallet;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantBranch;
use Modules\Merchant\Services\MerchantService;
use Tests\TestCase;

/**
 * Merchants → Claims: a merchant (or our staff for it) claims its used gift cards; we pay or reject.
 */
class MerchantClaimTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private GiftCard $card;

    private Merchant $kfc;

    private Merchant $pizza;

    private MerchantBranch $kfcBranch;

    private MerchantBranch $pizzaBranch;

    private User $admin;

    private User $kfcManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->customer = Customer::factory()->create(['external_id' => 'shop-7']);
        app(PointWallet::class)->credit($this->customer->id, 10000, PointTransactionType::Earn);
        $this->card = GiftCard::query()->create(['name' => 'Voucher 50,000 Ks', 'points_cost' => 100, 'face_value' => 50000, 'is_active' => true]);
        $this->kfc = Merchant::query()->create(['name' => 'KFC', 'settlement_rate' => 10]);
        $this->pizza = Merchant::query()->create(['name' => 'Pizza', 'settlement_rate' => 10]);
        $this->kfcBranch = app(MerchantService::class)->createBranch($this->kfc, ['name' => 'Junction City', 'is_active' => true]);
        $this->pizzaBranch = app(MerchantService::class)->createBranch($this->pizza, ['name' => 'Hledan', 'is_active' => true]);
        $this->admin = $this->userWithRole(SystemRole::Administrator);
        $this->kfcManager = $this->userWithRole(GiftCardDatabaseSeeder::MERCHANT_ROLE, ['merchant_id' => $this->kfc->id]);
    }

    public function test_merchant_claims_its_own_used_cards_and_only_our_staff_pay(): void
    {
        $this->travelTo(now()->setDate(2027, 1, 10));
        $january = $this->usedAt($this->kfcBranch);
        $this->travelTo(now()->setDate(2027, 2, 10));
        $february = $this->usedAt($this->kfcBranch);
        $pizzaCard = $this->usedAt($this->pizzaBranch);
        app(GiftCardExchangeService::class)->request($this->customer, $this->card); // issued, not used: nobody's claim

        $this->actingAs($this->kfcManager);
        $this->get(route('admin.claims.index'))->assertOk()->assertSee('100,000.00')->assertDontSee('Pizza');
        $this->get(route('admin.claims.create', ['up_to' => '2027-01-31']))->assertOk()
            ->assertSee($january->code)->assertDontSee($february->code)->assertDontSee($pizzaCard->code);

        // Claim all of KFC's cards; merchant_id can't be chosen by a merchant's staff.
        $this->post(route('admin.claims.store'), ['up_to' => '2027-02-10', 'note' => 'January and February'])->assertRedirect();
        $claim = MerchantClaim::query()->sole();
        $this->assertSame([$this->kfc->id, ClaimStatus::Submitted, 2, '100000.00'], [$claim->merchant_id, $claim->status, $claim->cards_count, $claim->amount]);
        $this->assertSame($claim->id, $january->fresh()->claim_id);
        $this->assertNull($pizzaCard->fresh()->claim_id);
        $this->post(route('admin.claims.store'), ['up_to' => '2027-02-10', 'merchant_id' => $this->pizza->id])->assertSessionHasErrors('merchant_id');

        // Edit: only January now; February is released.
        $this->put(route('admin.claims.update', $claim), ['up_to' => '2027-01-31'])->assertRedirect(route('admin.claims.show', $claim));
        $this->assertSame([1, '50000.00'], [$claim->fresh()->cards_count, $claim->fresh()->amount]);
        $this->assertNull($february->fresh()->claim_id);

        // A merchant can't pay its own claim.
        $this->post(route('admin.claims.pay', $claim), ['paid_on' => '2027-02-10'])->assertForbidden();
        $this->get(route('admin.claims.show', $claim))->assertOk()->assertDontSee('Mark 50,000.00 Ks as paid');

        $this->actingAs($this->admin);
        $this->get(route('admin.claims.show', $claim))->assertOk()->assertSee($january->code)->assertSee('as paid');
        $this->post(route('admin.claims.pay', $claim), ['paid_on' => '2027-02-10', 'payment_reference' => 'KBZ-1'])->assertRedirect();
        $this->assertSame([ClaimStatus::Paid, 'KBZ-1', $this->admin->id], [$claim->fresh()->status, $claim->fresh()->payment_reference, $claim->fresh()->decided_by]);

        // Paid: no more changes.
        $this->post(route('admin.claims.reject', $claim), ['reject_reason' => 'x'])->assertSessionHas('error');
        $this->actingAs($this->kfcManager)->delete(route('admin.claims.destroy', $claim))->assertSessionHas('error');
        $this->get(route('admin.claims.edit', $claim))->assertRedirect(route('admin.claims.show', $claim));
        $this->get(route('admin.claims.export', $claim))->assertOk();
    }

    public function test_our_staff_claim_for_a_shop_and_reject_or_delete_releases_the_cards(): void
    {
        $card = $this->usedAt($this->pizzaBranch);

        $this->actingAs($this->admin);
        $this->get(route('admin.claims.create', ['merchant_id' => $this->pizza->id]))->assertOk()->assertSee($card->code);
        $this->post(route('admin.claims.store'), ['merchant_id' => $this->pizza->id, 'up_to' => today()->toDateString()])->assertRedirect();
        $claim = MerchantClaim::query()->sole();

        // Nothing left to claim for Pizza.
        $this->post(route('admin.claims.store'), ['merchant_id' => $this->pizza->id, 'up_to' => today()->toDateString()])->assertSessionHas('error');

        $this->post(route('admin.claims.reject', $claim), ['reject_reason' => 'Wrong branch'])->assertRedirect();
        $this->assertSame(ClaimStatus::Rejected, $claim->fresh()->status);
        $this->assertNull($card->fresh()->claim_id);

        $this->post(route('admin.claims.store'), ['merchant_id' => $this->pizza->id, 'up_to' => today()->toDateString()])->assertRedirect();
        $again = MerchantClaim::query()->latest('id')->first();
        $this->delete(route('admin.claims.destroy', $again))->assertRedirect(route('admin.claims.index'));
        $this->assertModelMissing($again);
        $this->assertNull($card->fresh()->claim_id);
    }

    public function test_a_merchant_never_sees_another_merchants_claim(): void
    {
        $this->usedAt($this->pizzaBranch);
        $pizzaClaim = app(MerchantClaimService::class)->create($this->pizza, CarbonImmutable::today(), null, $this->admin);

        $this->actingAs($this->kfcManager);
        $this->get(route('admin.claims.index'))->assertOk()->assertDontSee($pizzaClaim->reference);
        $this->get(route('admin.claims.index', ['merchant_id' => $this->pizza->id]))->assertOk()->assertDontSee($pizzaClaim->reference);
        foreach (['show', 'edit', 'export'] as $page) {
            $this->get(route("admin.claims.{$page}", $pizzaClaim))->assertForbidden();
        }
        $this->delete(route('admin.claims.destroy', $pizzaClaim))->assertForbidden();
        $this->assertModelExists($pizzaClaim);
    }

    public function test_role_without_permission_gets_403(): void
    {
        $this->actingAs($this->userWithRole(SystemRole::Cashier));

        $this->get(route('admin.claims.index'))->assertForbidden();
        $this->get(route('admin.claims.create'))->assertForbidden();
        $this->post(route('admin.claims.store'), ['merchant_id' => $this->kfc->id, 'up_to' => today()->toDateString()])->assertForbidden();
    }

    private function usedAt(MerchantBranch $branch): GiftCardExchange
    {
        $service = app(GiftCardExchangeService::class);

        return $service->useAtBranch($this->customer, $service->request($this->customer, $this->card), $branch->id, $branch->code);
    }
}
