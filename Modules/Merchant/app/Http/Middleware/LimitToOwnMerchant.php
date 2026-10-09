<?php

namespace Modules\Merchant\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Modules\Merchant\Models\Merchant;
use Symfony\Component\HttpFoundation\Response;

/**
 * A merchant's own staff (users.merchant_id set, e.g. a KFC manager) only ever reach their own
 * merchant: every merchant, branch or claim in the URL must be theirs, and the routes that
 * act on all merchants (create / delete a merchant, pay or reject a claim) are closed to them.
 * Our own staff (merchant_id null) pass through unchanged.
 */
class LimitToOwnMerchant
{
    /** Routes a merchant's staff may never use, whatever permissions their role has. */
    private const OWN_STAFF_ONLY = [
        'admin.merchants.create', 'admin.merchants.store', 'admin.merchants.destroy',
        'admin.claims.pay', 'admin.claims.reject',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $merchantId = $request->user()?->merchant_id;
        if ($merchantId === null) {
            return $next($request);
        }

        abort_if($request->routeIs(...self::OWN_STAFF_ONLY), Response::HTTP_FORBIDDEN);

        // The merchants list is a list of one: go straight to their own page.
        if ($request->routeIs('admin.merchants.index')) {
            return redirect()->route('admin.merchants.show', $merchantId);
        }

        foreach ($request->route()?->parameters() ?? [] as $value) {
            if ($value instanceof Merchant) {
                abort_unless($value->id === $merchantId, Response::HTTP_FORBIDDEN);
            } elseif ($value instanceof Model && array_key_exists('merchant_id', $value->getAttributes())) {
                abort_unless((int) $value->getAttribute('merchant_id') === $merchantId, Response::HTTP_FORBIDDEN);
            }
        }

        return $next($request);
    }
}
