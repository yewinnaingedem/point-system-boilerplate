<?php

return [
    'name' => 'LogViewer',

    // Folder holding the daily log files.
    'path' => storage_path('logs'),

    // Daily files are "<prefix>-YYYY-MM-DD.log" (LOG_STACK=daily). Other files, such as a
    // leftover single-channel laravel.log, are not listed and cannot be opened.
    'prefix' => env('LOG_VIEWER_PREFIX', 'laravel'),

    // Only the newest part of a file is read, so a huge log can't exhaust memory.
    'max_bytes' => (int) env('LOG_VIEWER_MAX_BYTES', 5 * 1024 * 1024),

    'per_page' => 25,
];
