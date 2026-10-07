<?php

return [
    'name' => 'AppSetting',

    // Disk that stores uploaded logo/favicon. Must be public (run `php artisan storage:link`).
    'disk' => env('APPSETTING_DISK', 'public'),
];
