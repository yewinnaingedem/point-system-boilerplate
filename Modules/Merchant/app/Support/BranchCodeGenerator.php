<?php

namespace Modules\Merchant\Support;

/**
 * Random 6-digit branch codes. Easy-to-guess ones (000000, 123456, 112233-style repeats)
 * are skipped, since a shop's code is what stops a member confirming their own redemption.
 */
class BranchCodeGenerator
{
    public const LENGTH = 6;

    public function generate(): string
    {
        do {
            $code = str_pad((string) random_int(0, 10 ** self::LENGTH - 1), self::LENGTH, '0', STR_PAD_LEFT);
        } while ($this->isWeak($code));

        return $code;
    }

    public function isWeak(string $code): bool
    {
        $digits = array_map('intval', str_split($code));
        // Step between neighbours, wrapping round 9 -> 0, so 890123 and 135791 count as runs.
        $steps = array_map(fn (int $i) => ($digits[$i + 1] - $digits[$i] + 10) % 10, range(0, self::LENGTH - 2));

        return count(array_unique($digits)) <= 2   // 000000, 121212, 111222
            || count(array_unique($steps)) === 1;  // 123456, 654321, 135791, 890123
    }
}
