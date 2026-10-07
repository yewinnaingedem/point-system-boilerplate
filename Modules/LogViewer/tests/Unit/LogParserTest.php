<?php

namespace Modules\LogViewer\Tests\Unit;

use Modules\LogViewer\Services\LogParser;
use Modules\LogViewer\Support\LogLevel;
use PHPUnit\Framework\TestCase;

class LogParserTest extends TestCase
{
    public function test_it_splits_entries_and_keeps_stack_traces_with_their_entry(): void
    {
        $log = <<<'LOG'
        #3 cut-off trace from an older entry
        [2026-10-07 08:00:00] local.INFO: Started {"user":1}
        [2026-10-07 08:01:00] production.ERROR: Division by zero
        #0 /app/Foo.php(12): bar()
        #1 {main}
        [2026-10-07T08:02:00.123456+06:30] local.WARNING: Low stock
        LOG;

        $entries = (new LogParser)->parse($log);

        $this->assertCount(3, $entries);
        $this->assertSame(LogLevel::Info, $entries[0]->level);
        $this->assertSame('Started {"user":1}', $entries[0]->message);
        $this->assertSame('production', $entries[1]->environment);
        $this->assertSame("#0 /app/Foo.php(12): bar()\n#1 {main}", $entries[1]->details);
        $this->assertSame(LogLevel::Warning, $entries[2]->level);
    }
}
