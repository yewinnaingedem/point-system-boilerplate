<x-layouts.admin :title="__('Gift Cards')">
    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title mt-1"><i class="fas fa-gift mr-1"></i> {{ __('Gift cards customers can get for points') }}</h3>
            <div class="card-tools d-flex">
                <form id="giftcard-filters" class="input-group input-group-sm mr-2" style="width: 200px">
                    <input type="search" name="search" class="form-control" placeholder="{{ __('Name…') }}">
                    <div class="input-group-append"><button class="btn btn-default"><i class="fas fa-search"></i></button></div>
                </form>
                @can('create-giftcard')
                    <a href="{{ route('admin.gift-cards.create') }}" class="btn btn-primary btn-sm text-nowrap"><i class="fas fa-plus mr-1"></i>{{ __('New gift card') }}</a>
                @endcan
            </div>
        </div>
        <div class="card-body ">
            <x-datatable id="giftcards-table" :source="route('admin.gift-cards.data')" filters="#giftcard-filters" :order="[[2, 'asc']]"
                         :empty="__('No gift cards yet.')" :loading="__('Loading gift cards')" class="text-nowrap" :columns="[
                ['data' => 'id', 'name' => 'id', 'title' => '#', 'orderable' => true, 'class' => 'text-muted', 'priority' => 9],
                ['data' => 'card', 'name' => 'name', 'title' => __('Gift card'), 'orderable' => true, 'priority' => 1],
                ['data' => 'points', 'name' => 'points_cost', 'title' => __('Points'), 'orderable' => true, 'class' => 'text-right', 'priority' => 2],
                ['data' => 'value', 'title' => __('Value'), 'class' => 'text-right'],
                ['data' => 'tier', 'title' => __('Tier'), 'priority' => 4],
                ['data' => 'stock_left', 'name' => 'stock', 'title' => __('Stock'), 'orderable' => true, 'priority' => 3],
                ['data' => 'limits', 'title' => __('Rules')],
                ['data' => 'status', 'title' => __('Status'), 'priority' => 5],
                ['data' => 'actions', 'title' => __('Actions'), 'class' => 'text-right', 'priority' => 2],
            ]" />
        </div>
    </div>
</x-layouts.admin>
