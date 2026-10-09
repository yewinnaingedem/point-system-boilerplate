<?php

namespace Modules\Api\Gateway;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A gateway call that failed in an expected way. `code` is the stable string the caller
 * switches on (INVALID_SIGNATURE, NOT_ENOUGH_POINTS, ...); `status` is the HTTP status.
 */
class GatewayError extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors
     * @param  array<string, string>|null  $debug  only sent while APP_DEBUG is on
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = Response::HTTP_UNPROCESSABLE_ENTITY,
        public readonly array $errors = [],
        public readonly ?array $debug = null,
    ) {
        parent::__construct($message);
    }

    /** @param  array<string, list<string>>  $errors */
    public static function invalidRequest(array $errors): self
    {
        return new self('INVALID_REQUEST', __('The request envelope is not valid.'), Response::HTTP_BAD_REQUEST, $errors);
    }

    /** @param  array<string, list<string>>  $errors */
    public static function validation(array $errors): self
    {
        return new self('VALIDATION_FAILED', __('The biz_content is not valid.'), Response::HTTP_UNPROCESSABLE_ENTITY, $errors);
    }

    public static function unauthenticated(string $code, string $message): self
    {
        return new self($code, $message, Response::HTTP_UNAUTHORIZED);
    }

    public static function notFound(string $code, string $message): self
    {
        return new self($code, $message, Response::HTTP_NOT_FOUND);
    }
}
