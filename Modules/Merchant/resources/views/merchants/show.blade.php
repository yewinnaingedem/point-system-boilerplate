@php($box = auth()->user()->isMerchantUser() ? 'col-md-6' : 'col-md-4')
<x-layouts.admin :title="$merchant->name" :breadcrumbs="[__('Merchants') => route('admin.merchants.index')]">
    @can('edit-merchant')
        <div class="mb-3 text-right">
            <a href="{{ route('admin.merchants.edit', $merchant) }}" class="btn btn-info btn-sm"><i class="fas fa-pencil-alt mr-1"></i>{{ __('Edit shop details') }}</a>
        </div>
    @endcan
    <div class="row">
        <div class="{{ $box }} col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-file-invoice-dollar"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Gift card claims') }}</span>
                    @can('view-merchantclaim')
                        <a href="{{ route('admin.claims.index', ['merchant_id' => $merchant->id]) }}" class="info-box-number">{{ __('View claims') }}</a>
                    @else
                        <span class="small text-muted">{{ __('Merchants → Claims') }}</span>
                    @endcan
                </div>
            </div>
        </div>
        @unless (auth()->user()->isMerchantUser())
        <div class="col-md-4 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-info elevation-1"><i class="fas fa-percentage"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Payout per point') }}</span>
                    <span class="info-box-number">{{ money($merchant->settlement_rate, 4) }}</span>
                </div>
            </div>
        </div>
        @endunless
        <div class="{{ $box }} col-sm-12">
            <div class="info-box">
                <span @class(['info-box-icon elevation-1', 'bg-success' => $merchant->is_active, 'bg-secondary' => ! $merchant->is_active])><i class="fas fa-power-off"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Status') }}</span>
                    <span class="info-box-number">{{ $merchant->is_active ? __('Active') : __('Inactive') }}</span>
                    <span class="small text-muted text-truncate">{{ collect([$merchant->contact_person, $merchant->phone, $merchant->email])->filter()->implode(' · ') }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title mt-1"><i class="fas fa-map-marker-alt mr-1"></i> {{ __('Branches') }}</h3>
            <div class="card-tools d-flex">
                <form id="branch-filters" class="input-group input-group-sm mr-2" style="width: 200px">
                    <input type="search" name="search" class="form-control" placeholder="{{ __('Branch name…') }}">
                    <div class="input-group-append"><button class="btn btn-default"><i class="fas fa-search"></i></button></div>
                </form>
                @can('create-merchant')
                    <a href="{{ route('admin.merchants.branches.create', $merchant) }}" class="btn btn-primary btn-sm text-nowrap"><i class="fas fa-plus mr-1"></i>{{ __('New branch') }}</a>
                @endcan
            </div>
        </div>
        <div class="card-body  ">
            <x-datatable id="branches-table" :source="route('admin.merchants.branches.data', $merchant)" filters="#branch-filters" :order="[[0, 'asc']]"
                         :empty="__('No branches yet. Add the shops where members can redeem.')" :loading="__('Loading branches')" class="text-nowrap" :columns="[
                ['data' => 'branch', 'name' => 'name', 'title' => __('Branch'), 'orderable' => true, 'priority' => 1],
                ['data' => 'code', 'name' => 'code_changed_at', 'title' => __('Code'), 'orderable' => true, 'priority' => 3],
                ['data' => 'status', 'title' => __('Status')],
                ['data' => 'actions', 'title' => __('Actions'), 'class' => 'text-right', 'priority' => 2],
            ]" />
        </div>
    </div>

    @isset($merchantGiftCards)
        <div class="card card-secondary card-outline">
            <div class="card-header">
                <h3 class="card-title mt-1"><i class="fas fa-gift mr-1"></i> {{ __('Gift cards') }}</h3>
                @can('create-giftcard')
                    <div class="card-tools">
                        <a href="{{ route('admin.gift-cards.create', ['merchant_id' => $merchant->id]) }}" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i>{{ __('New gift card') }}</a>
                    </div>
                @endcan
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-striped mb-0 text-nowrap">
                    <thead>
                        <tr>
                            <th>{{ __('Gift card') }}</th>
                            <th class="text-right">{{ __('Points') }}</th>
                            <th class="text-right">{{ __('Value') }}</th>
                            <th class="text-right">{{ __('Stock') }}</th>
                            <th class="text-right">{{ __('With customers') }}</th>
                            <th class="text-right">{{ __('Used here') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($merchantGiftCards as $giftCard)
                            <tr>
                                <td class="font-weight-bold">
                                    @can('edit-giftcard')
                                        <a href="{{ route('admin.gift-cards.edit', $giftCard) }}">{{ $giftCard->name }}</a>
                                    @else
                                        {{ $giftCard->name }}
                                    @endcan
                                </td>
                                <td class="text-right">{{ number_format($giftCard->points_cost) }}</td>
                                <td class="text-right">{{ money($giftCard->face_value, 2) }}</td>
                                <td class="text-right">{{ $giftCard->stock === null ? __('Unlimited') : number_format($giftCard->stock) }}</td>
                                <td class="text-right">{{ number_format($giftCard->issued_count) }}</td>
                                <td class="text-right">{{ number_format($giftCard->used_count) }}</td>
                                <td>@include('giftcard::cells.active', ['active' => $giftCard->is_active])</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">{{ __('No gift cards for this merchant yet. Cards for "any partner shop" can be used here too.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endisset

    @if ($merchant->address || $merchant->notes)
        <div class="card card-outline card-secondary" data-remember-card="merchant.details">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-info-circle mr-1"></i> {{ __('Details') }}</h3>
                <div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button></div>
            </div>
            <div class="card-body">
                @if ($merchant->address) <p class="mb-2"><strong>{{ __('Address') }}:</strong> {{ $merchant->address }}</p> @endif
                @if ($merchant->notes && ! auth()->user()->isMerchantUser()) <p class="mb-0" style="white-space: pre-line">{{ $merchant->notes }}</p> @endif
            </div>
        </div>
    @endif
</x-layouts.admin>
