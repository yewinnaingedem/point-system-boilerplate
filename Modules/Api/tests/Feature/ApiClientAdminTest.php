<?php

namespace Modules\Api\Tests\Feature;

use App\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Api\Models\ApiClient;
use Tests\TestCase;

class ApiClientAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_a_client_and_sees_the_secret_once(): void
    {
        $this->seedAccess();
        $admin = $this->userWithRole(SystemRole::Administrator);

        $this->actingAs($admin)->post(route('admin.api-clients.store'), ['name' => 'Partner site', 'is_active' => '1'])
            ->assertRedirect(route('admin.api-clients.index'));
        $client = ApiClient::query()->sole();
        $this->assertSame(64, strlen($client->secret));
        $this->assertStringStartsWith('pos', $client->app_id);
        $this->assertNotSame($client->secret, $client->getRawOriginal('secret')); // stored encrypted

        $this->get(route('admin.api-clients.index'))->assertOk()->assertSee($client->secret);
        $this->get(route('admin.api-clients.index'))->assertOk()->assertSee($client->app_id)->assertDontSee($client->secret);
    }

    public function test_rotate_replaces_the_secret_and_edit_keeps_it(): void
    {
        $this->seedAccess();
        $admin = $this->userWithRole(SystemRole::Administrator);
        $this->actingAs($admin)->post(route('admin.api-clients.store'), ['name' => 'Partner site', 'is_active' => '1']);
        $client = ApiClient::query()->sole();
        $old = $client->secret;

        $this->post(route('admin.api-clients.rotate', $client))->assertRedirect();
        $this->assertNotSame($old, $client->fresh()->secret);
        $this->assertNotNull($client->fresh()->secret_rotated_at);

        $secret = $client->fresh()->secret;
        $this->put(route('admin.api-clients.update', $client), ['name' => 'Renamed', 'is_active' => '0'])->assertRedirect();
        $this->assertSame(['Renamed', false, $secret], [$client->fresh()->name, $client->fresh()->is_active, $client->fresh()->secret]);

        $this->delete(route('admin.api-clients.destroy', $client))->assertRedirect();
        $this->assertSame(0, ApiClient::query()->count());
    }

    public function test_the_old_api_tokens_screen_is_gone(): void
    {
        $this->seedAccess();
        $this->actingAs($this->userWithRole(SystemRole::Administrator))->get('/admin/api-tokens')->assertNotFound();
        $this->get(route('admin.api-clients.index'))->assertOk()->assertDontSee('API Tokens');
    }

    public function test_role_without_permission_gets_403(): void
    {
        $this->seedAccess();
        $manager = $this->userWithRole(SystemRole::Manager);

        $this->actingAs($manager)->get(route('admin.api-clients.index'))->assertForbidden();
        $this->post(route('admin.api-clients.store'), ['name' => 'x'])->assertForbidden();
    }
}
