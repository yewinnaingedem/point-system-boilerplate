<?php

namespace Modules\Loyalty\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Modules\Loyalty\Enums\PointTransactionType;
use Modules\Loyalty\Http\Requests\PointActivityRequest;
use Modules\Loyalty\Models\PointTransaction;
use Modules\Loyalty\Support\ActivityPeriod;
use Modules\Loyalty\Tables\PointActivityTable;
use Modules\Loyalty\Tables\PointEarnersTable;

/** All customers' point movements: who earned today (or in any period), and the full list. */
class PointActivityController extends Controller
{
    public function index(): View
    {
        $today = ActivityPeriod::from('today');
        $sumToday = function (PointTransactionType $type) use ($today) {
            $query = PointTransaction::query()->where('type', $type);
            $today->apply($query);

            return abs((int) $query->sum('points'));
        };
        $earners = PointTransaction::query()->where('type', PointTransactionType::Earn);
        $today->apply($earners);

        return view('loyalty::points.activity', [
            'earnedToday' => $sumToday(PointTransactionType::Earn),
            'earnersToday' => (clone $earners)->distinct()->count('customer_id'),
            'redeemedToday' => $sumToday(PointTransactionType::Redeem),
            'expiredToday' => $sumToday(PointTransactionType::Expire),
            'periods' => ActivityPeriod::OPTIONS,
            'types' => PointTransactionType::cases(),
            'today' => CarbonImmutable::now(config('app.timezone'))->toDateString(),
        ]);
    }

    public function transactions(PointActivityRequest $request, PointActivityTable $table): JsonResponse
    {
        return $table->response($request);
    }

    public function earners(PointActivityRequest $request, PointEarnersTable $table): JsonResponse
    {
        return $table->response($request);
    }
}
