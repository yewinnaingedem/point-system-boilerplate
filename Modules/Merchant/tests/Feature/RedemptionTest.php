<?php

namespace Modules\Merchant\Tests\Feature;

use App\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Customer\Models\Customer;
use Modules\Loyalty\Enums\PointTransactionType;
use Modules\Loyalty\Services\PointWallet;
use Modules\Merchant\Enums\RedemptionStatus;
use Modules\Merchant\Exceptions\RedemptionRejected;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantBranch;
use Modules\Merchant\Models\MerchantReward;
use Modules\Merchant\Models\Redemption;
use Modules\Merchant\Services\MerchantService;
use Modules\Merchant\Services\RedemptionService;
use Tests\TestCase;

/**
 * The customer app flow: pick a branch and reward, shop staff type the branch code.
 * KFC pays 10 per point; the Zinger costs 500 points (payout by rate = 5,000), the
 * Bucket 2,000 points with its own payout of 9,000.
 */
class RedemptionTest extends TestCase
{
    use RefreshDatabase;

    private Customer $member;

    private Merchant $kfc;

    private MerchantBranch $branch;

    private MerchantReward $zinger;

    private MerchantReward $bucket;

    private PointWallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();

        $this->member = Customer::factory()->create();
        $this->wallet = app(PointWallet::class);
        $this->wallet->credit($this->member->id, 3000, PointTransactionType::Earn);

        $service = app(MerchantService::class);
        $this->kfc = Merchant::query()->create(['name' => 'KFC', 'settlement_rate' => 10]);
        $this->branch = $service->createBranch($this->kfc, ['name' => 'Junction Square', 'is_active' => true]);
        $this->zinger = $service->saveReward($this->kfc, new MerchantReward, ['name' => 'Zinger Burger', 'points_cost' => 500, 'is_active' => true]);
        $this->bucket = $service->saveReward($this->kfc, new MerchantReward, ['name' => 'Bucket', 'points_cost' => 2000, 'payout_amount' => 9000, 'is_active' => true]);

