<?php

namespace Modules\LogViewer\Support;

use Illuminate\Support\Collection;

/**
 * Parsed entries of one file, newest first.
 */
final class LogContents
{
    /**
     * @param  Collection<int, LogEntry>  $entries
     * @param  bool  $truncated  true when only the newest part of the file was read
     */
    public function __construct(
        public readonly Collection $entries,
        public readonly bool $truncated,
    ) {}

    /**
     * @return array<string, int> level value => number of entries, for every level
     */
    public function countsByLevel(): array
    {
        $counts = $this->entries->countBy(fn (LogEntry $entry) => $entry->level->value);

        return collect(LogLevel::cases())
            ->mapWithKeys(fn (LogLevel $level) => [$level->value => $counts->get($level->value, 0)])
            ->all();
    }

    /**
     * @return Collection<int, LogEntry>
     */
    public function filter(?LogLevel $level, ?string $search): Collection
    {
        return $this->entries
            ->when($level, fn ($entries) => $entries->filter(fn (LogEntry $e) => $e->level === $level))
            ->when(filled($search), fn ($entries) => $entries->filter(fn (LogEntry $e) => $e->matches($search)))
            ->values();
    }
}
