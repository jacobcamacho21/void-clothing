<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'lalamove' => [
        'base_url' => env('LALAMOVE_BASE_URL', 'https://rest.sandbox.lalamove.com'),
        'api_key' => env('LALAMOVE_API_KEY'),
        'api_secret' => env('LALAMOVE_API_SECRET'),
        'market' => env('LALAMOVE_MARKET', 'PH'),
        'language' => env('LALAMOVE_LANGUAGE', 'en_PH'),
        'service_type' => env('LALAMOVE_SERVICE_TYPE', 'MOTORCYCLE'),
        'pickup_address' => env('LALAMOVE_PICKUP_ADDRESS'),
        'pickup_latitude' => env('LALAMOVE_PICKUP_LATITUDE'),
        'pickup_longitude' => env('LALAMOVE_PICKUP_LONGITUDE'),
    ],

    'paymongo' => [
        'base_url' => env('PAYMONGO_BASE_URL', 'https://api.paymongo.com/v2'),
        'secret_key' => env('PAYMONGO_SECRET_KEY'),
        'public_key' => env('PAYMONGO_PUBLIC_KEY'),
        'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
        'payment_method_types' => ['qrph'],
    ],

];
