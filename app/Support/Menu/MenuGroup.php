<?php

namespace App\Support\Menu;

use Illuminate\Support\Collection;

/**
 * A collapsible sidebar entry (AdminLTE treeview) holding MenuItems whose $parent is this key.
 * It is shown only when the user can see at least one of its items, and opens itself
 * when one of them is the current page.
 */
final class MenuGroup
{
    /**
     * @param  Collection<int, MenuItem>  $children  filled by MenuRegistry with the visible items
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $icon,
        public readonly string $section = 'Main',
        public readonly int $order = 100,
        public readonly Collection $children = new Collection,
    ) {}

    /**
     * @param  Collection<int, MenuItem>  $children
     */
    public function withChildren(Collection $children): self
    {
        return new self($this->key, $this->label, $this->icon, $this->section, $this->order, $children->values());
    }

    public function isActive(): bool
    {
        return $this->children->contains(fn (MenuItem $item) => $item->isActive());
    }
}
