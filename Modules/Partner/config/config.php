<?php

return [
    'name' => 'Partner',

    /*
     * Server-to-server API for the partner Laravel project (award points, read a customer's points).
     * It sends "Authorization: Bearer <PARTNER_API_KEY>". Keep the key on servers only, never in an app.
     */
    'api_key' => env('PARTNER_API_KEY'),                       // at least 32 characters; the API is off while empty
    'allowed_ips' => array_filter(array_map('trim', explode(',', (string) env('PARTNER_API_ALLOWED_IPS', '')))), // empty = any
    'rate_per_minute' => (int) env('PARTNER_API_RATE_PER_MINUTE', 120),
    'max_points_per_award' => 10_000_000,
];
