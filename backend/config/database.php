<?php

use Illuminate\Support\Str;

return [
    'default' => env('DB_CONNECTION', 'sqlite'),
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DATABASE_URL'),
            'database' => str_replace('%USERPROFILE%', rtrim(getenv('USERPROFILE'), '\\/'), env('DB_DATABASE', 'database/corex.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', false),
            'options' => [
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
                PDO::SQLITE_ENABLE_WAL => true,
                PDO::SQLITE_ENABLE_VWAL => true,
            ],
            'synchronous' => env('DB_SYNC_MODE', 'NORMAL'),
            'journal_mode' => env('DB_JOURNAL_MODE', 'WAL'),
            'busy_timeout' => env('DB_BUSY_TIMEOUT', 5000),
            'compile_mode' => env('DB_COMPILE_MODE', 'PRECOMPILE'),
            'write_ahead_log' => true,
        ],
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'corex'),
            'username' => env('DB_USERNAME', 'corex'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => env('DB_SCHEMA', 'public'),
            'sslmode' => env('DB_SSLMODE', 'prefer'),
            'options' => [
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            ],
        ],
    ],
    'migrations' => [
        'table' => 'migrations',
        'update_date_on_run' => true,
    ],
    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),
        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'corex'), '_') . '_database_'),
            'retry_interval' => 1000,
            'password_retry' => 3,
        ],
        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'read_timeout' => 30,
            'persistent' => false,
            'retry_interval' => 100,
            'connect_timeout' => 5,
            'idle_timeout' => 300,
        ],
        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'read_timeout' => 30,
            'persistent' => false,
            'compress_type' => 'LZF',
        ],
    ],
];
