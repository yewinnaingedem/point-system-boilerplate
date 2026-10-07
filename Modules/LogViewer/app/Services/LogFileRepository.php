<?php

namespace Modules\LogViewer\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Modules\LogViewer\Support\LogFile;

/**
 * The daily log files ("<prefix>-YYYY-MM-DD.log") in one folder, one per day.
 *
 * Names from the URL are matched against the real listing, so "../" or any other file
 * in or outside the folder can never be opened.
 */
final class LogFileRepository
{
    public function __construct(
        private readonly string $directory,
        private readonly string $prefix,
    ) {}

    /**
     * @return Collection<int, LogFile> newest day first
     */
    public function all(): Collection
    {
        if (! is_dir($this->directory)) {
            return collect();
        }

        $pattern = '/^'.preg_quote($this->prefix, '/').'-(\d{4}-\d{2}-\d{2})\.log$/';
        $timezone = config('app.timezone');

        return collect(glob($this->directory.DIRECTORY_SEPARATOR.'*.log') ?: [])
            ->filter(fn (string $path) => is_file($path) && preg_match($pattern, basename($path)))
            ->map(function (string $path) use ($pattern, $timezone) {
                preg_match($pattern, basename($path), $match);

                return new LogFile(
                    name: basename($path),
                    path: $path,
                    size: (int) filesize($path),
                    // Carbon 3 defaults timestamps to UTC; show them in the app's time zone like the entries.
                    modifiedAt: CarbonImmutable::createFromTimestamp(filemtime($path), $timezone),
                    date: CarbonImmutable::createFromFormat('!Y-m-d', $match[1], $timezone),
                );
            })
            ->sortByDesc(fn (LogFile $file) => $file->date->getTimestamp())
            ->values();
    }

    public function find(string $name): ?LogFile
    {
        return $this->all()->first(fn (LogFile $file) => $file->name === $name);
    }

    public function delete(LogFile $file): void
    {
        File::delete($file->path);
    }
}
