<?php

namespace App\Console\Commands;

use App\Services\Database\SqliteDataCopier;
use Illuminate\Console\Command;

/**
 * Moves data from a SQLite file into the app's database (e.g. the Docker MySQL).
 * Migrate the target first; every copied table is replaced, not merged.
 */
class ImportSqliteDatabase extends Command
{
    protected $signature = 'db:import-sqlite
        {path=database/database.sqlite : SQLite file to read}
        {--connection= : Target connection (default: the app\'s default connection)}
        {--force : Skip the confirmation}';

    protected $description = 'Copy all rows from a SQLite database into the target database (replaces its data)';

    public function handle(SqliteDataCopier $copier): int
    {
        $path = $this->resolvePath((string) $this->argument('path'));
        $connection = $this->option('connection') ?: config('database.default');
        $target = config("database.connections.{$connection}");

        $this->components->info("Copy {$path} → {$connection} ({$target['driver']} ".($target['host'] ?? '').':'.($target['port'] ?? '')."/{$target['database']})");

        if (! $this->option('force') && ! $this->confirm('Every copied table in the target is emptied and replaced. Continue?')) {
            return self::FAILURE;
        }

        $copied = $copier->copy($path, $connection);

        $this->table(['Table', 'Rows'], collect($copied)->map(fn (int $rows, string $table) => [$table, $rows])->values()->all());
        $this->components->info(array_sum($copied).' rows copied in '.count($copied).' tables.');

        return self::SUCCESS;
    }

    private function resolvePath(string $path): string
    {
        return str_starts_with($path, '/') ? $path : base_path($path);
    }
}
