<?php

namespace Modules\Access\Tests\Feature;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->admin = User::where('email', config('access.admin.email'))->firstOrFail();
        $this->cashier = $this->userWithRole(SystemRole::Cashier, ['last_login_at' => null]);
    }

    public function test_admin_logs_in_as_a_user_sees_their_menu_and_returns(): void
    {
        $this->actingAs($this->admin)
            ->post("/admin/access/users/{$this->cashier->id}/impersonate")
            ->assertRedirect('/admin/dashboard');

        $this->assertAuthenticatedAs($this->cashier);
        $this->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('You are signed in as')
            ->assertSee('Return to '.$this->admin->name)
            ->assertDontSee(route('admin.access.users.index'));
        $this->get('/admin/access/users')->assertForbidden();

        // "Login as" is not the cashier signing in.
        $this->assertNull($this->cashier->fresh()->last_login_at);

        $this->post('/admin/access/impersonate/leave')->assertRedirect("/admin/access/users/{$this->cashier->id}");
        $this->assertAuthenticatedAs($this->admin);
        $this->get('/admin/dashboard')->assertDontSee('You are signed in as');
    }

    public function test_switching_user_while_impersonating_still_returns_to_the_real_admin(): void
    {
        $manager = $this->userWithRole(SystemRole::Manager);
        Role::findByName('Manager')->givePermissionTo('impersonate-user');

        $this->actingAs($this->admin)->post("/admin/access/users/{$manager->id}/impersonate");
        $this->post("/admin/access/users/{$this->cashier->id}/impersonate");
        $this->assertAuthenticatedAs($this->cashier);

        $this->post('/admin/access/impersonate/leave');
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_cannot_log_in_as_yourself_or_an_inactive_user(): void
    {
        $this->actingAs($this->admin)->post("/admin/access/users/{$this->admin->id}/impersonate")->assertSessionHasErrors('user');

        $inactive = $this->userWithRole(SystemRole::Cashier, ['is_active' => false]);
        $this->actingAs($this->admin)->post("/admin/access/users/{$inactive->id}/impersonate")->assertSessionHasErrors('user');
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_permission_is_required_and_only_an_admin_can_log_in_as_an_admin(): void
    {
        $manager = $this->userWithRole(SystemRole::Manager);

        $this->actingAs($manager)->post("/admin/access/users/{$this->cashier->id}/impersonate")->assertForbidden();

        Role::findByName('Manager')->givePermissionTo('impersonate-user');
        $this->actingAs($manager)->post("/admin/access/users/{$this->admin->id}/impersonate")->assertForbidden();
        $this->assertAuthenticatedAs($manager);
    }

    public function test_login_as_button_is_not_offered_for_yourself_or_inactive_users(): void
    {
        $inactive = $this->userWithRole(SystemRole::Cashier, ['is_active' => false]);

        $rows = $this->dataTableText($this->actingAs($this->admin)->dataTable(route('admin.access.users.data'), ['id', 'user', 'phone', 'roles', 'status', 'last_seen', 'actions']));

        $this->assertStringContainsString(route('admin.access.users.impersonate', $this->cashier), $rows);
        $this->assertStringNotContainsString(route('admin.access.users.impersonate', $this->admin), $rows);
        $this->assertStringNotContainsString(route('admin.access.users.impersonate', $inactive), $rows);
    }

    public function test_leave_without_impersonation_does_nothing(): void
    {
        $this->actingAs($this->cashier)->post('/admin/access/impersonate/leave')->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($this->cashier);
    }
}
