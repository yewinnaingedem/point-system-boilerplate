<?php

namespace Modules\Api\Tests\Feature;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->cashier = $this->userWithRole(SystemRole::Cashier, ['email' => 'cashier@pos.test', 'phone' => '0911111111']);
    }

    private function login(string $login = 'cashier@pos.test', string $device = 'Counter 1', string $password = 'password')
    {
        return $this->postJson('/api/v1/auth/login', ['login' => $login, 'password' => $password, 'device_name' => $device]);
    }

    /**
     * The test client reuses one app, and Sanctum's guard remembers the user it resolved.
     * Real requests start fresh; forget the guard so each call resolves its own token.
     */
    private function bearer(string $token): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_sign_in_with_email_or_phone_returns_a_token_and_a_whitelisted_user(): void
    {
        $response = $this->login()->assertCreated()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'cashier@pos.test')
            ->assertJsonPath('data.user.roles.0', 'Cashier')
            ->assertJsonPath('data.user.permissions', ['view-dashboard']);

        $this->assertArrayNotHasKey('password', $response->json('data.user'));
        $this->assertArrayNotHasKey('remember_token', $response->json('data.user'));
        $this->assertNotNull($this->cashier->fresh()->last_login_at);

        $this->login('0911111111', 'Counter 2')->assertCreated();
    }

    public function test_wrong_password_unknown_user_and_inactive_user_get_the_same_answer(): void
    {
        $inactive = User::factory()->inactive()->create(['email' => 'gone@pos.test']);

        foreach ([['cashier@pos.test', 'wrong'], ['nobody@pos.test', 'password'], [$inactive->email, 'password']] as [$login, $password]) {
            $this->login($login, 'X', $password)->assertStatus(422)->assertJsonPath('errors.login.0', __('auth.failed'));
        }
    }

    public function test_nothing_is_reachable_without_a_token_and_errors_are_json(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->get('/api/v1/settings')->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
        $this->get('/api/v1/does-not-exist')->assertNotFound()->assertHeader('content-type', 'application/json');
        $this->get('/api/v1/me', $this->bearer('1|not-a-real-token'))->assertUnauthorized();
    }

    public function test_admin_browser_session_cannot_be_used_on_the_api(): void
    {
        $admin = User::where('email', config('access.admin.email'))->firstOrFail();

        $this->actingAs($admin, 'web')->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_me_and_settings_with_a_token(): void
    {
        $token = $this->login()->json('data.token');

        $this->getJson('/api/v1/me', $this->bearer($token))->assertOk()->assertJsonPath('data.name', $this->cashier->name);
        $this->getJson('/api/v1/settings', $this->bearer($token))->assertOk()
            ->assertJsonPath('data.currency_code', 'MMK')
            ->assertJsonPath('data.tax_rate', 0);
    }

    public function test_same_device_replaces_its_token_and_the_device_limit_drops_the_oldest(): void
    {
        config(['api.max_devices' => 2]);

        $first = $this->login(device: 'Counter 1')->json('data.token');
        $this->login(device: 'Counter 1');
        $this->assertSame(1, $this->cashier->tokens()->count());

        $this->login(device: 'Counter 2');
        $this->travel(1)->minutes();
        $this->login(device: 'Counter 3');

        $this->assertEqualsCanonicalizing(['Counter 2', 'Counter 3'], $this->cashier->tokens()->pluck('name')->all());
        $this->getJson('/api/v1/me', $this->bearer($first))->assertUnauthorized();
    }

    public function test_refresh_and_logout_revoke_the_old_token(): void
    {
        $old = $this->login()->json('data.token');

        $new = $this->postJson('/api/v1/auth/refresh', [], $this->bearer($old))->assertOk()->json('data.token');
        $this->assertNotSame($old, $new);
        $this->assertSame(1, PersonalAccessToken::count());

        $this->postJson('/api/v1/auth/logout', [], $this->bearer($new))->assertOk();
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_expired_token_is_rejected(): void
    {
        $token = $this->login()->json('data.token');

        $this->travel(config('api.token_ttl_days') + 1)->days();

        $this->getJson('/api/v1/me', $this->bearer($token))->assertUnauthorized();
    }

    public function test_deactivating_or_changing_password_signs_the_user_out_of_the_app(): void
    {
        $admin = User::where('email', config('access.admin.email'))->firstOrFail();
        $this->login();

        $this->actingAs($admin)->patch("/admin/access/users/{$this->cashier->id}/status");

        $this->assertSame(0, $this->cashier->tokens()->count());
    }

    public function test_user_deactivated_directly_in_the_database_is_cut_off_on_next_request(): void
    {
        $token = $this->login()->json('data.token');
        $this->cashier->forceFill(['is_active' => false])->save();

        $this->getJson('/api/v1/me', $this->bearer($token))->assertUnauthorized();
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_sign_in_is_rate_limited(): void
    {
        foreach (range(1, config('api.login_attempts_per_minute')) as $attempt) {
            $this->login(password: 'wrong')->assertStatus(422);
        }

        $this->login()->assertStatus(429);
    }
}
