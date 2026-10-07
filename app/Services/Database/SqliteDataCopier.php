<?php

namespace App\Services\Database;

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use RuntimeException;

/**
 * Copies every row of a SQLite database into another connection whose schema was created by
 * the same migrations (e.g. moving local SQLite data into the Docker MySQL).
 *
 * Runs in one transaction with foreign-key checks off, so tables can be filled in any order
 * and a failure leaves the target unchanged. Framework scratch tables are skipped.
 */
final class SqliteDataCopier
{
    /** Rebuilt by the framework; copying them is pointless or harmful (stale sessions/cache). */
    private const SKIPPED_TABLES = ['migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs'];

    private const CHUNK = 500;

    private const SOURCE_CONNECTION = 'sqlite_import_source';

    public function __construct(private readonly DatabaseManager $db) {}

    /**
     * @return array<string, int> table => rows copied
     */
    public function copy(string $sqlitePath, string $targetConnection): array
    {
        if (! is_file($sqlitePath)) {
            throw new RuntimeException("SQLite file not found: {$sqlitePath}");
        }

        $source = $this->openSource($sqlitePath);
        $target = $this->db->connection($targetConnection);

        if ($target->getDriverName() === 'sqlite' && realpath((string) $target->getDatabaseName()) === realpath($sqlitePath)) {
            throw new RuntimeException('Source and target are the same database.');
        }

        $tables = $this->tablesToCopy($source, $target);
        $copied = [];

        $target->transaction(function () use ($source, $target, $tables, &$copied) {
            $target->getSchemaBuilder()->withoutForeignKeyConstraints(function () use ($source, $target, $tables, &$copied) {
                foreach ($tables as $table) {
                    $copied[$table] = $this->copyTable($source, $target, $table);
                }
            });
        });

        return $copied;
    }

    /**
     * @return list<string>
     */
    private function tablesToCopy(Connection $source, Connection $target): array
    {
        $tables = collect($source->getSchemaBuilder()->getTableListing(schemaQualified: false))
            ->reject(fn (string $table) => str_starts_with($table, 'sqlite_') || in_array($table, self::SKIPPED_TABLES, true))
            ->values();

        $missing = $tables->reject(fn (string $table) => $target->getSchemaBuilder()->hasTable($table));
        if ($missing->isNotEmpty()) {
            throw new RuntimeException('Run the migrations on the target first. Missing tables: '.$missing->implode(', '));
        }

        return $tables->all();
    }

    private function copyTable(Connection $source, Connection $target, string $table): int
    {
        $columns = array_values(array_intersect(
            $source->getSchemaBuilder()->getColumnListing($table),
            $target->getSchemaBuilder()->getColumnListing($table),
        ));

        // Replace, don't merge: the target ends up exactly like the source.
        $target->table($table)->delete();

        $count = 0;
        $batch = [];

        foreach ($source->table($table)->select($columns)->cursor() as $row) {
            $batch[] = (array) $row;
            if (count($batch) === self::CHUNK) {
                $target->table($table)->insert($batch);
                $count += count($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $target->table($table)->insert($batch);
            $count += count($batch);
        }

        return $count;
    }

    private function openSource(string $path): Connection
    {
        config(['database.connections.'.self::SOURCE_CONNECTION => [
            'driver' => 'sqlite',
            'database' => $path,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);

        $this->db->purge(self::SOURCE_CONNECTION);

        return $this->db->connection(self::SOURCE_CONNECTION);
    }
}
