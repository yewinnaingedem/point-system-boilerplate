<?php

namespace Modules\Loyalty\Tables;

use Illuminate\Http\JsonResponse;
use Modules\Customer\Models\Customer;
use Modules\Loyalty\Models\PointTransaction;
use Yajra\DataTables\DataTables;

/** One customer's points history, newest first. */
class PointTransactionsTable
{
    private const ORDERABLE = ['id', 'points'];

    private const COLUMNS = ['date', 'type', 'points', 'balance_after', 'note', 'by'];

    public function __construct(private readonly DataTables $datatables) {}

    public function response(Customer $customer): JsonResponse
    {
        $query = PointTransaction::query()->with('creator:id,name')->where('customer_id', $customer->id);

        return $this->datatables->eloquent($query)
            ->filter(fn () => null)
            ->whitelist(self::ORDERABLE)
            ->addColumn('date', fn (PointTransaction $tx) => $tx->created_at->format(setting('date_format').' H:i'))
            ->addColumn('type', fn (PointTransaction $tx) => view('loyalty::points.cells.type', ['type' => $tx->type])->render())
            ->editColumn('points', fn (PointTransaction $tx) => ($tx->points > 0 ? '+' : '').number_format($tx->points))
            ->editColumn('balance_after', fn (PointTransaction $tx) => number_format($tx->balance_after))
            ->editColumn('note', fn (PointTransaction $tx) => $tx->note ?? '—')
            ->addColumn('by', fn (PointTransaction $tx) => $tx->creator?->name ?? __('System'))
            ->rawColumns(['type'])
            ->only(self::COLUMNS)
            ->toJson();
    }
}
