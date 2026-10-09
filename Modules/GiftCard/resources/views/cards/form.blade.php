@php($editing = $card->exists)
<x-layouts.admin :title="$editing ? __('Edit :name', ['name' => $card->name]) : __('New gift card')" :breadcrumbs="[__('Gift Cards') => route('admin.gift-cards.index')]">
    <form method="POST" action="{{ $editing ? route('admin.gift-cards.update', $card) : route('admin.gift-cards.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="row">
            <div class="col-lg-7">
                <div class="card card-primary card-outline">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-gift mr-1"></i> {{ __('Gift card') }}</h3></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="name">{{ __('Name') }}</label>
                            <input id="name" name="name" value="{{ old('name', $card->name) }}" required maxlength="150" placeholder="{{ __('e.g. City Mart 10,000 Ks voucher') }}" @class(['form-control', 'is-invalid' => $errors->has('name')])>
                            @error('name') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label for="description">{{ __('Description') }}</label>
                            <textarea id="description" name="description" rows="2" maxlength="1000" @class(['form-control', 'is-invalid' => $errors->has('description')])>{{ old('description', $card->description) }}</textarea>
                            @error('description') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="points_cost">{{ __('Points') }}</label>
                                <input type="number" min="1" step="1" id="points_cost" name="points_cost" value="{{ old('points_cost', $card->points_cost) }}" required @class(['form-control', 'is-invalid' => $errors->has('points_cost')])>
                                @error('points_cost') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label for="face_value">{{ __('Value (:currency)', ['currency' => setting('currency_code')]) }}</label>
                                <input type="number" min="0" step="0.01" id="face_value" name="face_value" value="{{ old('face_value', $card->face_value === null ? null : (float) $card->face_value) }}" required @class(['form-control', 'is-invalid' => $errors->has('face_value')])>
                                @error('face_value') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="custom-control custom-switch">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" @checked(old('is_active', $card->is_active))>
                            <label class="custom-control-label" for="is_active">{{ __('Active (customers can exchange it)') }}</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card card-secondary card-outline">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-sliders-h mr-1"></i> {{ __('Rules') }}</h3></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="merchant_id">{{ __('Merchant') }}</label>
                            <select id="merchant_id" name="merchant_id" @class(['custom-select', 'is-invalid' => $errors->has('merchant_id')])>
                                <option value="">{{ __('Any partner shop') }}</option>
                                @foreach ($merchants as $id => $merchantName)
                                    <option value="{{ $id }}" @selected((string) old('merchant_id', $card->merchant_id) === (string) $id)>{{ $merchantName }}</option>
                                @endforeach
                            </select>
                            @error('merchant_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            <small class="form-text text-muted">{{ __('Only this merchant\'s branches can accept the card, and only this merchant claims it.') }}</small>
                        </div>
                        <div class="form-group">
                            <label for="min_tier">{{ __('Tier') }}</label>
                            <select id="min_tier" name="min_tier" @class(['custom-select', 'is-invalid' => $errors->has('min_tier')])>
                                <option value="">{{ __('Every tier') }}</option>
                                @foreach ($tiers as $tier)
                                    <option value="{{ $tier->value }}" @selected(old('min_tier', $card->min_tier?->value) === $tier->value)>{{ __(':tier and above', ['tier' => $tier->label()]) }}</option>
                                @endforeach
                            </select>
                            @error('min_tier') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            <small class="form-text text-muted">{{ __('E.g. "Diamond and above" = Diamond members only.') }}</small>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label for="stock">{{ __('Stock') }}</label>
                                <input type="number" min="0" step="1" id="stock" name="stock" value="{{ old('stock', $card->stock) }}" placeholder="{{ __('Unlimited') }}" @class(['form-control', 'is-invalid' => $errors->has('stock')])>
                                @error('stock') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                <small class="form-text text-muted">{{ __('At 0 it shows as out of stock.') }}</small>
                            </div>
                            <div class="form-group col-6">
                                <label for="per_customer_limit">{{ __('Max per customer') }}</label>
                                <input type="number" min="1" step="1" id="per_customer_limit" name="per_customer_limit" value="{{ old('per_customer_limit', $card->per_customer_limit) }}" placeholder="{{ __('No limit') }}" @class(['form-control', 'is-invalid' => $errors->has('per_customer_limit')])>
                                @error('per_customer_limit') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="valid_days">{{ __('Valid for (days after exchange)') }}</label>
                            <input type="number" min="1" step="1" id="valid_days" name="valid_days" value="{{ old('valid_days', $card->valid_days) }}" placeholder="{{ __('No expiry') }}" @class(['form-control', 'is-invalid' => $errors->has('valid_days')])>
                            @error('valid_days') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>
                        <div class="custom-control custom-switch">
                            <input type="hidden" name="requires_verification" value="0">
                            <input type="checkbox" class="custom-control-input" id="requires_verification" name="requires_verification" value="1" @checked(old('requires_verification', $card->requires_verification))>
                            <label class="custom-control-label" for="requires_verification">{{ __('Two-step verification') }}</label>
                        </div>
                        <small class="form-text text-muted">{{ __('The customer gets a 6-digit code by email and must enter it before any points are taken.') }}</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="mb-4">
            <a href="{{ route('admin.gift-cards.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary float-right"><i class="fas fa-save mr-1"></i>{{ $editing ? __('Save changes') : __('Create gift card') }}</button>
        </div>
    </form>
</x-layouts.admin>
