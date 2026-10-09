<?php

namespace Modules\GiftCard\Tests\Feature;

use App\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Modules\Customer\Models\Customer;
use Modules\GiftCard\Enums\ExchangeStatus;
use Modules\GiftCard\Models\GiftCard;
use Modules\GiftCard\Models\GiftCardExchange;
use Modules\GiftCard\Notifications\GiftCardVerificationCode;
use Modules\GiftCard\Services\GiftCardExchangeService;
use Modules\Loyalty\Enums\PointTransactionType;
use Modules\Loyalty\Services\PointWallet;
use Modules\Loyalty\Services\TierQualificationEngine;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Services\MerchantService;
use Tests\TestCase;

class GiftCardExchangeTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private PointWallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->wallet = app(PointWallet::class);
        $this->customer = $this->customerWithPoints(5000);
        Sanctum::actingAs($this->customer);
    }

    public function test_exchange_issues_a_code_takes_points_and_counts_stock_down(): void
    {
        $card = $this->card(['stock' => 5, 'valid_days' => 30]);

        $response = $this->exchange($card)->assertCreated()->assertJsonPath('data.status', 'issued')->assertJsonPath('data.points', 1000);

        $this->assertMatchesRegularExpression('/^GC-[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}$/', $response->json('data.code'));
        $this->assertNotNull($response->json('data.expires_at'));
        $this->assertSame(4000, $this->wallet->balance($this->customer->id));
        $this->assertSame(4, $card->fresh()->stock);
        $this->getJson('/api/v1/customer/gift-card-exchanges')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_tier_limited_card_needs_that_tier(): void
    {
        $card = $this->card(['min_tier' => 'diamond']);

        $this->exchange($card)->assertUnprocessable()->assertJsonPath('reason', 'tier');
        $this->getJson('/api/v1/customer/gift-cards')->assertJsonPath('data.0.available', false)->assertJsonPath('data.0.reason', 'tier');

        app(TierQualificationEngine::class)->processTransaction($this->customer->id, 3000000, now()); // reaches Diamond
        $this->exchange($card)->assertCreated();
    }

    public function test_out_of_stock_per_customer_limit_and_points(): void
    {
        $limited = $this->card(['per_customer_limit' => 1]);
        $this->exchange($limited)->assertCreated();
        $this->exchange($limited)->assertUnprocessable()->assertJsonPath('reason', 'limit_reached');

        $lastOne = $this->card(['stock' => 1]);
        $this->exchange($lastOne)->assertCreated();
        Sanctum::actingAs($this->customerWithPoints(5000));
        $this->exchange($lastOne)->assertUnprocessable()->assertJsonPath('reason', 'out_of_stock');
        $this->getJson('/api/v1/customer/gift-cards')->assertJsonFragment(['id' => $lastOne->id, 'in_stock' => false]);

        $this->exchange($this->card(['points_cost' => 999999]))->assertUnprocessable()->assertJsonPath('reason', 'not_enough_points');
    }

    public function test_two_step_card_takes_nothing_until_the_emailed_code_is_entered(): void
    {
        Notification::fake();
        $card = $this->card(['requires_verification' => true]);

        $pending = $this->exchange($card)->assertStatus(202)->assertJsonPath('data.status', 'pending')->assertJsonPath('data.code', null);
        $this->assertSame(5000, $this->wallet->balance($this->customer->id), 'nothing taken yet');

        $code = null;
        Notification::assertSentTo($this->customer, GiftCardVerificationCode::class, function ($n) use (&$code) {
            $code = $n->code;

            return true;
        });
        $id = $pending->json('data.id');
        $wrong = $code === '000000' ? '111111' : '000000';

        $this->verify($id, $wrong)->assertUnprocessable()->assertJsonPath('reason', 'wrong_code');
        $this->verify($id, $code)->assertOk()->assertJsonPath('data.status', 'issued');
        $this->assertSame(4000, $this->wallet->balance($this->customer->id));
        $this->verify($id, $code)->assertUnprocessable()->assertJsonPath('reason', 'not_pending');
    }

    public function test_two_step_code_runs_out_after_five_wrong_tries_or_ten_minutes(): void
    {
        Notification::fake();
        $card = $this->card(['requires_verification' => true]);

        $id = $this->exchange($card)->json('data.id');
        foreach (range(1, 4) as $try) {
            $this->verify($id, '999999')->assertJsonPath('reason', 'wrong_code');
        }
        $this->verify($id, '999999')->assertJsonPath('reason', 'too_many_attempts');
        $this->assertSame(ExchangeStatus::Failed, GiftCardExchange::find($id)->status);

        $id = $this->exchange($card)->json('data.id');
        $this->travel(11)->minutes();
        $this->verify($id, '123456')->assertJsonPath('reason', 'code_expired');
        $this->assertSame(5000, $this->wallet->balance($this->customer->id));
    }

    public function test_admin_manages_cards_and_cancels_an_exchange_with_refund(): void
    {
        $admin = $this->userWithRole(SystemRole::Administrator);
        $this->actingAs($admin)->post(route('admin.gift-cards.store'), [
            'name' => 'City Mart 10,000', 'points_cost' => 1500, 'face_value' => '10000', 'min_tier' => 'gold',
            'stock' => 10, 'per_customer_limit' => 2, 'requires_verification' => '1', 'is_active' => '1',
        ])->assertRedirect(route('admin.gift-cards.index'));
        $created = GiftCard::query()->where('name', 'City Mart 10,000')->sole();
        $this->assertSame('gold', $created->min_tier->value);
        $this->assertTrue($created->requires_verification);

        $card = $this->card(['stock' => 3]);
        Sanctum::actingAs($this->customer);
        $code = $this->exchange($card)->json('data.code');
        $exchange = GiftCardExchange::query()->where('code', $code)->sole();

        $this->actingAs($admin);
        foreach ([route('admin.gift-cards.index'), route('admin.gift-cards.create'), route('admin.gift-cards.edit', $card), route('admin.gift-card-exchanges.index')] as $page) {
            $this->get($page)->assertOk();
        }
        $this->dataTable(route('admin.gift-cards.data'), ['id'])->assertJsonPath('recordsTotal', 2);
        $this->dataTable(route('admin.gift-card-exchanges.data'), ['date'], [], $code)->assertJsonPath('recordsFiltered', 1);

        $this->post(route('admin.gift-card-exchanges.cancel', $exchange), ['reason' => 'Wrong card'])->assertSessionHas('success');
        $this->assertSame(ExchangeStatus::Cancelled, $exchange->fresh()->status);
        $this->assertSame(5000, $this->wallet->balance($this->customer->id));
        $this->assertSame(3, $card->fresh()->stock);

        $this->delete(route('admin.gift-cards.destroy', $card))->assertSessionHas('error'); // has exchanges
    }

    public function test_a_role_without_permission_gets_403(): void
    {
        $this->actingAs($this->userWithRole(SystemRole::Cashier));

        $this->get(route('admin.gift-cards.index'))->assertForbidden();
        $this->get(route('admin.gift-card-exchanges.index'))->assertForbidden();
        $this->post(route('admin.gift-cards.store'), ['name' => 'x'])->assertForbidden();
    }

    public function test_staff_cannot_cancel_a_used_gift_card_and_see_where_it_was_used(): void
    {
        $card = $this->card();
        $id = $this->exchange($card)->assertCreated()->json('data.id');
        $kfc = Merchant::query()->create(['name' => 'KFC', 'settlement_rate' => 10]);
        $branch = app(MerchantService::class)->createBranch($kfc, ['name' => 'Junction Square', 'is_active' => true]);
        app(GiftCardExchangeService::class)->useAtBranch($this->customer, GiftCardExchange::query()->find($id), $branch->id, $branch->code);

        $admin = $this->userWithRole(SystemRole::Administrator);
        $this->actingAs($admin)->post(route('admin.gift-card-exchanges.cancel', $id), ['reason' => 'test'])->assertRedirect();
        $this->assertSame(ExchangeStatus::Used, GiftCardExchange::query()->find($id)->status);
        $this->assertSame(4000, $this->wallet->balance($this->customer->id));

        $rows = $this->dataTableText($this->dataTable(route('admin.gift-card-exchanges.data'), ['date', 'customer', 'card', 'points', 'code', 'expires', 'status', 'actions']));
        $this->assertStringContainsString('KFC Junction Square', $rows);
    }

    public function test_a_gift_card_can_belong_to_a_merchant_and_shows_on_its_page(): void
    {
        $kfc = Merchant::query()->create(['name' => 'KFC', 'settlement_rate' => 10]);
        $admin = $this->userWithRole(SystemRole::Administrator);
        $this->actingAs($admin);

        $this->get(route('admin.gift-cards.create', ['merchant_id' => $kfc->id]))->assertOk()->assertSee('Any partner shop');
        $this->post(route('admin.gift-cards.store'), ['merchant_id' => $kfc->id, 'name' => 'KFC voucher', 'points_cost' => 100, 'face_value' => 5000, 'is_active' => '1'])
            ->assertRedirect();
        $card = GiftCard::query()->where('name', 'KFC voucher')->sole();
        $this->assertSame($kfc->id, $card->merchant_id);

        $this->get(route('admin.merchants.show', $kfc))->assertOk()->assertSee('KFC voucher')->assertSee('New gift card');
        $this->dataTable(route('admin.gift-cards.data'), ['id', 'card', 'points', 'value', 'tier', 'stock_left', 'limits', 'status', 'actions'])
            ->assertJsonFragment(['id' => $card->id]);

        // The merchant can't be deleted while it has gift cards.
        $this->delete(route('admin.merchants.destroy', $kfc))->assertSessionHas('error');
        $this->assertModelExists($kfc);
    }

    private function card(array $attributes = []): GiftCard
    {
        return GiftCard::query()->create($attributes + ['name' => 'Voucher '.uniqid(), 'points_cost' => 1000, 'face_value' => 5000, 'is_active' => true]);
    }

    private function customerWithPoints(int $points): Customer
    {
        $customer = Customer::factory()->create();
        app(TierQualificationEngine::class)->enrol($customer->id);
        app(PointWallet::class)->credit($customer->id, $points, PointTransactionType::Earn);

        return $customer;
    }

    private function exchange(GiftCard $card)
    {
        return $this->postJson("/api/v1/customer/gift-cards/{$card->id}/exchange");
    }

    private function verify(int $id, string $code)
    {
        return $this->postJson("/api/v1/customer/gift-card-exchanges/{$id}/verify", ['code' => $code]);
    }
}
