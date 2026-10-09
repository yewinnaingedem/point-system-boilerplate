<?php

namespace Modules\Api\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Api\Gateway\GatewayKernel;

/** POST /api/v1/gateway: the signed {"Request": {...}} envelope (see GatewayKernel). */
class GatewayController extends Controller
{
    public function __invoke(Request $request, GatewayKernel $kernel): JsonResponse
    {
        return $kernel->handle($request);
    }
}
