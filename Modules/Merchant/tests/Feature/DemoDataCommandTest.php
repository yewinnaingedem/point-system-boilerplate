<?php

namespace Modules\Merchant\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Customer\Models\Customer;
use Modules\Loyalty\Models\MemberTierStatus;
use Modules\Loyalty\Models\PointAccount;
use Modules\Loyalty\Models\TierEvent;
use Modules\Merchant\Console\DemoDataCommand;
use Modules\Merchant\Enums\RedemptionStatus;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\Redemption;
use Tests\TestCase;

class DemoDataCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_is_consistent_and_removable(): void
    {
        $this->seedAccess();
        $this->artisan('merchant:demo-data')->assertSuccessful();

        $this->assertSame(4, Merchant::query()->where('notes', DemoDataCommand::MERCHANT_TAG)->count());
        $this->assertFalse(Merchant::query()->where('name', 'Lotteria')->value('is_active'));
        $this->assertGreaterThan(5, Redemption::query()->count());
        $this->assertSame(2, Redemption::query()->where('status', RedemptionStatus::Reversed)->count());
        $this->assertTrue(Redemption::query()->get()->every(fn (Redemption $r) => $r->redeemed_at->isPast()));

        // Every balance equals the sum of its history, and customers are spread over tiers.
        foreach (PointAccount::all() as $account) {
            $this->assertSame($account->balance, (int) DB::table('loyalty_point_transactions')->where('customer_id', $account->customer_id)->sum('points'));
        }
        $this->assertGreaterThan(1, MemberTierStatus::query()->distinct()->count('current_tier'));
        // Every demo customer starts at Silver: "enrolled" is the first entry of their tier history.
        foreach (Customer::all() as $customer) {
            $first = $customer->tierEvents()->orderBy('id')->first();
            $this->assertSame('enrolled', $first->transition->value);
            $this->assertSame('silver', $first->to_tier->value);
        }

        $this->artisan('merchant:demo-data')->assertFailed(); // already there

        $this->artisan('merchant:demo-data --remove')->assertSuccessful();
        $this->assertSame(0, Merchant::query()->count());
        $this->assertSame(0, Redemption::query()->count());
        $this->assertSame(0, Customer::query()->count());
        $this->assertSame(0, TierEvent::query()->count());
        $this->assertSame(0, PointAccount::query()->count());
    }
}
