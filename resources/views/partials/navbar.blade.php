@php($me = auth()->user())
<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button" aria-label="{{ __('Toggle menu') }}"><i class="fas fa-bars"></i></a>
        </li>
        <li class="nav-item d-none d-sm-inline-block">
            <a href="{{ route('admin.dashboard') }}" class="nav-link">{{ __('Home') }}</a>
        </li>
        @can('view-appsetting')
            <li class="nav-item d-none d-sm-inline-block">
                <a href="{{ route('admin.settings.edit') }}" class="nav-link">{{ __('Settings') }}</a>
            </li>
        @endcan
    </ul>

    <ul class="navbar-nav ml-auto">
        <li class="nav-item">
            <a class="nav-link" href="#" role="button" data-toggle-dark-mode title="{{ __('Dark mode') }}">
                <i class="fas fa-moon" data-dark-mode-icon></i>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-widget="fullscreen" href="#" role="button" title="{{ __('Full screen') }}">
                <i class="fas fa-expand-arrows-alt"></i>
            </a>
        </li>

        <li class="nav-item dropdown user-menu">
            <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">
                <x-avatar :user="$me" size="25" class="user-image img-circle elevation-1" />
                <span class="d-none d-md-inline">{{ $me->name }}</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                <li class="user-header bg-primary">
                    <x-avatar :user="$me" size="90" class="img-circle elevation-2" />
                    <p>
                        {{ $me->name }} – {{ $me->roles->pluck('name')->first() ?? __('No role') }}
                        <small>{{ __('Member since :date', ['date' => $me->created_at->format('M Y')]) }}</small>
                    </p>
                </li>
                <li class="user-footer">
                    <a href="{{ route('admin.profile.edit') }}" class="btn btn-default btn-flat">{{ __('Profile') }}</a>
                    <form method="POST" action="{{ route('logout') }}" class="d-inline float-right">
                        @csrf
                        <button type="submit" class="btn btn-default btn-flat">{{ __('Sign out') }}</button>
                    </form>
                </li>
            </ul>
        </li>
    </ul>
</nav>
