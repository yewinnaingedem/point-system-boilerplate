<?php

namespace Modules\Api\Tests\Feature;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_lists_and_revokes_device_tokens(): void
    {
        $this->seedAccess();
        $admin = User::where('email', config('access.admin.email'))->firstOrFail();
        $cashier = $this->userWithRole(SystemRole::Cashier);
        $phone = $cashier->createToken('Counter phone');
        $cashier->createToken('Tablet');

        $this->actingAs($admin)->get('/admin/api-tokens')->assertOk()->assertSee('Counter phone')->assertSee($cashier->name);

        $this->actingAs($admin)->delete("/admin/api-tokens/{$phone->accessToken->id}")->assertRedirect();
        $this->assertSame(['Tablet'], $cashier->tokens()->pluck('name')->all());

        $this->actingAs($admin)->delete("/admin/api-tokens/user/{$cashier->id}")->assertRedirect();
        $this->assertSame(0, $cashier->tokens()->count());
    }

    public function test_manager_cannot_see_tokens(): void
    {
        $this->seedAccess();

        $this->actingAs($this->userWithRole(SystemRole::Manager))->get('/admin/api-tokens')->assertForbidden();
    }
}
