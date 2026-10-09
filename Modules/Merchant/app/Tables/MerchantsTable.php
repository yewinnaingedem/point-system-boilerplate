<?php

namespace Modules\Merchant\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Modules\Merchant\Http\Requests\MerchantTableRequest;
use Modules\Merchant\Models\Merchant;
use Yajra\DataTables\DataTables;

class MerchantsTable
{
    private const ORDERABLE = ['id', 'name', 'branches_count'];

    private const COLUMNS = ['id', 'merchant', 'branches', 'rate', 'status', 'actions'];

    public function __construct(private readonly DataTables $datatables) {}

    public function response(MerchantTableRequest $request): JsonResponse
    {
        $search = $request->searchTerm();
        $status = $request->validated('status');

        $query = Merchant::query()
            ->select('merchants.*')
            ->withCount('branches')
            ->when($status, fn (Builder $q) => $q->where('is_active', $status === 'active'));

        return $this->datatables->eloquent($query)
            ->filter(fn (Builder $q) => $q->search($search))
            ->whitelist(self::ORDERABLE)
            ->addColumn('merchant', fn (Merchant $merchant) => view('merchant::cells.merchant', ['merchant' => $merchant])->render())
            ->addColumn('branches', fn (Merchant $merchant) => number_format($merchant->branches_count))
            ->addColumn('rate', fn (Merchant $merchant) => money($merchant->settlement_rate, 2))
            ->addColumn('status', fn (Merchant $merchant) => view('merchant::cells.active', ['active' => $merchant->is_active])->render())
            ->addColumn('actions', fn (Merchant $merchant) => view('merchant::cells.merchant-actions', ['merchant' => $merchant])->render())
            ->rawColumns(['merchant', 'status', 'actions'])
            ->only(self::COLUMNS)
            ->toJson();
    }
}
