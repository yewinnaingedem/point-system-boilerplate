<?php

namespace Modules\Api\Tests\Feature;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenAdminTest extends TestCase
{
    use RefreshDatabase;

    private const COLUMNS = ['owner', 'device', 'signed_in', 'last_used', 'expires', 'actions'];

    public function test_admin_lists_and_revokes_device_tokens(): void
    {
        $this->seedAccess();
        $admin = User::where('email', config('access.admin.email'))->firstOrFail();
        $cashier = $this->userWithRole(SystemRole::Cashier);
        $phone = $cashier->createToken('Counter phone');
        $cashier->createToken('Tablet');

        $this->actingAs($admin)->get('/admin/api-tokens')->assertOk();
        $response = $this->dataTable(route('admin.api-tokens.data'), self::COLUMNS);
        $this->assertStringContainsString('Counter phone', $this->dataTableText($response));
        $this->assertStringContainsString($cashier->name, $this->dataTableText($response));
        $this->assertSame(self::COLUMNS, array_keys($response->json('data.0')), 'no token hash or abilities are sent');
        $this->assertSame(1, $this->dataTable(route('admin.api-tokens.data'), self::COLUMNS, search: 'Tab')->json('recordsFiltered'));

        $this->actingAs($admin)->delete("/admin/api-tokens/{$phone->accessToken->id}")->assertRedirect();
        $this->assertSame(['Tablet'], $cashier->tokens()->pluck('name')->all());

        $this->actingAs($admin)->delete("/admin/api-tokens/user/{$cashier->id}")->assertRedirect();
        $this->assertSame(0, $cashier->tokens()->count());
    }

    public function test_manager_cannot_see_tokens(): void
    {
        $this->seedAccess();

        $manager = $this->userWithRole(SystemRole::Manager);

        $this->actingAs($manager)->get('/admin/api-tokens')->assertForbidden();
        $this->actingAs($manager)->dataTable(route('admin.api-tokens.data'), self::COLUMNS)->assertForbidden();
    }
}
