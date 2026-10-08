<?php

namespace Modules\Loyalty\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Modules\Customer\Models\Customer;
use Modules\Loyalty\Http\Requests\PointActivityRequest;
use Modules\Loyalty\Models\PointTransaction;
use Yajra\DataTables\DataTables;

/** Every customer's point movements in a period: incoming (earn) and the rest. */
class PointActivityTable
{
    private const ORDERABLE = ['id', 'points'];

    private const COLUMNS = ['date', 'customer', 'type', 'points', 'balance_after', 'reference', 'note'];

    public function __construct(private readonly DataTables $datatables) {}

    public function response(PointActivityRequest $request): JsonResponse
    {
        $search = $request->searchTerm();
        $query = PointTransaction::query()->with('customer:id,name,email,phone')
            ->when($request->type(), fn (Builder $q, $type) => $q->where('type', $type));
        $request->period()->apply($query);

        return $this->datatables->eloquent($query)
            // Customer name / email / phone or the award's reference (e.g. the order number), prefix.
            ->filter(fn (Builder $q) => $q->when($search, fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('reference', 'like', "{$search}%")
                ->orWhereIn('customer_id', Customer::search($search)->select('id')))))
            ->whitelist(self::ORDERABLE)
            ->addColumn('date', fn (PointTransaction $tx) => $tx->created_at->format(setting('date_format').' H:i'))
            ->addColumn('customer', fn (PointTransaction $tx) => view('loyalty::points.cells.member', ['customer' => $tx->customer])->render())
            ->addColumn('type', fn (PointTransaction $tx) => view('loyalty::points.cells.type', ['type' => $tx->type])->render())
            ->editColumn('points', fn (PointTransaction $tx) => view('loyalty::points.cells.amount', ['points' => $tx->points])->render())
            ->editColumn('balance_after', fn (PointTransaction $tx) => number_format($tx->balance_after))
            ->editColumn('reference', fn (PointTransaction $tx) => $tx->reference ?? '—')
            ->editColumn('note', fn (PointTransaction $tx) => $tx->note ?? '—')
            ->rawColumns(['customer', 'type', 'points'])
            ->only(self::COLUMNS)
            ->toJson();
    }
}
