<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| QBazaar — Project Constants
|--------------------------------------------------------------------------
|
| Centralised business-rule values referenced across the app. Anything that
| changes per-environment should be wired through .env; anything that
| represents a product decision lives here so future tweaks land in
| one diff.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Web URLs — used by mailed notifications to build deep links into the
    | seller-facing web app. Falls back to APP_URL when WEB_URL isn't set so
    | the contract stays predictable in dev (everything points at the API
    | host until the FE is wired up).
    |--------------------------------------------------------------------------
    */
    'web_url' => env('WEB_URL', env('APP_URL', 'http://localhost')),

    /*
    |--------------------------------------------------------------------------
    | Locale & Currency
    |--------------------------------------------------------------------------
    */
    'supported_languages' => ['ar', 'en'],
    'default_language' => 'ar',
    'supported_currencies' => ['QAR'],
    'default_currency' => 'QAR',
    'phone_country_code' => '+974',
    'phone_regex' => '/^\+974[0-9]{8}$/',
    'timezone_display' => 'Asia/Qatar',

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */
    'auth' => [
        'password_min_length' => 8,
        'access_token_ttl_minutes' => 15,
        'refresh_token_ttl_days' => 30,
        'max_login_attempts' => 5,
        'login_lockout_minutes' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | OTP (one-time password) — phone verification
    |--------------------------------------------------------------------------
    */
    'otp' => [
        'length' => 6,
        'ttl_minutes' => 5,
        'max_attempts' => 3,
        'resend_cooldown_seconds' => 60,
        'max_per_hour' => 5,

        // Dev override: when set, OtpService::issue() short-circuits the random
        // generator and emits this exact code (still goes through Twilio/log/email
        // channels so the full flow is exercised). Leave null in production —
        // a non-null value here is a security risk.
        'fixed_code' => env('OTP_FIXED_CODE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ads
    |--------------------------------------------------------------------------
    */
    'ads' => [
        'max_images' => 10,
        'min_images' => 1,
        'lifetime_days' => 30,
        'expiry_warning_days_before' => 3,
        'daily_publish_limit_per_user' => 10,
        'title_min_length' => 5,
        'title_max_length' => 100,
        'description_min_length' => 20,
        'description_max_length' => 3000,
        'price_max' => 99_999_999,
        'view_throttle_per_user_per_minute' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    */
    'search' => [
        'results_per_page' => 20,
        'suggestions_max' => 8,
        'saved_search_max_per_user' => 20,
        'saved_search_check_interval_minutes' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Favorites & Recently Viewed
    |--------------------------------------------------------------------------
    */
    'favorites' => [
        'max_per_user' => 1000,
    ],

    'recently_viewed' => [
        'cap_per_user' => 50,
        'cleanup_interval_hours' => 24,
    ],

    /*
    |--------------------------------------------------------------------------
    | Messaging
    |--------------------------------------------------------------------------
    */
    'messaging' => [
        'max_message_length' => 5_000,
        'rate_limit_per_minute' => 30,
        'auto_archive_inactive_days' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Offers
    |--------------------------------------------------------------------------
    */
    'offers' => [
        'expiry_days' => 7,
        'max_active_per_ad_per_user' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    */
    'uploads' => [
        'max_image_size_kb' => 10_240, // 10 MB
        'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],

        // Lifetime of the signed link to an original-resolution image —
        // originals are served via an expiring signed route so they can't
        // be hotlinked permanently (conversions stay public).
        'original_url_ttl_hours' => 24,
        'image_conversions' => [
            'thumbnail' => ['width' => 200, 'height' => 200],
            'medium' => ['width' => 640],
            'large' => ['width' => 1024],
            'original_webp' => ['width' => 1920],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Account lifecycle
    |--------------------------------------------------------------------------
    */
    'account' => [
        'deletion_grace_period_days' => 30,
        'data_export_link_ttl_hours' => 48,
    ],

    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */
    'reports' => [
        'max_per_target_per_user_per_week' => 1,

        // Window the duplicate-report guard uses when refusing a follow-up
        // report against the same target. Tightening this is the first
        // dial to turn if "report spam" becomes an abuse vector.
        'duplicate_window_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-moderation patterns
    |--------------------------------------------------------------------------
    | Used by ModerateAdAction and ContentSafetyService. Banned word list
    | itself lives in the moderation_rules DB table (admin-editable);
    | these are the regex shapes the service applies regardless.
    */
    'moderation' => [
        'phone_in_text_regex' => '/(?:\+?974[\s-]?)?[0-9]{8}/',
        'external_link_regex' => '/https?:\/\/(?!qbazaar\.qa)[^\s]+/i',
        'phash_distance_threshold' => 8, // for duplicate image detection
    ],

];
