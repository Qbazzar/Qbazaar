<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Settings\IntegrationField;
use App\Services\Settings\ServiceAccountJsonRule;

/**
 * Registry of the admin-editable integration settings (contact details and
 * third-party credentials). Infrastructure secrets (APP_KEY, DB, Redis,
 * storage, search, SMTP host) deliberately stay in .env and are not listed.
 */
enum IntegrationSetting: string
{
    case CONTACT_SUPPORT_EMAIL = 'contact_support_email';
    case CONTACT_PHONE = 'contact_phone';
    case CONTACT_WHATSAPP = 'contact_whatsapp';
    case CONTACT_ADDRESS_AR = 'contact_address_ar';
    case CONTACT_ADDRESS_EN = 'contact_address_en';
    case CONTACT_HOURS_AR = 'contact_hours_ar';
    case CONTACT_HOURS_EN = 'contact_hours_en';
    case SOCIAL_INSTAGRAM = 'social_instagram';
    case SOCIAL_X = 'social_x';
    case SOCIAL_FACEBOOK = 'social_facebook';
    case SOCIAL_TIKTOK = 'social_tiktok';
    case SOCIAL_SNAPCHAT = 'social_snapchat';
    case SOCIAL_YOUTUBE = 'social_youtube';
    case SOCIAL_LINKEDIN = 'social_linkedin';

    case EMAIL_ENABLED = 'email_enabled';
    case EMAIL_API_KEY = 'email_api_key';
    case EMAIL_FROM_ADDRESS = 'email_from_address';
    case EMAIL_FROM_NAME = 'email_from_name';
    case EMAIL_REPLY_TO = 'email_reply_to';

    case SMS_ACCOUNT_SID = 'sms_account_sid';
    case SMS_AUTH_TOKEN = 'sms_auth_token';
    case SMS_MESSAGING_SERVICE_SID = 'sms_messaging_service_sid';
    case SMS_FROM_NUMBER = 'sms_from_number';

    case GOOGLE_CLIENT_ID = 'google_client_id';
    case GOOGLE_CLIENT_SECRET = 'google_client_secret';
    case GOOGLE_GA4_MEASUREMENT_ID = 'google_ga4_measurement_id';

    case APPLE_SERVICE_ID = 'apple_service_id';
    case APPLE_TEAM_ID = 'apple_team_id';
    case APPLE_KEY_ID = 'apple_key_id';
    case APPLE_PRIVATE_KEY = 'apple_private_key';

    case PUSH_SERVICE_ACCOUNT = 'push_service_account';

    case TURNSTILE_ENABLED = 'turnstile_enabled';
    case TURNSTILE_SITE_KEY = 'turnstile_site_key';
    case TURNSTILE_SECRET_KEY = 'turnstile_secret_key';

    case PAYMENT_ENABLED = 'payment_enabled';
    case PAYMENT_PROVIDER = 'payment_provider';
    case PAYMENT_MODE = 'payment_mode';
    case PAYMENT_MERCHANT_ID = 'payment_merchant_id';
    case PAYMENT_PUBLIC_KEY = 'payment_public_key';
    case PAYMENT_SECRET_KEY = 'payment_secret_key';
    case PAYMENT_WEBHOOK_SECRET = 'payment_webhook_secret';

    public const array PAYMENT_PROVIDERS = ['generic', 'skipcash', 'dibsy', 'tap', 'qpay', 'paytabs', 'other'];

