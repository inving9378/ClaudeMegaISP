<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

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
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
        ],

        // Auditoría del acceso externo a la Hoja de Ruta (Circuito de Mejora Continua)
        'roadmap_externo' => [
            'driver' => 'daily',
            'path' => storage_path('logs/roadmap-externo.log'),
            'level' => 'info',
            'days' => 90,
        ],

        // Auditoría del conector MCP de la Hoja de Ruta (Circuito de Mejora Continua)
        'mcp_roadmap' => [
            'driver' => 'daily',
            'path' => storage_path('logs/mcp-roadmap.log'),
            'level' => 'info',
            'days' => 90,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
            'level' => env('LOG_LEVEL', 'critical'),
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => SyslogUdpHandler::class,
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
            ],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

        'evolution' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/evolution-api.log'),
            'level'  => 'debug',
            'days'   => 7,
        ],

        'claude' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/claude-api.log'),
            'level'  => 'debug',
            'days'   => 7,
        ],

        'marketing' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/marketing.log'),
            'level'  => 'debug',
            'days'   => 14,
        ],

        'asterisk' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/asterisk-ia-bot.log'),
            'level'  => 'debug',
            'days'   => 30,
        ],

        'olt-huawei' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/olt-huawei.log'),
            'level'  => 'debug',
            'days'   => 30,
        ],

        'backup' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/backup-db.log'),
            'level'  => 'debug',
            'days'   => 30,
        ],

        'migration_guard' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/migration-guard.log'),
            'level'  => 'debug',
            'days'   => 30,
        ],
    ],

];
