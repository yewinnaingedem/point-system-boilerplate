<x-layouts.admin :title="__('Adjust points')" :breadcrumbs="[__('Customer Points') => route('admin.loyalty.points.index')]">
    <div class="">
        <div class="">
            <form method="POST" action="{{ route('admin.loyalty.points.store') }}" class="card card-primary card-outline">
                @csrf
                <div class="card-body">
                    <div class="form-group">
                        <label for="customer">{{ __('Customer (email or phone)') }}</label>
                        <input id="customer" name="customer" value="{{ old('customer', request('customer')) }}" required autofocus
                               @class(['form-control', 'is-invalid' => $errors->has('customer')])>
                        @error('customer') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-group">
                        <label for="points">{{ __('Points') }}</label>
                        <input type="number" step="1" id="points" name="points" value="{{ old('points') }}" required
                               @class(['form-control', 'is-invalid' => $errors->has('points')])>
                        @error('points') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        <small class="form-text text-muted">{{ __('Positive adds points, negative removes them (e.g. -200).') }}</small>
                    </div>
                    <div class="form-group mb-0">
                        <label for="note">{{ __('Reason') }}</label>
                        <input id="note" name="note" value="{{ old('note') }}" maxlength="255" required
                               @class(['form-control', 'is-invalid' => $errors->has('note')])>
                        @error('note') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        <small class="form-text text-muted">{{ __('Shown in the customer\'s points history.') }}</small>
                    </div>
                </div>
                <div class="card-footer clearfix">
                    <a href="{{ route('admin.loyalty.points.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary float-right"><i class="fas fa-save mr-1"></i>{{ __('Save') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
