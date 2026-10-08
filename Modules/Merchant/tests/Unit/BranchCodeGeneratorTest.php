<?php

namespace Modules\Merchant\Tests\Unit;

use Modules\Merchant\Support\BranchCodeGenerator;
use PHPUnit\Framework\TestCase;

class BranchCodeGeneratorTest extends TestCase
{
    public function test_codes_are_six_digits_and_never_trivial(): void
    {
        $generator = new BranchCodeGenerator;

        for ($i = 0; $i < 500; $i++) {
            $code = $generator->generate();
            $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
            $this->assertFalse($generator->isWeak($code));
        }
    }

    public function test_easy_codes_are_recognised(): void
    {
        $generator = new BranchCodeGenerator;

        foreach (['000000', '111111', '123456', '654321', '121212', '135791', '890123', '482910'] as $code) {
            $expected = $code !== '482910';
            $this->assertSame($expected, $generator->isWeak($code), $code);
        }
    }
}
