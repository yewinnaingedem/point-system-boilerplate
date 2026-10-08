<?php

namespace Modules\Customer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Customer\Models\Customer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Customer API routes accept only customer tokens. A staff token gets 403; a customer
 * deactivated after signing in is cut off at once (token revoked, 401).
 */
class EnsureCustomerToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = $request->user();

        if (! $customer instanceof Customer) {
            return response()->json(['message' => __('This endpoint is for customers.')], Response::HTTP_FORBIDDEN);
        }

        if (! $customer->is_active) {
            $token = $customer->currentAccessToken();
            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }

            return response()->json(['message' => __('Your account has been deactivated.')], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
