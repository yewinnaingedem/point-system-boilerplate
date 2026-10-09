@php($editing = $client->exists)
<x-layouts.admin :title="$editing ? __('Edit :name', ['name' => $client->name]) : __('New API client')"
                 :breadcrumbs="[__('API Clients') => route('admin.api-clients.index')]">
    <form method="POST" action="{{ $editing ? route('admin.api-clients.update', $client) : route('admin.api-clients.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="row">
            <div class="col-lg-8">
                <div class="card card-primary card-outline">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-key mr-1"></i> {{ __('API client') }}</h3></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="name">{{ __('Name') }}</label>
                            <input id="name" name="name" value="{{ old('name', $client->name) }}" required maxlength="150" placeholder="{{ __('e.g. Partner website') }}" @class(['form-control', 'is-invalid' => $errors->has('name')])>
                            @error('name') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>
                        @if ($editing)
                            <div class="form-group">
                                <label>{{ __('appid') }}</label>
                                <input class="form-control" value="{{ $client->app_id }}" readonly>
                            </div>
                        @endif
                        <div class="form-group">
                            <label for="notes">{{ __('Notes') }}</label>
                            <textarea id="notes" name="notes" rows="2" maxlength="500" @class(['form-control', 'is-invalid' => $errors->has('notes')])>{{ old('notes', $client->notes) }}</textarea>
                            @error('notes') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>
                        <div class="custom-control custom-switch">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" @checked(old('is_active', $client->is_active))>
                            <label class="custom-control-label" for="is_active">{{ __('Active (can call the gateway)') }}</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="callout callout-info">
                    <h5><i class="fas fa-info-circle mr-1"></i> {{ __('Keys') }}</h5>
                    <p class="mb-0">{{ $editing
                        ? __('The secret key can\'t be shown again. Use "Rotate" on the list for a new one.')
                        : __('An appid and a secret key are generated. The key is shown once, on the next page.') }}</p>
                </div>
            </div>
        </div>
        <div class="mb-4">
            <a href="{{ route('admin.api-clients.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary float-right"><i class="fas fa-save mr-1"></i>{{ $editing ? __('Save changes') : __('Create API client') }}</button>
        </div>
    </form>
</x-layouts.admin>
