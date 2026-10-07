<?php

namespace Modules\LogViewer\Services;

use Modules\LogViewer\Support\LogContents;
use Modules\LogViewer\Support\LogFile;
use RuntimeException;

/**
 * Reads at most $maxBytes from the end of a log file and parses it, newest entry first.
 */
final class LogReader
{
    public function __construct(
        private readonly LogParser $parser,
        private readonly int $maxBytes,
    ) {}

    public function read(LogFile $file): LogContents
    {
        $truncated = $file->size > $this->maxBytes;
        $content = $this->tail($file->path, $truncated ? $this->maxBytes : $file->size);

        return new LogContents($this->parser->parse($content)->reverse()->values(), $truncated);
    }

    private function tail(string $path, int $bytes): string
    {
        if ($bytes <= 0) {
            return '';
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Cannot open log file {$path}");
        }

        try {
            fseek($handle, -$bytes, SEEK_END);

            return (string) stream_get_contents($handle);
        } finally {
            fclose($handle);
        }
    }
}
