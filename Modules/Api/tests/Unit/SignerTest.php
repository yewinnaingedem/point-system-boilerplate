<?php

namespace Modules\Api\Tests\Unit;

use Modules\Api\Gateway\Signer;
use PHPUnit\Framework\TestCase;

class SignerTest extends TestCase
{
    public function test_string_to_sign_is_sorted_flattened_and_skips_sign_and_empty_values(): void
    {
        $envelope = [
            'timestamp' => '1535166225',
            'method' => 'pos.point.create',
            'nonce_str' => '5K8264ILTKCH16CQ2502SI8ZNMTM67VS',
            'sign_type' => 'SHA256',
            'sign' => 'IGNORED',
            'version' => '1.0',
            'notify_url' => '',
            'biz_content' => ['points' => 250, 'appid' => 'pos123', 'note' => null, 'flag' => true, 'items' => [['id' => 2]], 'none' => []],
        ];

        $this->assertSame(
            'biz_content.appid=pos123&biz_content.flag=true&biz_content.items.0.id=2&biz_content.points=250'
            .'&method=pos.point.create&nonce_str=5K8264ILTKCH16CQ2502SI8ZNMTM67VS&timestamp=1535166225&version=1.0',
            (new Signer)->stringToSign($envelope),
        );
    }

    public function test_sign_is_uppercase_sha256_of_the_string_and_key(): void
    {
        $signer = new Signer;
        $envelope = ['method' => 'pos.merchant.list', 'biz_content' => ['appid' => 'pos123']];

        $expected = strtoupper(hash('sha256', 'biz_content.appid=pos123&method=pos.merchant.list&key=secret'));
        $this->assertSame($expected, $signer->sign($envelope, 'secret'));
        $this->assertTrue($signer->verify($envelope, 'secret', strtolower($expected)));
        $this->assertFalse($signer->verify($envelope, 'other', $expected));
    }
}
