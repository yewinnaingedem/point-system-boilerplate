{{--
    Searchable customer picker: type a name, email, phone or customer id, pick from the list.
    The form receives the customer's id, so there is no guessing who was meant.
    @include('customer::partials.select', ['name' => 'customer_id', 'selected' => $customer])   ($customer: ?Customer)
--}}
@php($field = $name ?? 'customer_id')
@php($chosen = $selected ?? null)
<select id="{{ $id ?? $field }}" name="{{ $field }}" {{ ($required ?? true) ? 'required' : '' }}
        data-search-url="{{ route('admin.customers.search') }}" data-template="customer"
        data-placeholder="{{ __('Search by name, email, phone or customer id…') }}" data-inactive-label="{{ __('Deactivated') }}"
        @class(['custom-select', 'is-invalid' => $errors->has($field)])>
    <option value=""></option>
    @if ($chosen)
        @php($option = \Modules\Customer\Http\Controllers\CustomerSearchController::option($chosen))
        <option value="{{ $chosen->id }}" selected data-option='@json($option)'>{{ $chosen->name }}</option>
    @endif
</select>
@error($field) <span class="invalid-feedback">{{ $message }}</span> @enderror
