<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\AppSetting\Services\SettingService;
use Modules\AppSetting\Support\SettingCatalog;
use Modules\Loyalty\Enums\TierTransition;
use Modules\Loyalty\Services\PointWallet;
use Modules\Loyalty\Services\TierQualificationEngine;
use Tests\TestCase;

class PerformanceDataTest extends TestCase
{
    use RefreshDatabase;

    private const SIZE = ['--customers' => 150, '--users' => 5, '--merchants' => 3, '--gift-cards' => 5, '--chunk' => 40];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        app(SettingService::class)->saveGroup(app(SettingCatalog::class)->group('loyalty'), [
            'loyalty_cycle_months' => '3', 'point_expiry_months' => '2', 'point_expiry_cutoff_day' => '15',
        ]);
    }

    public function test_seeded_books_agree_and_tiers_are_up_to_date(): void
    {
        $this->artisan('perf:seed', self::SIZE)->assertSuccessful();

        $this->assertSame(150, DB::table('customers')->where('external_id', 'like', 'perf-%')->count());
        $this->assertGreaterThan(0, DB::table('merchant_redemptions')->count());
        $this->assertGreaterThan(0, DB::table('loyalty_point_transactions')->where('type', 'expire')->count());

        // balance = ledger sum = remaining in lots, for every customer.
        $ledger = DB::table('loyalty_point_transactions')->groupBy('customer_id')->pluck(DB::raw('sum(points)'), 'customer_id');
        $lots = DB::table('loyalty_point_lots')->groupBy('customer_id')->pluck(DB::raw('sum(remaining)'), 'customer_id');
        foreach (DB::table('loyalty_point_accounts')->get() as $account) {
            $this->assertEquals($account->balance, $ledger[$account->customer_id], "ledger of customer {$account->customer_id}");
            $this->assertEquals($account->balance, $lots[$account->customer_id], "lots of customer {$account->customer_id}");
        }

        // The monthly summary matches the ledger.
        $earned = DB::table('loyalty_point_transactions')->where('type', 'earn')->sum('points');
        $this->assertEquals($earned, DB::table('loyalty_point_summaries')->sum('earned'));
        $expired = DB::table('loyalty_point_transactions')->where('type', 'expire')->sum('points');
        $this->assertEquals(-$expired, DB::table('loyalty_point_summaries')->sum('expired'));

        // Nothing is left for the real engine and wallet to catch up on.
        $engine = app(TierQualificationEngine::class);
        $wallet = app(PointWallet::class);
        foreach (DB::table('customers')->pluck('id') as $id) {
            $balance = DB::table('loyalty_point_accounts')->where('customer_id', $id)->value('balance') ?? 0;
            $this->assertSame($balance, $wallet->balance($id), "points of customer {$id} were due to expire");
            $this->assertSame(TierTransition::Unchanged, $engine->evaluateUserTierStatus($id)->transition, "tier of customer {$id}");
        }
    }

    public function test_remove_deletes_exactly_the_performance_data(): void
    {
        $this->artisan('perf:seed', self::SIZE)->assertSuccessful();
        $this->artisan('perf:seed', self::SIZE)->assertFailed(); // already there

        $this->artisan('perf:seed', ['--remove' => true])->assertSuccessful();

        foreach (['customers', 'merchants', 'merchant_branches', 'merchant_redemptions', 'gift_cards', 'gift_card_exchanges',
            'loyalty_point_transactions', 'loyalty_point_lots', 'loyalty_point_summaries', 'loyalty_member_statuses'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
        $this->assertSame(1, DB::table('users')->count()); // only the seeded administrator
    }
}
