<?php

namespace Modules\Loyalty\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Modules\Loyalty\Http\Requests\PointTableRequest;
use Modules\Loyalty\Models\PointAccount;
use Modules\Loyalty\Models\PointLot;
use Modules\Loyalty\Models\PointSummary;
use Modules\Loyalty\Support\PointExpiryPolicy;
use Modules\Loyalty\Tables\ExpiringPointsTable;
use Modules\Loyalty\Tables\PointSummaryTable;

/** Points across all customers: what is outstanding, what expires soon, month by month. */
class PointSummaryController extends Controller
{
    public function index(PointExpiryPolicy $expiry): View
    {
        $now = CarbonImmutable::now();
        $thisMonth = $now->startOfMonth();
        $expiringIn = fn (CarbonImmutable $month) => (int) PointLot::query()->where('remaining', '>', 0)
            ->where('expires_at', '>', $now)->where('expires_at', '<=', $month->addMonth())->where('expires_at', '>', $month)->sum('remaining');

        return view('loyalty::points.summary', [
            'outstanding' => (int) PointAccount::query()->sum('balance'),
            'expiringThisMonth' => $expiringIn($thisMonth),
            'expiringNextMonth' => $expiringIn($thisMonth->addMonth()),
            'thisMonth' => PointSummary::query()->where('period', $thisMonth->toDateString())
                ->selectRaw('sum(earned) as earned, sum(redeemed) as redeemed, sum(expired) as expired')->first(),
            'expiryMonths' => $expiry->months(),
            'cutoffDay' => $expiry->cutoffDay(),
            'monthNames' => [$thisMonth->format('M'), $thisMonth->addMonth()->format('M')],
        ]);
    }

    public function months(PointTableRequest $request, PointSummaryTable $table): JsonResponse
    {
        return $table->response();
    }

    public function expiring(PointTableRequest $request, ExpiringPointsTable $table): JsonResponse
    {
        return $table->response(null, $request->searchTerm());
    }
}
