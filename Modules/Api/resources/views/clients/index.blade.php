<x-layouts.admin :title="__('API Clients')">
    @if ($secret)
        <div class="callout callout-warning">
            <h5><i class="fas fa-key mr-1"></i> {{ __('Secret key for :name', ['name' => $secret['name']]) }}</h5>
            <p class="mb-2">{{ __('Copy it now and store it on the calling server. It is not shown again; if it is lost, rotate it.') }}</p>
            <dl class="row mb-0">
                <dt class="col-sm-2">{{ __('appid') }}</dt>
                <dd class="col-sm-10"><code class="user-select-all">{{ $secret['app_id'] }}</code></dd>
                <dt class="col-sm-2">{{ __('Secret key') }}</dt>
                <dd class="col-sm-10 mb-0"><code class="user-select-all text-break">{{ $secret['secret'] }}</code></dd>
            </dl>
        </div>
    @endif

    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title mt-1"><i class="fas fa-key mr-1"></i> {{ __('Systems that call the gateway') }}</h3>
            @can('create-apiclient')
                <div class="card-tools">
                    <a href="{{ route('admin.api-clients.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i>{{ __('New API client') }}</a>
                </div>
            @endcan
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover text-nowrap mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('appid') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Last used') }}</th>
                        <th>{{ __('Key rotated') }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clients as $client)
                        <tr>
                            <td>
                                {{ $client->name }}
                                @if ($client->notes) <div class="small text-muted text-wrap">{{ $client->notes }}</div> @endif
                            </td>
                            <td><code>{{ $client->app_id }}</code></td>
                            <td>
                                @if ($client->is_active)
                                    <span class="badge badge-success">{{ __('Active') }}</span>
                                @else
                                    <span class="badge badge-secondary">{{ __('Disabled') }}</span>
                                @endif
                            </td>
                            <td class="text-muted">{{ $client->last_used_at?->diffForHumans() ?? __('Never') }}</td>
                            <td class="text-muted">{{ ($client->secret_rotated_at ?? $client->created_at)->toDateString() }}</td>
                            <td class="text-right">
                                @can('edit-apiclient')
                                    <form method="POST" action="{{ route('admin.api-clients.rotate', $client) }}" class="d-inline"
                                          data-confirm="{{ __('Create a new secret key for :name? The current key stops working at once.', ['name' => $client->name]) }}">
                                        @csrf
                                        <button class="btn btn-warning btn-sm" title="{{ __('Rotate secret key') }}"><i class="fas fa-sync-alt"></i></button>
                                    </form>
                                    <a href="{{ route('admin.api-clients.edit', $client) }}" class="btn btn-info btn-sm" title="{{ __('Edit') }}"><i class="fas fa-pen"></i></a>
                                @endcan
                                @can('delete-apiclient')
                                    <x-confirm-delete :action="route('admin.api-clients.destroy', $client)" :title="__('Delete API client :name? It can no longer call the gateway.', ['name' => $client->name])" />
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">{{ __('No API clients yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer clearfix">
            <span class="text-muted small"><i class="fas fa-info-circle mr-1"></i>{{ __('Clients sign every request to POST :url with their secret key (SHA256). Keep the key on a server, never in a browser or mobile app.', ['url' => route('api.v1.gateway')]) }}</span>
        </div>
    </div>

    <div class="card card-secondary card-outline collapsed-card" data-remember-card="api.gateway-methods">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-list mr-1"></i> {{ __('Gateway methods') }}</h3>
            <div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button></div>
        </div>
        <div class="card-body">
            <p class="text-muted">{{ __('Every client may call every method. The contract is in docs/gateway-api.md.') }}</p>
            @foreach ($methods as $method) <code class="d-inline-block mr-3 mb-1">{{ $method }}</code> @endforeach
        </div>
    </div>
</x-layouts.admin>
