@php($activeTab = $errors->password->any() ? 'password' : 'details')
<x-layouts.admin :title="__('Profile')">
    <div class="row">
        <div class="col-md-4 col-lg-3">
            <div class="card card-primary card-outline">
                <div class="card-body box-profile">
                    <div class="text-center">
                        <x-avatar :user="$user" size="100" class="profile-user-img img-fluid img-circle" />
                    </div>
                    <h3 class="profile-username text-center">{{ $user->name }}</h3>
                    <p class="text-muted text-center">{{ $user->roles->pluck('name')->implode(', ') ?: __('No role') }}</p>

                    <ul class="list-group list-group-unbordered mb-0">
                        <li class="list-group-item"><b>{{ __('Email') }}</b> <span class="float-right text-muted">{{ $user->email }}</span></li>
                        <li class="list-group-item"><b>{{ __('Phone') }}</b> <span class="float-right text-muted">{{ $user->phone ?: '—' }}</span></li>
                        <li class="list-group-item"><b>{{ __('Last sign in') }}</b> <span class="float-right text-muted">{{ $user->last_login_at?->diffForHumans() ?? '—' }}</span></li>
                        <li class="list-group-item border-bottom-0"><b>{{ __('Member since') }}</b> <span class="float-right text-muted">{{ $user->created_at->format(setting('date_format')) }}</span></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-8 col-lg-9">
            <div class="card">
                <div class="card-header p-2">
                    <ul class="nav nav-pills">
                        <li class="nav-item"><a @class(['nav-link', 'active' => $activeTab === 'details']) href="#details" data-toggle="tab">{{ __('Account details') }}</a></li>
                        <li class="nav-item"><a @class(['nav-link', 'active' => $activeTab === 'password']) href="#password-tab" data-toggle="tab">{{ __('Change password') }}</a></li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <div @class(['tab-pane', 'active' => $activeTab === 'details']) id="details">
                            <form method="POST" action="{{ route('admin.profile.update') }}" enctype="multipart/form-data" class="form-horizontal">
                                @csrf
                                @method('PUT')
                                @foreach (['name' => __('Name'), 'email' => __('Email'), 'phone' => __('Phone')] as $field => $label)
                                    <div class="form-group row">
                                        <label for="{{ $field }}" class="col-sm-2 col-form-label">{{ $label }}</label>
                                        <div class="col-sm-10">
                                            <input type="{{ $field === 'email' ? 'email' : 'text' }}" id="{{ $field }}" name="{{ $field }}"
                                                   value="{{ old($field, $user->$field) }}" @class(['form-control', 'is-invalid' => $errors->has($field)]) @required($field !== 'phone')>
                                            @error($field) <span class="invalid-feedback">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                @endforeach
                                <div class="form-group row">
                                    <label for="avatar" class="col-sm-2 col-form-label">{{ __('Photo') }}</label>
                                    <div class="col-sm-10">
                                        <div class="custom-file">
                                            <input type="file" name="avatar" id="avatar" accept="image/*" data-placeholder="{{ __('Choose file') }}" @class(['custom-file-input', 'is-invalid' => $errors->has('avatar')])>
                                            <label class="custom-file-label" for="avatar">{{ __('Choose file') }}</label>
                                            @error('avatar') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group row mb-0">
                                    <div class="offset-sm-2 col-sm-10">
                                        <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>{{ __('Save changes') }}</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div @class(['tab-pane', 'active' => $activeTab === 'password']) id="password-tab">
                            <form method="POST" action="{{ route('admin.profile.password') }}" class="form-horizontal">
                                @csrf
                                @method('PUT')
                                @foreach (['current_password' => [__('Current'), 'current-password'], 'password' => [__('New password'), 'new-password'], 'password_confirmation' => [__('Confirm'), 'new-password']] as $field => [$label, $autocomplete])
                                    <div class="form-group row">
                                        <label for="{{ $field }}" class="col-sm-2 col-form-label">{{ $label }}</label>
                                        <div class="col-sm-10">
                                            <input type="password" id="{{ $field }}" name="{{ $field }}" autocomplete="{{ $autocomplete }}" required
                                                   @class(['form-control', 'is-invalid' => $errors->password->has($field)])>
                                            @error($field, 'password') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                @endforeach
                                <div class="form-group row mb-0">
                                    <div class="offset-sm-2 col-sm-10">
                                        <button type="submit" class="btn btn-danger"><i class="fas fa-key mr-1"></i>{{ __('Update password') }}</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
