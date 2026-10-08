<?php

namespace Modules\Access\Tables;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Modules\Access\Http\Requests\UserTableRequest;
use Yajra\DataTables\DataTables;

/**
 * Server-side data for the users list (yajra DataTables).
 *
 * HTML cells are rendered from Blade partials so permission checks (@can, policies) stay in
 * Blade, exactly as on a normal page. Only the listed columns are sent to the browser.
 */
class UsersTable
{
    /** Columns the browser may sort by; anything else it asks for is ignored. */
    private const ORDERABLE = ['id', 'name', 'last_login_at', 'deleted_at'];

    private const COLUMNS = ['id', 'user', 'phone', 'roles', 'status', 'last_seen', 'actions'];

    public function __construct(private readonly DataTables $datatables) {}

    public function response(UserTableRequest $request): JsonResponse
    {
        $status = $request->status();
        $deleted = $status === 'deleted';
        $search = $request->searchTerm();

        $query = User::query()
            ->select('users.*')
            ->with('roles:id,name')
            ->when($request->role(), fn (Builder $q, string $role) => $q->role($role))
            ->when($status === 'active', fn (Builder $q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn (Builder $q) => $q->where('is_active', false))
            ->when($deleted, fn (Builder $q) => $q->onlyTrashed());

        return $this->datatables->eloquent($query)
            // Replaces yajra's "%term%" search on every column with the indexed prefix search.
            ->filter(fn (Builder $q) => $q->search($search))
            ->whitelist(self::ORDERABLE)
            ->addColumn('user', fn (User $user) => view('access::users.cells.user', ['user' => $user, 'deleted' => $deleted])->render())
            ->editColumn('phone', fn (User $user) => $user->phone ?: '—')
            ->addColumn('roles', fn (User $user) => view('access::users.cells.roles', ['user' => $user])->render())
            ->addColumn('status', fn (User $user) => view('access::users.cells.status', ['user' => $user, 'deleted' => $deleted])->render())
            ->addColumn('last_seen', fn (User $user) => $deleted
                ? $user->deleted_at->diffForHumans()
                : ($user->last_login_at?->diffForHumans() ?? __('Never')))
            ->addColumn('actions', fn (User $user) => view('access::users.cells.actions', ['user' => $user, 'deleted' => $deleted])->render())
            ->rawColumns(['user', 'roles', 'status', 'actions'])
            ->only(self::COLUMNS)
            ->toJson();
    }
}
