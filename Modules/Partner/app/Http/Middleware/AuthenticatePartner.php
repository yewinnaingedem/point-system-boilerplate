<?php

namespace Modules\Partner\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-to-server calls from the partner project: "Authorization: Bearer <PARTNER_API_KEY>",
 * compared in constant time, optionally only from PARTNER_API_ALLOWED_IPS. No user is signed in.
 */
class AuthenticatePartner
{
    private const MIN_KEY_LENGTH = 32;

    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) config('partner.api_key');
        if (strlen($key) < self::MIN_KEY_LENGTH) {
            Log::error('PARTNER_API_KEY is missing or shorter than '.self::MIN_KEY_LENGTH.' characters; the partner API is disabled.');

            return $this->deny();
        }

        $allowed = config('partner.allowed_ips');
        if ($allowed !== [] && ! IpUtils::checkIp((string) $request->ip(), $allowed)) {
            Log::warning('Partner API call from an address that is not allowed', ['ip' => $request->ip()]);

            return $this->deny();
        }

        if (! hash_equals($key, (string) $request->bearerToken())) {
            return $this->deny();
        }

        return $next($request);
    }

    private function deny(): Response
    {
        return response()->json(['message' => __('Unauthenticated.')], Response::HTTP_UNAUTHORIZED);
    }
}
