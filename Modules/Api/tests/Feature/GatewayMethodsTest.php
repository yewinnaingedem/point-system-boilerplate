<?php

namespace Modules\Api\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Api\Tests\Feature\Concerns\CallsGateway;
use Modules\AppSetting\Services\SettingService;
use Modules\AppSetting\Support\SettingCatalog;
use Modules\Customer\Models\Customer;
use Modules\GiftCard\Models\GiftCard;
use Modules\GiftCard\Models\GiftCardExchange;
use Modules\GiftCard\Notifications\GiftCardVerificationCode;
use Modules\Loyalty\Services\PointWallet;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Services\MerchantService;
use Tests\TestCase;

/**
 * Each gateway method end to end: customers, points, tiers, gift cards, redemptions.
 */
class GatewayMethodsTest extends TestCase
{
    use CallsGateway, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->makeGatewayClient();
        app(SettingService::class)->saveGroup(app(SettingCatalog::class)->group('loyalty'), [
            'loyalty_cycle_months' => '1', 'point_expiry_months' => '2', 'point_expiry_cutoff_day' => '15',
        ]);
        $this->travelTo(CarbonImmutable::parse('2027-01-20 10:00'));
    }

    public function test_point_create_awards_once_per_reference(): void
    {
        $biz = ['external_id' => 'shop-7', 'name' => 'Aung Aung', 'points' => '250', 'reference' => 'ORDER-1001'];

        $this->gateway('pos.point.create', $biz)->assertOk()
            ->assertJsonPath('Response.biz_content.points', 250)
            ->assertJsonPath('Response.biz_content.expires_on', '2027-03-31')
            ->assertJsonPath('Response.biz_content.replayed', false)
            ->assertJsonPath('Response.biz_content.customer.balance', 250);

        $this->gateway('pos.point.create', $biz)->assertOk()->assertJsonPath('Response.biz_content.replayed', true);
        $this->gateway('pos.point.create', ['points' => '999'] + $biz)->assertStatus(409)->assertJsonPath('Response.code', 'REFERENCE_CONFLICT');

        $this->assertSame(250, app(PointWallet::class)->balance(Customer::query()->sole()->id));
    }

    public function test_point_create_without_name_needs_an_existing_active_customer_and_keeps_their_profile(): void
    {
        $this->gateway('pos.point.create', ['external_id' => 'ghost', 'points' => '10', 'reference' => 'R1'])
            ->assertNotFound()->assertJsonPath('Response.code', 'CUSTOMER_NOT_FOUND');

        $this->gateway('pos.customer.register', ['external_id' => 'shop-7', 'name' => 'Aung Aung', 'phone' => '0991234567'])->assertOk();
        $this->gateway('pos.point.create', ['external_id' => 'shop-7', 'points' => '10', 'reference' => 'R2'])->assertOk();
        $customer = Customer::query()->sole();
        $this->assertSame(['Aung Aung', '0991234567'], [$customer->name, $customer->phone]);

        $customer->update(['is_active' => false]);
        $this->gateway('pos.point.create', ['external_id' => 'shop-7', 'points' => '10', 'reference' => 'R3'])
            ->assertForbidden()->assertJsonPath('Response.code', 'CUSTOMER_INACTIVE');
    }

    public function test_fields_left_out_keep_the_profile_and_empty_ones_clear_it(): void
    {
        $this->gateway('pos.customer.register', ['external_id' => 'shop-7', 'name' => 'Aung', 'email' => 'aung@example.com', 'phone' => '0991'])->assertOk();

        // An award with a name but no email / phone must not wipe them (two-step gift cards need the email).
        $this->gateway('pos.point.create', ['external_id' => 'shop-7', 'name' => 'Aung Aung', 'points' => '10', 'reference' => 'R1'])->assertOk();
        $this->assertSame(['Aung Aung', 'aung@example.com', '0991'], array_values(Customer::query()->sole()->only(['name', 'email', 'phone'])));

        $this->gateway('pos.customer.register', ['external_id' => 'shop-7', 'name' => 'Aung Aung', 'phone' => ''])->assertOk();
        $this->assertSame(['aung@example.com', null], array_values(Customer::query()->sole()->only(['email', 'phone'])));
    }

    public function test_spent_amount_moves_the_tier_and_tier_query_shows_it(): void
    {
        $this->gateway('pos.point.create', ['external_id' => 'shop-7', 'name' => 'A', 'points' => '600', 'reference' => 'O-1', 'spent_amount' => '600000'])->assertOk();

        $this->gateway('pos.tier.query', ['external_id' => 'shop-7'])->assertOk()
            ->assertJsonPath('Response.biz_content.tier.cycle.spent', '600000.00');

        $history = $this->gateway('pos.tier.history', ['external_id' => 'shop-7'])->assertOk();
        $this->assertSame('enrolled', collect($history->json('Response.biz_content.items'))->last()['transition']);
        $history->assertJsonPath('Response.biz_content.page', 1)->assertJsonPath('Response.biz_content.has_more', false);
    }

    public function test_point_query_and_customer_query(): void
    {
        $this->gateway('pos.point.create', ['external_id' => 'shop-7', 'name' => 'A', 'points' => '300', 'reference' => 'O-1'])->assertOk();

        $this->gateway('pos.point.query', ['external_id' => 'shop-7'])->assertOk()
            ->assertJsonPath('Response.biz_content.balance', 300)
            ->assertJsonPath('Response.biz_content.history.items.0.reference', 'O-1');

        $this->gateway('pos.customer.query', ['external_id' => 'shop-7'])->assertOk()
            ->assertJsonPath('Response.biz_content.customer.points', 300);

        $this->gateway('pos.customer.query', ['external_id' => 'nobody'])->assertNotFound()->assertJsonPath('Response.code', 'CUSTOMER_NOT_FOUND');
    }

    public function test_gift_card_list_exchange_and_rejection_reasons(): void
    {
        $this->gateway('pos.point.create', ['external_id' => 'shop-7', 'name' => 'A', 'points' => '1500', 'reference' => 'O-1'])->assertOk();
        $card = GiftCard::query()->create(['name' => 'Voucher', 'points_cost' => 1000, 'face_value' => 5000, 'is_active' => true, 'stock' => 1]);

        $this->gateway('pos.giftcard.list', ['external_id' => 'shop-7'])->assertOk()
            ->assertJsonPath('Response.biz_content.items.0.available', true);

        $response = $this->gateway('pos.giftcard.exchange', ['external_id' => 'shop-7', 'gift_card_id' => (string) $card->id])->assertOk()
            ->assertJsonPath('Response.biz_content.status', 'issued');
        $this->assertStringStartsWith('GC-', $response->json('Response.biz_content.code'));
        $this->assertSignedResponse($response);

        $this->gateway('pos.giftcard.exchange', ['external_id' => 'shop-7', 'gift_card_id' => (string) $card->id])
            ->assertUnprocessable()->assertJsonPath('Response.code', 'OUT_OF_STOCK');
        $this->gateway('pos.giftcard.exchange', ['external_id' => 'shop-7', 'gift_card_id' => '999'])
            ->assertNotFound()->assertJsonPath('Response.code', 'GIFT_CARD_NOT_FOUND');

        $this->gateway('pos.giftcard.exchanges', ['external_id' => 'shop-7'])->assertOk()
            ->assertJsonCount(1, 'Response.biz_content.items');
    }

    public function test_two_step_gift_card_is_pending_until_the_emailed_code_is_verified(): void
    {
        Notification::fake();
        $this->gateway('pos.customer.register', ['external_id' => 'shop-7', 'name' => 'A', 'email' => 'a@example.com'])->assertOk();
        $this->gateway('pos.point.create', ['external_id' => 'shop-7', 'name' => 'A', 'points' => '500', 'reference' => 'O-1'])->assertOk();
        $card = GiftCard::query()->create(['name' => 'Two-step', 'points_cost' => 100, 'face_value' => 5000, 'is_active' => true, 'requires_verification' => true]);

        $pending = $this->gateway('pos.giftcard.exchange', ['external_id' => 'shop-7', 'gift_card_id' => (string) $card->id])->assertOk()
            ->assertJsonPath('Response.biz_content.status', 'pending')
            ->assertJsonPath('Response.biz_content.code', null);
        $exchangeId = (string) $pending->json('Response.biz_content.id');

        $code = null;
        Notification::assertSentTo(Customer::query()->sole(), GiftCardVerificationCode::class, function ($notification) use (&$code) {
            $code = $notification->code;

            return true;
        });

        $this->gateway('pos.giftcard.verify', ['external_id' => 'shop-7', 'exchange_id' => $exchangeId, 'code' => $code === '000000' ? '111111' : '000000'])
            ->assertUnprocessable()->assertJsonPath('Response.code', 'WRONG_CODE');
        $this->gateway('pos.giftcard.verify', ['external_id' => 'shop-7', 'exchange_id' => $exchangeId, 'code' => $code])->assertOk()
            ->assertJsonPath('Response.biz_content.status', 'issued');
        $this->assertSame(400, app(PointWallet::class)->balance(Customer::query()->sole()->id));
    }

    public function test_an_issued_gift_card_is_used_at_a_branch_with_its_code(): void
    {
        $this->gateway('pos.point.create', ['external_id' => 'shop-7', 'name' => 'A', 'points' => '500', 'reference' => 'O-1'])->assertOk();
        $card = GiftCard::query()->create(['name' => 'KFC 5,000 Ks', 'points_cost' => 200, 'face_value' => 5000, 'is_active' => true, 'stock' => 1, 'valid_days' => 30]);
        $kfc = Merchant::query()->create(['name' => 'KFC', 'settlement_rate' => 10]);
        $branch = app(MerchantService::class)->createBranch($kfc, ['name' => 'Junction Square', 'is_active' => true]);
        $exchangeId = (string) $this->gateway('pos.giftcard.exchange', ['external_id' => 'shop-7', 'gift_card_id' => (string) $card->id])
            ->assertOk()->json('Response.biz_content.id');

        $merchants = $this->gateway('pos.merchant.list')->assertOk()
            ->assertJsonPath('Response.biz_content.items.0.branches.0.name', 'Junction Square');
        $this->assertArrayNotHasKey('rewards', $merchants->json('Response.biz_content.items.0'));
        $this->assertStringNotContainsString($branch->code, $merchants->getContent());

        $use = ['external_id' => 'shop-7', 'exchange_id' => $exchangeId, 'branch_id' => (string) $branch->id];
        $wrong = $branch->code === '111111' ? '222222' : '111111';
        $this->gateway('pos.giftcard.use', ['code' => $wrong] + $use)->assertUnprocessable()->assertJsonPath('Response.code', 'WRONG_CODE');

        $used = $this->gateway('pos.giftcard.use', ['code' => $branch->code] + $use)->assertOk()
            ->assertJsonPath('Response.biz_content.status', 'used')
            ->assertJsonPath('Response.biz_content.used_at_merchant', 'KFC')
            ->assertJsonPath('Response.biz_content.used_at_branch', 'Junction Square')
            ->assertJsonPath('Response.biz_content.replayed', false);
        $this->assertStringNotContainsString('payout', $used->getContent());
        $this->assertSignedResponse($used);

        // Retry of the same use is fine; another branch is not.
        $this->gateway('pos.giftcard.use', ['code' => $branch->code] + $use)->assertOk()->assertJsonPath('Response.biz_content.replayed', true);
        $other = app(MerchantService::class)->createBranch($kfc, ['name' => 'Myaynigone', 'is_active' => true]);
        $this->gateway('pos.giftcard.use', ['code' => $other->code, 'branch_id' => (string) $other->id] + $use)
            ->assertUnprocessable()->assertJsonPath('Response.code', 'ALREADY_USED');

        $exchange = GiftCardExchange::query()->sole();
        $this->assertSame(['5000.00', $kfc->id, $branch->id, null], [$exchange->payout_amount, $exchange->merchant_id, $exchange->branch_id, $exchange->claim_id]);
        $this->assertSame(300, app(PointWallet::class)->balance(Customer::query()->sole()->id)); // taken once, at the exchange

        // A used card still counts against stock (1) and shows under "my gift cards".
        $this->gateway('pos.giftcard.exchange', ['external_id' => 'shop-7', 'gift_card_id' => (string) $card->id])
            ->assertUnprocessable()->assertJsonPath('Response.code', 'OUT_OF_STOCK');
        $this->gateway('pos.giftcard.exchanges', ['external_id' => 'shop-7'])->assertOk()
            ->assertJsonPath('Response.biz_content.items.0.status', 'used');
    }

    public function test_a_merchant_gift_card_works_only_at_that_merchants_branches(): void
    {
        $this->gateway('pos.point.create', ['external_id' => 'shop-7', 'name' => 'A', 'points' => '500', 'reference' => 'O-1'])->assertOk();
        $service = app(MerchantService::class);
        $kfc = Merchant::query()->create(['name' => 'KFC', 'settlement_rate' => 10]);
        $pizza = Merchant::query()->create(['name' => 'Pizza', 'settlement_rate' => 10]);
        $kfcBranch = $service->createBranch($kfc, ['name' => 'Junction City', 'is_active' => true]);
        $pizzaBranch = $service->createBranch($pizza, ['name' => 'Hledan', 'is_active' => true]);
        $card = GiftCard::query()->create(['merchant_id' => $kfc->id, 'name' => 'KFC 5,000 Ks', 'points_cost' => 100, 'face_value' => 5000, 'is_active' => true]);

        $this->gateway('pos.giftcard.list', ['external_id' => 'shop-7'])->assertOk()
            ->assertJsonPath('Response.biz_content.items.0.merchant.name', 'KFC');
        $exchangeId = (string) $this->gateway('pos.giftcard.exchange', ['external_id' => 'shop-7', 'gift_card_id' => (string) $card->id])
            ->assertOk()->assertJsonPath('Response.biz_content.for_merchant', 'KFC')->json('Response.biz_content.id');

        // Pizza can't take it, and the refusal doesn't count as a wrong branch code.
        foreach (range(1, 6) as $try) {
            $this->gateway('pos.giftcard.use', ['external_id' => 'shop-7', 'exchange_id' => $exchangeId, 'branch_id' => (string) $pizzaBranch->id, 'code' => $pizzaBranch->code])
                ->assertUnprocessable()->assertJsonPath('Response.code', 'WRONG_SHOP');
        }
        $this->gateway('pos.giftcard.use', ['external_id' => 'shop-7', 'exchange_id' => $exchangeId, 'branch_id' => (string) $kfcBranch->id, 'code' => $kfcBranch->code])
            ->assertOk()->assertJsonPath('Response.biz_content.used_at_merchant', 'KFC');
    }

    public function test_an_expired_or_cancelled_gift_card_cannot_be_used(): void
    {
        $this->gateway('pos.point.create', ['external_id' => 'shop-7', 'name' => 'A', 'points' => '500', 'reference' => 'O-1'])->assertOk();
        $card = GiftCard::query()->create(['name' => 'Voucher', 'points_cost' => 100, 'face_value' => 5000, 'is_active' => true, 'valid_days' => 1]);
        $kfc = Merchant::query()->create(['name' => 'KFC', 'settlement_rate' => 10]);
        $branch = app(MerchantService::class)->createBranch($kfc, ['name' => 'Junction Square', 'is_active' => true]);
        $exchangeId = (string) $this->gateway('pos.giftcard.exchange', ['external_id' => 'shop-7', 'gift_card_id' => (string) $card->id])->json('Response.biz_content.id');

        $this->travel(3)->days();
        $this->gateway('pos.giftcard.use', ['external_id' => 'shop-7', 'exchange_id' => $exchangeId, 'branch_id' => (string) $branch->id, 'code' => $branch->code])
            ->assertUnprocessable()->assertJsonPath('Response.code', 'EXPIRED');

        GiftCardExchange::query()->whereKey($exchangeId)->update(['status' => 'cancelled']);
        $this->gateway('pos.giftcard.use', ['external_id' => 'shop-7', 'exchange_id' => $exchangeId, 'branch_id' => (string) $branch->id, 'code' => $branch->code])
            ->assertUnprocessable()->assertJsonPath('Response.code', 'NOT_ISSUED');

        $this->gateway('pos.redemption.create', ['external_id' => 'shop-7'])->assertStatus(400)->assertJsonPath('Response.code', 'UNKNOWN_METHOD');
    }
}
