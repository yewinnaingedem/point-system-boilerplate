<?php

namespace Modules\Dashboard\Tests\Feature;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
    }

    public function test_admin_sees_overview_and_every_menu_link(): void
    {
        $admin = User::where('email', config('access.admin.email'))->firstOrFail();

        $this->actingAs($admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Total users')
            ->assertSee('Access Management')
            ->assertSee(route('admin.access.users.index'))
            ->assertSee(route('admin.settings.edit'));
    }

    public function test_user_without_overview_permission_gets_a_plain_dashboard_and_short_menu(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee('Total users')
            ->assertDontSee('Access Management')
            ->assertDontSee(route('admin.access.users.index'));
    }

    public function test_cashier_sees_overview(): void
    {
        $this->actingAs($this->userWithRole(SystemRole::Cashier))->get('/admin/dashboard')->assertOk()->assertSee('Total users');
    }
}
