<?php

namespace Modules\Access\Http\Controllers;

use App\Enums\SystemRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Access\Http\Requests\RoleRequest;
use Modules\Access\Services\RoleService;
use Modules\Access\Support\PermissionMatrix;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roles,
        private readonly PermissionMatrix $matrix,
    ) {}

    public function index(): View
    {
        return view('access::roles.index', [
            'roles' => Role::withCount(['users', 'permissions'])->orderBy('name')->get(),
            'totalPermissions' => Permission::count(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Role);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $role = $this->roles->create($request->validated('name'), $request->validated('permissions', []));

        return redirect()->route('admin.access.roles.index')
            ->with('success', __('Role :name created.', ['name' => $role->name]));
    }

    public function edit(Role $role): View
    {
        return $this->form($role->load('permissions'));
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $this->roles->update($role, $request->validated('name'), $request->validated('permissions', []));

        return redirect()->route('admin.access.roles.index')
            ->with('success', __('Role :name updated.', ['name' => $role->name]));
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->roles->delete($role);

        return redirect()->route('admin.access.roles.index')
            ->with('success', __('Role :name deleted.', ['name' => $role->name]));
    }

    private function form(Role $role): View
    {
        return view('access::roles.form', [
            'role' => $role,
            'groups' => $this->matrix->group(Permission::orderBy('name')->get()),
            'granted' => $role->exists ? $role->permissions->pluck('name')->all() : [],
            'isSystem' => $role->exists && SystemRole::isProtected($role->name),
            'isAdministrator' => $role->name === SystemRole::Administrator->value,
        ]);
    }
}
