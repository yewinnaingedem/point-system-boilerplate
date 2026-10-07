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
                <form method="GET" class="input-group input-group-sm" style="width: 260px">
                    <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('User or device…') }}">
                    <div class="input-group-append"><button class="btn btn-default"><i class="fas fa-search"></i></button></div>
                </form>
            </div>
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap mb-0">
                <thead>
                    <tr>
                        <th>{{ __('User') }}</th>
                        <th>{{ __('Device') }}</th>
                        <th>{{ __('Signed in') }}</th>
                        <th>{{ __('Last used') }}</th>
                        <th>{{ __('Expires') }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tokens as $token)
                        @php($owner = $token->tokenable)
                        @php($expired = $token->expires_at?->isPast())
                        <tr>
                            <td>
                                @if ($owner)
                                    <div class="d-flex align-items-center">
                                        <x-avatar :user="$owner" size="32" class="mr-2" />
                                        <div>
                                            <div class="font-weight-bold">{{ $owner->name }}</div>
                                            <div class="small text-muted">{{ $owner->email }}</div>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-muted">{{ __('Deleted user') }}</span>
                                @endif
                            </td>
                            <td><i class="fas fa-mobile-alt text-muted mr-1"></i>{{ $token->name }}</td>
                            <td class="text-muted">{{ $token->created_at->diffForHumans() }}</td>
                            <td>{{ $token->last_used_at?->diffForHumans() ?? __('Never') }}</td>
                            <td>
                                @if ($expired)
                                    <span class="badge badge-secondary">{{ __('Expired') }}</span>
                                @elseif ($token->expires_at)
                                    <span class="badge badge-success">{{ $token->expires_at->diffForHumans() }}</span>
                                @else
                                    <span class="badge badge-warning">{{ __('Never') }}</span>
                                @endif
                            </td>
                            <td class="text-right">
                                @can('delete-apitoken')
                                    <div class="btn-group">
                                        @if ($owner)
                                            <form method="POST" action="{{ route('admin.api-tokens.destroy-user', $owner) }}" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-warning btn-sm" title="{{ __('Sign out of all devices') }}"><i class="fas fa-user-slash"></i></button>
                                            </form>
                                        @endif
                                        <x-confirm-delete :action="route('admin.api-tokens.destroy', $token)" :title="__('Sign out device :name?', ['name' => $token->name])" />
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-5">{{ __('No app devices are signed in.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer clearfix">
            <span class="text-muted small"><i class="fas fa-info-circle mr-1"></i>{{ __('Tokens are stored hashed; revoking one takes effect on the app\'s next request.') }}</span>
            <div class="float-right">{{ $tokens->links() }}</div>
        </div>
    </div>
</x-layouts.admin>
