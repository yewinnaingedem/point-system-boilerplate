@php($editing = $claim->exists)
@php($merchantUser = auth()->user()->isMerchantUser())
<x-layouts.admin :title="$editing ? __('Edit :reference', ['reference' => $claim->reference]) : __('New claim')"
                 :breadcrumbs="[__('Merchants') => route('admin.merchants.index'), __('Claims') => route('admin.claims.index')]">
    {{-- Choosing the merchant / day reloads the page with a preview of the cards. --}}
    <form method="GET" class="card card-secondary card-outline">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-search mr-1"></i> {{ __('1. What to claim') }}</h3></div>
        <div class="card-body">
            <div class="form-row align-items-end">
                @if ($editing || $merchantUser)
                    <div class="form-group col-md-5">
                        <label>{{ __('Merchant') }}</label>
                        <input class="form-control" value="{{ $claim->merchant?->name }}" readonly>
                    </div>
                @else
                    <div class="form-group col-md-5">
                        <label for="pick_merchant">{{ __('Merchant') }}</label>
                        <select id="pick_merchant" name="merchant_id" class="custom-select" data-autosubmit>
                            <option value="">{{ __('Choose…') }}</option>
                            @foreach ($merchants as $id => $name)
                                @php($row = $ready[$id] ?? null)
                                <option value="{{ $id }}" @selected((string) $claim->merchant_id === (string) $id)>
                                    {{ $name }}{{ $row ? ' — '.trans_choice('1 card|:count cards', $row->cards).', '.money($row->amount, 2) : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="form-group col-md-4">
                    <label for="pick_up_to">{{ __('Gift cards used up to') }}</label>
                    <input type="date" id="pick_up_to" name="up_to" value="{{ $claim->up_to?->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control">
                </div>
                <div class="form-group col-md-3">
                    <button class="btn btn-default btn-block"><i class="fas fa-sync-alt mr-1"></i>{{ __('Show cards') }}</button>
                </div>
            </div>
        </div>
    </form>

    <div class="card card-primary card-outline">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-list mr-1"></i> {{ __('2. Gift cards in this claim') }}</h3></div>
        <div class="card-body p-0 table-responsive">
            @if ($previewCards === null)
                <p class="text-center text-muted py-4 mb-0">{{ __('Choose a merchant first.') }}</p>
            @else
                @include('giftcard::claims._cards', ['cards' => $previewCards, 'count' => $previewCount, 'total' => $previewAmount,
                    'empty' => __('No used gift cards to claim up to this day.')])
                @if ($previewCount > $previewCards->count())
                    <p class="small text-muted px-3 py-2 mb-0">{{ __('Showing the first :shown of :count.', ['shown' => $previewCards->count(), 'count' => $previewCount]) }}</p>
                @endif
            @endif
        </div>
    </div>

    @if ($previewCount > 0)
        <form method="POST" action="{{ $editing ? route('admin.claims.update', $claim) : route('admin.claims.store') }}" class="card card-success card-outline">
            @csrf
            @if ($editing) @method('PUT') @endif
            @unless ($editing || $merchantUser) <input type="hidden" name="merchant_id" value="{{ $claim->merchant_id }}"> @endunless
            <input type="hidden" name="up_to" value="{{ $claim->up_to->toDateString() }}">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-paper-plane mr-1"></i> {{ __('3. Submit') }}</h3></div>
            <div class="card-body">
                <div class="form-group mb-0">
                    <label for="note">{{ __('Note') }} <span class="text-muted font-weight-normal">({{ __('optional') }})</span></label>
                    <input id="note" name="note" value="{{ old('note', $claim->note) }}" maxlength="500" @class(['form-control', 'is-invalid' => $errors->has('note')])>
                    @error('note') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    @error('up_to') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="card-footer">
                <a href="{{ $editing ? route('admin.claims.show', $claim) : route('admin.claims.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                <button type="submit" class="btn btn-success float-right">
                    <i class="fas fa-check mr-1"></i>{{ $editing ? __('Save claim (:amount)', ['amount' => money($previewAmount, 2)]) : __('Submit claim (:amount)', ['amount' => money($previewAmount, 2)]) }}
                </button>
            </div>
        </form>
    @endif
</x-layouts.admin>
