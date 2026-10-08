<x-layouts.admin :title="__('Customers')">
    <div class="row">
        <div class="col-md-4 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-primary elevation-1"><i class="fas fa-user-tag"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Customers') }}</span>
                    <span class="info-box-number">{{ number_format($total) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-success elevation-1"><i class="fas fa-sign-in-alt"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Signed in today') }}</span>
                    <span class="info-box-number">{{ number_format($activeToday) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-body border-bottom">
            <form id="customer-filters" class="form-row">
                <div class="col-md-6 mb-2 mb-md-0">
                    <div class="input-group">
                        <input type="search" name="search" class="form-control" placeholder="{{ __('Search name, email or phone…') }}">
                        <div class="input-group-append"><button class="btn btn-default"><i class="fas fa-search"></i></button></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select name="tier" class="custom-select">
                        <option value="">{{ __('All tiers') }}</option>
                        @foreach ($tiers as $tier)
                            <option value="{{ $tier->value }}">{{ __($tier->label()) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <select name="status" class="custom-select">
                        <option value="">{{ __('Active and deactivated') }}</option>
                        <option value="active">{{ __('Active') }}</option>
                        <option value="inactive">{{ __('Deactivated') }}</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="card-body ">
            <x-datatable id="customers-table" :source="route('admin.customers.data')" filters="#customer-filters" :order="[[0, 'desc']]"
                         :empty="__('No customers yet. They appear here the first time they sign in from the partner app.')" :loading="__('Loading customers')" class="text-nowrap" :columns="[
                ['data' => 'id', 'name' => 'id', 'title' => '#', 'orderable' => true, 'class' => 'text-muted', 'priority' => 6],
                ['data' => 'customer', 'name' => 'name', 'title' => __('Customer'), 'orderable' => true, 'priority' => 1],
                ['data' => 'phone', 'title' => __('Phone')],
                ['data' => 'tier', 'title' => __('Tier'), 'priority' => 2],
                ['data' => 'points', 'name' => 'points_balance', 'title' => __('Points'), 'orderable' => true, 'class' => 'text-right', 'priority' => 3],
                ['data' => 'last_login', 'name' => 'last_login_at', 'title' => __('Last sign in'), 'orderable' => true, 'class' => 'text-muted'],
                ['data' => 'status', 'title' => __('Status'), 'priority' => 4],
                ['data' => 'actions', 'title' => __('Actions'), 'class' => 'text-right', 'priority' => 5],
            ]" />
        </div>
    </div>
</x-layouts.admin>
