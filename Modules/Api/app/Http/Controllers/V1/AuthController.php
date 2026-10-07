<?php

namespace Modules\Api\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Api\Http\Requests\LoginRequest;
use Modules\Api\Http\Resources\TokenResource;
use Modules\Api\Services\ApiAuthenticator;
use Modules\Api\Services\ApiTokenService;

class AuthController extends Controller
{
    public function __construct(private readonly ApiTokenService $tokens) {}

    public function login(LoginRequest $request, ApiAuthenticator $authenticator): JsonResponse
    {
        $user = $authenticator->authenticate($request->validated('login'), $request->validated('password'));
        $token = $this->tokens->issue($user, $request->validated('device_name'));

        event(new Login('sanctum', $user, false));

        return (new TokenResource($token))->response()->setStatusCode(201);
    }

    public function refresh(Request $request): TokenResource
    {
        $user = $request->user();

        return new TokenResource($this->tokens->refresh($user, $user->currentAccessToken()));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->tokens->revoke($request->user()->currentAccessToken());

        return response()->json(['message' => __('Signed out.')]);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $count = $this->tokens->revokeAll($request->user());

        return response()->json(['message' => __('Signed out of :count devices.', ['count' => $count])]);
    }
}
