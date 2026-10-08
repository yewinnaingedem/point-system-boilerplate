<x-layouts.admin :title="__('API Tokens')">
    <div class="row">
        <div class="col-md-4 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-primary elevation-1"><i class="fas fa-mobile-alt"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Valid tokens') }}</span>
                    <span class="info-box-number">{{ $activeCount }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-success elevation-1"><i class="fas fa-signal"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Used today') }}</span>
                    <span class="info-box-number">{{ $usedToday }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="info-box">
                <span class="info-box-icon bg-secondary elevation-1"><i class="fas fa-shield-alt"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Policy') }}</span>
                    <span class="info-box-number small font-weight-normal">
                        {{ __(':days-day tokens · :devices devices per user', ['days' => config('api.token_ttl_days'), 'devices' => config('api.max_devices')]) }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title mt-1"><i class="fas fa-plug mr-1"></i> {{ __('Signed-in app devices') }}</h3>
            <div class="card-tools">
                <form method="GET" id="token-filters" class="input-group input-group-sm" style="width: 260px">
                    <input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="{{ __('User or device…') }}">
                    <div class="input-group-append"><button class="btn btn-default"><i class="fas fa-search"></i></button></div>
                </form>
            </div>
        </div>
        <div class="card-body ">
            <x-datatable id="tokens-table" :source="route('admin.api-tokens.data')" filters="#token-filters" :page-length="20"
                         :empty="__('No app devices are signed in.')" :loading="__('Loading devices')" class="text-nowrap" :order="[[2, 'desc']]" :columns="[
                ['data' => 'owner', 'title' => __('User'), 'priority' => 1],
                ['data' => 'device', 'name' => 'name', 'title' => __('Device'), 'orderable' => true, 'priority' => 3],
                ['data' => 'signed_in', 'name' => 'created_at', 'title' => __('Signed in'), 'orderable' => true, 'class' => 'text-muted'],
                ['data' => 'last_used', 'name' => 'last_used_at', 'title' => __('Last used'), 'orderable' => true],
                ['data' => 'expires', 'name' => 'expires_at', 'title' => __('Expires'), 'orderable' => true],
                ['data' => 'actions', 'title' => __('Actions'), 'class' => 'text-right', 'priority' => 2],
            ]" />
        </div>
        <div class="card-footer clearfix">
            <span class="text-muted small"><i class="fas fa-info-circle mr-1"></i>{{ __('Tokens are stored hashed; revoking one takes effect on the app\'s next request.') }}</span>
        </div>
    </div>
</x-layouts.admin>
