<?php

namespace Modules\Access\Tests\Feature;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->admin = User::where('email', config('access.admin.email'))->firstOrFail();
    }

    public function test_admin_can_list_search_and_create_users(): void
    {
        $this->actingAs($this->admin)->get('/admin/access/users?search=Admin')->assertOk()->assertSee($this->admin->email);

        $this->actingAs($this->admin)->post('/admin/access/users', [
            'name' => 'Cashier One',
            'email' => 'cashier1@pos.test',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
            'is_active' => '1',
            'roles' => ['Cashier'],
        ])->assertRedirect('/admin/access/users')->assertSessionHasNoErrors();

        $this->assertTrue(User::where('email', 'cashier1@pos.test')->firstOrFail()->hasRole('Cashier'));
    }

    public function test_cashier_cannot_open_user_management(): void
    {
        $this->actingAs($this->userWithRole(SystemRole::Cashier))->get('/admin/access/users')->assertForbidden();
    }

    public function test_manager_cannot_grant_administrator_role(): void
    {
        $manager = $this->userWithRole(SystemRole::Manager);

        $this->actingAs($manager)->post('/admin/access/users', [
            'name' => 'Sneaky',
            'email' => 'sneaky@pos.test',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
            'roles' => ['Administrator'],
        ])->assertSessionHasErrors('roles.0');

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@pos.test']);
    }

    public function test_manager_cannot_edit_an_administrator(): void
    {
        $manager = $this->userWithRole(SystemRole::Manager);

        $this->actingAs($manager)->get("/admin/access/users/{$this->admin->id}/edit")->assertForbidden();
        $this->actingAs($manager)->put("/admin/access/users/{$this->admin->id}", [
            'name' => 'Hijacked',
            'email' => $this->admin->email,
            'password' => 'hijacked-pass-1',
            'password_confirmation' => 'hijacked-pass-1',
            'roles' => ['Manager'],
        ])->assertForbidden();
    }

    public function test_user_cannot_delete_or_deactivate_themselves(): void
    {
        $this->actingAs($this->admin)->delete("/admin/access/users/{$this->admin->id}")->assertSessionHasErrors('user');
        $this->actingAs($this->admin)->patch("/admin/access/users/{$this->admin->id}/status")->assertSessionHasErrors('user');

        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_last_active_administrator_cannot_lose_the_role(): void
    {
        $this->actingAs($this->admin)->put("/admin/access/users/{$this->admin->id}", [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'is_active' => '1',
            'roles' => ['Manager'],
        ])->assertSessionHasErrors('user');

        $this->assertTrue($this->admin->fresh()->isAdministrator());
    }

    public function test_admin_can_deactivate_and_delete_another_user(): void
    {
        $cashier = $this->userWithRole(SystemRole::Cashier);

        $this->actingAs($this->admin)->patch("/admin/access/users/{$cashier->id}/status")->assertSessionHasNoErrors();
        $this->assertFalse($cashier->fresh()->is_active);

        $this->actingAs($this->admin)->delete("/admin/access/users/{$cashier->id}")->assertRedirect('/admin/access/users');
        $this->assertSoftDeleted($cashier);
    }
}
