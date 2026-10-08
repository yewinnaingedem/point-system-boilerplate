<x-layouts.admin :title="$customer->name" :breadcrumbs="[__('Customers') => route('admin.customers.index')]">
    <div class="row">
        <div class="col-lg-4">
            <div class="card card-primary card-outline">
                <div class="card-body box-profile text-center">
                    @include('customer::partials.avatar', ['customer' => $customer, 'size' => 80, 'class' => 'mb-2'])
                    <h3 class="profile-username">{{ $customer->name }}</h3>
                    <p class="mb-2">
                        @include('customer::customers.cells.status', ['active' => $customer->is_active])
                    </p>
                    <ul class="list-group list-group-unbordered text-left mb-3">
                        <li class="list-group-item"><b>{{ __('Email') }}</b> <span class="float-right">{{ $customer->email ?: '—' }}</span></li>
                        <li class="list-group-item"><b>{{ __('Phone') }}</b> <span class="float-right">{{ $customer->phone ?: '—' }}</span></li>
                        <li class="list-group-item"><b>{{ __('Partner id') }}</b> <span class="float-right text-monospace">{{ $customer->external_id }}</span></li>
                        <li class="list-group-item"><b>{{ __('Points') }}</b>
                            <a class="float-right" href="{{ route('admin.loyalty.points.show', $customer) }}">{{ number_format($points) }}</a></li>
                        <li class="list-group-item"><b>{{ __('Last sign in') }}</b> <span class="float-right">{{ $customer->last_login_at?->diffForHumans() ?? __('Never') }}</span></li>
                        <li class="list-group-item"><b>{{ __('Customer since') }}</b> <span class="float-right">{{ $customer->created_at->format(setting('date_format')) }}</span></li>
                    </ul>
                    <p class="small text-muted mb-0">{{ __('Name, email and phone come from the partner app and update on every sign-in.') }}</p>
                </div>
                @can('edit-customer')
                    <div class="card-footer">
                        <form method="POST" action="{{ route('admin.customers.status', $customer) }}"
                              data-confirm="{{ $customer->is_active ? __('Deactivate :name? They are signed out of every device and cannot sign in until reactivated.', ['name' => $customer->name]) : __('Reactivate :name?', ['name' => $customer->name]) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" @class(['btn btn-block', 'btn-outline-danger' => $customer->is_active, 'btn-success' => ! $customer->is_active])>
                                <i class="fas {{ $customer->is_active ? 'fa-user-slash' : 'fa-user-check' }} mr-1"></i>{{ $customer->is_active ? __('Deactivate') : __('Reactivate') }}
                            </button>
                        </form>
                    </div>
                @endcan
            </div>

            <div class="card card-outline card-secondary" data-remember-card="customer.devices">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-mobile-alt mr-1"></i> {{ __('Signed-in devices') }}</h3>
                    <div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button></div>
                </div>
                <div class="card-body   ">
                    <ul class="list-group list-group-flush">
                        @forelse ($devices as $device)
                            <li class="list-group-item">
                                <div class="font-weight-bold">{{ $device->name }}</div>
                                <div class="small text-muted">{{ __('Last used :when · expires :expires', ['when' => $device->last_used_at?->diffForHumans() ?? __('never'), 'expires' => $device->expires_at?->format(setting('date_format')) ?? '—']) }}</div>
                            </li>
                        @empty
                            <li class="list-group-item text-muted">{{ __('Not signed in on any device.') }}</li>
                        @endforelse
                    </ul>
                </div>
                @can('edit-customer')
                    @if ($devices->isNotEmpty())
                        <div class="card-footer">
                            <x-confirm-delete :action="route('admin.customers.devices.destroy', $customer)" :title="__('Sign :name out of every device?', ['name' => $customer->name])" />
                            <span class="small text-muted ml-1">{{ __('Sign out everywhere') }}</span>
                        </div>
                    @endif
                @endcan
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-medal mr-1"></i> {{ __('Tier') }}</h3></div>
                <div class="card-body">
                    @if ($tier)
                        <div class="d-flex flex-wrap align-items-center mb-3">
                            <span class="h4 mb-0 mr-3">@include('loyalty::partials.tier-badge', ['tier' => $tierConfig])</span>
                            <span class="text-muted">
                                @if ($tier['guarantee_expires_at'])
                                    {{ __('Guaranteed until :date', ['date' => $tier['guarantee_expires_at']->format(setting('date_format'))]) }}
                                @else
                                    {{ __('No guarantee running') }}
                                @endif
                            </span>
                        </div>
                        <p class="mb-1">
                            {{ __('This cycle (:from – :to): :spent spent', ['from' => $tier['cycle_start']->format(setting('date_format')), 'to' => $tier['cycle_end']->subDay()->format(setting('date_format')), 'spent' => money($tier['cycle_spent'])]) }}
                        </p>
                        @if ($tier['next'])
                            @php($share = (float) $tier['next']['threshold'] > 0 ? min(100, round((float) $tier['cycle_spent'] / (float) $tier['next']['threshold'] * 100)) : 0)
                            <div class="progress progress-sm mb-1"><div class="progress-bar" style="width: {{ $share }}%"></div></div>
                            <small class="text-muted">{{ __(':remaining more this cycle to reach :tier.', ['remaining' => money($tier['next']['remaining']), 'tier' => $tier['next']['label']]) }}</small>
                        @else
                            <small class="text-muted">{{ __('Highest tier.') }}</small>
                        @endif
                    @else
                        <p class="text-muted mb-0">{{ __('Not enrolled yet.') }}</p>
                    @endif
                </div>
            </div>

            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-history mr-1"></i> {{ __('Tier history') }}</h3></div>
                <div class="card-body   ">
                    <x-datatable id="tier-history" :source="route('admin.customers.tier-history', $customer)" :order="[[0, 'desc']]"
                                 :empty="__('No tier changes yet.')" :loading="__('Loading tier history')" class="text-nowrap" :columns="[
                        ['data' => 'date', 'name' => 'occurred_at', 'title' => __('Date'), 'orderable' => true, 'priority' => 1],
                        ['data' => 'transition', 'title' => __('Change'), 'priority' => 2],
                        ['data' => 'from', 'title' => __('From')],
                        ['data' => 'to', 'title' => __('To'), 'priority' => 3],
                        ['data' => 'cycle_spent', 'title' => __('Cycle spending'), 'class' => 'text-right'],
                        ['data' => 'guarantee', 'title' => __('Guaranteed until')],
                    ]" />
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
