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

    // İşlemsel SMS (OTP, fason/sipariş bildirimi) — bkz. App\Contracts\SmsProviderInterface.
    // key/hash/sender tanımlı değilse App\Services\Sms\NullSmsProvider (dış istek atmaz).
    'sms' => [
        'driver' => env('SMS_DRIVER', 'iletimerkezi'),

        'iletimerkezi' => [
            'base_url' => env('ILETIMERKEZI_BASE_URL', 'https://api.iletimerkezi.com/v1'),
            'key'      => env('ILETIMERKEZI_KEY'),
            'hash'     => env('ILETIMERKEZI_HASH'),
            // Panelde onaylı gönderici başlığı (max 11 karakter).
            'sender'   => env('ILETIMERKEZI_SENDER'),
        ],
    ],

];
