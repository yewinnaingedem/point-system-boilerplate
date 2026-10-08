<?php

namespace Modules\Loyalty\Tables;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Modules\Customer\Models\Customer;
use Modules\Loyalty\Enums\PointTransactionType;
use Modules\Loyalty\Http\Requests\PointActivityRequest;
use Modules\Loyalty\Models\PointTransaction;
use Yajra\DataTables\DataTables;

/** Customers who earned points in a period: one row each, with their total. */
class PointEarnersTable
{
    private const ORDERABLE = ['earned', 'awards', 'last_at'];

    private const COLUMNS = ['customer', 'earned_points', 'award_count', 'last_award', 'actions'];

    public function __construct(private readonly DataTables $datatables) {}

    public function response(PointActivityRequest $request): JsonResponse
    {
        $search = $request->searchTerm();
        $query = PointTransaction::query()
            ->selectRaw('customer_id, sum(points) as earned, count(*) as awards, max(created_at) as last_at')
            ->with('customer:id,name,email,phone')
            ->where('type', PointTransactionType::Earn)
            ->groupBy('customer_id');
        $request->period()->apply($query);

        return $this->datatables->eloquent($query)
            ->filter(fn (Builder $q) => $q->when($search, fn (Builder $q) => $q->whereIn('customer_id', Customer::search($search)->select('id'))))
            ->whitelist(self::ORDERABLE)
            ->addColumn('customer', fn (PointTransaction $row) => view('loyalty::points.cells.member', ['customer' => $row->customer])->render())
            ->addColumn('earned_points', fn (PointTransaction $row) => '+'.number_format((int) $row->earned))
            ->addColumn('award_count', fn (PointTransaction $row) => number_format((int) $row->awards))
            ->addColumn('last_award', fn (PointTransaction $row) => CarbonImmutable::parse($row->last_at)->format(setting('date_format').' H:i'))
            ->addColumn('actions', fn (PointTransaction $row) => view('loyalty::points.cells.actions', ['account' => $row])->render())
            ->rawColumns(['customer', 'actions'])
            ->only(self::COLUMNS)
            ->toJson();
    }
}
