<?php

namespace Modules\LogViewer\Database\Seeders;

use App\Support\Access\ModulePermissionSeeder;

class LogViewerDatabaseSeeder extends ModulePermissionSeeder
{
    protected function permissions(): array
    {
        return [
            'logviewer' => ['view', 'download', 'delete'],
        ];
    }
}
