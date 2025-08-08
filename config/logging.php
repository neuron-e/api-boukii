<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

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
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => false,
    ],

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
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => LOG_USER,
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

        'accuweather' => [
            'driver' => 'daily',
            'path' => storage_path('logs/accuweather.log'),
            'level' => 'debug',
            'days' => 7
        ],

        'payrexx' => [
            'driver' => 'daily',
            'path' => storage_path('logs/payrexx.log'),
            'level' => 'debug',
            'days' => 7
        ],

        'migration' => [
            'driver' => 'daily',
            'path' => storage_path('logs/migration.log'),
            'level' => 'debug',
            'days' => 7
        ],

        // V5 Logging Channels
        'v5_enterprise' => [
            'driver' => 'stack',
            'channels' => ['v5_daily', 'v5_database'],
            'ignore_exceptions' => false,
        ],

        'v5_daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/v5/enterprise.log'),
            'level' => env('V5_LOG_LEVEL', 'debug'),
            'days' => env('V5_LOG_RETENTION_DAYS', 30),
            'replace_placeholders' => true,
        ],

        'v5_payments' => [
            'driver' => 'daily',
            'path' => storage_path('logs/v5/payments.log'),
            'level' => env('V5_LOG_LEVEL', 'debug'),
            'days' => env('V5_LOG_RETENTION_DAYS', 30),
            'replace_placeholders' => true,
        ],

        'v5_financial' => [
            'driver' => 'daily',
            'path' => storage_path('logs/v5/financial.log'),
            'level' => env('V5_LOG_LEVEL', 'info'),
            'days' => env('V5_LOG_RETENTION_DAYS', 90),
            'replace_placeholders' => true,
        ],

        'v5_security' => [
            'driver' => 'daily',
            'path' => storage_path('logs/v5/security.log'),
            'level' => env('V5_LOG_LEVEL', 'warning'),
            'days' => env('V5_LOG_RETENTION_DAYS', 365),
            'replace_placeholders' => true,
        ],

        'v5_alerts' => [
            'driver' => 'daily',
            'path' => storage_path('logs/v5/alerts.log'),
            'level' => env('V5_LOG_LEVEL', 'warning'),
            'days' => env('V5_LOG_RETENTION_DAYS', 60),
            'replace_placeholders' => true,
        ],

        'v5_database' => [
            'driver' => 'monolog',
            'handler' => \App\V5\Logging\DatabaseLogHandler::class,
            'level' => env('V5_LOG_LEVEL', 'debug'),
        ],

        'v5_performance' => [
            'driver' => 'daily',
            'path' => storage_path('logs/v5/performance.log'),
            'level' => env('V5_LOG_LEVEL', 'info'),
            'days' => env('V5_LOG_RETENTION_DAYS', 7),
            'replace_placeholders' => true,
        ],
    ],

];
