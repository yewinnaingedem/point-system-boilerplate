@php($editing = $branch->exists)
<x-layouts.admin :title="$editing ? __('Edit :name', ['name' => $branch->name]) : __('New branch')"
                 :breadcrumbs="[__('Merchants') => route('admin.merchants.index'), $merchant->name => route('admin.merchants.show', $merchant)]">
    <div class="">
        <div class="">
            <form method="POST" action="{{ $editing ? route('admin.merchants.branches.update', $branch) : route('admin.merchants.branches.store', $merchant) }}" class="card card-primary card-outline">
                @csrf
                @if ($editing) @method('PUT') @endif
                <div class="card-body">
                    <div class="form-group">
                        <label for="name">{{ __('Branch name') }}</label>
                        <input id="name" name="name" value="{{ old('name', $branch->name) }}" required maxlength="150" placeholder="{{ __('e.g. Junction Square') }}" @class(['form-control', 'is-invalid' => $errors->has('name')])>
                        @error('name') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-group">
                        <label for="phone">{{ __('Phone') }}</label>
                        <input id="phone" name="phone" value="{{ old('phone', $branch->phone) }}" maxlength="50" @class(['form-control', 'is-invalid' => $errors->has('phone')])>
                        @error('phone') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-group">
                        <label for="address">{{ __('Address') }}</label>
                        <textarea id="address" name="address" rows="2" maxlength="500" @class(['form-control', 'is-invalid' => $errors->has('address')])>{{ old('address', $branch->address) }}</textarea>
                        @error('address') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>
                    <div class="custom-control custom-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" @checked(old('is_active', $branch->is_active))>
                        <label class="custom-control-label" for="is_active">{{ __('Active (takes redemptions)') }}</label>
                    </div>
                    @unless ($editing)
                        <p class="text-muted small mt-3 mb-0"><i class="fas fa-key mr-1"></i>{{ __('A 6-digit code is created for the branch when you save. Give it to the shop: staff type it on the member\'s phone to confirm a redemption.') }}</p>
                    @endunless
                </div>
                <div class="card-footer clearfix">
                    <a href="{{ route('admin.merchants.show', $merchant) }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary float-right"><i class="fas fa-save mr-1"></i>{{ $editing ? __('Save changes') : __('Create branch') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
