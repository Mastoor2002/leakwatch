<?php

return [
    // Full URL of your Leakwatch instance's report endpoint.
    'endpoint' => env('LEAKWATCH_ENDPOINT'),

    // The per-site token shown once in Leakwatch (Site > Agent tab).
    'token' => env('LEAKWATCH_TOKEN'),

    // Include require-dev packages in the report (flagged as dev).
    'include_dev' => env('LEAKWATCH_INCLUDE_DEV', true),

    // Report automatically once a day through the Laravel scheduler.
    'schedule' => env('LEAKWATCH_SCHEDULE', true),
];
