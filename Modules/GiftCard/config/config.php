<?php

return [
    'name' => 'GiftCard',

    // Two-step exchanges: the emailed code works this long, and this many wrong tries end it.
    'verification_minutes' => 10,
    'verification_attempts' => 5,
];
