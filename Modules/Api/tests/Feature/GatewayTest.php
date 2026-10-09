<?php

namespace Modules\Api\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Api\Tests\Feature\Concerns\CallsGateway;
use Modules\Customer\Models\Customer;
use Tests\TestCase;

/**
 * The signed envelope: who may call, what a valid request looks like, and what comes back.
 */
class GatewayTest extends TestCase
{
    use CallsGateway, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeGatewayClient();
    }

    public function test_a_signed_request_succeeds_and_the_response_is_signed(): void
    {
        $response = $this->gateway('pos.customer.register', ['external_id' => 'shop-7', 'name' => 'Aung Aung', 'email' => 'aung@example.com'])
            ->assertOk()
            ->assertJsonPath('Response.result', 'SUCCESS')
            ->assertJsonPath('Response.code', '0')
            ->assertJsonPath('Response.method', 'pos.customer.register')
            ->assertJsonPath('Response.biz_content.created', true)
            ->assertJsonPath('Response.biz_content.customer.tier.level', 'silver');

        $this->assertSignedResponse($response);
        $this->assertSame('Aung Aung', Customer::query()->sole()->name);
        $this->assertNotNull($this->client->fresh()->last_used_at);
    }

    public function test_a_wrong_key_or_changed_content_is_refused(): void
    {
        $this->gateway('pos.customer.register', ['external_id' => 'x', 'name' => 'X'], secret: str_repeat('a', 64))
            ->assertUnauthorized()->assertJsonPath('Response.code', 'INVALID_SIGNATURE');

        // Signed for one customer, sent for another.
        $this->gateway('pos.customer.register', ['external_id' => 'x', 'name' => 'X'], ['biz_content.external_id' => 'y'])
            ->assertUnauthorized()->assertJsonPath('Response.code', 'INVALID_SIGNATURE');

        $this->assertSame(0, Customer::query()->count());
    }

    public function test_the_server_string_to_sign_is_shown_only_in_debug_mode(): void
    {
        config(['app.debug' => true]);
        $response = $this->gateway('pos.merchant.list', secret: str_repeat('b', 64))->assertUnauthorized();
        $this->assertSame("biz_content.appid={$this->client->app_id}&method=pos.merchant.list", substr($response->json('Response.debug.string_to_sign'), 0, strlen("biz_content.appid={$this->client->app_id}&method=pos.merchant.list")));
        $this->assertStringNotContainsString($this->secret, $response->getContent());
        $this->assertSignedResponse($response);

        config(['app.debug' => false]);
        $this->assertArrayNotHasKey('debug', $this->gateway('pos.merchant.list', secret: str_repeat('b', 64))->json('Response'));
    }

    public function test_unknown_or_disabled_appid_is_refused_without_a_signature(): void
    {
        $response = $this->gateway('pos.merchant.list', overrides: ['biz_content.appid' => 'nope'])
            ->assertUnauthorized()->assertJsonPath('Response.code', 'INVALID_APPID');
        $this->assertArrayNotHasKey('sign', $response->json('Response'));

        $this->client->update(['is_active' => false]);
        $this->gateway('pos.merchant.list')->assertUnauthorized()->assertJsonPath('Response.code', 'INVALID_APPID');
    }

    public function test_an_old_timestamp_is_refused(): void
    {
        $request = $this->envelope('pos.merchant.list');
        $this->travel(6)->minutes();

        $this->postJson('/api/v1/gateway', ['Request' => $request])
            ->assertUnauthorized()->assertJsonPath('Response.code', 'TIMESTAMP_EXPIRED');
    }

    public function test_a_nonce_works_once(): void
    {
        $request = $this->envelope('pos.merchant.list');

        $this->postJson('/api/v1/gateway', ['Request' => $request])->assertOk();
        $this->postJson('/api/v1/gateway', ['Request' => $request])
            ->assertStatus(409)->assertJsonPath('Response.code', 'DUPLICATE_NONCE');
    }

    public function test_a_malformed_envelope_is_a_400_with_the_fields(): void
    {
        $this->postJson('/api/v1/gateway', ['request' => []])
            ->assertStatus(400)->assertJsonPath('Response.code', 'INVALID_REQUEST');

        $this->gateway('pos.merchant.list', overrides: ['sign_type' => 'MD5', 'version' => '2.0', 'nonce_str' => 'short', 'timestamp' => '1535166225000'])
            ->assertStatus(400)
            ->assertJsonPath('Response.code', 'INVALID_REQUEST')
            ->assertJsonStructure(['Response' => ['errors' => ['sign_type', 'version', 'nonce_str', 'timestamp']]]);

        $this->gateway('pos.customer.register', ['external_id' => ['nested' => 1], 'name' => 'X'])
            ->assertStatus(400)->assertJsonStructure(['Response' => ['errors' => ['biz_content.external_id']]]);
    }

    public function test_unknown_method_and_invalid_biz_content(): void
    {
        $this->gateway('pos.nothing')->assertStatus(400)->assertJsonPath('Response.code', 'UNKNOWN_METHOD');

        $response = $this->gateway('pos.point.create', ['external_id' => 'shop-7', 'points' => '0', 'email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonPath('Response.result', 'FAIL')
            ->assertJsonPath('Response.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['Response' => ['errors' => ['points', 'reference', 'email']]]);
        $this->assertSignedResponse($response);
    }

    public function test_calls_are_rate_limited_per_client(): void
    {
        config(['api.gateway.rate_per_minute' => 2]);

        $this->gateway('pos.merchant.list')->assertOk();
        $this->gateway('pos.merchant.list')->assertOk();
        $this->gateway('pos.merchant.list')->assertStatus(429)->assertJsonPath('Response.code', 'RATE_LIMITED');
    }
}
