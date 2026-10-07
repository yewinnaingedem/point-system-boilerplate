<?php

namespace Modules\Access\Database\Seeders;

use App\Enums\SystemRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the system roles. Run after every module's permission seeder.
 *
 * Permissions are granted only when a role is first created, so re-seeding never
 * undoes what an administrator changed on the roles screen.
 */
class RoleSeeder extends Seeder
{
    private const GUARD = 'web';

    /**
     * Starting permissions per role. Administrator needs none: it passes every check.
     *
     * @var array<string, list<string>>
     */
    private const GRANTS = [
        'Manager' => ['view-dashboard', 'view-user', 'create-user', 'edit-user', 'view-appsetting'],
        'Cashier' => ['view-dashboard'],
    ];

    public function run(): void
    {
        foreach (SystemRole::cases() as $systemRole) {
            $role = Role::firstOrCreate(['name' => $systemRole->value, 'guard_name' => self::GUARD]);

            if ($role->wasRecentlyCreated) {
                $role->syncPermissions($this->existing(self::GRANTS[$systemRole->value] ?? []));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function existing(array $names): array
    {
        return Permission::whereIn('name', $names)->pluck('name')->all();
    }
}
