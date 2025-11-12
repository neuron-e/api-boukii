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
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'accuweather'  => [
        'key' => env('ACCUWEATHER_API_KEY')
    ],

    'timing' => [
        // API key used by Timing Connector to ingest data
        'api_key' => env('TIMING_API_KEY'),
    ],

    'payrexx' => [
        'instance' => env('PAYREXX_INSTANCE'),
        'key' => env('PAYREXX_KEY'),
        'base_domain' => env('PAYREXX_API_BASE_DOMAIN', 'payrexx.com'),
    ],

];
