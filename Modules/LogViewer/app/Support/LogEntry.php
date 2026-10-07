<?php

namespace Modules\LogViewer\Support;

use Carbon\CarbonImmutable;

/**
 * One log record: the header line plus everything up to the next header (context, stack trace).
 */
final class LogEntry
{
    public function __construct(
        public readonly CarbonImmutable $loggedAt,
        public readonly string $environment,
        public readonly LogLevel $level,
        public readonly string $message,
        public readonly string $details,
    ) {}

    public function matches(string $term): bool
    {
        return stripos($this->message, $term) !== false || stripos($this->details, $term) !== false;
    }
}
