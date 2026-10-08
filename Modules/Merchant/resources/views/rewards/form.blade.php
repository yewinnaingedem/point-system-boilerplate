@php($editing = $reward->exists)
<x-layouts.admin :title="$editing ? __('Edit :name', ['name' => $reward->name]) : __('New reward')"
                 :breadcrumbs="[__('Merchants') => route('admin.merchants.index'), $merchant->name => route('admin.merchants.show', $merchant)]">
    <div class="">
        <div class="">
            <form method="POST" action="{{ $editing ? route('admin.merchants.rewards.update', $reward) : route('admin.merchants.rewards.store', $merchant) }}" class="card card-primary card-outline">
                @csrf
                @if ($editing) @method('PUT') @endif
                <div class="card-body">
                    <div class="form-group">
                        <label for="name">{{ __('Reward') }}</label>
                        <input id="name" name="name" value="{{ old('name', $reward->name) }}" required maxlength="150" placeholder="{{ __('e.g. Zinger Burger') }}" @class(['form-control', 'is-invalid' => $errors->has('name')])>
                        @error('name') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-group">
                        <label for="description">{{ __('Description') }}</label>
                        <textarea id="description" name="description" rows="2" maxlength="1000" @class(['form-control', 'is-invalid' => $errors->has('description')])>{{ old('description', $reward->description) }}</textarea>
                        @error('description') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="points_cost">{{ __('Points') }}</label>
                            <input type="number" min="1" step="1" id="points_cost" name="points_cost" value="{{ old('points_cost', $reward->points_cost) }}" required @class(['form-control', 'is-invalid' => $errors->has('points_cost')])>
                            @error('points_cost') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group col-md-6">
                            <label for="payout_amount">{{ __('Payout to merchant (:currency)', ['currency' => setting('currency_code')]) }}</label>
                            <input type="number" min="0" step="0.01" id="payout_amount" name="payout_amount" value="{{ old('payout_amount', $reward->payout_amount === null ? null : (float) $reward->payout_amount) }}"
                                   placeholder="{{ __('By rate (:rate / point)', ['rate' => money($merchant->settlement_rate, 4)]) }}" @class(['form-control', 'is-invalid' => $errors->has('payout_amount')])>
                            @error('payout_amount') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            <small class="form-text text-muted">{{ __('Leave empty to pay points × the merchant\'s payout per point.') }}</small>
                        </div>
                    </div>
                    <div class="custom-control custom-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" @checked(old('is_active', $reward->is_active))>
                        <label class="custom-control-label" for="is_active">{{ __('Available to members') }}</label>
                    </div>
                </div>
                <div class="card-footer clearfix">
                    <a href="{{ route('admin.merchants.show', $merchant) }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary float-right"><i class="fas fa-save mr-1"></i>{{ $editing ? __('Save changes') : __('Create reward') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
