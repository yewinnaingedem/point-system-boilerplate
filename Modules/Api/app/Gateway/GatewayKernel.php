<?php

namespace Modules\Api\Gateway;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Modules\Api\Models\ApiClient;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * POST /api/v1/gateway: one signed envelope, many methods (KBZPay style).
 *
 *   {"Request": {"timestamp", "method", "nonce_str", "sign_type", "sign", "version",
 *                "biz_content": {"appid", ...method fields}}}
 *
 * Checks, in order: envelope shape → client (appid, active) → rate limit → signature →
 * timestamp window → nonce used once → method → biz_content rules → the method itself.
 * Every answer, success or failure, is a {"Response": {...}} envelope, signed with the
 * client's key once the client is known.
 */
class GatewayKernel
{
    public function __construct(
        private readonly Signer $signer,
        private readonly GatewayMethods $methods,
        private readonly GatewayEnvelope $envelope,
        private readonly ValidationFactory $validator,
        private readonly Cache $cache,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $client = null;
        $method = null;

        try {
            $payload = $request->json('Request');
            if (! is_array($payload)) {
                throw GatewayError::invalidRequest(['Request' => [__('The body must be a JSON object with a "Request" object.')]]);
            }

            $this->validateEnvelope($payload);
            $method = $payload['method'];
            $client = $this->client((string) $payload['biz_content']['appid']);
            $this->throttle($client, (string) $request->ip());
            // IP allow-list per client: check $request->ip() against the client's list here (not built yet).
            $this->authenticate($payload, $client, (string) $request->ip());

            $handler = $this->methods->resolve($method)
                ?? throw new GatewayError('UNKNOWN_METHOD', __('Unknown method ":method".', ['method' => $method]), Response::HTTP_BAD_REQUEST);
            $data = $handler->handle($this->validateBizContent($handler, $payload['biz_content']), $client);
            $this->touch($client);

            return $this->envelope->success($method, $data, $client);
        } catch (GatewayError $e) {
            return $this->envelope->failure($e, $method, $client);
        } catch (ValidationException $e) {
            return $this->envelope->failure(GatewayError::validation($e->errors()), $method, $client);
        } catch (ModelNotFoundException) {
            return $this->envelope->failure(GatewayError::notFound('NOT_FOUND', __('The record was not found.')), $method, $client);
        } catch (Throwable $e) {
            report($e);

            return $this->envelope->failure(new GatewayError('SYSTEM_ERROR', __('Something went wrong. Try again later.'), Response::HTTP_INTERNAL_SERVER_ERROR), $method, $client);
        }
    }

    /** @param  array<string, mixed>  $payload */
    private function validateEnvelope(array $payload): void
    {
        $validator = $this->validator->make($payload, [
            'timestamp' => ['required', 'regex:/^\d{10}$/'],
            'method' => ['required', 'string', 'max:64'],
            'nonce_str' => ['required', 'string', 'regex:/^[A-Za-z0-9]{16,32}$/'],
            'sign_type' => ['required', 'string', 'in:'.Signer::TYPE],
            'sign' => ['required', 'string', 'regex:/^[A-Fa-f0-9]{64}$/'],
            'version' => ['required', 'string', 'in:'.implode(',', config('api.gateway.versions'))],
            'notify_url' => ['nullable', 'string', 'url', 'max:255'], // accepted for KBZ compatibility; not used
            'biz_content' => ['required', 'array'],
            'biz_content.appid' => ['required', 'string', 'max:64'],
        ], [
            'timestamp.regex' => __('The timestamp must be Unix time in seconds (10 digits).'),
            'nonce_str.regex' => __('The nonce_str must be 16 to 32 letters or digits.'),
            'sign.regex' => __('The sign must be a 64-character SHA-256 hex string.'),
        ]);

        $validator->after(function ($validator) use ($payload) {
            if (! is_array($payload['biz_content'] ?? null) || array_is_list($payload['biz_content'])) {
                return;
            }
            foreach ($payload['biz_content'] as $key => $value) {
                if ($value !== null && ! is_string($value) && ! is_int($value)) {
                    $validator->errors()->add("biz_content.{$key}", __('Send every biz_content value as a string.'));
                }
            }
        });

        if ($validator->fails()) {
            throw GatewayError::invalidRequest($validator->errors()->toArray());
        }
    }

    private function client(string $appId): ApiClient
    {
        $client = ApiClient::query()->where('app_id', $appId)->first();
        if (! $client?->is_active) {
            throw GatewayError::unauthenticated('INVALID_APPID', __('Unknown or disabled appid.'));
        }

        return $client;
    }

    private function throttle(ApiClient $client, string $ip): void
    {
        $key = "api-gateway:{$client->id}:{$ip}";
        if (RateLimiter::tooManyAttempts($key, config('api.gateway.rate_per_minute'))) {
            throw new GatewayError('RATE_LIMITED', __('Too many requests. Try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($key)]), Response::HTTP_TOO_MANY_REQUESTS);
        }
        RateLimiter::hit($key, 60);
    }

    /** @param  array<string, mixed>  $payload */
    private function authenticate(array $payload, ApiClient $client, string $ip): void
    {
        if (! $this->signer->verify($payload, $client->secret, (string) $payload['sign'])) {
            Log::notice('API gateway: wrong signature', ['app_id' => $client->app_id, 'method' => $payload['method'], 'ip' => $ip]);

            // While developing, show what we signed (never the key), so the caller can tell a wrong
            // key (same string) from different content (different string).
            throw new GatewayError('INVALID_SIGNATURE', __('The signature is not valid.'), Response::HTTP_UNAUTHORIZED,
                debug: config('app.debug') ? ['string_to_sign' => $this->signer->stringToSign($payload)] : null);
        }

        $tolerance = (int) config('api.gateway.timestamp_tolerance');
        if (abs(now()->getTimestamp() - (int) $payload['timestamp']) > $tolerance) {
            throw GatewayError::unauthenticated('TIMESTAMP_EXPIRED', __('The timestamp is more than :seconds seconds away from the server time.', ['seconds' => $tolerance]));
        }

        // Single use. Kept a little longer than the timestamp window, so a replay can't slip in after it.
        if (! $this->cache->add("api-gateway:nonce:{$client->id}:{$payload['nonce_str']}", true, $tolerance * 2 + 60)) {
            throw new GatewayError('DUPLICATE_NONCE', __('This nonce_str was already used. Send a new one with every request.'), Response::HTTP_CONFLICT);
        }
    }

    /**
     * @param  array<string, mixed>  $bizContent
     * @return array<string, mixed>
     */
    private function validateBizContent(GatewayMethod $handler, array $bizContent): array
    {
        $validator = $this->validator->make($bizContent, $handler->rules());
        if ($validator->fails()) {
            throw GatewayError::validation($validator->errors()->toArray());
        }

        return $validator->validated();
    }

    private function touch(ApiClient $client): void
    {
        if ($client->last_used_at === null || $client->last_used_at->lt(now()->subMinute())) {
            $client->forceFill(['last_used_at' => now()])->saveQuietly();
        }
    }
}
