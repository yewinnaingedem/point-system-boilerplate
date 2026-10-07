<x-layouts.admin :title="__('Permissions')">
    <div class="callout callout-info">
        <p class="mb-0"><i class="fas fa-info-circle mr-1"></i> {{ __('Permissions are defined by each module. Grant them to roles on the Roles screen.') }}</p>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-body table-responsive p-0">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th class="pl-3">{{ __('Permission') }}</th>
                        <th>{{ __('Granted to') }}</th>
                    </tr>
                </thead>
                @foreach ($groups as $resource => $permissions)
                    <tbody>
                        <tr class="bg-light">
                            <th colspan="2" class="pl-3"><i class="fas fa-folder-open text-primary mr-1"></i> {{ \Modules\Access\Support\PermissionMatrix::resourceLabel($resource) }}</th>
                        </tr>
                        @foreach ($permissions as $permission)
                            <tr>
                                <td class="pl-4"><code>{{ $permission->name }}</code></td>
                                <td>
                                    <span class="badge badge-primary">{{ \App\Enums\SystemRole::Administrator->value }}</span>
                                    @foreach ($permission->roles as $role)
                                        <span class="badge badge-secondary">{{ $role->name }}</span>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                @endforeach
            </table>
        </div>
    </div>
</x-layouts.admin>
