<x-layouts.admin :title="__('Customer Points')">
    <div class="row">
        <div class="col-md-4 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-primary elevation-1"><i class="fas fa-coins"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Points outstanding') }}</span>
                    <span class="info-box-number">{{ number_format($outstanding) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-success elevation-1"><i class="fas fa-users"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Customers with points') }}</span>
                    <span class="info-box-number">{{ number_format($members) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title mt-1"><i class="fas fa-coins mr-1"></i> {{ __('Balances') }}</h3>
            <div class="card-tools d-flex">
                <form id="point-filters" class="input-group input-group-sm mr-2" style="width: 240px">
                    <input type="search" name="search" class="form-control" placeholder="{{ __('Name, email or phone…') }}">
                    <div class="input-group-append"><button class="btn btn-default"><i class="fas fa-search"></i></button></div>
                </form>
                @can('adjust-point')
                    <a href="{{ route('admin.loyalty.points.create') }}" class="btn btn-primary btn-sm text-nowrap"><i class="fas fa-exchange-alt mr-1"></i>{{ __('Adjust points') }}</a>
                @endcan
            </div>
        </div>
        <div class="card-body   ">
            <x-datatable id="points-table" :source="route('admin.loyalty.points.data')" filters="#point-filters" :order="[[1, 'desc']]"
                         :empty="__('No customer has points yet.')" :loading="__('Loading balances')" class="text-nowrap" :columns="[
                ['data' => 'member', 'title' => __('Customer'), 'priority' => 1],
                ['data' => 'balance', 'name' => 'balance', 'title' => __('Balance'), 'orderable' => true, 'class' => 'text-right', 'priority' => 2],
                ['data' => 'updated', 'name' => 'updated_at', 'title' => __('Last change'), 'orderable' => true, 'class' => 'text-muted'],
                ['data' => 'actions', 'title' => __('Actions'), 'class' => 'text-right'],
            ]" />
        </div>
    </div>
</x-layouts.admin>
