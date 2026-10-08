<?php

namespace Modules\Loyalty\Tests\Feature;

use App\Enums\SystemRole;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\AppSetting\Services\SettingService;
use Modules\AppSetting\Support\SettingCatalog;
use Modules\Customer\Models\Customer;
use Modules\Loyalty\Enums\PointTransactionType;
use Modules\Loyalty\Models\PointLot;
use Modules\Loyalty\Models\PointSummary;
use Modules\Loyalty\Models\PointTransaction;
use Modules\Loyalty\Services\PointWallet;
use Modules\Loyalty\Support\PointExpiryPolicy;
use Tests\TestCase;

/**
 * Points expire per the settings (here 2 months, cutoff day 15):
 * earned 1st–15th → earning month + next → gone at the start of the month after;
 * earned 16th–end → the next two months → gone one month later.
 */
class PointExpiryTest extends TestCase
{
    use RefreshDatabase;

    private PointWallet $wallet;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setExpiry(2, 15);
        $this->wallet = app(PointWallet::class);
        $this->customer = Customer::factory()->create();
    }

    public function test_expiry_date_follows_the_cutoff_day(): void
    {
        $policy = app(PointExpiryPolicy::class);
        $expires = fn (string $earned) => $policy->expiresAt(CarbonImmutable::parse($earned))?->toDateString();

        $this->assertSame('2027-03-01', $expires('2027-01-10 09:00'), 'Jan + Feb');
        $this->assertSame('2027-03-01', $expires('2027-01-15 23:59'), 'the 15th still counts');
        $this->assertSame('2027-04-01', $expires('2027-01-16 00:00'), 'Feb + Mar');
        $this->assertSame('2027-04-01', $expires('2027-01-31 12:00'));
        $this->assertSame('2027-02-01', $expires('2026-12-05 12:00'), 'across the year end');

        $this->setExpiry(0, 15);
        $this->assertNull(app(PointExpiryPolicy::class)->expiresAt(CarbonImmutable::parse('2027-01-10')), '0 = never');
    }

    public function test_spending_uses_the_points_that_expire_first(): void
    {
        $this->at('2027-01-20 10:00', fn () => $this->earn(100)); // Feb + Mar → gone 1 Apr
        $this->at('2027-02-03 10:00', fn () => $this->earn(100)); // Feb + Mar → gone 1 Apr
        $this->at('2027-02-20 10:00', fn () => $this->earn(100)); // Mar + Apr → gone 1 May

        $this->at('2027-03-01 10:00', fn () => $this->wallet->debit($this->customer->id, 250, PointTransactionType::Redeem));

        $lots = PointLot::query()->orderBy('id')->pluck('remaining')->all();
        $this->assertSame([0, 0, 50], $lots, 'the two April lots go first, then the May one');
    }

    public function test_unspent_points_expire_at_the_end_of_the_last_counted_month(): void
    {
        $this->at('2027-01-05 10:00', fn () => $this->earn(100)); // Jan + Feb
        $this->at('2027-02-10 10:00', fn () => $this->wallet->debit($this->customer->id, 30, PointTransactionType::Redeem));

        $this->at('2027-02-28 23:59', fn () => $this->assertSame(70, $this->wallet->balance($this->customer->id)));
        $this->at('2027-03-01 00:00', fn () => $this->assertSame(0, $this->wallet->balance($this->customer->id)));

        $expire = PointTransaction::query()->where('type', PointTransactionType::Expire)->sole();
        $this->assertSame(-70, $expire->points);
        $this->assertSame(70, PointSummary::query()->where('period', '2027-03-01')->value('expired'));
        $this->assertBooksAgree();
    }

    public function test_the_daily_sweep_expires_every_customer(): void
    {
        $other = Customer::factory()->create();
        $this->at('2027-01-05 10:00', function () use ($other) {
            $this->earn(100);
            $this->wallet->credit($other->id, 40, PointTransactionType::Earn);
        });

        $this->at('2027-03-01 00:10', fn () => $this->artisan('loyalty:expire-points')->expectsOutputToContain('Expired 140 points of 2 customer(s)')->assertSuccessful());

        $this->assertSame(0, (int) DB::table('loyalty_point_accounts')->sum('balance'));
        $this->assertBooksAgree();
    }

    public function test_a_reversal_returns_points_to_their_lot_with_its_expiry(): void
    {
        $this->at('2027-01-05 10:00', fn () => $this->earn(100));                // gone 1 Mar
        $this->at('2027-01-25 10:00', fn () => $this->earn(50));                 // gone 1 Apr
        // Takes all 100 of the March lot, then 20 of the April lot.
        $debit = $this->at('2027-02-10 10:00', fn () => $this->wallet->debit($this->customer->id, 120, PointTransactionType::Redeem));

        // Reversed after the March lot expired: its 100 come back and expire again at once;
        // the April lot gets its 20 back and is whole again.
        $this->at('2027-03-05 10:00', fn () => $this->wallet->restore($debit));

        $this->assertSame(50, $this->wallet->balance($this->customer->id));
        $this->assertSame([0, 50], PointLot::query()->orderBy('id')->pluck('remaining')->all());
        $types = PointTransaction::query()->orderBy('id')->pluck('type')->map->value->all();
        $this->assertSame(['earn', 'earn', 'redeem', 'reversal', 'expire'], $types);
        $this->assertBooksAgree();
    }

    public function test_the_monthly_summary_adds_up(): void
    {
        $this->at('2027-01-05 10:00', fn () => $this->earn(300));
        $this->at('2027-01-06 10:00', fn () => $this->wallet->adjust($this->customer->id, 20, 'bonus', $this->staffId()));
        $this->at('2027-02-10 10:00', fn () => $this->wallet->debit($this->customer->id, 100, PointTransactionType::Redeem));
        $this->at('2027-02-11 10:00', fn () => $this->wallet->adjust($this->customer->id, -10, 'correction', $this->staffId()));
        $this->at('2027-03-02 10:00', fn () => $this->wallet->balance($this->customer->id)); // expires the rest

        $jan = PointSummary::query()->where('period', '2027-01-01')->sole();
        $feb = PointSummary::query()->where('period', '2027-02-01')->sole();
        $mar = PointSummary::query()->where('period', '2027-03-01')->sole();
        $this->assertSame([300, 20], [$jan->earned, $jan->adjusted_in]);
        $this->assertSame([100, 10], [$feb->redeemed, $feb->adjusted_out]);
        $this->assertSame(210, $mar->expired);
        $this->assertBooksAgree();
    }

    public function test_admin_summary_pages_render(): void
    {
        $this->seedAccess();
        $this->at('2027-01-05 10:00', fn () => $this->earn(100));
        $this->actingAs($this->userWithRole(SystemRole::Administrator));

        $this->at('2027-02-10 10:00', function () {
            $this->get(route('admin.loyalty.points-summary.index'))->assertOk()->assertSee('Expire end of Feb');
            $months = $this->dataTable(route('admin.loyalty.points-summary.months'), ['month'], ['length' => 100])->json('data');
            $this->assertSame('Jan 2027', $months[0]['month']);
            $this->assertSame('+100', $months[0]['net']);
            $expiring = $this->dataTable(route('admin.loyalty.points-summary.expiring'), ['customer'])->json('data');
            $this->assertSame('100', $expiring[0]['amount']);
            $this->get(route('admin.loyalty.points.show', $this->customer))->assertOk()->assertSee('expire at the end of');
            $this->dataTable(route('admin.loyalty.points.lots', $this->customer), ['amount'])->assertJsonPath('data.0.amount', '100');
            $this->dataTable(route('admin.loyalty.points.months', $this->customer), ['month'])->assertJsonPath('data.0.earned', '100');
        });
    }

    private function earn(int $points): PointTransaction
    {
        return $this->wallet->credit($this->customer->id, $points, PointTransactionType::Earn);
    }

    private function at(string $moment, callable $callback): mixed
    {
        $this->travelTo(CarbonImmutable::parse($moment));

        return $callback();
    }

    private function setExpiry(int $months, int $cutoff): void
    {
        app(SettingService::class)->saveGroup(app(SettingCatalog::class)->group('loyalty'), [
            'loyalty_cycle_months' => '1', 'point_expiry_months' => (string) $months, 'point_expiry_cutoff_day' => (string) $cutoff,
        ]);
    }

    private function staffId(): int
    {
        return User::factory()->create()->id;
    }

    /** balance = sum of the ledger = sum of what is left in unexpired lots. */
    private function assertBooksAgree(): void
    {
        foreach (DB::table('loyalty_point_accounts')->get() as $account) {
            $ledger = (int) PointTransaction::query()->where('customer_id', $account->customer_id)->sum('points');
            $lots = (int) PointLot::query()->where('customer_id', $account->customer_id)->sum('remaining');
            $this->assertSame((int) $account->balance, $ledger, 'ledger');
            $this->assertSame((int) $account->balance, $lots, 'lots');
        }
    }
}
