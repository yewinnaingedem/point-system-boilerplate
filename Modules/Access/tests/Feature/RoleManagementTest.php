<?php

namespace Modules\Access\Tests\Feature;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->admin = User::where('email', config('access.admin.email'))->firstOrFail();
    }

    public function test_seeding_twice_does_not_duplicate_or_reset_permissions(): void
    {
        Role::findByName('Cashier')->givePermissionTo('view-user');

        $this->seedAccess();

        $this->assertTrue(Role::findByName('Cashier')->hasPermissionTo('view-user'));
        $this->assertSame(1, Role::where('name', 'Cashier')->count());
    }

    public function test_admin_can_create_a_role_and_grant_permissions(): void
    {
        $this->actingAs($this->admin)->get('/admin/access/roles/create')->assertOk()->assertSee('App Settings');

        $this->actingAs($this->admin)->post('/admin/access/roles', [
            'name' => 'Stock Keeper',
            'permissions' => ['view-dashboard', 'view-user'],
        ])->assertRedirect('/admin/access/roles');

        $role = Role::findByName('Stock Keeper');
        $this->assertEqualsCanonicalizing(['view-dashboard', 'view-user'], $role->permissions->pluck('name')->all());
    }

    public function test_unknown_permission_is_rejected(): void
    {
        $this->actingAs($this->admin)->post('/admin/access/roles', [
            'name' => 'Bad',
            'permissions' => ['launch-rockets'],
        ])->assertSessionHasErrors('permissions.0');
    }

    public function test_system_roles_cannot_be_deleted_or_renamed(): void
    {
        $cashier = Role::findByName('Cashier');

        $this->actingAs($this->admin)->delete("/admin/access/roles/{$cashier->id}")->assertSessionHasErrors('role');
        $this->actingAs($this->admin)->put("/admin/access/roles/{$cashier->id}", ['name' => 'Renamed', 'permissions' => []]);

        $this->assertSame('Cashier', $cashier->fresh()->name);
    }

    public function test_role_in_use_cannot_be_deleted(): void
    {
        $role = Role::create(['name' => 'Temp', 'guard_name' => 'web']);
        $this->userWithRole('Temp');

        $this->actingAs($this->admin)->delete("/admin/access/roles/{$role->id}")->assertSessionHasErrors('role');
        $this->assertModelExists($role);
    }

    public function test_permissions_page_requires_permission(): void
    {
        $this->actingAs($this->admin)->get('/admin/access/permissions')->assertOk()->assertSee('view-user');
        $this->actingAs($this->userWithRole(SystemRole::Manager))->get('/admin/access/permissions')->assertForbidden();
    }
}
