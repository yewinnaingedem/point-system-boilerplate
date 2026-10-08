<?php

return [
    'name' => 'Merchant',

    // Wrong branch codes allowed per member per branch before a lock-out (brute-force guard:
    // a 6-digit code has 1,000,000 values).
    'code_max_attempts' => (int) env('MERCHANT_CODE_MAX_ATTEMPTS', 5),
    'code_lockout_minutes' => (int) env('MERCHANT_CODE_LOCKOUT_MINUTES', 15),
];
