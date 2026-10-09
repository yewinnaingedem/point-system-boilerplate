<x-layouts.admin :title="__('Gift Card Exchanges')">
    <div class="row">
        <div class="col-md-4 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-success elevation-1"><i class="fas fa-gift"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Issued today') }}</span>
                    <span class="info-box-number">{{ number_format($issuedToday) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-info elevation-1"><i class="fas fa-store"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Used at shops today') }}</span>
                    <span class="info-box-number">{{ number_format($usedToday) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="info-box">
                <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-shield-alt"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Waiting for a code') }}</span>
                    <span class="info-box-number">{{ number_format($pending) }}</span>
                </div>
            </div>
        </div>
    </div>
    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title mt-1"><i class="fas fa-exchange-alt mr-1"></i> {{ __('Exchanges') }}</h3>
            @can('create-giftcardexchange')
                <div class="card-tools">
                    <a href="{{ route('admin.gift-card-exchanges.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i>{{ __('Exchange for a customer') }}</a>
                </div>
            @endcan
        </div>
        <div class="card-body border-bottom">
            <form id="exchange-filters" class="form-row">
                <div class="col-md-5 mb-2 mb-md-0">
                    <div class="input-group">
                        <input type="search" name="search" class="form-control" placeholder="{{ __('Gift card code or customer…') }}">
                        <div class="input-group-append"><button class="btn btn-default"><i class="fas fa-search"></i></button></div>
                    </div>
                </div>
                <div class="col-md-4 col-6">
                    <select name="gift_card_id" class="custom-select">
                        <option value="">{{ __('All gift cards') }}</option>
                        @foreach ($cards as $id => $name)
                            <option value="{{ $id }}" @selected((string) request('gift_card_id') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 col-6">
                    <select name="status" class="custom-select">
                        <option value="">{{ __('Any status') }}</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}">{{ __($status->label()) }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>
        <div class="card-body ">
            <x-datatable id="exchanges-table" :source="route('admin.gift-card-exchanges.data')" filters="#exchange-filters" :order="[[0, 'desc']]"
                         :empty="__('No gift card exchanges yet.')" :loading="__('Loading exchanges')" class="text-nowrap" :columns="[
                ['data' => 'date', 'name' => 'id', 'title' => __('Date'), 'orderable' => true, 'priority' => 1],
                ['data' => 'customer', 'title' => __('Customer'), 'priority' => 2],
                ['data' => 'card', 'title' => __('Gift card'), 'priority' => 3],
                ['data' => 'points', 'name' => 'points', 'title' => __('Points'), 'orderable' => true, 'class' => 'text-right'],
                ['data' => 'code', 'title' => __('Code'), 'class' => 'text-monospace', 'priority' => 4],
                ['data' => 'expires', 'title' => __('Valid until'), 'class' => 'text-muted'],
                ['data' => 'status', 'title' => __('Status'), 'priority' => 2],
                ['data' => 'actions', 'title' => __('Actions'), 'class' => 'text-right', 'priority' => 1],
            ]" />
        </div>
    </div>
</x-layouts.admin>
