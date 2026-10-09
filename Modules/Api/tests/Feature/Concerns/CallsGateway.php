<?php

namespace Modules\Api\Tests\Feature\Concerns;

use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Api\Gateway\Signer;
use Modules\Api\Models\ApiClient;
use Modules\Api\Services\ApiClientService;

/** Builds and signs gateway envelopes the way a caller does. */
trait CallsGateway
{
    protected ApiClient $client;

    protected string $secret;

    protected function makeGatewayClient(): void
    {
        [$this->client, $this->secret] = app(ApiClientService::class)->create(['name' => 'Partner site', 'is_active' => true]);
    }

    /**
     * @param  array<string, mixed>  $biz  biz_content without appid
     * @param  array<string, mixed>  $overrides  envelope fields to replace after signing (null removes)
     */
    protected function gateway(string $method, array $biz = [], array $overrides = [], ?string $secret = null): TestResponse
    {
        $request = $this->envelope($method, $biz, $secret);
        foreach ($overrides as $key => $value) {
            data_set($request, $key, $value);
        }

        return $this->postJson('/api/v1/gateway', ['Request' => $request]);
    }

    /** @return array<string, mixed> */
    protected function envelope(string $method, array $biz = [], ?string $secret = null): array
    {
        $request = [
            'timestamp' => (string) now()->getTimestamp(),
            'method' => $method,
            'nonce_str' => Str::upper(Str::random(32)),
            'version' => '1.0',
            'biz_content' => ['appid' => $this->client->app_id, ...$biz],
        ];
        $request['sign_type'] = Signer::TYPE;
        $request['sign'] = app(Signer::class)->sign($request, $secret ?? $this->secret);

        return $request;
    }

    protected function assertSignedResponse(TestResponse $response): void
    {
        $body = $response->json('Response');
        $this->assertSame('SHA256', $body['sign_type']);
        $this->assertTrue(app(Signer::class)->verify($body, $this->secret, $body['sign']), 'The response signature does not match.');
    }
}
