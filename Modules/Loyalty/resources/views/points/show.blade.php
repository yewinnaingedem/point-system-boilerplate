<x-layouts.admin :title="$customer->name" :breadcrumbs="[__('Customer Points') => route('admin.loyalty.points.index')]">
    <div class="row">
        <div class="col-md-4">
            <div class="card card-primary card-outline">
                <div class="card-body box-profile text-center">
                    @include('customer::partials.avatar', ['customer' => $customer, 'size' => 80, 'class' => 'mb-2'])
                    <h3 class="profile-username">{{ $customer->name }}</h3>
                    <p class="text-muted mb-3">{{ $customer->email ?? $customer->phone }}</p>
                    <div class="h2 mb-0">{{ number_format($statement['balance']) }}</div>
                    <div class="text-muted">{{ __('points') }}</div>
                    @if ($statement['next_expiry'])
                        <div class="mt-2 small text-danger">
                            <i class="fas fa-hourglass-half mr-1"></i>{{ __(':points expire at the end of :date', ['points' => number_format($statement['next_expiry']['points']), 'date' => \Carbon\CarbonImmutable::parse($statement['next_expiry']['expires_on'])->format(setting('date_format'))]) }}
                        </div>
                    @endif
                </div>
                @can('adjust-point')
                    <div class="card-footer">
                        <a href="{{ route('admin.loyalty.points.create', ['customer' => $customer->email ?? $customer->phone]) }}" class="btn btn-primary btn-block"><i class="fas fa-exchange-alt mr-1"></i>{{ __('Adjust points') }}</a>
                    </div>
                @endcan
            </div>
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-hourglass-half mr-1"></i> {{ __('Points by expiry') }}</h3></div>
                <div class="card-body p-0">
                    <x-datatable id="point-lots" :source="route('admin.loyalty.points.lots', $customer)" :order="[]" :paging="false"
                                 :empty="__('No unspent points.')" :loading="__('Loading points')" class="text-nowrap" :columns="[
                        ['data' => 'amount', 'title' => __('Points'), 'class' => 'text-right', 'priority' => 1],
                        ['data' => 'expires', 'title' => __('Expire at end of'), 'priority' => 2],
                        ['data' => 'when', 'title' => __('In'), 'class' => 'text-muted'],
                    ]" />
                </div>
            </div>

        </div>
        <div class="col-md-8">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-history mr-1"></i> {{ __('History') }}</h3></div>
                <div class="card-body p-0">
                    <x-datatable id="point-history" :source="route('admin.loyalty.points.history', $customer)"
                                 :empty="__('No points activity yet.')" :loading="__('Loading history')" class="text-nowrap" :columns="[
                        ['data' => 'date', 'name' => 'id', 'title' => __('Date'), 'orderable' => true, 'priority' => 1],
                        ['data' => 'type', 'title' => __('Type'), 'priority' => 3],
                        ['data' => 'points', 'name' => 'points', 'title' => __('Points'), 'orderable' => true, 'class' => 'text-right', 'priority' => 2],
                        ['data' => 'balance_after', 'title' => __('Balance'), 'class' => 'text-right'],
                        ['data' => 'note', 'title' => __('Note')],
                        ['data' => 'by', 'title' => __('By'), 'class' => 'text-muted'],
                    ]" />
                </div>
            </div>

            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-calendar-alt mr-1"></i> {{ __('Month by month') }}</h3></div>
                <div class="card-body p-0">
                    <x-datatable id="point-months" :source="route('admin.loyalty.points.months', $customer)" :order="[]" :paging="false"
                                 :empty="__('No points activity yet.')" :loading="__('Loading summary')" class="text-nowrap" :columns="[
                        ['data' => 'month', 'title' => __('Month'), 'priority' => 1],
                        ['data' => 'earned', 'title' => __('Earned'), 'class' => 'text-right'],
                        ['data' => 'redeemed', 'title' => __('Redeemed'), 'class' => 'text-right'],
                        ['data' => 'reversed', 'title' => __('Reversed'), 'class' => 'text-right'],
                        ['data' => 'adjusted', 'title' => __('Adjusted'), 'class' => 'text-right'],
                        ['data' => 'expired', 'title' => __('Expired'), 'class' => 'text-right'],
                        ['data' => 'net', 'title' => __('Net'), 'class' => 'text-right font-weight-bold', 'priority' => 2],
                    ]" />
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
