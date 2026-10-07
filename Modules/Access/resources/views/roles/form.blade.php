@php($editing = $role->exists)
@php($checked = old('permissions', $granted))
@php($colors = ['card-primary', 'card-info', 'card-success', 'card-warning', 'card-danger', 'card-secondary'])
<x-layouts.admin :title="$editing ? __('Edit role') : __('New role')" :breadcrumbs="[__('Roles') => route('admin.access.roles.index')]">
    <form method="POST" action="{{ $editing ? route('admin.access.roles.update', $role) : route('admin.access.roles.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="card card-primary card-outline">
            <div class="card-body">
                <div class="form-group mb-0" style="max-width: 420px">
                    <label for="name">{{ __('Role name') }}</label>
                    @if ($isSystem)
                        <input type="hidden" name="name" value="{{ $role->name }}">
                        <input class="form-control" id="name" value="{{ $role->name }}" disabled>
                        <small class="form-text text-muted">{{ __('System roles keep their name.') }}</small>
                    @else
                        <input id="name" name="name" value="{{ old('name', $role->name) }}" required @class(['form-control', 'is-invalid' => $errors->has('name')])>
                        @error('name') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    @endif
                </div>
            </div>
        </div>

        <div class="row">
            @foreach ($groups as $resource => $permissions)
                <div class="col-md-6 col-xl-4">
                    <div class="card card-outline {{ $colors[$loop->index % count($colors)] }}">
                        <div class="card-header">
                            <h3 class="card-title">{{ \Modules\Access\Support\PermissionMatrix::resourceLabel($resource) }}</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-toggle-all title="{{ __('Toggle all') }}"><i class="fas fa-check-double"></i></button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                @foreach ($permissions as $permission)
                                    <div class="col-6">
                                        <div class="custom-control custom-checkbox mb-1">
                                            <input type="checkbox" class="custom-control-input" id="perm-{{ $permission->id }}" name="permissions[]" value="{{ $permission->name }}" @checked(in_array($permission->name, $checked, true))>
                                            <label class="custom-control-label font-weight-normal" for="perm-{{ $permission->id }}">{{ ucfirst(\Modules\Access\Support\PermissionMatrix::action($permission->name)) }}</label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        @error('permissions.*') <div class="alert alert-danger">{{ $message }}</div> @enderror

        <div class="mb-4">
            <a href="{{ route('admin.access.roles.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary float-right"><i class="fas fa-save mr-1"></i>{{ $editing ? __('Save role') : __('Create role') }}</button>
        </div>
    </form>
</x-layouts.admin>
