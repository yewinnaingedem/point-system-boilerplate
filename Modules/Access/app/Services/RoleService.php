<?php

namespace Modules\Access\Services;

use App\Enums\SystemRole;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

final class RoleService
{
    private const GUARD = 'web';

    public function __construct(private readonly ConnectionInterface $db) {}

    /**
     * @param  list<string>  $permissions
     */
    public function create(string $name, array $permissions): Role
    {
        return $this->db->transaction(function () use ($name, $permissions) {
            $role = Role::create(['name' => $name, 'guard_name' => self::GUARD]);
            $role->syncPermissions($permissions);

            return $role;
        });
    }

    /**
     * System roles keep their name; only their permissions change.
     *
     * @param  list<string>  $permissions
     */
    public function update(Role $role, string $name, array $permissions): Role
    {
        return $this->db->transaction(function () use ($role, $name, $permissions) {
            if (! SystemRole::isProtected($role->name)) {
                $role->update(['name' => $name]);
            }
            $role->syncPermissions($permissions);

            return $role;
        });
    }

    public function delete(Role $role): void
    {
        if (SystemRole::isProtected($role->name)) {
            throw ValidationException::withMessages(['role' => __('System roles cannot be deleted.')]);
        }
        if ($role->users()->exists()) {
            throw ValidationException::withMessages(['role' => __('Remove this role from its users before deleting it.')]);
        }

        $role->delete();
    }
}
