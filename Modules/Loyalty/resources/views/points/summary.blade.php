<x-layouts.admin :title="__('Points Summary')">
    <div class="row">
        <div class="col-lg-3 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-primary elevation-1"><i class="fas fa-coins"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Points outstanding') }}</span>
                    <span class="info-box-number">{{ number_format($outstanding) }}</span>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-hourglass-end"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Expire end of :month', ['month' => $monthNames[0]]) }}</span>
                    <span class="info-box-number">{{ number_format($expiringThisMonth) }}</span>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-hourglass-half"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Expire end of :month', ['month' => $monthNames[1]]) }}</span>
                    <span class="info-box-number">{{ number_format($expiringNextMonth) }}</span>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-success elevation-1"><i class="fas fa-calendar-check"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('This month') }}</span>
                    <span class="info-box-number">
                        {{ number_format((int) $thisMonth?->earned) }} / {{ number_format((int) $thisMonth?->redeemed) }} / {{ number_format((int) $thisMonth?->expired) }}
                    </span>
                    <span class="small text-muted">{{ __('earned / redeemed / expired') }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-outline card-secondary" data-remember-card="loyalty.points-expiry-rule">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-info-circle mr-1"></i> {{ __('How points expire') }}</h3>
            <div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button></div>
        </div>
        <div class="card-body">
            @if ($expiryMonths === 0)
                <p class="mb-0">{{ __('Points never expire.') }}</p>
            @else
                <p>{{ trans_choice('{1} Points expire after :count month.|[2,*] Points expire after :count months.', $expiryMonths) }}
                    @can('view-appsetting') <a href="{{ route('admin.settings.edit', 'loyalty') }}">{{ __('Change') }}</a> @endcan</p>
                <ul class="pl-3 mb-0">
                    <li>{{ __('Earned on or before day :day: the earning month is the first counted month.', ['day' => $cutoffDay]) }}</li>
                    <li>{{ __('Earned after day :day: counting starts with the next month; the earning month does not count.', ['day' => $cutoffDay]) }}</li>
                    <li>{{ __('Points expire at the end of the last counted month. Spending always uses the points that expire first.') }}</li>
                    <li>{{ __('A reversed redemption returns its points with their original expiry date.') }}</li>
                </ul>
            @endif
        </div>
    </div>

    <div class="row">
        <div class="col-xl-6">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-calendar-alt mr-1"></i> {{ __('Month by month') }}</h3></div>
                <div class="card-body p-0">
                    <x-datatable id="points-months" :source="route('admin.loyalty.points-summary.months')" :order="[]" :paging="false"
                                 :empty="__('No points activity yet.')" :loading="__('Loading summary')" class="text-nowrap" :columns="[
                        ['data' => 'month', 'title' => __('Month'), 'priority' => 1],
                        ['data' => 'earned', 'title' => __('Earned'), 'class' => 'text-right', 'priority' => 2],
                        ['data' => 'redeemed', 'title' => __('Redeemed'), 'class' => 'text-right', 'priority' => 3],
                        ['data' => 'reversed', 'title' => __('Reversed'), 'class' => 'text-right'],
                        ['data' => 'adjusted', 'title' => __('Adjusted'), 'class' => 'text-right'],
                        ['data' => 'expired', 'title' => __('Expired'), 'class' => 'text-right', 'priority' => 4],
                        ['data' => 'net', 'title' => __('Net'), 'class' => 'text-right font-weight-bold', 'priority' => 5],
                    ]" />
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title mt-1"><i class="fas fa-hourglass-half mr-1"></i> {{ __('Expiring soon') }}</h3>
                    <div class="card-tools">
                        <form id="expiring-filters" class="input-group input-group-sm" style="width: 200px">
                            <input type="search" name="search" class="form-control" placeholder="{{ __('Customer…') }}">
                            <div class="input-group-append"><button class="btn btn-default"><i class="fas fa-search"></i></button></div>
                        </form>
                    </div>
                </div>
                <div class="card-body p-0">
                    <x-datatable id="points-expiring" :source="route('admin.loyalty.points-summary.expiring')" filters="#expiring-filters" :order="[]"
                                 :empty="__('Nothing expires in the next months.')" :loading="__('Loading expiring points')" class="text-nowrap" :columns="[
                        ['data' => 'customer', 'title' => __('Customer'), 'priority' => 1],
                        ['data' => 'amount', 'title' => __('Points'), 'class' => 'text-right', 'priority' => 2],
                        ['data' => 'expires', 'title' => __('Expire at end of'), 'priority' => 3],
                        ['data' => 'when', 'title' => __('In'), 'class' => 'text-muted'],
                    ]" />
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
