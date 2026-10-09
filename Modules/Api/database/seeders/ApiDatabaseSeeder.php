<?php

namespace Modules\Api\Database\Seeders;

use App\Support\Access\ModulePermissionSeeder;

class ApiDatabaseSeeder extends ModulePermissionSeeder
{
    protected function permissions(): array
    {
        return [
            'apiclient' => self::CRUD,
        ];
    }
}
