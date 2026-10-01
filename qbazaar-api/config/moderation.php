<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| QBazaar — Auto-moderation Rules
|--------------------------------------------------------------------------
|
| Static rules consulted by ModerationRulesService at publish time. The
| service uses these three buckets:
|
|   1. `banned_words`      — substring match (case-insensitive, punctuation
|                            stripped). Seed list covers common scam, spam,
|                            and adult-content terms in Arabic + English.
|   2. `allowed_domains`   — URLs to domains NOT in this list trip the
|                            external-link rule. Leave empty in dev so any
|                            URL in title / description is flagged.
|   3. `phone_regex` /
|      `external_link_regex` — kept here (instead of config/qbazaar.php)
|                            so moderation tuning lives in a single file.
|
| The `enabled` flag is honoured by ModerateAdAction so a kill-switch is
| one .env tweak away if a regex misfires in production.
|
| NOTE: The banned-word list is intentionally non-exhaustive. Sprint 11
| (Filament admin) will move this list to an admin-editable DB table; for
| now the static array seeds the same logic so the contract stays stable.
*/

return [

    'enabled' => (bool) env('MODERATION_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Banned words (substring, case-insensitive, punctuation stripped)
    |--------------------------------------------------------------------------
    | Mix of Arabic + English. Keep entries lower-cased for the en side —
    | the matcher lower-cases incoming text before comparing.
    */
    'banned_words' => [
        // English — scam / financial fraud
        'bitcoin',
        'crypto investment',
        'fast cash',
        'easy money',
        'work from home guaranteed',
        'pyramid scheme',
        'mlm opportunity',
        'click here to win',
        'free money',
        'forex signals',

        // English — adult / illicit
        'escort',
        'massage parlor',
        'adult services',
        'sexy massage',

        // English — counterfeit / illegal goods
        'replica rolex',
        'fake passport',
        'counterfeit',
        'stolen phone',
        'hacked account',

        // Arabic — financial scams
        'استثمار مضمون',
        'ربح سريع',
        'فلوس بسرعة',
        'تداول مضمون',
        'بيتكوين',

        // Arabic — adult / illicit
        'تدليك للكبار',
        'مرافقة',
        'خدمات للكبار',

        // Arabic — counterfeit
        'تقليد',
        'مزور',
        'هاتف مسروق',
        'حساب مسروق',

        // Cross-locale spam markers
        'whatsapp only',
        'واتساب فقط',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed link domains
    |--------------------------------------------------------------------------
    | URLs whose host is NOT in this list trip the external-link rule.
    | qbazaar.qa is the canonical platform domain; staging / dev environments
    | can extend via env.
    */
    'allowed_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MODERATION_ALLOWED_DOMAINS', 'qbazaar.qa,www.qbazaar.qa')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Detection patterns
    |--------------------------------------------------------------------------
    | Phone regex covers:
    |   • Qatari +974 / 00974 / bare 8-digit local numbers
    |   • Generic international numbers (+[country code][digits])
    | External-link regex picks up bare URLs (http/https) AND `www.` prefixed
    | hosts since sellers often drop "www.example.com" without a protocol.
    */
    'phone_regex' => '/(?:\+?974|00974)[\s\-]?\d{4}[\s\-]?\d{4}|\+\d{1,3}[\s\-]?\d{6,}|\b\d{8,}\b/u',
    'external_link_regex' => '/(?:https?:\/\/|\bwww\.)[^\s,]+/iu',

    /*
    |--------------------------------------------------------------------------
    | Duplicate images
    |--------------------------------------------------------------------------
    | Bounds for the queued near-duplicate image check. It compares the ad's
    | images with other sellers' ads published in the last `window_days`,
    | within the same top-level category unless `same_root_category` is off.
    | The distance threshold is qbazaar.moderation.phash_distance_threshold.
    */
    'duplicate_images' => [
        'window_days' => (int) env('MODERATION_DUPLICATE_WINDOW_DAYS', 90),
        'same_root_category' => (bool) env('MODERATION_DUPLICATE_SAME_ROOT_CATEGORY', true),
        'max_matches' => 10,
    ],

];
