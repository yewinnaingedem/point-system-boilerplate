<x-layouts.admin :title="__('Dashboard')">
    {{-- Welcome --}}
    <div class="card dashboard-welcome">
        <div class="card-body d-flex align-items-center">
            <x-avatar :user="$user" size="56" class="mr-3 d-none d-sm-inline-flex" />
            <div>
                <h2 class="h4 mb-1">{{ $greeting }}, {{ $user->name }}</h2>
                <p class="text-muted mb-0">
                    {{ now()->translatedFormat('l, j F Y') }}
                    @if ($merchantName) · {{ $merchantName }} @endif
                    @if ($role) · {{ $role }} @endif
                </p>
            </div>
        </div>
    </div>

    {{-- One card per module the user may open (the same entries as the sidebar). --}}
    @forelse ($sections as $section => $cards)
        @unless ($section === 'Main')
            <h3 class="dashboard-section-title">{{ __($section) }}</h3>
        @endunless
        <div class="row">
            @foreach ($cards as $card)
                <div class="col-xl-3 col-lg-4 col-sm-6 d-flex">
                    <div class="card module-card flex-fill">
                        <div class="card-body">
                            <a href="{{ $card['url'] }}" class="module-card-link stretched-link d-flex align-items-center">
                                <span class="module-card-icon"><i class="{{ $card['icon'] }}"></i></span>
                                <span class="module-card-title">{{ __($card['label']) }}</span>
                                <i class="fas fa-chevron-right module-card-arrow ml-auto"></i>
                            </a>
                            @if ($card['links'] !== [])
                                <ul class="module-card-links list-unstyled mb-0 mt-3">
                                    @foreach ($card['links'] as $link)
                                        <li><a href="{{ $link['url'] }}" class="module-card-sublink">{{ __($link['label']) }}</a></li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @empty
        <div class="callout callout-info">
            <p class="mb-0">{{ __('Your account has no screens yet. Ask an administrator to give your role access.') }}</p>
        </div>
    @endforelse

    @if ($canSeeOverview)
        <div class="card card-primary card-outline mt-2">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-sign-in-alt mr-1"></i> {{ __('Recent sign-ins') }}</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
                </div>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('User') }}</th>
                            <th>{{ __('Role') }}</th>
                            <th class="text-right">{{ __('Signed in') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentLogins as $login)
                            <tr>
                                <td>
                                    <span class="font-weight-bold">{{ $login->name }}</span>
                                    <small class="text-muted d-none d-md-inline ml-1">{{ $login->email }}</small>
                                </td>
                                <td><span class="badge badge-primary">{{ $login->roles->pluck('name')->first() ?? '—' }}</span></td>
                                <td class="text-right text-muted"><i class="far fa-clock mr-1"></i>{{ $login->last_login_at->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">{{ __('No sign-ins yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @can('view-user')
                <div class="card-footer clearfix">
                    <a href="{{ route('admin.access.users.create') }}" class="btn btn-sm btn-primary float-left"><i class="fas fa-plus mr-1"></i>{{ __('New user') }}</a>
                    <a href="{{ route('admin.access.users.index') }}" class="btn btn-sm btn-default float-right">{{ __('View all users') }}</a>
                </div>
            @endcan
        </div>
    @endif
</x-layouts.admin>
