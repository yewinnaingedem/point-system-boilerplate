@php($editing = $user->exists)
@php($selectedRoles = old('roles', $editing ? $user->roles->pluck('name')->all() : []))
<x-layouts.admin :title="$editing ? __('Edit user') : __('New user')" :breadcrumbs="[__('Users') => route('admin.access.users.index')]">
    <form method="POST" enctype="multipart/form-data"
          action="{{ $editing ? route('admin.access.users.update', $user) : route('admin.access.users.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="row">
            <div class="col-lg-8">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-id-card mr-1"></i> {{ __('Account') }}</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="name">{{ __('Full name') }}</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user"></i></span></div>
                                    <input id="name" name="name" value="{{ old('name', $user->name) }}" required @class(['form-control', 'is-invalid' => $errors->has('name')])>
                                    @error('name') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="email">{{ __('Email') }}</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-envelope"></i></span></div>
                                    <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required @class(['form-control', 'is-invalid' => $errors->has('email')])>
                                    @error('email') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="phone">{{ __('Phone') }}</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-phone"></i></span></div>
                                    <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" @class(['form-control', 'is-invalid' => $errors->has('phone')])>
                                    @error('phone') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            @isset($merchants)
                                <div class="form-group col-md-6">
                                    <label for="merchant_id">{{ __('Merchant') }}</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-store"></i></span></div>
                                        <select id="merchant_id" name="merchant_id" @class(['custom-select', 'is-invalid' => $errors->has('merchant_id')])>
                                            <option value="">{{ __('None (our staff)') }}</option>
                                            @foreach ($merchants as $id => $merchantName)
                                                <option value="{{ $id }}" @selected((string) old('merchant_id', $user->merchant_id) === (string) $id)>{{ $merchantName }}</option>
                                            @endforeach
                                        </select>
                                        @error('merchant_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                    </div>
                                    <small class="form-text text-muted">{{ __('A merchant\'s own staff (e.g. KFC manager) see only that merchant. Give them the Merchant role.') }}</small>
                                </div>
                            @endisset
                            <div class="form-group col-md-6">
                                <label for="avatar">{{ __('Photo') }}</label>
                                <div class="custom-file">
                                    <input type="file" id="avatar" name="avatar" accept="image/*" data-placeholder="{{ __('Choose file') }}" @class(['custom-file-input', 'is-invalid' => $errors->has('avatar')])>
                                    <label class="custom-file-label" for="avatar">{{ __('Choose file') }}</label>
                                    @error('avatar') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="password">{{ __('Password') }}</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-lock"></i></span></div>
                                    <input id="password" type="password" name="password" autocomplete="new-password" @required(! $editing) @class(['form-control', 'is-invalid' => $errors->has('password')])>
                                    @error('password') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                                @if ($editing) <small class="form-text text-muted">{{ __('Leave blank to keep the current password.') }}</small> @endif
                            </div>
                            <div class="form-group col-md-6">
                                <label for="password_confirmation">{{ __('Confirm password') }}</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-lock"></i></span></div>
                                    <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card card-info">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-user-shield mr-1"></i> {{ __('Roles') }}</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">{{ __('A user gets every permission of every role they hold.') }}</p>
                        @foreach ($roles as $role)
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" id="role-{{ $role->id }}" name="roles[]" value="{{ $role->name }}" @checked(in_array($role->name, $selectedRoles, true))>
                                <label class="custom-control-label" for="role-{{ $role->id }}">{{ $role->name }}</label>
                            </div>
                        @endforeach
                        @error('roles') <div class="text-danger small">{{ $message }}</div> @enderror
                        @error('roles.*') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="card card-success">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-toggle-on mr-1"></i> {{ __('Status') }}</h3>
                    </div>
                    <div class="card-body">
                        <input type="hidden" name="is_active" value="0">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
                            <label class="custom-control-label" for="is_active">{{ __('Active — can sign in') }}</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-4">
            <a href="{{ route('admin.access.users.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary float-right"><i class="fas fa-save mr-1"></i>{{ $editing ? __('Save changes') : __('Create user') }}</button>
        </div>
    </form>
</x-layouts.admin>
