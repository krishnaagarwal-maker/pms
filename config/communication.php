<?php

return [
    'rpc' => [
        'enabled' => true,
    ],
    'queue' => [
        'enabled' => false,
        'connection' => env('QUEUE_CONNECTION', 'sync'),
    ],
];


