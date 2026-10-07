@php
    use Modules\LogViewer\Support\LogFile;
    use Modules\LogViewer\Support\LogLevel;
    $levels = LogLevel::cases();
@endphp
<x-layouts.admin :title="__('Logs')">
    <div class="row">
        @foreach ($levels as $level)
            <div class="col-6 col-md-3">
                <div class="info-box mb-3">
                    <span class="info-box-icon bg-{{ $level->color() }} elevation-1"><i class="{{ $level->icon() }}"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __($level->label()) }}</span>
                        <span class="info-box-number">{{ number_format($totals[$level->value]) }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title mt-1"><i class="fas fa-calendar-alt mr-1"></i> {{ __('Daily logs') }}</h3>
            <div class="card-tools text-muted small mt-2">
                {{ trans_choice(':count day|:count days', $files->count()) }} · {{ LogFile::formatBytes($files->sum('size')) }}
                · {{ __('kept for :days days', ['days' => config('logging.channels.daily.days')]) }}
            </div>
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover table-sm text-nowrap mb-0">
                <thead>
                    <tr>
                        <th class="pl-3">{{ __('Date') }}</th>
                        <th class="text-center">{{ __('All') }}</th>
                        @foreach ($levels as $level)
                            <th class="text-center" title="{{ __($level->label()) }}"><i class="{{ $level->icon() }} text-{{ $level->color() }}"></i></th>
                        @endforeach
                        <th>{{ __('Size') }}</th>
                        <th class="text-right pr-3">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($files as $file)
                        @php($row = $counts[$file->name])
                        <tr>
                            <td class="pl-3">
                                <a href="{{ route('admin.logs.show', $file->name) }}" class="font-weight-bold">{{ $file->date->format(setting('date_format')) }}</a>
                                <span class="text-muted small ml-1">{{ $file->date->translatedFormat('l') }}</span>
                                @if ($file->date->isToday()) <span class="badge badge-success ml-1">{{ __('Today') }}</span> @endif
                            </td>
                            <td class="text-center"><span class="badge badge-dark">{{ array_sum($row) }}</span></td>
                            @foreach ($levels as $level)
                                <td class="text-center">
                                    @if ($row[$level->value] > 0)
                                        <a href="{{ route('admin.logs.show', ['file' => $file->name, 'level' => $level->value]) }}" class="badge badge-{{ $level->color() }}">{{ $row[$level->value] }}</a>
                                    @else
                                        <span class="text-muted">·</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="text-muted">{{ $file->humanSize() }}</td>
                            <td class="text-right pr-3">
                                <div class="btn-group">
                                    <a href="{{ route('admin.logs.show', $file->name) }}" class="btn btn-info btn-xs" title="{{ __('View') }}"><i class="fas fa-search"></i></a>
                                    @can('download-logviewer')
                                        <a href="{{ route('admin.logs.download', $file->name) }}" class="btn btn-success btn-xs" title="{{ __('Download') }}"><i class="fas fa-download"></i></a>
                                    @endcan
                                    @can('delete-logviewer')
                                        <button type="button" class="btn btn-danger btn-xs" data-confirm-delete="{{ route('admin.logs.destroy', $file->name) }}"
                                                data-title="{{ __('Delete the log of :date?', ['date' => $file->date->format(setting('date_format'))]) }}" title="{{ __('Delete') }}">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($levels) + 4 }}" class="text-center text-muted py-5"><i class="fas fa-check-circle text-success mr-1"></i>{{ __('No daily logs yet. Nothing has been logged.') }}</td></tr>
                    @endforelse
                </tbody>
                @if ($files->isNotEmpty())
                    <tfoot>
                        <tr class="font-weight-bold">
                            <td class="pl-3">{{ __('Total') }}</td>
                            <td class="text-center">{{ array_sum($totals) }}</td>
                            @foreach ($levels as $level)
                                <td class="text-center">{{ $totals[$level->value] ?: '·' }}</td>
                            @endforeach
                            <td>{{ LogFile::formatBytes($files->sum('size')) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</x-layouts.admin>
