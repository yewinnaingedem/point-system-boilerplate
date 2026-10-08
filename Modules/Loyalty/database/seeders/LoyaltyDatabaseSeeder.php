<?php

namespace Modules\Loyalty\Database\Seeders;

use App\Support\Access\ModulePermissionSeeder;

class LoyaltyDatabaseSeeder extends ModulePermissionSeeder
{
    protected function permissions(): array
    {
        return [
            'loyaltytier' => ['view', 'edit'],
            'point' => ['view', 'adjust'],
        ];
    }
}
