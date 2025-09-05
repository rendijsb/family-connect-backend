<?php

return [
    'default' => env('REVERB_SERVER', 'reverb'),

    'servers' => [
        'reverb' => [
            'host' => env('REVERB_SERVER_HOST', '0.0.0.0'),
            'port' => env('REVERB_SERVER_PORT', 8080),
            'path' => env('REVERB_SERVER_PATH', ''),
            'hostname' => env('REVERB_HOST', 'family-connect.laravel.cloud'),
            'options' => [
                'tls' => [],
            ],
            'max_request_size' => env('REVERB_MAX_REQUEST_SIZE', 10000),
            'scaling' => [
                'enabled' => env('REVERB_SCALING_ENABLED', true),
                'channel' => env('REVERB_SCALING_CHANNEL', 'reverb'),
                'server' => [
                    'url' => env('REDIS_URL'),
                    'host' => env('REVERB_REDIS_HOST', env('REDIS_HOST', 'redis')),
                    'port' => env('REVERB_REDIS_PORT', env('REDIS_PORT', '6379')),
                    'username' => env('REVERB_REDIS_USERNAME', env('REDIS_USERNAME')),
                    'password' => env('REVERB_REDIS_PASSWORD', env('REDIS_PASSWORD')),
                    'database' => env('REVERB_REDIS_DATABASE', env('REDIS_DB', '0')),
                    'timeout' => env('REVERB_REDIS_TIMEOUT', env('REDIS_TIMEOUT', 60)),
                    'persistent' => env('REDIS_PERSISTENT', false),
                ],
            ],
            'pulse_ingest_interval' => env('REVERB_PULSE_INGEST_INTERVAL', 15),
            'telescope_ingest_interval' => env('REVERB_TELESCOPE_INGEST_INTERVAL', 15),
        ],
    ],

    'apps' => [
        'provider' => 'config',
        'apps' => [
            [
                'key' => env('REVERB_APP_KEY', 'family-connect-key'),
                'secret' => env('REVERB_APP_SECRET', 'family-connect-secret'),
                'app_id' => env('REVERB_APP_ID', 'family-connect'),
                'options' => [
                    'host' => env('REVERB_HOST', 'family-connect.laravel.cloud'),
                    'port' => env('REVERB_PORT', 443),
                    'scheme' => env('REVERB_SCHEME', 'https'),
                    'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
                ],
                'allowed_origins' => [
                    'https://family-connect.laravel.cloud',
                    'capacitor://localhost',
                    'ionic://localhost',
                    'http://localhost:4200',
                    'http://localhost:8100',
                ],
                'ping_interval' => env('REVERB_APP_PING_INTERVAL', 60),
                'activity_timeout' => env('REVERB_APP_ACTIVITY_TIMEOUT', 30),
                'max_message_size' => env('REVERB_APP_MAX_MESSAGE_SIZE', 10000),
            ],
        ],
    ],
];
