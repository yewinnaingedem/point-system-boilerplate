<?php

namespace App\Services\PerformanceData;

use Illuminate\Database\ConnectionInterface;

/**
 * Collects rows per table and writes them with multi-row INSERTs, so 100k customers' worth of
 * history goes in as a few thousand statements instead of millions.
 */
class RowBuffer
{
    /** Rows per INSERT: well under MySQL's 65,535 placeholders for the widest table. */
    private const BATCH = 1000;

    /** @var array<string, list<array<string, mixed>>> */
    private array $rows = [];

    /** @var array<string, int> */
    private array $written = [];

    /** Tables are flushed in this order, so foreign keys always point at rows already written. */
    private const ORDER = [
        'users', 'model_has_roles', 'merchants', 'merchant_branches', 'merchant_rewards', 'gift_cards',
        'customers', 'loyalty_member_statuses', 'loyalty_cycle_spendings', 'loyalty_tier_events',
        'loyalty_point_accounts', 'merchant_redemptions', 'gift_card_exchanges',
        'loyalty_point_transactions', 'loyalty_point_lots', 'loyalty_point_lot_usages', 'loyalty_point_summaries',
    ];

    public function __construct(private readonly ConnectionInterface $db) {}

    /** @param  array<string, mixed>  $row */
    public function add(string $table, array $row): void
    {
        $this->rows[$table][] = $row;
    }

    /** @param  list<array<string, mixed>>  $rows */
    public function addMany(string $table, array $rows): void
    {
        foreach ($rows as $row) {
            $this->rows[$table][] = $row;
        }
    }

    public function flush(): void
    {
        $tables = array_unique([...array_intersect(self::ORDER, array_keys($this->rows)), ...array_keys($this->rows)]);

        foreach ($tables as $table) {
            foreach (array_chunk($this->rows[$table] ?? [], self::BATCH) as $chunk) {
                $this->db->table($table)->insert($chunk);
                $this->written[$table] = ($this->written[$table] ?? 0) + count($chunk);
            }
            unset($this->rows[$table]);
        }
    }

    /** @return array<string, int> rows written per table so far */
    public function written(): array
    {
        return $this->written;
    }
}
