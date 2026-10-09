<?php

namespace Modules\Api\Gateway;

use Illuminate\Support\Arr;

/**
 * KBZPay-style signature over a gateway envelope (Request or Response object):
 *
 *   1. flatten to dot keys (`biz_content.appid`, `biz_content.items.0.name`),
 *   2. drop `sign` and `sign_type`, and every null, empty-string or empty-array value,
 *   3. sort by key (byte order) and join as `k1=v1&k2=v2` (true/false for booleans),
 *   4. append `&key=<secret>`, SHA-256, uppercase hex.
 */
class Signer
{
    public const TYPE = 'SHA256';

    private const UNSIGNED = ['sign', 'sign_type'];

    /** @param  array<string, mixed>  $envelope */
    public function sign(array $envelope, string $secret): string
    {
        return strtoupper(hash('sha256', $this->stringToSign($envelope)."&key={$secret}"));
    }

    /** @param  array<string, mixed>  $envelope */
    public function verify(array $envelope, string $secret, string $signature): bool
    {
        return hash_equals($this->sign($envelope, $secret), strtoupper($signature));
    }

    /** @param  array<string, mixed>  $envelope */
    public function stringToSign(array $envelope): string
    {
        $pairs = [];
        foreach (Arr::dot(Arr::except($envelope, self::UNSIGNED)) as $key => $value) {
            if ($value === null || $value === '' || is_array($value)) {
                continue; // empty arrays are left as [] by Arr::dot
            }
            $pairs[(string) $key] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }
        ksort($pairs, SORT_STRING);

        return implode('&', array_map(fn ($key, $value) => "{$key}={$value}", array_keys($pairs), $pairs));
    }
}
