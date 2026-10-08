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
                        <a href="{{ route('admin.access.users.index', array_filter(['status' => $tabStatus, 'search' => $search, 'role' => $role])) }}" data-keep-filters
                           @class(['nav-link', 'active' => $status === $tabStatus])>
                            <i class="{{ $icon }} mr-1"></i> {{ $label }}
                            <span class="badge badge-{{ $key === 'deleted' ? 'danger' : 'secondary' }} ml-1">{{ $tabCounts[$key] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="card-body border-bottom">
            <form method="GET" id="users-filters" class="form-row">
                <div class="col-md-6 mb-2 mb-md-0">
                    <div class="input-group">
                        <input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="{{ __('Search name, email or phone…') }}">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-default"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select name="role" class="custom-select">
                        <option value="">{{ __('All roles') }}</option>
                        @foreach ($roles as $roleName)
                            <option value="{{ $roleName }}" @selected($role === $roleName)>{{ $roleName }}</option>
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

        <div class="card-body ">
            <x-datatable id="users-table" :source="route('admin.access.users.data')" filters="#users-filters" :params="['status' => $status]"
                         :page-length="config('access.users_per_page')" :empty="__('No users match these filters.')" :loading="__('Loading users')"
                         class="text-nowrap" :columns="[
                ['data' => 'id', 'name' => 'id', 'title' => '#', 'orderable' => true, 'class' => 'text-muted', 'priority' => 3],
                ['data' => 'user', 'name' => 'name', 'title' => __('User'), 'orderable' => true, 'priority' => 1],
                ['data' => 'phone', 'title' => __('Phone')],
                ['data' => 'roles', 'title' => __('Roles')],
                ['data' => 'status', 'title' => __('Status'), 'priority' => 4],
                ['data' => 'last_seen', 'name' => $deletedTab ? 'deleted_at' : 'last_login_at', 'title' => $deletedTab ? __('Deleted') : __('Last sign in'), 'orderable' => true, 'class' => 'text-muted'],
                ['data' => 'actions', 'title' => __('Actions'), 'class' => 'text-right', 'priority' => 2],
            ]" />
        </div>
    </div>
</x-layouts.admin>
