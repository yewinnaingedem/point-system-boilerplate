<?php

namespace App\Support\Menu;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Collects sidebar links from every enabled module, so the layout never has to know
 * which modules exist. Register in a module provider's boot():
 *
 *     $menu = $this->app->make(MenuRegistry::class);
 *     $menu->addGroup(new MenuGroup('inventory', 'Inventory', 'fas fa-boxes', 'Inventory'));
 *     $menu->add(new MenuItem('Products', 'admin.products.index', 'fas fa-box', permission: 'view-product', parent: 'inventory'));
 */
final class MenuRegistry
{
    /**
     * Order in which sidebar sections appear; unknown sections go last. "Main" (Dashboard) has no
     * header; the others are the section headers.
     */
    private const SECTION_ORDER = ['Main', 'Access Management', 'Management', 'Inventory', 'Reports', 'Log Management'];

    /** @var list<MenuItem> */
    private array $items = [];

    /** @var array<string, MenuGroup> */
    private array $groups = [];

    public function add(MenuItem $item): self
    {
        $this->items[] = $item;

        return $this;
    }

    public function addGroup(MenuGroup $group): self
    {
        $this->groups[$group->key] = $group;

        return $this;
    }

    /**
     * Visible links and groups, grouped by section, in display order. An item whose parent
     * group was never registered is shown on its own rather than lost.
     *
     * @return Collection<string, Collection<int, MenuItem|MenuGroup>>
     */
    public function sectionsFor(?User $user): Collection
    {
        $visible = collect($this->items)
            ->filter(fn (MenuItem $item) => $item->visibleTo($user))
            ->sortBy('order');

        [$grouped, $standalone] = $visible->partition(fn (MenuItem $item) => $item->parent !== null && isset($this->groups[$item->parent]));

        $groups = collect($this->groups)
            ->map(fn (MenuGroup $group) => $group->withChildren($grouped->where('parent', $group->key)))
            ->filter(fn (MenuGroup $group) => $group->children->isNotEmpty());

        return $standalone
            ->concat($groups->values())
            ->sortBy('order')
            ->groupBy('section')
            ->sortBy(fn ($entries, string $section) => $this->sectionRank($section));
    }

    private function sectionRank(string $section): int
    {
        $rank = array_search($section, self::SECTION_ORDER, true);

        return $rank === false ? count(self::SECTION_ORDER) : $rank;
    }
}
