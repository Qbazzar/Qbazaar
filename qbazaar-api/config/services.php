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
        'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Turnstile (bot protection on public email / SMS senders)
    |--------------------------------------------------------------------------
    |
    | Never enforced under APP_ENV=testing so the suite needs no tokens;
    | tests that cover the check turn it on explicitly with a fake verifier.
    */
    'turnstile' => [
        'enabled' => (bool) env('TURNSTILE_ENABLED', false) && env('APP_ENV') !== 'testing',
        'secret' => env('TURNSTILE_SECRET_KEY'),
        'site_key' => env('TURNSTILE_SITE_KEY'),
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
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'ga4_measurement_id' => env('GA4_MEASUREMENT_ID'),
    ],

    'apple' => [
        'client_ids' => env('APPLE_CLIENT_IDS', ''),
        'team_id' => env('APPLE_TEAM_ID'),
        'key_id' => env('APPLE_KEY_ID'),
        'private_key' => env('APPLE_PRIVATE_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging and the payment gateway
    |--------------------------------------------------------------------------
    |
    | Configuration only: no FCM sender or payment integration ships yet
    | (checkout stays cash-only until milestone M7). The admin integration
    | settings overlay these values.
    */
    'fcm' => [
        'service_account' => env('FCM_SERVICE_ACCOUNT_JSON'),
        'project_id' => env('FCM_PROJECT_ID'),
    ],

    'payment' => [
        'enabled' => false,
        'provider' => env('PAYMENT_PROVIDER', 'generic'),
        'mode' => env('PAYMENT_MODE', 'test'),
        'merchant_id' => env('PAYMENT_MERCHANT_ID'),
        'public_key' => env('PAYMENT_PUBLIC_KEY'),
        'secret_key' => env('PAYMENT_SECRET_KEY'),
        'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET'),
    ],

];
