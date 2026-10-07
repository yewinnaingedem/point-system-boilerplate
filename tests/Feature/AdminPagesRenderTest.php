<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Every admin screen renders for an administrator, in case a view breaks.
 */
class AdminPagesRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_admin_page_renders(): void
    {
        $this->seedAccess();
        $admin = User::where('email', config('access.admin.email'))->firstOrFail();
        $other = $this->userWithRole('Cashier');
        $role = Role::findByName('Manager');

        $pages = [
            '/admin/dashboard',
            '/admin/profile',
            '/admin/access/users',
            '/admin/access/users/create',
            '/admin/access/users?status=deleted',
            "/admin/access/users/{$other->id}",
            "/admin/access/users/{$other->id}/password",
            "/admin/access/users/{$other->id}/edit",
            '/admin/access/roles',
            '/admin/access/roles/create',
            "/admin/access/roles/{$role->id}/edit",
            '/admin/access/permissions',
            '/admin/settings/general',
            '/admin/settings/pos',
            '/admin/settings/localization',
            '/admin/settings/appearance',
        ];

        foreach ($pages as $page) {
            $this->actingAs($admin)->get($page)->assertOk();
        }
    }
}
