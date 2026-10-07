<?php

namespace Modules\LogViewer\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\LogViewer\Support\LogEntry;
use Modules\LogViewer\Support\LogLevel;

/**
 * Splits Laravel's Monolog line format into entries:
 *
 *     [2026-10-07 08:50:41] production.ERROR: Message {"context":1}
 *     #0 /path/to/file.php(12): ...      ← continuation lines belong to the entry above
 */
final class LogParser
{
    private const HEADER = '/^\[(?<date>\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[+-]\d{2}:?\d{2}|Z)?)\]\s(?<env>[\w.-]+)\.(?<level>[A-Za-z]+):\s?(?<message>.*)$/';

    /**
     * @return Collection<int, LogEntry> in file order (oldest first)
     */
    public function parse(string $content): Collection
    {
        $entries = [];
        $current = null;

        foreach (preg_split('/\R/', $content) as $line) {
            if (preg_match(self::HEADER, $line, $match)) {
                if ($current !== null) {
                    $entries[] = $this->build($current);
                }
                $current = ['header' => $match, 'details' => []];
            } elseif ($current !== null) {
                $current['details'][] = $line;
            }
            // Lines before the first header (a cut-off entry) are dropped.
        }

        if ($current !== null) {
            $entries[] = $this->build($current);
        }

        return collect($entries);
    }

    /**
     * @param  array{header: array<string, string>, details: list<string>}  $raw
     */
    private function build(array $raw): LogEntry
    {
        $header = $raw['header'];

        return new LogEntry(
            loggedAt: CarbonImmutable::parse($header['date']),
            environment: $header['env'],
            level: LogLevel::fromLogName($header['level']),
            message: $header['message'],
            details: rtrim(implode("\n", $raw['details'])),
        );
    }
}
