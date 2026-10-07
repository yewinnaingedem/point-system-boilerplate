<?php

namespace Modules\LogViewer\Services;

use Illuminate\Contracts\Cache\Repository as Cache;
use Modules\LogViewer\Support\LogFile;
use Modules\LogViewer\Support\LogLevel;
use RuntimeException;

/**
 * Entries per level for a whole file, for the daily overview table.
 *
 * Reads line by line and only looks at entry headers, so even a large day costs little
 * memory. Results are cached per file fingerprint (name + size + mtime): finished days are
 * counted once, and today's file is recounted only after it has been written to.
 */
final class LogLevelCounter
{
    private const CACHE_PREFIX = 'logviewer.counts.';

    private const CACHE_DAYS = 31;

    private const HEADER = '/^\[\d{4}-\d{2}-\d{2}[T ][\d:.+\-Z]+\]\s[\w.-]+\.([A-Za-z]+):/';

    public function __construct(private readonly Cache $cache) {}

    /**
     * @return array<string, int> level value => entries, for every level
     */
    public function count(LogFile $file): array
    {
        return $this->cache->remember(
            self::CACHE_PREFIX.sha1($file->fingerprint()),
            now()->addDays(self::CACHE_DAYS),
            fn () => $this->countFile($file->path),
        );
    }

    /**
     * @return array<string, int>
     */
    private function countFile(string $path): array
    {
        $counts = array_fill_keys(array_column(LogLevel::cases(), 'value'), 0);

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Cannot open log file {$path}");
        }

        try {
            while (($line = fgets($handle)) !== false) {
                if ($line[0] === '[' && preg_match(self::HEADER, $line, $match)) {
                    $counts[LogLevel::fromLogName($match[1])->value]++;
                }
            }
        } finally {
            fclose($handle);
        }

        return $counts;
    }
}
