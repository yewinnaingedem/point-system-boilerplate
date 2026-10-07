<?php

namespace Modules\Api\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\NewAccessToken;

/**
 * A freshly issued token. The plain-text value is only ever shown here, once.
 *
 * @property NewAccessToken $resource
 */
class TokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->resource->plainTextToken,
            'token_type' => 'Bearer',
            'device_name' => $this->resource->accessToken->name,
            'expires_at' => $this->resource->accessToken->expires_at?->toIso8601String(),
            'user' => new UserResource($this->resource->accessToken->tokenable),
        ];
    }
}