        Sanctum::actingAs($this->member);
    }

    public function test_member_redeems_with_the_branch_code(): void
    {
        $response = $this->redeem($this->zinger)->assertCreated()
            ->assertJsonPath('data.reward', 'Zinger Burger')
            ->assertJsonPath('data.branch', 'Junction Square')
            ->assertJsonPath('data.points', 500);
        $this->assertArrayNotHasKey('payout_amount', $response->json('data'));

        $this->assertSame(2500, $this->wallet->balance($this->member->id));
        $redemption = Redemption::query()->sole();
        $this->assertSame('5000.00', $redemption->payout_amount); // 500 x 10
        $this->assertSame(RedemptionStatus::Completed, $redemption->status);
        $this->assertNull($redemption->settlement_id);

        $this->redeem($this->bucket)->assertCreated();
        $this->assertSame('9000.00', Redemption::query()->latest('id')->first()->payout_amount); // own payout

        $this->getJson('/api/v1/customer/points')->assertOk()->assertJsonPath('data.balance', 500)->assertJsonPath('data.history.0.type', 'redeem');
        $this->getJson('/api/v1/customer/redemptions')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_payout_is_frozen_when_prices_change_later(): void
    {
        $this->redeem($this->zinger)->assertCreated();
        $this->kfc->update(['settlement_rate' => 99]);

        $this->assertSame('5000.00', Redemption::query()->sole()->payout_amount);
    }

    public function test_wrong_codes_lock_the_member_out(): void
    {
        $wrong = $this->branch->code === '482910' ? '482911' : '482910';

        $this->redeem($this->zinger, $wrong)->assertUnprocessable()->assertJsonValidationErrors('code');
        foreach (range(2, 4) as $attempt) {
            $this->redeem($this->zinger, $wrong)->assertUnprocessable();
        }
        $this->redeem($this->zinger, $wrong)->assertStatus(429);
        // Locked out: even the right code is refused now.
        $this->redeem($this->zinger)->assertStatus(429);

        $this->assertSame(0, Redemption::query()->count());
        $this->assertSame(3000, $this->wallet->balance($this->member->id));
    }

    public function test_not_enough_points_changes_nothing(): void
    {
        $this->wallet->debit($this->member->id, 2800, PointTransactionType::Adjust);

        $this->redeem($this->zinger)->assertUnprocessable()->assertJsonValidationErrors('reward_id');

        $this->assertSame(0, Redemption::query()->count());
        $this->assertSame(200, $this->wallet->balance($this->member->id));
    }

    public function test_inactive_branch_or_another_merchants_reward_is_refused(): void
    {
        $other = Merchant::query()->create(['name' => 'Pizza', 'settlement_rate' => 1]);
        $pizza = app(MerchantService::class)->saveReward($other, new MerchantReward, ['name' => 'Pizza', 'points_cost' => 10, 'is_active' => true]);
        $this->redeem($pizza)->assertUnprocessable()->assertJsonValidationErrors('reward_id');

        $this->branch->update(['is_active' => false]);
        $this->redeem($this->zinger)->assertUnprocessable()->assertJsonValidationErrors('branch_id');
    }

    public function test_retrying_with_the_same_request_id_charges_once(): void
    {
        $first = $this->redeem($this->zinger, null, 'phone-req-1')->assertCreated()->json('data.reference');
        $second = $this->redeem($this->zinger, null, 'phone-req-1')->assertOk()->json('data.reference');

        $this->assertSame($first, $second);
        $this->assertSame(1, Redemption::query()->count());
        $this->assertSame(2500, $this->wallet->balance($this->member->id));
    }

    public function test_an_unsettled_redemption_can_be_reversed_once(): void
    {
        // The admin Redemptions screen was removed (2026-10-09); the service rule stays.
        $this->redeem($this->zinger)->assertCreated();
        $redemption = Redemption::query()->sole();
        $admin = $this->userWithRole(SystemRole::Administrator);
        $service = app(RedemptionService::class);

        $service->reverse($redemption, $admin, 'Out of stock');
        $this->assertSame(RedemptionStatus::Reversed, $redemption->fresh()->status);
        $this->assertSame(3000, $this->wallet->balance($this->member->id));
        $this->assertSame(0, Redemption::query()->unsettled()->count());

        // Not twice, and never once it is in a settlement.
        $this->assertThrows(fn () => $service->reverse($redemption->fresh(), $admin, 'again'), RedemptionRejected::class);
        $this->assertSame(3000, $this->wallet->balance($this->member->id));

        $this->redeem($this->zinger)->assertCreated();
        $settled = Redemption::query()->latest('id')->first();
        $settled->update(['settlement_id' => 1]);
        $this->assertThrows(fn () => $service->reverse($settled->fresh(), $admin, 'late'), RedemptionRejected::class);
    }

    public function test_the_admin_redemptions_screen_is_gone(): void
    {
        $this->actingAs($this->userWithRole(SystemRole::Administrator))->get('/admin/redemptions')->assertNotFound();
    }

    public function test_merchant_list_for_the_app_hides_codes_and_inactive_items(): void
    {
        app(MerchantService::class)->createBranch($this->kfc, ['name' => 'Closed shop', 'is_active' => false]);
        Merchant::query()->create(['name' => 'Hidden', 'settlement_rate' => 1, 'is_active' => false]);

        $response = $this->getJson('/api/v1/customer/merchants')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonCount(1, 'data.0.branches')->assertJsonPath('data.0.rewards.0.points', 500);

        $this->assertStringNotContainsString($this->branch->code, $response->getContent());
        $this->assertStringNotContainsString('payout', $response->getContent());
    }

    public function test_staff_tokens_cannot_use_the_customer_api_and_vice_versa(): void
    {
        Sanctum::actingAs($this->userWithRole(SystemRole::Administrator));
        $this->getJson('/api/v1/customer/merchants')->assertForbidden();
        $this->redeem($this->zinger)->assertForbidden();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->member);
        $this->getJson('/api/v1/me')->assertForbidden();
        $this->getJson('/api/v1/settings')->assertForbidden();
    }

    private function redeem(MerchantReward $reward, ?string $code = null, ?string $requestId = null)
    {
        return $this->postJson('/api/v1/customer/redemptions', array_filter([
            'branch_id' => $this->branch->id,
            'reward_id' => $reward->id,
            'code' => $code ?? $this->branch->code,
            'request_id' => $requestId,
        ]));
    }
}
