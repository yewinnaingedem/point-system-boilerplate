<?php

namespace Modules\GiftCard\Tests\Feature;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Customer\Models\Customer;
use Modules\GiftCard\Enums\ExchangeStatus;
use Modules\GiftCard\Models\GiftCard;
use Modules\GiftCard\Models\GiftCardExchange;
use Modules\GiftCard\Notifications\GiftCardVerificationCode;
use Modules\GiftCard\Services\GiftCardExchangeService;
use Modules\Loyalty\Enums\PointTransactionType;
use Modules\Loyalty\Services\PointWallet;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Services\MerchantService;
use Tests\TestCase;

/**
 * Gift Cards → Exchanges as CRUD pages: list, view (with cancel), exchange for a customer.
 */
class ExchangeAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->admin = $this->userWithRole(SystemRole::Administrator);
        $this->customer = Customer::factory()->create(['external_id' => 'shop-7', 'email' => 'aung@example.com', 'phone' => '0991234567']);
        app(PointWallet::class)->credit($this->customer->id, 5000, PointTransactionType::Earn);
        $this->actingAs($this->admin);
    }

    public function test_staff_exchange_a_card_for_a_customer_by_phone_and_cancel_it_on_its_page(): void
    {
        $card = GiftCard::query()->create(['name' => 'Voucher', 'points_cost' => 1000, 'face_value' => 5000, 'is_active' => true, 'stock' => 3]);

        $this->get(route('admin.gift-card-exchanges.index'))->assertOk()->assertSee('Exchange for a customer');
        $this->get(route('admin.gift-card-exchanges.create'))->assertOk()->assertSee('Voucher');
        $this->post(route('admin.gift-card-exchanges.store'), ['customer_id' => 999999, 'gift_card_id' => $card->id])->assertSessionHasErrors('customer_id');

        $this->post(route('admin.gift-card-exchanges.store'), ['customer_id' => $this->customer->id, 'gift_card_id' => $card->id])->assertRedirect();
        $exchange = GiftCardExchange::query()->sole();
        $this->assertSame(ExchangeStatus::Issued, $exchange->status);
        $this->assertSame(4000, app(PointWallet::class)->balance($this->customer->id));

        $this->get(route('admin.gift-card-exchanges.show', $exchange))->assertOk()
            ->assertSee($exchange->code)->assertSee('Cancel and refund')->assertSee('aung@example.com');

        $this->post(route('admin.gift-card-exchanges.cancel', $exchange), ['reason' => 'Wrong card'])
            ->assertRedirect(route('admin.gift-card-exchanges.show', $exchange));
        $this->assertSame(ExchangeStatus::Cancelled, $exchange->fresh()->status);
        $this->get(route('admin.gift-card-exchanges.show', $exchange))->assertOk()->assertSee('Wrong card')->assertDontSee('Cancel and refund');
    }

    public function test_a_two_step_card_waits_for_the_emailed_code_on_its_page(): void
    {
        Notification::fake();
        $card = GiftCard::query()->create(['name' => 'Two-step', 'points_cost' => 100, 'face_value' => 5000, 'is_active' => true, 'requires_verification' => true]);

        $this->post(route('admin.gift-card-exchanges.store'), ['customer_id' => $this->customer->id, 'gift_card_id' => $card->id])->assertRedirect();
        $exchange = GiftCardExchange::query()->sole();
        $this->assertSame(ExchangeStatus::Pending, $exchange->status);
        $this->get(route('admin.gift-card-exchanges.show', $exchange))->assertOk()->assertSee('Emailed code')->assertSee('Not issued yet');

        $code = null;
        Notification::assertSentTo($this->customer, GiftCardVerificationCode::class, function ($n) use (&$code) {
            $code = $n->code;

            return true;
        });
        $this->post(route('admin.gift-card-exchanges.verify', $exchange), ['code' => $code === '000000' ? '111111' : '000000'])->assertSessionHas('error');
        $this->post(route('admin.gift-card-exchanges.verify', $exchange), ['code' => $code])->assertRedirect(route('admin.gift-card-exchanges.show', $exchange));
        $this->assertSame(ExchangeStatus::Issued, $exchange->fresh()->status);
    }

    public function test_a_used_card_shows_the_shop_and_its_claim_and_cannot_be_cancelled(): void
    {
        $card = GiftCard::query()->create(['name' => 'Voucher', 'points_cost' => 100, 'face_value' => 5000, 'is_active' => true]);
        $kfc = Merchant::query()->create(['name' => 'KFC', 'settlement_rate' => 10]);
        $branch = app(MerchantService::class)->createBranch($kfc, ['name' => 'Junction City', 'is_active' => true]);
        $service = app(GiftCardExchangeService::class);
        $exchange = $service->useAtBranch($this->customer, $service->request($this->customer, $card), $branch->id, $branch->code);

        $this->get(route('admin.gift-card-exchanges.show', $exchange))->assertOk()
            ->assertSee('KFC Junction City')->assertSee('not claimed yet')->assertDontSee('Cancel and refund');
    }

    public function test_role_without_permission_gets_403(): void
    {
        $card = GiftCard::query()->create(['name' => 'Voucher', 'points_cost' => 100, 'face_value' => 5000, 'is_active' => true]);
        $exchange = app(GiftCardExchangeService::class)->request($this->customer, $card);
        $this->actingAs($this->userWithRole(SystemRole::Cashier));

        $this->get(route('admin.gift-card-exchanges.show', $exchange))->assertForbidden();
        $this->get(route('admin.gift-card-exchanges.create'))->assertForbidden();
        $this->post(route('admin.gift-card-exchanges.store'), ['customer_id' => $this->customer->id, 'gift_card_id' => $card->id])->assertForbidden();
    }
}
