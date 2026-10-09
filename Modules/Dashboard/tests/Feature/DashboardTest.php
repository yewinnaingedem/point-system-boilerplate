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

    public function test_admin_is_welcomed_and_sees_a_card_for_every_module_and_recent_sign_ins(): void
    {
        $admin = User::where('email', config('access.admin.email'))->firstOrFail();

        $this->actingAs($admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee(', '.$admin->name)
            ->assertSee('module-card', false)
            ->assertSee(route('admin.access.users.index'))
            ->assertSee(route('admin.settings.edit'))
            ->assertSee(route('admin.loyalty.tiers.index'))          // a group card opens its first page
            ->assertSee(route('admin.loyalty.points-summary.index'))  // and lists the others
            ->assertSee('Recent sign-ins')
            // Dashboard on top, then the sidebar sections in this order.
            ->assertSeeInOrder(['Dashboard', 'Settings', 'ACCESS MANAGEMENT', 'Roles', 'Users', 'Customers', 'Permissions',
                'MANAGEMENT', 'Loyalty', 'Merchants', 'Gift Cards', 'API Clients', 'LOG MANAGEMENT', 'Logs']);
    }

    public function test_user_without_permissions_is_welcomed_without_cards_or_sign_ins(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee(', '.$user->name)
            ->assertSee('Your account has no screens yet')
            ->assertDontSee('Recent sign-ins')
            ->assertDontSee('ACCESS MANAGEMENT')
            ->assertDontSee(route('admin.access.users.index'));
    }

    public function test_cards_follow_permissions(): void
    {
        // Cashier: dashboard overview (recent sign-ins) only, no module cards.
        $this->actingAs($this->userWithRole(SystemRole::Cashier))->get('/admin/dashboard')
            ->assertOk()->assertSee('Recent sign-ins')->assertDontSee(route('admin.merchants.index'));
    }
}
