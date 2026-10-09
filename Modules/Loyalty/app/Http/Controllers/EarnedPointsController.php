<?php

namespace Modules\Loyalty\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Modules\Loyalty\Enums\EarnedPointStatus;
use Modules\Loyalty\Http\Requests\EarnedPointsRequest;
use Modules\Loyalty\Models\PointLot;
use Modules\Loyalty\Support\ActivityPeriod;
use Modules\Loyalty\Tables\EarnedPointsTable;

/** Points → Earned Points: all customers' credits of points, with what is left and when it expires. */
class EarnedPointsController extends Controller
{
    private const EXPIRING_DAYS = 30;

    public function index(): View
    {
        $now = CarbonImmutable::now();
        $month = ActivityPeriod::from('month');
        $thisMonth = PointLot::query();
        $month->apply($thisMonth, 'earned_at');
        $valid = fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);

        return view('loyalty::points.earned', [
            'earnedThisMonth' => (int) (clone $thisMonth)->sum('points'),
            'earnersThisMonth' => (clone $thisMonth)->distinct()->count('customer_id'),
            'unusedPoints' => (int) PointLot::query()->where('remaining', '>', 0)->where($valid)->sum('remaining'),
            'expiringSoon' => (int) PointLot::query()->where('remaining', '>', 0)->where('expires_at', '>', $now)
                ->where('expires_at', '<=', $now->addDays(self::EXPIRING_DAYS))->sum('remaining'),
            'periods' => ActivityPeriod::OPTIONS,
            'sources' => EarnedPointsRequest::SOURCES,
            'statuses' => EarnedPointStatus::cases(),
            'today' => CarbonImmutable::now(config('app.timezone'))->toDateString(),
        ]);
    }

    public function data(EarnedPointsRequest $request, EarnedPointsTable $table): JsonResponse
    {
        return $table->response($request);
    }
}
