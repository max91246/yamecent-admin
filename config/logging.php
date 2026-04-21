<?php

use Monolog\Handler\StreamHandler;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single'],
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => 'debug',
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => 'debug',
            'days' => 7,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
            'level' => 'critical',
        ],

        'stderr' => [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => 'debug',
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => 'debug',
        ],

        'tg_webhook' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/tg-webhook.log'),
            'level'  => 'debug',
            'days'   => 14,
        ],

        'oil_price' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/oil-price.log'),
            'level'  => 'debug',
            'days'   => 14,
        ],

        'tw_index' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/tw-index.log'),
            'level'  => 'debug',
            'days'   => 14,
        ],

        'notify_holdings' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/notify-holdings.log'),
            'level'  => 'debug',
            'days'   => 14,
        ],

        'settle_payments' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/settle-payments.log'),
            'level'  => 'debug',
            'days'   => 14,
        ],

        'scrape_wantgoo' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/scrape-wantgoo.log'),
            'level'  => 'debug',
            'days'   => 14,
        ],
    ],

];
