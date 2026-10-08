{{-- Links come from MenuRegistry; each module registers its own in its service provider. --}}
@php($me = auth()->user())
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="{{ route('admin.dashboard') }}" class="brand-link">
        @if ($logo = setting()->url('logo'))
            <img src="{{ $logo }}" alt="{{ setting('app_name') }}" class="brand-image img-circle elevation-3" style="opacity: .9 ; width: 30px ; height: 30px ;" >
        @else
            <span class="brand-image-initial elevation-3"><i class="fas fa-cash-register"></i></span>
        @endif
        <span class="brand-text font-weight-light">{{ setting('app_name') }}</span>
    </a>

    <div class="sidebar">
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
                <x-avatar :user="$me" size="34" class="img-circle elevation-2" />
            </div>
            <div class="info">
                <a href="{{ route('admin.profile.edit') }}" class="d-block">{{ $me->name }}</a>
            </div>
        </div>

        <div class="form-inline">
            <div class="input-group" data-widget="sidebar-search">
                <input class="form-control form-control-sidebar" type="search" placeholder="{{ __('Search menu') }}" aria-label="{{ __('Search menu') }}">
                <div class="input-group-append">
                    <button class="btn btn-sidebar" type="button"><i class="fas fa-search fa-fw"></i></button>
                </div>
            </div>
        </div>

        <nav class="mt-2">
            {{-- One list per section, so each can carry its own divider (.nav-sidebar-section in app.css).
                 Each list needs its own id: AdminLTE's treeview binds its click handler to "#id .nav-link", and
                 without an id every list handles every click, toggling a group several times (it never closes). --}}
            @foreach ($menuSections as $section => $items)
                <ul id="sidebar-section-{{ $loop->index }}" class="nav nav-pills nav-sidebar nav-sidebar-section flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    @unless ($loop->first && $section === 'Main')
                        <li class="nav-header">{{ mb_strtoupper(__($section)) }}</li>
                    @endunless
                    @foreach ($items as $entry)
                        @if ($entry instanceof \App\Support\Menu\MenuGroup)
                            @php($open = $entry->isActive())
                            <li @class(['nav-item', 'menu-open' => $open]) data-menu-group="{{ $entry->key }}">
                                <a href="#" @class(['nav-link', 'active' => $open])>
                                    <i class="nav-icon {{ $entry->icon }}"></i>
                                    <p>{{ __($entry->label) }} <i class="right fas fa-angle-left"></i></p>
                                </a>
                                <ul class="nav nav-treeview">
                                    @foreach ($entry->children as $item)
                                        @include('partials.sidebar-link', ['item' => $item, 'child' => true])
                                    @endforeach
                                </ul>
                            </li>
                        @else
                            @include('partials.sidebar-link', ['item' => $entry, 'child' => false])
                        @endif
                    @endforeach
                </ul>
            @endforeach
        </nav>
    </div>
</aside>
