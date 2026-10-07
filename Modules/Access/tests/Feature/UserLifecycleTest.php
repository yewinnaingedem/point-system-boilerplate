<?php

namespace Modules\Access\Tests\Feature;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Access\Services\UserSessionService;
use Tests\TestCase;

class UserLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->admin = User::where('email', config('access.admin.email'))->firstOrFail();
        $this->cashier = $this->userWithRole(SystemRole::Cashier);
    }

    private function fakeSession(User $user, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id, 'user_id' => $user->id, 'ip_address' => '10.0.0.5',
            'user_agent' => 'Mozilla/5.0 Test', 'payload' => '', 'last_activity' => now()->timestamp,
        ]);
    }

    public function test_deleted_user_goes_to_deleted_tab_and_can_be_restored_with_roles(): void
    {
        $this->actingAs($this->admin)->delete("/admin/access/users/{$this->cashier->id}");

        $this->actingAs($this->admin)->get('/admin/access/users')->assertDontSee($this->cashier->email);
        $this->actingAs($this->admin)->get('/admin/access/users?status=deleted')->assertOk()->assertSee($this->cashier->email);

        // Deleted users can't sign in.
        auth()->logout();
        $this->post('/login', ['email' => $this->cashier->email, 'password' => 'password'])->assertSessionHasErrors('email');

        $this->actingAs($this->admin)->patch("/admin/access/deleted-users/{$this->cashier->id}/restore")->assertRedirect();
        $this->assertNotSoftDeleted($this->cashier);
        $this->assertTrue($this->cashier->fresh()->hasRole('Cashier'));
    }

    public function test_permanent_delete_only_works_on_deleted_users(): void
    {
        $this->actingAs($this->admin)->delete("/admin/access/deleted-users/{$this->cashier->id}")->assertNotFound();

        $this->cashier->delete();
        $this->actingAs($this->admin)->delete("/admin/access/deleted-users/{$this->cashier->id}")->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $this->cashier->id]);
        $this->assertDatabaseMissing('model_has_roles', ['model_id' => $this->cashier->id]);
    }

    public function test_deleted_tab_needs_delete_permission(): void
    {
        $this->actingAs($this->userWithRole(SystemRole::Manager))->get('/admin/access/users?status=deleted')->assertForbidden();
    }

    public function test_show_page_lists_effective_permissions_sessions_and_devices(): void
    {
        config(['session.driver' => 'database']);
        $this->fakeSession($this->cashier, 'sess-1');
        $this->cashier->createToken('Counter tablet');

        $this->actingAs($this->admin)->get("/admin/access/users/{$this->cashier->id}")
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('10.0.0.5')
            ->assertSee('Counter tablet');
    }

    public function test_admin_changes_a_password_and_the_user_is_signed_out_everywhere(): void
    {
        config(['session.driver' => 'database']);
        $this->fakeSession($this->cashier, 'sess-1');
        $this->cashier->createToken('Counter tablet');
        $oldRemember = $this->cashier->remember_token;

        $this->actingAs($this->admin)->put("/admin/access/users/{$this->cashier->id}/password", [
            'password' => 'brand-new-pass-1',
            'password_confirmation' => 'brand-new-pass-1',
        ])->assertRedirect("/admin/access/users/{$this->cashier->id}");

        $fresh = $this->cashier->fresh();
        $this->assertTrue(Hash::check('brand-new-pass-1', $fresh->password));
        $this->assertNotSame($oldRemember, $fresh->remember_token);
        $this->assertSame(0, $fresh->tokens()->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $this->cashier->id)->count());
    }

    public function test_manager_cannot_change_an_administrators_password(): void
    {
        $manager = $this->userWithRole(SystemRole::Manager);

        $this->actingAs($manager)->get("/admin/access/users/{$this->admin->id}/password")->assertForbidden();
        $this->actingAs($manager)->put("/admin/access/users/{$this->admin->id}/password", [
            'password' => 'hijack-pass-123', 'password_confirmation' => 'hijack-pass-123',
        ])->assertForbidden();
    }

    public function test_clear_sessions_ends_a_users_sessions(): void
    {
        config(['session.driver' => 'database']);
        $this->fakeSession($this->cashier, 'sess-1');
        $this->fakeSession($this->cashier, 'sess-2');

        $this->actingAs($this->admin)->delete("/admin/access/users/{$this->cashier->id}/sessions")->assertSessionHasNoErrors();
        $this->assertSame(0, DB::table('sessions')->where('user_id', $this->cashier->id)->count());
    }

    public function test_clearing_your_own_sessions_keeps_the_browser_you_are_using(): void
    {
        $this->fakeSession($this->admin, 'my-other-laptop');
        $this->fakeSession($this->admin, 'this-browser');

        $service = new UserSessionService(DB::connection(), 'database', 'sessions', 'this-browser');
        $service->clear($this->admin);

        $this->assertSame(['this-browser'], DB::table('sessions')->where('user_id', $this->admin->id)->pluck('id')->all());
    }
}
