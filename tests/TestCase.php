<?php

namespace Tests;

use App\Enums\SystemRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

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

    /**
     * Request one page of a server-side DataTables endpoint the way the browser does.
     *
     * @param  list<string>  $columns  the columns' `data` keys, in table order
     * @param  array<string, mixed>  $params  extra filters and overrides (status, role, length, order ...)
     */
    protected function dataTable(string $url, array $columns, array $params = [], ?string $search = null): TestResponse
    {
        $query = [
            'draw' => 1,
            'start' => 0,
            'length' => 15,
            'search' => ['value' => $search ?? ''],
            'columns' => array_map(fn (string $data) => ['data' => $data, 'name' => '', 'searchable' => 'false', 'orderable' => 'false'], $columns),
            ...$params,
        ];

        return $this->getJson($url.'?'.http_build_query($query), ['X-Requested-With' => 'XMLHttpRequest']);
    }

    /** All cells of a DataTables JSON response joined into one string, for assertStringContainsString. */
    protected function dataTableText(TestResponse $response): string
    {
        return collect($response->assertOk()->json('data'))->flatten()->implode(' ');
    }
}
