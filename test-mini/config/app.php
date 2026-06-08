<?php

return [
    'debug' => env('APP_DEBUG', true),
    'env' => env('APP_ENV', 'development'),
    'url' => env('APP_URL', 'http://localhost:8080'),
    'views_path' => __DIR__ . '/../views',
    'storage_path' => __DIR__ . '/../storage',

    'database' => [
        'driver' => env('DB_DRIVER', 'mysql'),
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'database' => env('DB_DATABASE', $PROJECT_NAME),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
    ],
];
