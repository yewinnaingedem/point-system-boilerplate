<?php

namespace Modules\Merchant\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Modules\Merchant\Http\Requests\MerchantTableRequest;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantBranch;
use Yajra\DataTables\DataTables;

/** One merchant's branches. The code cell is rendered only for users allowed to see codes. */
class BranchesTable
{
    private const ORDERABLE = ['name', 'code_changed_at'];

    private const COLUMNS = ['branch', 'code', 'status', 'actions'];

    public function __construct(private readonly DataTables $datatables) {}

    public function response(MerchantTableRequest $request, Merchant $merchant): JsonResponse
    {
        $search = $request->searchTerm();
        $query = $merchant->branches()->getQuery()->withCount('redemptions');

        return $this->datatables->eloquent($query)
            ->filter(fn (Builder $q) => $q->when($search, fn (Builder $q) => $q->where('name', 'like', "{$search}%")))
            ->whitelist(self::ORDERABLE)
            ->addColumn('branch', fn (MerchantBranch $branch) => view('merchant::cells.branch', ['branch' => $branch])->render())
            ->addColumn('code', fn (MerchantBranch $branch) => view('merchant::cells.code', ['branch' => $branch])->render())
            ->addColumn('status', fn (MerchantBranch $branch) => view('merchant::cells.active', ['active' => $branch->is_active])->render())
            ->addColumn('actions', fn (MerchantBranch $branch) => view('merchant::cells.branch-actions', ['branch' => $branch])->render())
            ->rawColumns(['branch', 'code', 'status', 'actions'])
            ->only(self::COLUMNS)
            ->toJson();
    }
}
