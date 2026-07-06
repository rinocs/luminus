<?php

return [
    'default' => env('QUEUE_DRIVER', 'database'),

    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],
        
        'database' => [
            'driver' => 'database',
            'table' => env('QUEUE_DB_TABLE', 'jobs'),
            'failed_table' => env('QUEUE_FAILED_TABLE', 'failed_jobs'),
        ],
        
        'redis' => [
            'driver' => 'redis',
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', null),
            'database' => env('REDIS_QUEUE_DB', 1),
            'prefix' => env('REDIS_QUEUE_PREFIX', 'luminus:queues:'),
        ],
        
        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => env('BEANSTALKD_HOST', '127.0.0.1'),
            'port' => env('BEANSTALKD_PORT', 11300),
            'timeout' => 10,
        ],
    ],
];
