@php
    $deletedTab = $status === 'deleted';
    $tabs = ['all' => [__('All'), 'fas fa-users'], 'active' => [__('Active'), 'fas fa-user-check'], 'inactive' => [__('Deactivated'), 'fas fa-user-slash']];
    if (auth()->user()->can('delete-user')) {
        $tabs['deleted'] = [__('Deleted'), 'fas fa-trash-restore'];
    }
@endphp
<x-layouts.admin :title="__('Users')">
    <div class="card card-primary card-outline card-outline-tabs">
        <div class="card-header p-0 border-bottom-0">
            <ul class="nav nav-tabs">
                @foreach ($tabs as $key => [$label, $icon])
                    @php($tabStatus = $key === 'all' ? null : $key)
                    <li class="nav-item">
                        <a href="{{ route('admin.access.users.index', array_filter(['status' => $tabStatus, 'search' => request('search'), 'role' => request('role')])) }}"
                           @class(['nav-link', 'active' => $status === $tabStatus])>
                            <i class="{{ $icon }} mr-1"></i> {{ $label }}
                            <span class="badge badge-{{ $key === 'deleted' ? 'danger' : 'secondary' }} ml-1">{{ $tabCounts[$key] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="card-body border-bottom">
            <form method="GET" class="form-row">
                @if ($status) <input type="hidden" name="status" value="{{ $status }}"> @endif
                <div class="col-md-6 mb-2 mb-md-0">
                    <div class="input-group">
                        <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('Search name, email or phone…') }}">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-default"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select name="role" class="custom-select" data-autosubmit>
                        <option value="">{{ __('All roles') }}</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role }}" @selected(request('role') === $role)>{{ $role }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 text-md-right">
                    @can('create-user')
                        <a href="{{ route('admin.access.users.create') }}" class="btn btn-primary"><i class="fas fa-plus mr-1"></i>{{ __('New user') }}</a>
                    @endcan
                </div>
            </form>
        </div>

        @if ($deletedTab)
            <div class="callout callout-danger m-3 mb-0">
                <i class="fas fa-info-circle mr-1"></i>
                {{ __('Deleted users cannot sign in. Restore brings them back with their roles; delete permanently removes them for good.') }}
            </div>
        @endif

        <div class="card-body table-responsive p-0">
            <table class="table table-hover table-striped text-nowrap mb-0">
                <thead>
                    <tr>
                        <th style="width: 60px">#</th>
                        <th>{{ __('User') }}</th>
                        <th>{{ __('Phone') }}</th>
                        <th>{{ __('Roles') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ $deletedTab ? __('Deleted') : __('Last sign in') }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td class="text-muted">{{ $user->id }}</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <x-avatar :user="$user" size="34" class="mr-2" />
                                    <div>
                                        @if ($deletedTab)
                                            <div class="font-weight-bold">{{ $user->name }}</div>
                                        @else
                                            <a href="{{ route('admin.access.users.show', $user) }}" class="font-weight-bold">{{ $user->name }}</a>
                                        @endif
                                        <div class="small text-muted">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $user->phone ?: '—' }}</td>
                            <td>
                                @foreach ($user->roles as $role)
                                    <span class="badge badge-primary">{{ $role->name }}</span>
                                @endforeach
                            </td>
                            <td>
                                @if ($deletedTab)
                                    <span class="badge badge-danger">{{ __('Deleted') }}</span>
                                @elseif ($user->is_active)
                                    <span class="badge badge-success">{{ __('Active') }}</span>
                                @else
                                    <span class="badge badge-secondary">{{ __('Deactivated') }}</span>
                                @endif
                            </td>
                            <td class="text-muted">
                                {{ $deletedTab ? $user->deleted_at->diffForHumans() : ($user->last_login_at?->diffForHumans() ?? __('Never')) }}
                            </td>
                            <td class="text-right">
                                <div class="btn-group">
                                    @if ($deletedTab)
                                        @can('restore', $user)
                                            <form method="POST" action="{{ route('admin.access.users.restore', $user) }}" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-success btn-sm" title="{{ __('Restore') }}"><i class="fas fa-trash-restore"></i></button>
                                            </form>
                                            <x-confirm-delete :action="route('admin.access.users.force-delete', $user)" :title="__('Permanently delete :name? This cannot be undone.', ['name' => $user->name])" />
                                        @endcan
                                    @else
                                        <a href="{{ route('admin.access.users.show', $user) }}" class="btn btn-default btn-sm" title="{{ __('View') }}"><i class="fas fa-eye"></i></a>
                                        @can('update', $user)
                                            <a href="{{ route('admin.access.users.edit', $user) }}" class="btn btn-info btn-sm" title="{{ __('Edit') }}"><i class="fas fa-pencil-alt"></i></a>
                                        @endcan
                                        @can('impersonate', $user)
                                            <form method="POST" action="{{ route('admin.access.users.impersonate', $user) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-warning btn-sm" title="{{ __('Login as :name', ['name' => $user->name]) }}"><i class="fas fa-user-secret"></i></button>
                                            </form>
                                        @endcan
                                        @can('delete', $user)
                                            @unless ($user->is(auth()->user()))
                                                <x-confirm-delete :action="route('admin.access.users.destroy', $user)" :title="__('Delete :name?', ['name' => $user->name])" />
                                            @endunless
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-5">{{ __('No users match these filters.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer clearfix">
            <span class="text-muted small">{{ __('Showing :from–:to of :total', ['from' => $users->firstItem() ?? 0, 'to' => $users->lastItem() ?? 0, 'total' => $users->total()]) }}</span>
            <div class="float-right">{{ $users->links() }}</div>
        </div>
    </div>
</x-layouts.admin>
