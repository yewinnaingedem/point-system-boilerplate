<?php

namespace Modules\Dashboard\Database\Seeders;

use App\Support\Access\ModulePermissionSeeder;

class DashboardDatabaseSeeder extends ModulePermissionSeeder
{
    protected function permissions(): array
    {
        return [
            'dashboard' => ['view'],
        ];
    }
}
