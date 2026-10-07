<?php

namespace Tests;

use App\Enums\SystemRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seed permissions, system roles and the first administrator.
     */
    protected function seedAccess(): void
    {
        $this->seed(DatabaseSeeder::class);
    }

    protected function userWithRole(SystemRole|string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role instanceof SystemRole ? $role->value : $role);

        return $user;
    }
}
