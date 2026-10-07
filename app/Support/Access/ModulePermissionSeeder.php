<?php

namespace App\Support\Access;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Base seeder every module extends to register its permissions.
 *
 * Permissions are named "<action>-<resource>" (view-user, edit-appsetting, ...). The
 * resource part is what the role screen groups by. Seeding is idempotent, so
 * `php artisan module:seed <Module>` can be re-run after adding a permission.
 */
abstract class ModulePermissionSeeder extends Seeder
{
    public const CRUD = ['view', 'create', 'edit', 'delete'];

    private const GUARD = 'web';

    /**
     * Resources this module owns, mapped to their actions.
     *
     * @return array<string, list<string>>
     */
    abstract protected function permissions(): array;

    public function run(): void
    {
        foreach ($this->permissions() as $resource => $actions) {
            foreach ($actions as $action) {
                Permission::findOrCreate("{$action}-{$resource}", self::GUARD);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
