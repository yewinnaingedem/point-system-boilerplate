<?php

namespace Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Access\Http\Requests\UserRequest;
use Modules\Access\Services\UserService;
use Modules\Access\Services\UserSessionService;
use Modules\Access\Support\PermissionMatrix;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /** Tabs on the users list. "deleted" lists soft-deleted users (needs delete-user). */
    private const STATUSES = ['active', 'inactive', 'deleted'];

    public function __construct(private readonly UserService $users) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'exists:roles,name'],
            'status' => ['nullable', 'in:'.implode(',', self::STATUSES)],
        ]);
        $status = $filters['status'] ?? null;

        abort_if($status === 'deleted' && ! $request->user()->can('delete-user'), 403);

        $users = User::query()
            ->with('roles:id,name')
            ->search($filters['search'] ?? null)
            ->when($filters['role'] ?? null, fn ($q, string $role) => $q->role($role))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($status === 'deleted', fn ($q) => $q->onlyTrashed())
            ->latest('id')
            ->paginate(config('access.users_per_page'))
            ->withQueryString();

        return view('access::users.index', [
            'users' => $users,
            'status' => $status,
            'tabCounts' => [
                'all' => User::count(),
                'active' => User::where('is_active', true)->count(),
                'inactive' => User::where('is_active', false)->count(),
                'deleted' => User::onlyTrashed()->count(),
            ],
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function show(User $user, UserSessionService $sessions, PermissionMatrix $matrix): View
    {
        $user->load('roles');

        return view('access::users.show', [
            'user' => $user,
            'permissionGroups' => $matrix->group(
                $user->isAdministrator() ? Permission::orderBy('name')->get() : $user->getAllPermissions()->sortBy('name')->values(),
            ),
            'sessionsSupported' => $sessions->supported(),
            'sessions' => $sessions->forUser($user),
            'tokens' => $user->tokens()->latest('id')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('access::users.form', [
            'user' => new User(['is_active' => true]),
            'roles' => $this->users->assignableRoles($request->user()),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = $this->users->create($request->validated(), $request->file('avatar'));

        return redirect()->route('admin.access.users.index')
            ->with('success', __('User :name created.', ['name' => $user->name]));
    }

    public function edit(Request $request, User $user): View
    {
        Gate::authorize('update', $user);

        return view('access::users.form', [
            'user' => $user->load('roles'),
            'roles' => $this->users->assignableRoles($request->user()),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->users->update($user, $request->validated(), $request->file('avatar'));

        return redirect()->route('admin.access.users.index')
            ->with('success', __('User :name updated.', ['name' => $user->name]));
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $this->users->toggleStatus($user, $request->user());

        return back()->with('success', $user->is_active
            ? __(':name can sign in again.', ['name' => $user->name])
            : __(':name has been deactivated.', ['name' => $user->name]));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $this->users->delete($user, $request->user());

        return redirect()->route('admin.access.users.index')
            ->with('success', __('User :name moved to Deleted users. You can restore them from there.', ['name' => $user->name]));
    }
}
