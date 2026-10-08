<x-layouts.admin :title="__('Merchants')">
    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title mt-1"><i class="fas fa-store mr-1"></i> {{ __('Partners where members spend points') }}</h3>
            <div class="card-tools d-flex">
                <form id="merchant-filters" class="d-flex">
                    <select name="status" class="custom-select custom-select-sm mr-2" style="width: 8rem">
                        <option value="">{{ __('All') }}</option>
                        <option value="active">{{ __('Active') }}</option>
                        <option value="inactive">{{ __('Inactive') }}</option>
                    </select>
                    <div class="input-group input-group-sm mr-2" style="width: 220px">
                        <input type="search" name="search" class="form-control" placeholder="{{ __('Name, phone or email…') }}">
                        <div class="input-group-append"><button class="btn btn-default"><i class="fas fa-search"></i></button></div>
                    </div>
                </form>
                @can('create-merchant')
                    <a href="{{ route('admin.merchants.create') }}" class="btn btn-primary btn-sm text-nowrap"><i class="fas fa-plus mr-1"></i>{{ __('New merchant') }}</a>
                @endcan
            </div>
        </div>
        <div class="card-body">
            <x-datatable id="merchants-table" :source="route('admin.merchants.data')" filters="#merchant-filters" :order="[[1, 'asc']]"
                         :empty="__('No merchants yet.')" :loading="__('Loading merchants')" class="text-nowrap" :columns="[
                ['data' => 'id', 'name' => 'id', 'title' => '#', 'orderable' => true, 'class' => 'text-muted', 'priority' => 5],
                ['data' => 'merchant', 'name' => 'name', 'title' => __('Merchant'), 'orderable' => true, 'priority' => 1],
                ['data' => 'branches', 'name' => 'branches_count', 'title' => __('Branches'), 'orderable' => true, 'class' => 'text-right'],
                ['data' => 'rewards', 'title' => __('Rewards'), 'class' => 'text-right'],
                ['data' => 'rate', 'title' => __('Payout / point'), 'class' => 'text-right'],
                ['data' => 'unsettled', 'name' => 'unsettled_total', 'title' => __('Owed (unsettled)'), 'orderable' => true, 'class' => 'text-right', 'priority' => 3],
                ['data' => 'status', 'title' => __('Status'), 'priority' => 4],
                ['data' => 'actions', 'title' => __('Actions'), 'class' => 'text-right', 'priority' => 2],
            ]" />
        </div>
    </div>
</x-layouts.admin>
