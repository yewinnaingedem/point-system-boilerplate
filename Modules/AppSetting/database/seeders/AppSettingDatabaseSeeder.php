<?php

namespace Modules\AppSetting\Database\Seeders;

use App\Support\Access\ModulePermissionSeeder;

class AppSettingDatabaseSeeder extends ModulePermissionSeeder
{
    protected function permissions(): array
    {
        return [
            'appsetting' => ['view', 'edit'],
        ];
    }
}
