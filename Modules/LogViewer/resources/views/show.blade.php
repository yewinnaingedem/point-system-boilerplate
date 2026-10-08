@php use Modules\LogViewer\Support\LogLevel; @endphp
<x-layouts.admin :title="__('Log of :date', ['date' => $file->date->format(setting('date_format'))])" :breadcrumbs="[__('Logs') => route('admin.logs.index')]">
    @if ($truncated)
        <div class="callout callout-warning">
            <i class="fas fa-exclamation-triangle text-warning mr-1"></i>
            {{ __('This file is :size. Only the newest :limit are shown; download it to see everything.', ['size' => $file->humanSize(), 'limit' => round($maxBytes / 1048576, 1).' MB']) }}
        </div>
    @endif

    <div class="row">
        <div class="col-lg-3">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ __('Levels') }}</h3>
                </div>
                <div class="card-body ">
                    <ul class="nav nav-pills flex-column">
                        <li class="nav-item">
                            <a href="{{ route('admin.logs.show', ['file' => $file->name, 'search' => request('search')]) }}" @class(['nav-link', 'active' => $level === null])>
                                <i class="fas fa-layer-group mr-2" style="width: 1rem"></i> {{ __('All') }}
                                <span class="badge badge-dark float-right">{{ $total }}</span>
                            </a>
                        </li>
                        @foreach (LogLevel::cases() as $case)
                            <li class="nav-item">
                                <a href="{{ route('admin.logs.show', ['file' => $file->name, 'level' => $case->value, 'search' => request('search')]) }}" @class(['nav-link', 'active' => $level === $case, 'text-muted' => $counts[$case->value] === 0 && $level !== $case])>
                                    <i class="{{ $case->icon() }} mr-2 text-{{ $case->color() }}" style="width: 1rem"></i> {{ __($case->label()) }}
                                    <span class="badge badge-{{ $case->color() }} float-right">{{ $counts[$case->value] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <dl class="mb-0 small">
                        <dt>{{ __('File') }}</dt><dd class="text-monospace">{{ $file->name }}</dd>
                        <dt>{{ __('Size') }}</dt><dd>{{ $file->humanSize() }}</dd>
                        <dt>{{ __('Last modified') }}</dt><dd class="mb-0">{{ $file->modifiedAt->format(setting('date_format').' H:i:s') }}</dd>
                    </dl>
                </div>
                <div class="card-footer">
                    @can('download-logviewer')
                        <a href="{{ route('admin.logs.download', $file->name) }}" class="btn btn-success btn-sm"><i class="fas fa-download mr-1"></i>{{ __('Download') }}</a>
                    @endcan
                    @can('delete-logviewer')
                        <span class="float-right"><x-confirm-delete :action="route('admin.logs.destroy', $file->name)" :title="__('Delete :name?', ['name' => $file->name])" /></span>
                    @endcan
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title mt-1">
                        @if ($level)
                            <i class="{{ $level->icon() }} text-{{ $level->color() }} mr-1"></i> {{ __($level->label()) }}
                        @else
                            <i class="fas fa-stream mr-1"></i> {{ __('All entries') }}
                        @endif
                    </h3>
                    <div class="card-tools">
                        <form method="GET" class="input-group input-group-sm" style="width: 260px">
                            @if ($level) <input type="hidden" name="level" value="{{ $level->value }}"> @endif
                            <input type="search" name="search" value="{{ request('search') }}" class="form-control float-right" placeholder="{{ __('Search messages…') }}">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-default"><i class="fas fa-search"></i></button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card-body table-responsive p-0">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 110px">{{ __('Level') }}</th>
                                <th style="width: 170px">{{ __('Time') }}</th>
                                <th>{{ __('Message') }}</th>
                                <th style="width: 50px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($entries as $entry)
                                @php($rowId = 'log-entry-'.$loop->index)
                                <tr>
                                    <td><span class="badge badge-{{ $entry->level->color() }}"><i class="{{ $entry->level->icon() }} mr-1"></i>{{ $entry->level->label() }}</span></td>
                                    <td class="text-nowrap">
                                        {{ $entry->loggedAt->format('Y-m-d H:i:s') }}
                                        <div class="small text-muted">{{ $entry->environment }} · {{ $entry->loggedAt->diffForHumans() }}</div>
                                    </td>
                                    <td class="text-break">{{ \Illuminate\Support\Str::limit($entry->message, 300) }}</td>
                                    <td class="text-right">
                                        @if ($entry->details !== '' || mb_strlen($entry->message) > 300)
                                            <button type="button" class="btn btn-tool" data-toggle="collapse" data-target="#{{ $rowId }}" title="{{ __('Details') }}">
                                                <i class="fas fa-search-plus"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                                @if ($entry->details !== '' || mb_strlen($entry->message) > 300)
                                    <tr class="collapse" id="{{ $rowId }}">
                                        <td colspan="4" class="bg-light p-0">
                                            <pre class="m-0 p-3 small" style="max-height: 420px; overflow: auto; white-space: pre-wrap; word-break: break-word;">{{ $entry->message }}@if ($entry->details !== '')

{{ $entry->details }}@endif</pre>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-5">{{ __('No entries match.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="card-footer clearfix">
                    <span class="text-muted small">{{ __('Showing :from–:to of :total', ['from' => $entries->firstItem() ?? 0, 'to' => $entries->lastItem() ?? 0, 'total' => $entries->total()]) }}</span>
                    <div class="float-right">{{ $entries->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
