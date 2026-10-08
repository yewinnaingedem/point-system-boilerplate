<?php

namespace Modules\Loyalty\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Loyalty\Models\MemberTierStatus;
use Modules\Loyalty\Services\TierQualificationEngine;
use Throwable;

/**
 * Applies cycle rollovers and guarantee expiries for members without new sales, so stored
 * tiers stay current. Sales already evaluate on the spot; this only catches the quiet ones.
 * Picks rows by the indexed next_evaluation_at, so it touches only members that are due.
 */
class EvaluateTiersCommand extends Command
{
    protected $signature = 'loyalty:evaluate-tiers';

    protected $description = 'Roll over ended loyalty cycles and demote members whose tier guarantee has expired';

    private const CHUNK = 500;

    public function handle(TierQualificationEngine $engine): int
    {
        $now = CarbonImmutable::now();
        $evaluated = 0;
        $failed = 0;

        MemberTierStatus::query()
            ->dueForEvaluation($now)
            ->select(['id', 'customer_id'])
            ->chunkById(self::CHUNK, function ($statuses) use ($engine, $now, &$evaluated, &$failed) {
                foreach ($statuses as $status) {
                    try {
                        $engine->evaluateUserTierStatus($status->customer_id, $now);
                        $evaluated++;
                    } catch (Throwable $e) {
                        // One bad row must not stop the run; it is retried next time.
                        report($e);
                        $failed++;
                    }
                }
            });

        $this->info("Evaluated {$evaluated} member(s), {$failed} failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
