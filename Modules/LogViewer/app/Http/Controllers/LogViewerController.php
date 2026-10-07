<?php

namespace Modules\LogViewer\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Modules\LogViewer\Services\LogFileRepository;
use Modules\LogViewer\Services\LogLevelCounter;
use Modules\LogViewer\Services\LogReader;
use Modules\LogViewer\Support\LogFile;
use Modules\LogViewer\Support\LogLevel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LogViewerController extends Controller
{
    public function __construct(
        private readonly LogFileRepository $files,
        private readonly LogReader $reader,
    ) {}

    public function index(LogLevelCounter $counter): View
    {
        $files = $this->files->all();
        $counts = $files->mapWithKeys(fn (LogFile $file) => [$file->name => $counter->count($file)]);

        return view('logviewer::index', [
            'files' => $files,
            'counts' => $counts,
            'totals' => collect(LogLevel::cases())
                ->mapWithKeys(fn (LogLevel $level) => [$level->value => $counts->sum($level->value)])
                ->all(),
        ]);
    }

    public function show(Request $request, string $file): View
    {
        $log = $this->findOrFail($file);
        $filters = $request->validate([
            'level' => ['nullable', 'in:'.implode(',', array_column(LogLevel::cases(), 'value'))],
            'search' => ['nullable', 'string', 'max:200'],
        ]);

        $contents = $this->reader->read($log);
        $level = isset($filters['level']) ? LogLevel::from($filters['level']) : null;
        $matching = $contents->filter($level, $filters['search'] ?? null);

        $perPage = config('logviewer.per_page');
        $page = LengthAwarePaginator::resolveCurrentPage();
        $entries = new LengthAwarePaginator(
            $matching->forPage($page, $perPage)->values(),
            $matching->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('logviewer::show', [
            'file' => $log,
            'entries' => $entries,
            'counts' => $contents->countsByLevel(),
            'total' => $contents->entries->count(),
            'truncated' => $contents->truncated,
            'level' => $level,
            'maxBytes' => config('logviewer.max_bytes'),
        ]);
    }

    public function download(string $file): BinaryFileResponse
    {
        $log = $this->findOrFail($file);

        return response()->download($log->path, $log->name);
    }

    public function destroy(string $file): RedirectResponse
    {
        $log = $this->findOrFail($file);
        $this->files->delete($log);

        return redirect()->route('admin.logs.index')
            ->with('success', __('Log :name deleted.', ['name' => $log->name]));
    }

    private function findOrFail(string $name): LogFile
    {
        return $this->files->find($name) ?? abort(404);
    }
}
