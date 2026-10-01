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

    // Swagger UI (/swagger, /docs) and /api/v1/openapi.yaml. Off in
    // production unless API_DOCS_ENABLED=true.
    'api_docs_enabled' => in_array(env('API_DOCS_ENABLED'), [null, ''], true)
        ? env('APP_ENV', 'production') !== 'production'
        : (bool) env('API_DOCS_ENABLED'),

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
        'max_per_minute' => 3,
        'max_per_day_per_phone' => 10,
        'max_per_day_per_ip' => 30,
        'verify_max_per_minute' => 5,

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
        // max_images and daily_publish_limit_per_user are defaults for the
        // admin-editable platform settings of the same meaning.
        'max_images' => 20,
        'max_images_per_upload' => 10,
        'min_images' => 1,
        'lifetime_days' => 30,
        'expiry_warning_days_before' => 3,
        'daily_publish_limit_per_user' => 10,
        'drafts_per_hour_per_user' => 30,
        'publish_attempts_per_minute_per_user' => 10,
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
        'saved_search_max_per_user' => 10,
        'saved_search_check_interval_minutes' => 60,
        // GET /ads?ids= — favourites synced from another device, recently viewed on the web.
        'ids_lookup_max' => 50,
        'geo_max_radius_km' => 100,
        // View counters change on every visit, so they reach the index in batches instead of per view.
        'views_sync_minutes' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Catalog — category pages and the home feed
    |--------------------------------------------------------------------------
    */
    'catalog' => [
        'counts_cache_seconds' => 600,
        'category_section_ads' => 6,
    ],

    'home' => [
        'cache_seconds' => 300,
        'recommended_limit' => 12,
        'recommended_window_days' => 30,
        'best_selling_limit' => 12,
        'featured_sellers_limit' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Favorites & Recently Viewed
    |--------------------------------------------------------------------------
    */
    'favorites' => [
        'max_per_user' => 1000,
    ],

    // Fan-out alerts (price drops, new ads from followed sellers, saved
    // searches): users per queued chunk, and how long a user stays marked
    // as alerted for one event so retries and repeated events stay silent.
    'notifications' => [
        'fan_out_chunk' => 500,
        'alert_dedupe_hours' => 72,
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
        'new_conversations_per_minute' => 10,
        'new_conversations_per_day' => 50,
        'auto_archive_inactive_days' => 90,
        // Skip the push when the recipient has an app open on Reverb.
        'push_skip_online_recipients' => (bool) env('CHAT_PUSH_SKIP_ONLINE', true),
        // File a report for staff when a message matches the moderation rules.
        'auto_report_flagged_messages' => (bool) env('CHAT_AUTO_REPORT_FLAGGED', true),
        // Photos cost storage and bandwidth, so they get a tighter budget than text.
        'images_per_minute' => 10,
        'images_per_day' => 200,
        'bulk_hide_max' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Offers
    |--------------------------------------------------------------------------
    */
    'offers' => [
        'expiry_days' => 7,
        'max_active_per_ad_per_user' => 1,
        'max_per_minute' => 10,
        'max_per_day' => 50,
        // Default for the admin setting; each side may counter this many times.
        'counter_rounds_per_side' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Commission
    |--------------------------------------------------------------------------
    | Defaults only: the live values are admin-editable platform settings
    | (see App\Enums\PlatformSetting) and fall back to these until saved.
    */
    'commission' => [
        'debt_ceiling' => '500.00',
        'settlement_deadline_days' => 14,
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    */
    'uploads' => [
        'max_image_size_kb' => 10_240, // 10 MB
        'max_avatar_size_kb' => 5_120, // 5 MB
        'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],

        // Lifetime of the signed link to an original-resolution image —
        // originals are served via an expiring signed route so they can't
        // be hotlinked permanently (conversions stay public).
        'original_url_ttl_hours' => 24,

        // Originals live on MEDIA_DISK (see config/media-library.php). Files
        // that are linked permanently — conversions and avatars — go to
        // MEDIA_PUBLIC_DISK, which defaults to the same disk.
        'public_disk' => env('MEDIA_PUBLIC_DISK', env('MEDIA_DISK', 'public')),

        // On a remote disk the signed original route redirects to a presigned
        // URL valid for this long.
        'original_redirect_ttl_minutes' => 5,
        'image_conversions' => [
            'thumbnail' => ['width' => 200, 'height' => 200],
            'medium' => ['width' => 640],
            'large' => ['width' => 1024],
            'original_webp' => ['width' => 1920],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin panel
    |--------------------------------------------------------------------------
    | Failed sign-ins are counted per email + IP, so an attacker guessing a
    | password cannot also lock the real staff member out from their network.
    */
    'admin' => [
        'login_max_attempts' => 5,
        'login_lockout_seconds' => 900,
        'bulk_action_max' => 100,
        'impersonation_ttl_minutes' => 20,
        'impersonation_reason_min_length' => 10,
        'impersonation_reason_max_length' => 500,
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
