<?php

declare(strict_types=1);

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

    /*
    |--------------------------------------------------------------------------
    | Twilio (SMS — OTP delivery)
    |--------------------------------------------------------------------------
    |
    | Leaving TWILIO_SID empty puts us in "dev mode": the OtpNotification's
    | TwilioSmsChannel skips the real API call and instead emits to the local
    | log + (optionally) the user's email so devs can grab the code without a
    | real SMS round-trip.
    */
    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from' => env('TWILIO_FROM'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Turnstile (bot protection on register and OTP sends)
    |--------------------------------------------------------------------------
    |
    | Never enforced under APP_ENV=testing so the suite needs no tokens;
    | tests that cover the check turn it on explicitly with a fake verifier.
    */
    'turnstile' => [
        'enabled' => (bool) env('TURNSTILE_ENABLED', false) && env('APP_ENV') !== 'testing',
        'secret' => env('TURNSTILE_SECRET_KEY'),
        'verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        'timeout_seconds' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Google / Apple sign-in (id_token audiences)
    |--------------------------------------------------------------------------
    |
    | Comma-separated OAuth client ids whose id_tokens we accept: the web,
    | iOS and Android client ids for Google; the bundle id and Services ID
    | for Apple. An empty list turns that provider off (AUTH_015).
    */
    'google' => [
        'client_ids' => env('GOOGLE_CLIENT_IDS', ''),
    ],

    'apple' => [
        'client_ids' => env('APPLE_CLIENT_IDS', ''),
    ],

];
