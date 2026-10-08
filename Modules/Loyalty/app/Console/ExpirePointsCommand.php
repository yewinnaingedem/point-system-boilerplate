<?php

namespace Modules\Loyalty\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Loyalty\Services\PointWallet;
use Throwable;

/**
 * Expires points that reached their expiry date (settings Loyalty → points expire after).
 * Spending and balance checks also expire on the spot; this keeps everyone's balance and the
 * monthly summary current even for customers who don't come back.
 */
class ExpirePointsCommand extends Command
{
    protected $signature = 'loyalty:expire-points';

    protected $description = 'Expire customer points that have passed their expiry date';

    public function handle(PointWallet $wallet): int
    {
        $now = CarbonImmutable::now();
        $customers = 0;
        $points = 0;
        $failed = 0;

        foreach ($wallet->customersWithDuePoints($now) as $customerId) {
            try {
                $tx = $wallet->expireDue($customerId, $now);
                if ($tx !== null) {
                    $customers++;
                    $points += -$tx->points;
                }
            } catch (Throwable $e) {
                report($e); // one bad account must not stop the sweep; retried tomorrow
                $failed++;
            }
        }

        $this->info("Expired {$points} points of {$customers} customer(s), {$failed} failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
