<?php

namespace Modules\Loyalty\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Modules\Customer\Models\Customer;
use Modules\Loyalty\Http\Requests\PointTableRequest;
use Modules\Loyalty\Models\PointAccount;
use Yajra\DataTables\DataTables;

/** Customers' balances for the Customer Points screen. */
class PointAccountsTable
{
    private const ORDERABLE = ['balance', 'updated_at'];

    private const COLUMNS = ['member', 'balance', 'updated', 'actions'];

    public function __construct(private readonly DataTables $datatables) {}

    public function response(PointTableRequest $request): JsonResponse
    {
        $search = $request->searchTerm();
        $query = PointAccount::query()->with('customer');

        return $this->datatables->eloquent($query)
            ->filter(fn (Builder $q) => $q->when($search, fn (Builder $q) => $q->whereIn('customer_id', Customer::search($search)->select('id'))))
            ->whitelist(self::ORDERABLE)
            ->addColumn('member', fn (PointAccount $account) => view('loyalty::points.cells.member', ['customer' => $account->customer])->render())
            // editColumn, not addColumn: yajra blacklists added names, which would silently stop sorting.
            ->editColumn('balance', fn (PointAccount $account) => number_format($account->balance))
            ->addColumn('updated', fn (PointAccount $account) => $account->updated_at?->diffForHumans())
            ->addColumn('actions', fn (PointAccount $account) => view('loyalty::points.cells.actions', ['account' => $account])->render())
            ->rawColumns(['member', 'actions'])
            ->only(self::COLUMNS)
            ->toJson();
    }
}
