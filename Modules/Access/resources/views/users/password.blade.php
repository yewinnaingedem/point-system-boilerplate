<x-layouts.admin :title="__('Change password')" :breadcrumbs="[__('Users') => route('admin.access.users.index'), $user->name => route('admin.access.users.show', $user)]">
    <div class="">
        <div class="">
            <form method="POST" action="{{ route('admin.access.users.password.update', $user) }}" class="card card-warning card-outline">
                @csrf
                @method('PUT')
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-key mr-1"></i> {{ __('New password for :name', ['name' => $user->name]) }}</h3>
                </div>
                <div class="card-body">
                    <div class="callout callout-warning">
                        {{ __(':name will be signed out of every browser and app and must sign in with the new password.', ['name' => $user->name]) }}
                    </div>
                    <div class="form-group">
                        <label for="password">{{ __('New password') }}</label>
                        <div class="input-group">
                            <input type="password" id="password" name="password" autocomplete="new-password" required @class(['form-control', 'is-invalid' => $errors->has('password')])>
                            <div class="input-group-append">
                                <button type="button" class="input-group-text" data-toggle-password="#password"><i class="fas fa-eye"></i></button>
                            </div>
                            @error('password') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label for="password_confirmation">{{ __('Confirm password') }}</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required class="form-control">
                    </div>
                </div>
                <div class="card-footer">
                    <a href="{{ route('admin.access.users.show', $user) }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-warning float-right"><i class="fas fa-save mr-1"></i>{{ __('Change password') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
