<?php

namespace Modules\Access\Database\Seeders;

use App\Support\Access\ModulePermissionSeeder;

class AccessDatabaseSeeder extends ModulePermissionSeeder
{
    protected function permissions(): array
    {
        return [
            'user' => [...self::CRUD, 'impersonate'],
            'role' => self::CRUD,
            'permission' => ['view'],
        ];
    }
}
