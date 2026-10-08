<?php

namespace App\Services\PerformanceData;

use Illuminate\Database\ConnectionInterface;

/**
 * Hands out primary keys in PHP, so rows that point at each other (ledger ↔ lots ↔ usages,
 * redemption ↔ ledger source) can be built before anything is inserted. Only safe while
 * nothing else writes to these tables: the seeder is meant for a quiet test database.
 */
class IdSequence
{
    /** @var array<string, int> */
    private array $next = [];

    public function __construct(private readonly ConnectionInterface $db) {}

    public function next(string $table): int
    {
        $this->next[$table] ??= (int) $this->db->table($table)->max('id') + 1;

        return $this->next[$table]++;
    }
}
