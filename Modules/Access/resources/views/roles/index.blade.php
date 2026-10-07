<x-layouts.admin :title="__('Roles')">
    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title mt-1"><i class="fas fa-user-shield mr-1"></i> {{ __('Roles & access levels') }}</h3>
            @can('create-role')
                <div class="card-tools">
                    <a href="{{ route('admin.access.roles.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i>{{ __('New role') }}</a>
                </div>
            @endcan
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Role') }}</th>
                        <th>{{ __('Users') }}</th>
                        <th style="width: 35%">{{ __('Permissions') }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($roles as $role)
                        @php($isAdmin = $role->name === \App\Enums\SystemRole::Administrator->value)
                        @php($isSystem = \App\Enums\SystemRole::isProtected($role->name))
                        @php($share = $isAdmin ? 100 : ($totalPermissions ? round($role->permissions_count / $totalPermissions * 100) : 0))
                        <tr>
                            <td>
                                <span class="font-weight-bold">{{ $role->name }}</span>
                                @if ($isSystem) <span class="badge badge-secondary ml-1">{{ __('System') }}</span> @endif
                            </td>
                            <td><span class="badge badge-info">{{ $role->users_count }}</span></td>
                            <td>
                                <div class="progress progress-xs mb-1">
                                    <div class="progress-bar" style="width: {{ $share }}%"></div>
                                </div>
                                <small class="text-muted">
                                    {{ $isAdmin ? __('Full access') : __(':granted of :total', ['granted' => $role->permissions_count, 'total' => $totalPermissions]) }}
                                </small>
                            </td>
                            <td class="text-right">
                                <div class="btn-group">
                                    @can('edit-role')
                                        @unless ($isAdmin)
                                            <a href="{{ route('admin.access.roles.edit', $role) }}" class="btn btn-info btn-sm" title="{{ __('Edit permissions') }}"><i class="fas fa-pencil-alt"></i></a>
                                        @endunless
                                    @endcan
                                    @can('delete-role')
                                        @unless ($isSystem)
                                            <x-confirm-delete :action="route('admin.access.roles.destroy', $role)" :title="__('Delete role :name?', ['name' => $role->name])" />
                                        @endunless
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.admin>