    public function definition(): IntegrationField
    {
        $text = IntegrationFieldKind::TEXT;

        return match ($this) {
            self::CONTACT_SUPPORT_EMAIL => IntegrationField::plain(IntegrationGroup::CONTACT, IntegrationFieldKind::EMAIL, public: true),
            self::CONTACT_PHONE, self::CONTACT_WHATSAPP => IntegrationField::plain(IntegrationGroup::CONTACT, IntegrationFieldKind::PHONE, public: true),
            self::CONTACT_ADDRESS_AR, self::CONTACT_ADDRESS_EN, self::CONTACT_HOURS_AR, self::CONTACT_HOURS_EN => IntegrationField::plain(IntegrationGroup::CONTACT, $text, public: true),
            self::SOCIAL_INSTAGRAM, self::SOCIAL_X, self::SOCIAL_FACEBOOK, self::SOCIAL_TIKTOK,
            self::SOCIAL_SNAPCHAT, self::SOCIAL_YOUTUBE, self::SOCIAL_LINKEDIN => IntegrationField::plain(IntegrationGroup::CONTACT, IntegrationFieldKind::URL, public: true),

            self::EMAIL_ENABLED => IntegrationField::toggle(IntegrationGroup::EMAIL),
            self::EMAIL_API_KEY => IntegrationField::secret(IntegrationGroup::EMAIL),
            self::EMAIL_FROM_ADDRESS, self::EMAIL_REPLY_TO => IntegrationField::plain(IntegrationGroup::EMAIL, IntegrationFieldKind::EMAIL),
            self::EMAIL_FROM_NAME => IntegrationField::plain(IntegrationGroup::EMAIL, $text),

            self::SMS_ACCOUNT_SID => IntegrationField::secret(IntegrationGroup::SMS, rules: ['regex:/^AC[0-9a-fA-F]{32}$/']),
            self::SMS_AUTH_TOKEN => IntegrationField::secret(IntegrationGroup::SMS),
            self::SMS_MESSAGING_SERVICE_SID => IntegrationField::plain(IntegrationGroup::SMS, $text, rules: ['regex:/^MG[0-9a-fA-F]{32}$/']),
            self::SMS_FROM_NUMBER => IntegrationField::plain(IntegrationGroup::SMS, IntegrationFieldKind::PHONE),

            self::GOOGLE_CLIENT_ID => IntegrationField::plain(IntegrationGroup::GOOGLE, $text),
            self::GOOGLE_CLIENT_SECRET => IntegrationField::secret(IntegrationGroup::GOOGLE),
            self::GOOGLE_GA4_MEASUREMENT_ID => IntegrationField::plain(IntegrationGroup::GOOGLE, $text, public: true, rules: ['regex:/^G-[A-Z0-9]{4,20}$/']),

            self::APPLE_SERVICE_ID, self::APPLE_TEAM_ID, self::APPLE_KEY_ID => IntegrationField::plain(IntegrationGroup::APPLE, $text),
            self::APPLE_PRIVATE_KEY => IntegrationField::secret(IntegrationGroup::APPLE, IntegrationFieldKind::TEXTAREA, ['regex:/-----BEGIN PRIVATE KEY-----.+-----END PRIVATE KEY-----/s']),

            self::PUSH_SERVICE_ACCOUNT => IntegrationField::secret(IntegrationGroup::PUSH, IntegrationFieldKind::TEXTAREA, [new ServiceAccountJsonRule]),

            self::TURNSTILE_ENABLED => IntegrationField::toggle(IntegrationGroup::ANTIBOT, public: true),
            self::TURNSTILE_SITE_KEY => IntegrationField::plain(IntegrationGroup::ANTIBOT, $text, public: true),
            self::TURNSTILE_SECRET_KEY => IntegrationField::secret(IntegrationGroup::ANTIBOT),

            self::PAYMENT_ENABLED => IntegrationField::toggle(IntegrationGroup::PAYMENT),
            self::PAYMENT_PROVIDER => IntegrationField::select(IntegrationGroup::PAYMENT, self::PAYMENT_PROVIDERS),
            self::PAYMENT_MODE => IntegrationField::select(IntegrationGroup::PAYMENT, ['test', 'live']),
            self::PAYMENT_MERCHANT_ID, self::PAYMENT_PUBLIC_KEY => IntegrationField::plain(IntegrationGroup::PAYMENT, $text),
            self::PAYMENT_SECRET_KEY, self::PAYMENT_WEBHOOK_SECRET => IntegrationField::secret(IntegrationGroup::PAYMENT),
        };
    }

    /**
     * Where a public setting sits in the public API payload. Null for any
     * setting that is not public.
     */
    public function publicPath(): ?string
    {
        return match ($this) {
            self::CONTACT_SUPPORT_EMAIL => 'contact.support_email',
            self::CONTACT_PHONE => 'contact.phone',
            self::CONTACT_WHATSAPP => 'contact.whatsapp',
            self::CONTACT_ADDRESS_AR => 'contact.address.ar',
            self::CONTACT_ADDRESS_EN => 'contact.address.en',
            self::CONTACT_HOURS_AR => 'contact.working_hours.ar',
            self::CONTACT_HOURS_EN => 'contact.working_hours.en',
            self::SOCIAL_INSTAGRAM => 'social.instagram',
            self::SOCIAL_X => 'social.x',
            self::SOCIAL_FACEBOOK => 'social.facebook',
            self::SOCIAL_TIKTOK => 'social.tiktok',
            self::SOCIAL_SNAPCHAT => 'social.snapchat',
            self::SOCIAL_YOUTUBE => 'social.youtube',
            self::SOCIAL_LINKEDIN => 'social.linkedin',
            self::GOOGLE_GA4_MEASUREMENT_ID => 'analytics.ga4_measurement_id',
            self::TURNSTILE_ENABLED => 'antibot.turnstile_enabled',
            self::TURNSTILE_SITE_KEY => 'antibot.turnstile_site_key',
            default => null,
        };
    }

    /**
     * @return list<self>
     */
    public static function forGroup(IntegrationGroup $group): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $setting): bool => $setting->definition()->group === $group,
        ));
    }
}
