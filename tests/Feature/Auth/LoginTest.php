<?php

namespace Tests\Feature\Auth;

use App\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Sign in to start your session');
    }

    public function test_active_user_can_sign_in_and_last_login_is_recorded(): void
    {
        $user = $this->userWithRole(SystemRole::Cashier);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/admin/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = $this->userWithRole(SystemRole::Cashier);

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_sign_in(): void
    {
        $user = $this->userWithRole(SystemRole::Cashier, ['is_active' => false]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_deactivated_mid_session_is_signed_out(): void
    {
        $user = $this->userWithRole(SystemRole::Cashier);
        $this->actingAs($user);
        $user->update(['is_active' => false]);

        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_user_can_sign_out(): void
    {
        $this->actingAs($this->userWithRole(SystemRole::Cashier))->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_user_can_change_own_password(): void
    {
        $user = $this->userWithRole(SystemRole::Cashier);

        $this->actingAs($user)->put('/admin/profile/password', [
            'current_password' => 'password',
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(\Hash::check('new-secret-123', $user->fresh()->password));
    }
}
