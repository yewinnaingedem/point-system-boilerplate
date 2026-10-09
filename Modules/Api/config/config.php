<?php

return [
    'name' => 'Api',

    // A token stops working this many days after it was issued; the app then signs in again.
    'token_ttl_days' => (int) env('API_TOKEN_TTL_DAYS', 30),

    // Signed-in devices per user. Signing in on one more device revokes the oldest token.
    'max_devices' => (int) env('API_MAX_DEVICES', 3),

    // Requests per minute per signed-in user.
    'rate_per_minute' => (int) env('API_RATE_PER_MINUTE', 60),

    // Failed sign-in attempts per minute per login name + IP.
    'login_attempts_per_minute' => 5,

    /*
     * Signed gateway (POST /api/v1/gateway, docs/gateway-api.md). Callers are API clients
     * (Administration → API Clients): appid + secret key, KBZPay-style SHA256 signature.
     */
    'gateway' => [
        'versions' => ['1.0'],
        // Request.timestamp may be this many seconds away from our clock (either way).
        'timestamp_tolerance' => (int) env('API_GATEWAY_TIMESTAMP_TOLERANCE', 300),
        // Requests per minute per client + IP.
        'rate_per_minute' => (int) env('API_GATEWAY_RATE_PER_MINUTE', 300),
    ],
];
