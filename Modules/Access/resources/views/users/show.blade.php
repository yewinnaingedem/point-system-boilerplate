@php
    use Modules\Access\Support\PermissionMatrix;
    $isSelf = $user->is(auth()->user());
@endphp
<x-layouts.admin :title="$user->name" :breadcrumbs="[__('Users') => route('admin.access.users.index')]">
    <div class="row">
        <div class="col-md-4 col-lg-3">
            <div class="card card-primary card-outline">
                <div class="card-body box-profile">
                    <div class="text-center">
                        <x-avatar :user="$user" size="100" class="profile-user-img img-fluid img-circle" />
                    </div>
                    <h3 class="profile-username text-center">{{ $user->name }}</h3>
                    <p class="text-center mb-2">
                        @foreach ($user->roles as $role)
                            <span class="badge badge-primary">{{ $role->name }}</span>
                        @endforeach
                    </p>
                    <p class="text-center">
                        @if ($user->is_active)
                            <span class="badge badge-success">{{ __('Active') }}</span>
                        @else
                            <span class="badge badge-secondary">{{ __('Deactivated') }}</span>
                        @endif
                    </p>

                    <ul class="list-group list-group-unbordered mb-3">
                        <li class="list-group-item"><b>{{ __('Email') }}</b> <span class="float-right text-muted">{{ $user->email }}</span></li>
                        <li class="list-group-item"><b>{{ __('Phone') }}</b> <span class="float-right text-muted">{{ $user->phone ?: '—' }}</span></li>
                        <li class="list-group-item"><b>{{ __('Last sign in') }}</b> <span class="float-right text-muted">{{ $user->last_login_at?->diffForHumans() ?? __('Never') }}</span></li>
                        <li class="list-group-item"><b>{{ __('Created') }}</b> <span class="float-right text-muted">{{ $user->created_at->format(setting('date_format')) }}</span></li>
                    </ul>

                    {{-- Actions --}}
                    @can('impersonate', $user)
                        <form method="POST" action="{{ route('admin.access.users.impersonate', $user) }}" class="mb-2">
                            @csrf
                            <button type="submit" class="btn btn-warning btn-block"><i class="fas fa-user-secret mr-1"></i>{{ __('Login as :name', ['name' => $user->name]) }}</button>
                        </form>
                    @endcan
                    @can('update', $user)
                        <a href="{{ route('admin.access.users.edit', $user) }}" class="btn btn-info btn-block"><i class="fas fa-pencil-alt mr-1"></i>{{ __('Edit') }}</a>
                    @endcan
                    @can('changePassword', $user)
                        <a href="{{ route('admin.access.users.password.edit', $user) }}" class="btn btn-default btn-block"><i class="fas fa-key mr-1"></i>{{ __('Change password') }}</a>
                    @endcan
                    @can('clearSessions', $user)
                        <form method="POST" action="{{ route('admin.access.users.sessions.destroy', $user) }}" class="mt-2">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-default btn-block"><i class="fas fa-sign-out-alt mr-1"></i>{{ __('Clear sessions') }}</button>
                        </form>
                    @endcan
                    @can('update', $user)
                        @unless ($isSelf)
                            <form method="POST" action="{{ route('admin.access.users.status', $user) }}" class="mt-2">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-block {{ $user->is_active ? 'btn-outline-secondary' : 'btn-success' }}">
                                    <i class="fas fa-power-off mr-1"></i>{{ $user->is_active ? __('Deactivate') : __('Activate') }}
                                </button>
                            </form>
                        @endunless
                    @endcan
                    @can('delete', $user)
                        @unless ($isSelf)
                            <button type="button" class="btn btn-outline-danger btn-block mt-2"
                                    data-confirm-delete="{{ route('admin.access.users.destroy', $user) }}" data-title="{{ __('Delete :name?', ['name' => $user->name]) }}">
                                <i class="fas fa-trash mr-1"></i>{{ __('Delete') }}
                            </button>
                        @endunless
                    @endcan
                </div>
            </div>
        </div>

        <div class="col-md-8 col-lg-9">
            <div class="card">
                <div class="card-header p-2">
                    <ul class="nav nav-pills">
                        <li class="nav-item"><a class="nav-link active" href="#tab-permissions" data-toggle="tab"><i class="fas fa-key mr-1"></i>{{ __('Permissions') }}</a></li>
                        <li class="nav-item"><a class="nav-link" href="#tab-sessions" data-toggle="tab"><i class="fas fa-desktop mr-1"></i>{{ __('Browser sessions') }} <span class="badge badge-light">{{ $sessions->count() }}</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="#tab-devices" data-toggle="tab"><i class="fas fa-mobile-alt mr-1"></i>{{ __('App devices') }} <span class="badge badge-light">{{ $tokens->count() }}</span></a></li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <div class="tab-pane active" id="tab-permissions">
                            <p class="text-muted">
                                @if ($user->isAdministrator())
                                    <i class="fas fa-crown text-warning mr-1"></i>{{ __('Administrator: every permission, including those of modules added later.') }}
                                @else
                                    {{ __('Everything this user can do, from all of their roles.') }}
                                @endif
                            </p>
                            <div class="row">
                                @forelse ($permissionGroups as $resource => $permissions)
                                    <div class="col-md-6 col-xl-4 mb-3">
                                        <h6 class="font-weight-bold mb-1">{{ PermissionMatrix::resourceLabel($resource) }}</h6>
                                        @foreach ($permissions as $permission)
                                            <span class="badge badge-light border"><i class="fas fa-check text-success mr-1"></i>{{ ucfirst(PermissionMatrix::action($permission->name)) }}</span>
                                        @endforeach
                                    </div>
                                @empty
                                    <div class="col-12 text-muted">{{ __('No permissions. This user can only see the dashboard.') }}</div>
                                @endforelse
                            </div>
                        </div>

                        <div class="tab-pane" id="tab-sessions">
                            @if (! $sessionsSupported)
                                <div class="callout callout-warning mb-0">{{ __('Session list needs SESSION_DRIVER=database.') }}</div>
                            @else
                                <table class="table table-sm mb-0">
                                    <thead><tr><th>{{ __('IP address') }}</th><th>{{ __('Browser') }}</th><th>{{ __('Last active') }}</th></tr></thead>
                                    <tbody>
                                        @forelse ($sessions as $session)
                                            <tr>
                                                <td><code>{{ $session->ip_address ?? '—' }}</code></td>
                                                <td class="small text-muted text-break">{{ \Illuminate\Support\Str::limit($session->user_agent, 90) }}</td>
                                                <td class="text-nowrap">
                                                    {{ $session->last_activity->diffForHumans() }}
                                                    @if ($session->is_current) <span class="badge badge-success">{{ __('This browser') }}</span> @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="3" class="text-muted text-center py-3">{{ __('Not signed in on any browser.') }}</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            @endif
                        </div>

                        <div class="tab-pane" id="tab-devices">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>{{ __('Device') }}</th><th>{{ __('Signed in') }}</th><th>{{ __('Last used') }}</th><th>{{ __('Expires') }}</th></tr></thead>
                                <tbody>
                                    @forelse ($tokens as $token)
                                        <tr>
                                            <td><i class="fas fa-mobile-alt text-muted mr-1"></i>{{ $token->name }}</td>
                                            <td>{{ $token->created_at->diffForHumans() }}</td>
                                            <td>{{ $token->last_used_at?->diffForHumans() ?? __('Never') }}</td>
                                            <td>{{ $token->expires_at?->diffForHumans() ?? __('Never') }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-muted text-center py-3">{{ __('Not signed in on any app device.') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
