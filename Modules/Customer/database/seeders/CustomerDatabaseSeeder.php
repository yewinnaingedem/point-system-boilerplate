<?php

namespace Modules\Customer\Database\Seeders;

use App\Support\Access\ModulePermissionSeeder;

class CustomerDatabaseSeeder extends ModulePermissionSeeder
{
    protected function permissions(): array
    {
        return [
            // customers come from the partner project's sign-in; staff can view and (de)activate
            'customer' => ['view', 'edit'],
        ];
    }
}
