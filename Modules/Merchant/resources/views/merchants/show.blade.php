<x-layouts.admin :title="$merchant->name" :breadcrumbs="[__('Merchants') => route('admin.merchants.index')]">
    <div class="row">
        <div class="col-md-4 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-hand-holding-usd"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Owed (unsettled)') }}</span>
                    <span class="info-box-number">{{ money($unsettledTotal, 2) }}</span>
                    <span class="small text-muted">{{ trans_choice('{0} no redemptions|{1} 1 redemption|[2,*] :count redemptions', $unsettledCount) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-info elevation-1"><i class="fas fa-percentage"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Payout per point') }}</span>
                    <span class="info-box-number">{{ money($merchant->settlement_rate, 4) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
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

    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title mt-1"><i class="fas fa-gift mr-1"></i> {{ __('Rewards') }}</h3>
            <div class="card-tools d-flex">
                <form id="reward-filters" class="input-group input-group-sm mr-2" style="width: 200px">
                    <input type="search" name="search" class="form-control" placeholder="{{ __('Reward name…') }}">
                    <div class="input-group-append"><button class="btn btn-default"><i class="fas fa-search"></i></button></div>
                </form>
                @can('create-merchant')
                    <a href="{{ route('admin.merchants.rewards.create', $merchant) }}" class="btn btn-primary btn-sm text-nowrap"><i class="fas fa-plus mr-1"></i>{{ __('New reward') }}</a>
                @endcan
            </div>
        </div>
        <div class="card-body  ">
            <x-datatable id="rewards-table" :source="route('admin.merchants.rewards.data', $merchant)" filters="#reward-filters" :order="[[1, 'asc']]"
                         :empty="__('No rewards yet. Add what members can get here.')" :loading="__('Loading rewards')" class="text-nowrap" :columns="[
                ['data' => 'reward', 'name' => 'name', 'title' => __('Reward'), 'orderable' => true, 'priority' => 1],
                ['data' => 'points', 'name' => 'points_cost', 'title' => __('Points'), 'orderable' => true, 'class' => 'text-right', 'priority' => 3],
                ['data' => 'payout', 'title' => __('Payout to merchant'), 'class' => 'text-right'],
                ['data' => 'status', 'title' => __('Status')],
                ['data' => 'actions', 'title' => __('Actions'), 'class' => 'text-right', 'priority' => 2],
            ]" />
        </div>
    </div>

    @if ($merchant->address || $merchant->notes)
        <div class="card card-outline card-secondary" data-remember-card="merchant.details">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-info-circle mr-1"></i> {{ __('Details') }}</h3>
                <div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button></div>
            </div>
            <div class="card-body">
                @if ($merchant->address) <p class="mb-2"><strong>{{ __('Address') }}:</strong> {{ $merchant->address }}</p> @endif
                @if ($merchant->notes) <p class="mb-0" style="white-space: pre-line">{{ $merchant->notes }}</p> @endif
            </div>
        </div>
    @endif
</x-layouts.admin>
