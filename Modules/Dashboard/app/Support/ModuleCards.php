<?php

namespace Modules\Dashboard\Support;

use App\Models\User;
use App\Support\Menu\MenuGroup;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Illuminate\Support\Collection;

/**
 * The dashboard's module cards: the same entries as the sidebar (so the same permissions),
 * one card per link or group, under the same section headings. A group's card opens its first
 * page and lists the others.
 */
final class ModuleCards
{
    private const DASHBOARD_ROUTE = 'admin.dashboard';

    public function __construct(private readonly MenuRegistry $menu) {}

    /**
     * @return Collection<string, Collection<int, array{label: string, icon: string, url: string, links: list<array{label: string, url: string}>}>>
     */
    public function for(User $user): Collection
    {
        return $this->menu->sectionsFor($user)
            ->map(fn (Collection $entries) => $entries
                ->reject(fn ($entry) => $entry instanceof MenuItem && $entry->route === self::DASHBOARD_ROUTE)
                ->map(fn (MenuItem|MenuGroup $entry) => $entry instanceof MenuGroup ? $this->group($entry) : $this->item($entry))
                ->values())
            ->filter(fn (Collection $cards) => $cards->isNotEmpty());
    }

    /** @return array{label: string, icon: string, url: string, links: list<array{label: string, url: string}>} */
    private function item(MenuItem $item): array
    {
        return ['label' => $item->label, 'icon' => $item->icon, 'url' => $item->url(), 'links' => []];
    }

    /** @return array{label: string, icon: string, url: string, links: list<array{label: string, url: string}>} */
    private function group(MenuGroup $group): array
    {
        return [
            'label' => $group->label,
            'icon' => $group->icon,
            'url' => $group->children->first()->url(),
            'links' => $group->children->map(fn (MenuItem $item) => ['label' => $item->label, 'url' => $item->url()])->all(),
        ];
    }
}
