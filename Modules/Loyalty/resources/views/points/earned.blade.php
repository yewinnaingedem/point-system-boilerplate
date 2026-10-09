@php($sourceLabels = ['earn' => __('Awarded'), 'adjust' => __('Manual')])
<x-layouts.admin :title="__('Earned Points')">
    <div class="row">
        <div class="col-lg-3 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-primary elevation-1"><i class="fas fa-arrow-down"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Earned this month') }}</span>
                    <span class="info-box-number">{{ number_format($earnedThisMonth) }}</span>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-primary elevation-1"><i class="fas fa-user-tag"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Customers who earned this month') }}</span>
                    <span class="info-box-number">{{ number_format($earnersThisMonth) }}</span>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-primary elevation-1"><i class="fas fa-wallet"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Points not spent yet') }}</span>
                    <span class="info-box-number">{{ number_format($unusedPoints) }}</span>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-hourglass-half"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Expiring in 30 days') }}</span>
                    <span class="info-box-number">{{ number_format($expiringSoon) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-coins mr-1"></i> {{ __('Points earned by customers') }}</h3>
            @can('adjust-point')
                <div class="card-tools">
                    <a href="{{ route('admin.loyalty.points.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i>{{ __('Adjust points') }}</a>
                </div>
            @endcan
        </div>
        <div class="card-body border-bottom">
            <form id="earned-filters" class="form-row align-items-end">
                <div class="col-lg-2 col-md-4 col-6 mb-2">
                    <label for="period-select" class="small text-muted mb-1">{{ __('Earned') }}</label>
                    <select id="period-select" name="period" class="custom-select">
                        @foreach ($periods as $value => $label)
                            <option value="{{ $value }}" @selected($value === (request('period') ?? 'month'))>{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-4 col-6 mb-2">
                    <label for="from" class="small text-muted mb-1">{{ __('From') }}</label>
                    <input type="date" id="from" name="from" value="{{ request('from') }}" max="{{ $today }}" class="form-control" data-period-custom="#period-select">
                </div>
                <div class="col-lg-2 col-md-4 col-6 mb-2">
                    <label for="to" class="small text-muted mb-1">{{ __('To') }}</label>
                    <input type="date" id="to" name="to" value="{{ request('to') }}" max="{{ $today }}" class="form-control" data-period-custom="#period-select">
                </div>
                <div class="col-lg-2 col-md-4 col-6 mb-2">
                    <label for="source" class="small text-muted mb-1">{{ __('Source') }}</label>
                    <select id="source" name="source" class="custom-select">
                        <option value="">{{ __('All sources') }}</option>
                        @foreach ($sources as $source)
                            <option value="{{ $source->value }}" @selected(request('source') === $source->value)>{{ $sourceLabels[$source->value] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-4 col-6 mb-2">
                    <label for="status" class="small text-muted mb-1">{{ __('Status') }}</label>
                    <select id="status" name="status" class="custom-select">
                        <option value="">{{ __('Any status') }}</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ __($status->label()) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-4 col-6 mb-2">
                    <label for="expiring" class="small text-muted mb-1">{{ __('Expiry') }}</label>
                    <select id="expiring" name="expiring" class="custom-select">
                        <option value="">{{ __('Any time') }}</option>
                        <option value="30" @selected(request('expiring') === '30')>{{ __('Expiring in 30 days') }}</option>
                    </select>
                </div>
                <div class="col-12">
                    <label for="earned-search" class="small text-muted mb-1">{{ __('Customer or reference') }}</label>
                    <div class="input-group">
                        <input type="search" id="earned-search" name="search" value="{{ request('search') }}" class="form-control"
                               placeholder="{{ __('All customers — or type a name, email, phone, customer id or order reference…') }}">
                        <div class="input-group-append"><button class="btn btn-default"><i class="fas fa-search"></i></button></div>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-body">
            <x-datatable id="earned-table" :source="route('admin.loyalty.points-earned.data')" filters="#earned-filters" :order="[[0, 'desc']]" :page-length="25"
                         :empty="__('No points earned with these filters.')" :loading="__('Loading earned points')" class="text-nowrap" :columns="[
                ['data' => 'earned_at', 'name' => 'loyalty_point_lots.earned_at', 'title' => __('Earned at'), 'orderable' => true, 'priority' => 1],
                ['data' => 'customer', 'title' => __('Customer'), 'priority' => 1],
                ['data' => 'points', 'name' => 'loyalty_point_lots.points', 'title' => __('Points'), 'orderable' => true, 'class' => 'text-right', 'priority' => 2],
                ['data' => 'remaining', 'name' => 'loyalty_point_lots.remaining', 'title' => __('Left'), 'orderable' => true, 'class' => 'text-right', 'priority' => 3],
                ['data' => 'status', 'title' => __('Status'), 'priority' => 2],
                ['data' => 'expires_at', 'name' => 'loyalty_point_lots.expires_at', 'title' => __('Expires'), 'orderable' => true, 'priority' => 3],
                ['data' => 'source', 'title' => __('Source')],
                ['data' => 'reference', 'title' => __('Reference'), 'class' => 'text-muted'],
                ['data' => 'note', 'title' => __('Note'), 'class' => 'text-muted'],
            ]" />
        </div>
    </div>
</x-layouts.admin>
