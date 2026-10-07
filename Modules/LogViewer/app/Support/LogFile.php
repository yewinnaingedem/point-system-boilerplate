<?php

namespace Modules\LogViewer\Support;

use Carbon\CarbonImmutable;

final class LogFile
{
    public function __construct(
        public readonly string $name,
        public readonly string $path,
        public readonly int $size,
        public readonly CarbonImmutable $modifiedAt,
        public readonly CarbonImmutable $date,
    ) {}

    /**
     * Changes whenever the file is written, so cached counts for it go stale automatically.
     */
    public function fingerprint(): string
    {
        return "{$this->name}:{$this->size}:{$this->modifiedAt->getTimestamp()}";
    }

    public function humanSize(): string
    {
        return self::formatBytes($this->size);
    }

    public static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 1).' '.$units[$unit];
    }
}
