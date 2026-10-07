<li class="nav-item">
    <a href="{{ $item->url() }}" @class(['nav-link', 'active' => $item->isActive()])>
        <i @class(['nav-icon', $item->icon, 'fa-sm' => $child])></i>
        <p>{{ __($item->label) }}</p>
    </a>
</li>
