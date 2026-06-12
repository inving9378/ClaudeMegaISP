<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'cache' => [
        'center_map_latitude' => env('CENTER_MAP_LATITUDE'),
        'center_map_longitude' => env('CENTER_MAP_LONGITUDE')
    ],

    'smartolt' => [
        'domain'        => env('SMARTOLT_DOMAIN'),
        'token'         => env('SMARTOLT_TOKEN'),
        'ttl'           => env('SMARTOLT_TTL', 120),
        'hourly_budget' => env('SMARTOLT_HOURLY_BUDGET', 1000),
    ],

    'anthropic' => [
        'key' => env('CLAUDE_API_KEY'),
        'model' => env('CLAUDE_MODEL', 'claude-sonnet-4-20250514'),
        'endpoint' => env('CLAUDE_API_ENDPOINT', 'https://api.anthropic.com/v1/messages'),
    ],

    // Huawei OLT (motor propio — B1b-4). Sin valores hasta validar B1c.
    'huawei_olt' => [
        'host'            => env('OLT_HUAWEI_HOST', ''),
        'port'            => env('OLT_HUAWEI_PORT', 23),
        'username'        => env('OLT_HUAWEI_USER', ''),
        'password'        => env('OLT_HUAWEI_PASS', ''),
        'transport'       => env('OLT_HUAWEI_TRANSPORT', 'telnet'),
        'connect_timeout' => env('OLT_HUAWEI_TIMEOUT', 30),
    ],

];
