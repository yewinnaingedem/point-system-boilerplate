<?php

return [
    'name' => 'Access',

    // First administrator created by `php artisan db:seed` (AdminUserSeeder).
    'admin' => [
        'email' => env('ADMIN_EMAIL', 'admin@pos.test'),
        'password' => env('ADMIN_PASSWORD', 'password'),
    ],

    'users_per_page' => 15,
];
