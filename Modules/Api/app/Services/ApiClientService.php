<?php

namespace Modules\Api\Services;

use Illuminate\Support\Str;
use Modules\Api\Models\ApiClient;

/**
 * Gateway clients: a public app_id and a secret key. The key is shown once (on create and
 * on rotate); afterwards only the server reads it, to check signatures.
 */
class ApiClientService
{
    private const APP_ID_PREFIX = 'pos';

    private const APP_ID_LENGTH = 32;

    private const SECRET_BYTES = 32; // 64 hex characters

    /**
     * @param  array{name: string, notes?: ?string, is_active?: bool}  $data
     * @return array{0: ApiClient, 1: string} the client and its secret in clear
     */
    public function create(array $data): array
    {
        $secret = $this->newSecret();
        $client = ApiClient::query()->create([
            ...$data,
            'app_id' => $this->newAppId(),
            'secret' => $secret,
        ]);

        return [$client, $secret];
    }

    /** New secret key; the old one stops working at once. */
    public function rotate(ApiClient $client): string
    {
        $secret = $this->newSecret();
        $client->update(['secret' => $secret, 'secret_rotated_at' => now()]);

        return $secret;
    }

    private function newAppId(): string
    {
        do {
            $appId = self::APP_ID_PREFIX.Str::lower(Str::random(self::APP_ID_LENGTH - strlen(self::APP_ID_PREFIX)));
        } while (ApiClient::query()->where('app_id', $appId)->exists());

        return $appId;
    }

    private function newSecret(): string
    {
        return bin2hex(random_bytes(self::SECRET_BYTES));
    }
}
