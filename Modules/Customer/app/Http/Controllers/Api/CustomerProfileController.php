<?php

namespace Modules\Customer\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Customer\Http\Resources\CustomerResource;
use Modules\Customer\Http\Resources\TierEventResource;

class CustomerProfileController extends Controller
{
    private const HISTORY_PAGE = 20;

    /** Profile, points balance, tier and progress to the next tier. */
    public function me(Request $request): CustomerResource
    {
        return new CustomerResource($request->user());
    }

    /** Tier history, newest first: enrolled, upgraded, requalified, protected, demoted. */
    public function tierHistory(Request $request): AnonymousResourceCollection
    {
        return TierEventResource::collection(
            $request->user()->tierEvents()->latest('occurred_at')->latest('id')->paginate(self::HISTORY_PAGE)
        );
    }
}
