<?php

namespace Modules\Dashboard\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Nwidart\Modules\Contracts\RepositoryInterface as Modules;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Numbers for the dashboard overview. Each method is one small query.
 */
final class DashboardStats
{
    private const RECENT_LOGINS = 6;

    public function __construct(private readonly Modules $modules) {}

    /**
     * @return array{users: int, active_users: int, roles: int, permissions: int, modules: int}
     */
    public function totals(): array
    {
        $users = User::selectRaw('count(*) as total, sum(case when is_active then 1 else 0 end) as active')->first();

        return [
            'users' => (int) $users->total,
            'active_users' => (int) $users->active,
            'roles' => Role::count(),
            'permissions' => Permission::count(),
            'modules' => count($this->modules->allEnabled()),
        ];
    }

    /**
     * @return Collection<int, User>
     */
    public function recentLogins(): Collection
    {
        return User::query()
            ->with('roles:id,name')
            ->whereNotNull('last_login_at')
            ->latest('last_login_at')
            ->limit(self::RECENT_LOGINS)
            ->get(['id', 'name', 'email', 'avatar', 'last_login_at']);
    }

    /**
     * @return Collection<int, Role> roles with users_count
     */
    public function usersPerRole(): Collection
    {
        return Role::withCount('users')->orderByDesc('users_count')->get(['id', 'name']);
    }

    /**
     * @return list<string>
     */
    public function enabledModules(): array
    {
        return array_values(array_map(fn ($module) => $module->getName(), $this->modules->allEnabled()));
    }
}
