<x-layouts.admin :title="__('Points Activity')">
    <div class="row">
        <div class="col-lg-3 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-success elevation-1"><i class="fas fa-arrow-down"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Earned today') }}</span>
                    <span class="info-box-number">{{ number_format($earnedToday) }}</span>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-primary elevation-1"><i class="fas fa-user-tag"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Customers who earned today') }}</span>
                    <span class="info-box-number">{{ number_format($earnersToday) }}</span>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-info elevation-1"><i class="fas fa-gift"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Redeemed today') }}</span>
                    <span class="info-box-number">{{ number_format($redeemedToday) }}</span>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-hourglass-end"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Expired today') }}</span>
                    <span class="info-box-number">{{ number_format($expiredToday) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-body">
            {{-- One filter form for both tables below. --}}
            <form id="activity-filters" class="form-row align-items-end">
                <div class="col-md-3 col-6 mb-2 mb-md-0">
                    <label for="period-select" class="small text-muted mb-1">{{ __('Period') }}</label>
                    <select id="period-select" name="period" class="custom-select">
                        @foreach ($periods as $value => $label)
                            <option value="{{ $value }}" @selected($value === 'today')>{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-6 mb-2 mb-md-0">
                    <label for="from" class="small text-muted mb-1">{{ __('From') }}</label>
                    <input type="date" id="from" name="from" max="{{ $today }}" class="form-control" data-period-custom="#period-select">
                </div>
                <div class="col-md-2 col-6 mb-2 mb-md-0">
                    <label for="to" class="small text-muted mb-1">{{ __('To') }}</label>
                    <input type="date" id="to" name="to" max="{{ $today }}" class="form-control" data-period-custom="#period-select">
                </div>
                <div class="col-md-2 col-6 mb-2 mb-md-0">
                    <label for="type" class="small text-muted mb-1">{{ __('Type') }}</label>
                    <select id="type" name="type" class="custom-select">
                        <option value="">{{ __('All types') }}</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}">{{ __($type->label()) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="activity-search" class="small text-muted mb-1">{{ __('Customer or reference') }}</label>
                    <div class="input-group">
                        <input type="search" id="activity-search" name="search" class="form-control" placeholder="{{ __('Name, email, phone, order…') }}">
                        <div class="input-group-append"><button class="btn btn-default"><i class="fas fa-search"></i></button></div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-5">
            <div class="card card-success card-outline">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-user-tag mr-1"></i> {{ __('Customers who earned') }}</h3></div>
                <div class="card-body p-0">
                    <x-datatable id="points-earners" :source="route('admin.loyalty.points-activity.earners')" filters="#activity-filters" :order="[[1, 'desc']]"
                                 :empty="__('Nobody earned points in this period.')" :loading="__('Loading customers')" class="text-nowrap" :columns="[
                        ['data' => 'customer', 'title' => __('Customer'), 'priority' => 1],
                        ['data' => 'earned_points', 'name' => 'earned', 'title' => __('Earned'), 'orderable' => true, 'class' => 'text-right text-success font-weight-bold', 'priority' => 2],
                        ['data' => 'award_count', 'name' => 'awards', 'title' => __('Awards'), 'orderable' => true, 'class' => 'text-right'],
                        ['data' => 'last_award', 'name' => 'last_at', 'title' => __('Last'), 'orderable' => true, 'class' => 'text-muted'],
                        ['data' => 'actions', 'title' => '', 'class' => 'text-right', 'priority' => 3],
                    ]" />
                </div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-exchange-alt mr-1"></i> {{ __('All point movements') }}</h3></div>
                <div class="card-body p-0">
                    <x-datatable id="points-activity" :source="route('admin.loyalty.points-activity.transactions')" filters="#activity-filters" :order="[[0, 'desc']]"
                                 :empty="__('No point movements in this period.')" :loading="__('Loading point movements')" class="text-nowrap" :columns="[
                        ['data' => 'date', 'name' => 'id', 'title' => __('Date'), 'orderable' => true, 'priority' => 1],
                        ['data' => 'customer', 'title' => __('Customer'), 'priority' => 2],
                        ['data' => 'type', 'title' => __('Type'), 'priority' => 4],
                        ['data' => 'points', 'name' => 'points', 'title' => __('Points'), 'orderable' => true, 'class' => 'text-right', 'priority' => 3],
                        ['data' => 'balance_after', 'title' => __('Balance'), 'class' => 'text-right'],
                        ['data' => 'reference', 'title' => __('Reference'), 'class' => 'text-monospace small'],
                        ['data' => 'note', 'title' => __('Note'), 'class' => 'text-muted'],
                    ]" />
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
