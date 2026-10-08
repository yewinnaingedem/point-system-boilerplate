<x-layouts.admin :title="__('Redemptions')">
    <div class="row">
        <div class="col-md-4 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-hand-holding-usd"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Owed to merchants (unsettled)') }}</span>
                    <span class="info-box-number">{{ money($unsettledTotal, 2) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-success elevation-1"><i class="fas fa-gift"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Redeemed today') }}</span>
                    <span class="info-box-number">{{ number_format($todayCount) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-body border-bottom">
            <form id="redemption-filters" class="form-row">
                <div class="col-md-4 mb-2 mb-md-0">
                    <div class="input-group">
                        <input type="search" name="search" class="form-control" placeholder="{{ __('Reference or customer…') }}">
                        <div class="input-group-append"><button class="btn btn-default"><i class="fas fa-search"></i></button></div>
                    </div>
                </div>
                <div class="col-md-4 col-12 mb-2 mb-md-0">
                    <select name="merchant_id" class="custom-select">
                        <option value="">{{ __('All merchants') }}</option>
                        @foreach ($merchants as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-6">
                    <select name="status" class="custom-select">
                        <option value="">{{ __('Any status') }}</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}">{{ __($status->label()) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-6">
                    <select name="settlement" class="custom-select">
                        <option value="">{{ __('Settled or not') }}</option>
                        <option value="unsettled">{{ __('Unsettled') }}</option>
                        <option value="settled">{{ __('Settled') }}</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="card-body">
            <x-datatable id="redemptions-table" :source="route('admin.redemptions.data')" filters="#redemption-filters" :order="[[0, 'desc']]"
                         :empty="__('No redemptions match these filters.')" :loading="__('Loading redemptions')" class="text-nowrap" :columns="[
                ['data' => 'date', 'name' => 'redeemed_at', 'title' => __('Date'), 'orderable' => true, 'priority' => 1],
                ['data' => 'member', 'title' => __('Customer'), 'priority' => 4],
                ['data' => 'shop', 'title' => __('Shop')],
                ['data' => 'reward', 'title' => __('Reward'), 'priority' => 5],
                ['data' => 'points', 'name' => 'points', 'title' => __('Points'), 'orderable' => true, 'class' => 'text-right'],
                ['data' => 'payout', 'name' => 'payout_amount', 'title' => __('Payout'), 'orderable' => true, 'class' => 'text-right'],
                ['data' => 'status', 'title' => __('Status'), 'priority' => 2],
                ['data' => 'actions', 'title' => __('Reference'), 'class' => 'text-right', 'priority' => 3],
            ]" />
        </div>
    </div>
</x-layouts.admin>
