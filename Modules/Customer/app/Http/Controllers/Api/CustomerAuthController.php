<?php

namespace Modules\Customer\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Customer\Exceptions\SsoTokenRejected;
use Modules\Customer\Http\Requests\SsoRequest;
use Modules\Customer\Http\Resources\CustomerResource;
use Modules\Customer\Services\CustomerSsoService;

class CustomerAuthController extends Controller
{
    public function __construct(private readonly CustomerSsoService $sso) {}

    /** Exchange the partner project's sign-in JWT for our long-lived customer token. */
    public function sso(SsoRequest $request): JsonResponse
    {
        try {
            $session = $this->sso->signIn($request->validated('token'), $request->validated('device_name'));
        } catch (SsoTokenRejected $e) {
            Log::notice('Customer SSO rejected', ['reason' => $e->getMessage(), 'ip' => $request->ip()]);
            throw ValidationException::withMessages(['token' => __('This sign-in link is invalid or has expired. Please sign in again.')]);
        }

        return response()->json(['data' => [
            'token' => $session['token'],
            'token_type' => 'Bearer',
            'expires_at' => $session['expires_at']->toIso8601String(),
            'new_customer' => $session['created'],
            'customer' => new CustomerResource($session['customer']),
        ]], $session['created'] ? 201 : 200);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('Signed out.')]);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $count = $this->sso->revokeAll($request->user());

        return response()->json(['message' => __('Signed out of :count devices.', ['count' => $count])]);
    }
}
