<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\Menu\MenuGroup;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Mockery;
use Tests\TestCase;

class MenuRegistryTest extends TestCase
{
    private function userWho(array $permissions): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')->andReturnUsing(fn (string $permission) => in_array($permission, $permissions, true));

        return $user;
    }

    private function registry(): MenuRegistry
    {
        return (new MenuRegistry)
            ->addGroup(new MenuGroup('access', 'Access Management', 'fas fa-user-lock', 'Administration', 10))
            ->add(new MenuItem('Settings', 'admin.settings.edit', 'fas fa-cogs', 'Administration', 'view-appsetting', 90))
            ->add(new MenuItem('Roles', 'admin.access.roles.index', 'fas fa-user-shield', permission: 'view-role', order: 20, parent: 'access'))
            ->add(new MenuItem('Users', 'admin.access.users.index', 'fas fa-users', permission: 'view-user', order: 10, parent: 'access'))
            ->add(new MenuItem('Orphan', 'admin.dashboard', 'fas fa-cube', 'Main', parent: 'missing-group'));
    }

    public function test_group_holds_only_visible_children_in_order_and_takes_its_own_place(): void
    {
        $sections = $this->registry()->sectionsFor($this->userWho(['view-user', 'view-role', 'view-appsetting']));

        $admin = $sections->get('Administration');
        $this->assertInstanceOf(MenuGroup::class, $admin[0]);
        $this->assertSame(['Users', 'Roles'], $admin[0]->children->pluck('label')->all());
        $this->assertSame('Settings', $admin[1]->label);
    }

    public function test_group_is_hidden_when_no_child_is_visible(): void
    {
        $sections = $this->registry()->sectionsFor($this->userWho(['view-appsetting']));

        $this->assertSame(['Settings'], $sections->get('Administration')->pluck('label')->all());
    }

    public function test_item_with_unknown_parent_is_still_shown(): void
    {
        $sections = $this->registry()->sectionsFor($this->userWho([]));

        $this->assertSame(['Orphan'], $sections->get('Main')->pluck('label')->all());
    }
}
