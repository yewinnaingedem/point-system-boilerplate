<x-layouts.admin :title="__('Dashboard')">
    @if ($canSeeOverview)
        @php($boxes = [
            ['bg' => 'bg-info', 'value' => $totals['users'], 'label' => __('Total users'), 'icon' => 'fas fa-users', 'url' => auth()->user()->can('view-user') ? route('admin.access.users.index') : null],
            ['bg' => 'bg-success', 'value' => $totals['active_users'], 'label' => __('Active users'), 'icon' => 'fas fa-user-check', 'url' => auth()->user()->can('view-user') ? route('admin.access.users.index', ['status' => 'active']) : null],
            ['bg' => 'bg-warning', 'value' => $totals['roles'], 'label' => __('Roles'), 'icon' => 'fas fa-user-shield', 'url' => auth()->user()->can('view-role') ? route('admin.access.roles.index') : null],
            ['bg' => 'bg-danger', 'value' => $totals['permissions'], 'label' => __('Permissions'), 'icon' => 'fas fa-key', 'url' => auth()->user()->can('view-permission') ? route('admin.access.permissions.index') : null],
        ])
        <div class="row">
            @foreach ($boxes as $box)
                <div class="col-lg-3 col-6">
                    <div class="small-box {{ $box['bg'] }}">
                        <div class="inner">
                            <h3>{{ number_format($box['value']) }}</h3>
                            <p>{{ $box['label'] }}</p>
                        </div>
                        <div class="icon"><i class="{{ $box['icon'] }}"></i></div>
                        @if ($box['url'])
                            <a href="{{ $box['url'] }}" class="small-box-footer">{{ __('More info') }} <i class="fas fa-arrow-circle-right"></i></a>
                        @else
                            <span class="small-box-footer">&nbsp;</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row">
            <section class="col-lg-8">
                <div class="card">
                    <div class="card-header border-transparent">
                        <h3 class="card-title"><i class="fas fa-sign-in-alt mr-1"></i> {{ __('Recent sign-ins') }}</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
                        </div>
                    </div>
                    <div class="card-body ">
                        <div class="table-responsive">
                            <table class="table m-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('User') }}</th>
                                        <th>{{ __('Role') }}</th>
                                        <th class="text-right">{{ __('Signed in') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($recentLogins as $user)
                                        <tr>
                                            <td>
                                                <x-avatar :user="$user" size="32" class="mr-2" />
                                                <span class="font-weight-bold">{{ $user->name }}</span>
                                                <small class="text-muted d-none d-md-inline ml-1">{{ $user->email }}</small>
                                            </td>
                                            <td><span class="badge badge-info">{{ $user->roles->pluck('name')->first() ?? '—' }}</span></td>
                                            <td class="text-right text-muted"><i class="far fa-clock mr-1"></i>{{ $user->last_login_at->diffForHumans() }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="text-center text-muted py-4">{{ __('No sign-ins yet.') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @can('view-user')
                        <div class="card-footer clearfix">
                            <a href="{{ route('admin.access.users.create') }}" class="btn btn-sm btn-info float-left"><i class="fas fa-plus mr-1"></i>{{ __('New user') }}</a>
                            <a href="{{ route('admin.access.users.index') }}" class="btn btn-sm btn-secondary float-right">{{ __('View all users') }}</a>
                        </div>
                    @endcan
                </div>
            </section>

            <section class="col-lg-4">
                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-chart-bar mr-1"></i> {{ __('Users per role') }}</h3>
                    </div>
                    <div class="card-body">
                        @php($max = max(1, $usersPerRole->max('users_count')))
                        @foreach ($usersPerRole as $role)
                            <div class="progress-group">
                                {{ $role->name }}
                                <span class="float-right"><b>{{ $role->users_count }}</b></span>
                                <div class="progress progress-sm">
                                    <div class="progress-bar" style="width: {{ round($role->users_count / $max * 100) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="info-box mb-3">
                    <span class="info-box-icon bg-primary elevation-1"><i class="fas fa-cubes"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Enabled modules') }}</span>
                        <span class="info-box-number">{{ $totals['modules'] }}</span>
                        <div>
                            @foreach ($modules as $module)
                                <span class="badge badge-light">{{ $module }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="info-box mb-3">
                    <span class="info-box-icon bg-secondary elevation-1"><i class="far fa-calendar-alt"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ now()->format(setting('date_format')) }}</span>
                        <span class="info-box-number">{{ config('app.timezone') }}</span>
                    </div>
                </div>
            </section>
        </div>
    @else
        <div class="callout callout-info">
            <h5><i class="fas fa-cash-register mr-1"></i> {{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</h5>
            <p class="mb-0">{{ __('Use the menu to open the screens your role allows.') }}</p>
        </div>
    @endif
</x-layouts.admin>
