<?php

namespace Modules\Api\Gateway;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Modules\Api\Models\ApiClient;

/**
 * Builds the {"Response": {...}} answer. Signed the same way as requests (see Signer) once the
 * client is known, so the caller can check the answer really came from us.
 */
class GatewayEnvelope
{
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    public function __construct(private readonly Signer $signer) {}

    /** @param  array<string, mixed>  $data */
    public function success(string $method, array $data, ApiClient $client): JsonResponse
    {
        return $this->respond(200, [
            'result' => 'SUCCESS',
            'code' => '0',
            'msg' => 'success',
            'method' => $method,
            'biz_content' => $data,
        ], $client);
    }

    public function failure(GatewayError $error, ?string $method, ?ApiClient $client): JsonResponse
    {
        return $this->respond($error->status, [
            'result' => 'FAIL',
            'code' => $error->errorCode,
            'msg' => $error->getMessage(),
            'method' => $method,
            'errors' => $error->errors ?: null,
            'debug' => $error->debug,
        ], $client);
    }

    /** @param  array<string, mixed>  $body */
    private function respond(int $status, array $body, ?ApiClient $client): JsonResponse
    {
        // Through JSON and back: Resources and Collections become plain arrays, so what we sign is
        // exactly what the caller decodes.
        $body = json_decode(json_encode($body, self::JSON_FLAGS | JSON_THROW_ON_ERROR), true);
        $body = array_filter([
            ...$body,
            'nonce_str' => Str::random(32),
            'timestamp' => (string) now()->getTimestamp(),
        ], fn ($value) => $value !== null);

        if ($client !== null) {
            $body['sign_type'] = Signer::TYPE;
            $body['sign'] = $this->signer->sign($body, $client->secret);
        }

        return response()->json(['Response' => $body], $status, [], self::JSON_FLAGS);
    }
}
