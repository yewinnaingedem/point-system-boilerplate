<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Every admin screen renders for an administrator, in case a view breaks.
 */
class AdminPagesRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_admin_page_renders(): void
    {
        $this->seedAccess();
        $admin = User::where('email', config('access.admin.email'))->firstOrFail();
        $other = $this->userWithRole('Cashier');
        $role = Role::findByName('Manager');

        $pages = [
            '/admin/dashboard',
            '/admin/profile',
            '/admin/access/users',
            '/admin/access/users/create',
            '/admin/access/users?status=deleted',
            "/admin/access/users/{$other->id}",
            "/admin/access/users/{$other->id}/password",
            "/admin/access/users/{$other->id}/edit",
            '/admin/access/roles',
            '/admin/access/roles/create',
            "/admin/access/roles/{$role->id}/edit",
            '/admin/access/permissions',
            '/admin/settings/general',
            '/admin/settings/pos',
            '/admin/settings/localization',
            '/admin/settings/loyalty',
            '/admin/settings/appearance',
            '/admin/loyalty/tiers',
            '/admin/loyalty/tiers/silver/edit',
            '/admin/loyalty/tiers/gold/edit',
            '/admin/loyalty/points',
            '/admin/loyalty/points/adjust',
            '/admin/loyalty/points/summary',
            '/admin/loyalty/points/activity',
            '/admin/merchants',
            '/admin/merchants/create',
            '/admin/redemptions',
            '/admin/customers',
            '/admin/gift-cards',
            '/admin/gift-cards/create',
            '/admin/gift-cards/exchanges',
        ];

        foreach ($pages as $page) {
            $this->actingAs($admin)->get($page)->assertOk();
        }
    }

    public function test_every_sidebar_section_list_has_its_own_id(): void
    {
        // AdminLTE's treeview scopes its click handler by the list's id. Lists without one all
        // handle every click, so a group is toggled several times and can never be closed.
        $this->seedAccess();
        $admin = User::where('email', config('access.admin.email'))->firstOrFail();

        $html = $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->getContent();
        preg_match_all('/<ul\b[^>]*data-widget="treeview"[^>]*>/', $html, $lists);
        preg_match_all('/<ul\b[^>]*\bid="(sidebar-section-\d+)"[^>]*data-widget="treeview"/', $html, $ids);

        $this->assertGreaterThan(1, count($lists[0]));
        $this->assertCount(count($lists[0]), array_unique($ids[1]));
    }
}
