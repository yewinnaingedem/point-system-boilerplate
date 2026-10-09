<?php

namespace Modules\Merchant\Database\Seeders;

use App\Support\Access\ModulePermissionSeeder;

class MerchantDatabaseSeeder extends ModulePermissionSeeder
{
    protected function permissions(): array
    {
        return [
            // merchants and their branches
            'merchant' => self::CRUD,
            // see and regenerate branch codes (handed to shops; keep this narrow)
            'merchantcode' => ['view', 'edit'],
        ];
    }
}
