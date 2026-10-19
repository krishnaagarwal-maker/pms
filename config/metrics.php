<?php

return [
    'enabled' => env('RCV_METRICS_ENABLED', true),

    // array|cache
    'driver' => env('RCV_METRICS_DRIVER', 'array'),

    // When using cache driver
    'cache_store' => env('RCV_METRICS_CACHE_STORE', null),
    'cache_prefix' => env('RCV_METRICS_CACHE_PREFIX', 'rcv:metrics:'),
];
