<?php

namespace Modules\Access\Database\Seeders;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * First administrator account. Credentials come from ADMIN_EMAIL / ADMIN_PASSWORD;
 * change the password after the first sign-in.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => config('access.admin.email')],
            [
                'name' => 'Administrator',
                'password' => config('access.admin.password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $user->assignRole(SystemRole::Administrator->value);
    }
}
