<?php

namespace Modules\Partner\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\AppSetting\Services\SettingService;
use Modules\AppSetting\Support\SettingCatalog;
use Modules\Customer\Models\Customer;
use Modules\Loyalty\Models\PointTransaction;
use Modules\Loyalty\Services\PointWallet;
use Tests\TestCase;

/**
 * The partner project awards points server to server with its API key.
 */
class PartnerPointsApiTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'partner-key-with-at-least-32-characters-ok';

    protected function setUp(): void
    {
        parent::setUp();
        config(['partner.api_key' => self::KEY, 'partner.allowed_ips' => []]);
        app(SettingService::class)->saveGroup(app(SettingCatalog::class)->group('loyalty'), [
            'loyalty_cycle_months' => '1', 'point_expiry_months' => '2', 'point_expiry_cutoff_day' => '15',
        ]);
        $this->travelTo(CarbonImmutable::parse('2027-01-20 10:00'));
    }

    public function test_award_creates_the_customer_at_silver_and_gives_expiring_points(): void
    {
        $this->award()->assertCreated()
            ->assertJsonPath('data.points', 250)
            ->assertJsonPath('data.expires_on', '2027-03-31') // earned after the 15th: Feb + Mar
            ->assertJsonPath('data.replayed', false)
            ->assertJsonPath('data.customer.balance', 250);

        $customer = Customer::query()->sole();
        $this->assertSame('shop-7', $customer->external_id);
        $this->assertSame('silver', $customer->tierStatus->current_tier->value);
        $this->assertSame('ORDER-1001', PointTransaction::query()->sole()->reference);
    }

    public function test_the_same_reference_is_awarded_only_once(): void
    {
        $this->award()->assertCreated();
        $this->award()->assertOk()->assertJsonPath('data.replayed', true);

        $this->assertSame(250, app(PointWallet::class)->balance(Customer::query()->sole()->id));
        $this->award(['points' => 999])->assertStatus(409);
        $this->award(['customer' => ['external_id' => 'someone-else', 'name' => 'X']])->assertStatus(409);
    }

    public function test_spent_amount_counts_towards_the_tier(): void
    {
        $this->award(['spent_amount' => '600000'])->assertCreated();

        $this->assertSame('gold', Customer::query()->sole()->tierStatus->current_tier->value);
    }

    public function test_deactivated_customers_get_no_points(): void
    {
        Customer::factory()->create(['external_id' => 'shop-7', 'is_active' => false]);

        $this->award()->assertUnprocessable();
        $this->assertSame(0, PointTransaction::query()->count());
    }

    public function test_partner_reads_a_customers_points(): void
    {
        $this->award()->assertCreated();

        $this->getJson('/api/v1/partner/customers/shop-7/points', $this->auth())->assertOk()
            ->assertJsonPath('data.balance', 250)
            ->assertJsonPath('data.next_expiry.points', 250)
            ->assertJsonPath('data.next_expiry.expires_on', '2027-03-31')
            ->assertJsonPath('data.months.0.earned', 250)
            ->assertJsonPath('data.tier.level', 'silver');
        $this->getJson('/api/v1/partner/customers/nobody/points', $this->auth())->assertNotFound();
    }

    public function test_the_customer_app_sees_its_expiring_points(): void
    {
        $this->award()->assertCreated();
        Sanctum::actingAs(Customer::query()->sole());

        $this->getJson('/api/v1/customer/points')->assertOk()
            ->assertJsonPath('data.balance', 250)
            ->assertJsonPath('data.next_expiry.expires_on', '2027-03-31')
            ->assertJsonPath('data.by_expiry.0.points', 250)
            ->assertJsonPath('data.history.0.type', 'earn');
    }

    public function test_requests_without_the_right_key_or_from_other_addresses_are_refused(): void
    {
        $this->postJson('/api/v1/partner/points', $this->payload())->assertUnauthorized();
        $this->postJson('/api/v1/partner/points', $this->payload(), ['Authorization' => 'Bearer wrong'])->assertUnauthorized();

        config(['partner.allowed_ips' => ['10.0.0.9']]);
        $this->award()->assertUnauthorized();

        config(['partner.allowed_ips' => [], 'partner.api_key' => 'short']);
        $this->postJson('/api/v1/partner/points', $this->payload(), ['Authorization' => 'Bearer short'])->assertUnauthorized();

        $this->assertSame(0, Customer::query()->count());
    }

    private function award(array $override = [])
    {
        return $this->postJson('/api/v1/partner/points', $this->payload($override), $this->auth());
    }

    private function payload(array $override = []): array
    {
        return array_replace([
            'customer' => ['external_id' => 'shop-7', 'name' => 'Mya Mya', 'email' => 'mya@shop.test', 'phone' => '0977'],
            'points' => 250,
            'reference' => 'ORDER-1001',
            'note' => 'Order ORDER-1001',
        ], $override);
    }

    private function auth(): array
    {
        return ['Authorization' => 'Bearer '.self::KEY];
    }
}
