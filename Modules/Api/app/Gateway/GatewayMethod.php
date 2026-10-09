<?php

namespace Modules\Api\Gateway;

use Modules\Api\Models\ApiClient;

/**
 * One `method` of the gateway (e.g. pos.point.create). The kernel has already checked the
 * envelope, the client, the signature, the timestamp and the nonce.
 */
interface GatewayMethod
{
    /**
     * Validation rules for biz_content (without `appid`, which the kernel checks). Only these
     * keys reach handle().
     *
     * @return array<string, mixed>
     */
    public function rules(): array;

    /**
     * @param  array<string, mixed>  $input  validated biz_content
     * @return array<string, mixed> the response biz_content
     *
     * @throws GatewayError
     */
    public function handle(array $input, ApiClient $client): array;
}
