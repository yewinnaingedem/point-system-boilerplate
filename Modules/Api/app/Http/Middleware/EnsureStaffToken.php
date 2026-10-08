<?php

namespace Modules\Api\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff API routes (POS app) accept only staff tokens; a customer token gets 403.
 * Sanctum resolves any token owner, so without this a customer could call staff endpoints.
 */
class EnsureStaffToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() instanceof User) {
            return response()->json(['message' => __('This endpoint is for staff.')], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
