<?php

return [
    'name' => 'Customer',

    /*
     * Single sign-on from the partner Laravel project. It signs a short-lived JWT (HS256) for its
     * logged-in customer with this shared secret; the customer app exchanges it at
     * POST /api/v1/customer/auth/sso for our own long-lived token. See docs/customer-sso.md.
     */
    'sso' => [
        'secret' => env('CUSTOMER_SSO_SECRET'),          // at least 32 characters, same value on both sides
        'issuer' => env('CUSTOMER_SSO_ISSUER'),          // "iss" the partner puts in the token, e.g. https://shop.example.com
        'audience' => env('CUSTOMER_SSO_AUDIENCE', env('APP_URL')), // "aud": this system
        'max_lifetime' => (int) env('CUSTOMER_SSO_MAX_LIFETIME', 300), // seconds between iat and exp, at most
        'leeway' => 30,                                   // clock difference tolerated, seconds
    ],

    // Our token, sent as "Authorization: Bearer ..." on every customer API call.
    'token_ttl_days' => (int) env('CUSTOMER_TOKEN_TTL_DAYS', 90),
    'max_devices' => (int) env('CUSTOMER_MAX_DEVICES', 5),
];
