<?php

namespace Modules\Loyalty\Tables;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Modules\Customer\Models\Customer;
use Modules\Loyalty\Models\PointLot;
use Yajra\DataTables\DataTables;

/**
 * Unspent points by expiry date: per customer (one customer's page) or, for the summary page,
 * every customer whose points expire within the next few months.
 */
class ExpiringPointsTable
{
    private const WINDOW_MONTHS = 3;

    public function __construct(private readonly DataTables $datatables) {}

    public function response(?Customer $customer = null, ?string $search = null): JsonResponse
    {
        $now = CarbonImmutable::now();

        $query = PointLot::query()
            ->selectRaw('customer_id, expires_at, sum(remaining) as points')
            ->with('customer:id,name,email,phone')
            ->where('remaining', '>', 0)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now))
            ->when($customer, fn (Builder $q) => $q->where('customer_id', $customer->id),
                fn (Builder $q) => $q->whereNotNull('expires_at')->where('expires_at', '<=', $now->startOfMonth()->addMonths(self::WINDOW_MONTHS + 1)))
            ->when($search, fn (Builder $q) => $q->whereIn('customer_id', Customer::search($search)->select('id')))
            ->groupBy('customer_id', 'expires_at')
            ->orderByRaw('expires_at is null')->orderBy('expires_at')->orderByDesc('points');

        // Grouped query, paged by the database (yajra counts it through a subquery).
        return $this->datatables->eloquent($query)
            ->filter(fn () => null) // the search is applied above, as a prefix search on customers
            ->whitelist(['none'])   // fixed order: soonest expiry first
            ->addColumn('customer', fn (PointLot $lot) => view('loyalty::points.cells.member', ['customer' => $lot->customer])->render())
            ->addColumn('amount', fn (PointLot $lot) => number_format((int) $lot->points))
            ->addColumn('expires', fn (PointLot $lot) => $lot->expires_at ? $lot->expires_at->subSecond()->format(setting('date_format')) : __('Never'))
            ->addColumn('when', fn (PointLot $lot) => $lot->expires_at ? $lot->expires_at->subSecond()->diffForHumans() : '—')
            ->rawColumns(['customer'])
            ->only(['customer', 'amount', 'expires', 'when'])
            ->toJson();
    }
}
