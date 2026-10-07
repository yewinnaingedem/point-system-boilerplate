<?php

namespace App\Support\Menu;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * One sidebar link. Modules register these in their service provider.
 * Give it a $parent (a MenuGroup key) to show it inside that collapsible group.
 */
final class MenuItem
{
    /**
     * @param  string  $route  route name the link points to
     * @param  string  $icon  Font Awesome 5 classes, e.g. "fas fa-users"
     * @param  string|null  $permission  hidden unless the user has it (null = every signed-in user)
     * @param  string|null  $activePattern  route-name pattern that highlights the link; defaults to "<route prefix>.*"
     * @param  string|null  $parent  key of the MenuGroup it belongs to (section is then taken from the group)
     */
    public function __construct(
        public readonly string $label,
        public readonly string $route,
        public readonly string $icon,
        public readonly string $section = 'Main',
        public readonly ?string $permission = null,
        public readonly int $order = 100,
        private readonly ?string $activePattern = null,
        public readonly ?string $parent = null,
    ) {}

    public function visibleTo(?User $user): bool
    {
        return $user !== null && ($this->permission === null || $user->can($this->permission));
    }

    public function url(): string
    {
        return route($this->route);
    }

    public function isActive(): bool
    {
        return request()->routeIs($this->activePattern ?? Str::beforeLast($this->route, '.').'.*');
    }
}
