<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Stable, public error codes returned in the `error.code` field of every
 * non-success API response. Mirrors the catalogue in qbazaar-contracts/error-codes.md.
 *
 * Once a code ships, its meaning MUST NOT change. New conditions get a new code.
 */
enum ErrorCode: string
{
    // ── Cross-cutting ───────────────────────────────────────────────
    case VALIDATION_FAILED = 'VALIDATION_FAILED';
    case RATE_LIMIT_EXCEEDED = 'RATE_LIMIT_EXCEEDED';
    case SERVER_ERROR = 'SERVER_ERROR';
    case REQUEST_IN_PROGRESS = 'REQUEST_IN_PROGRESS';
    case NOT_FOUND = 'NOT_FOUND';
    case FORBIDDEN = 'FORBIDDEN';

    // ── Auth (Sprint 1) ─────────────────────────────────────────────
    case AUTH_INVALID_CREDENTIALS = 'AUTH_001';
    case AUTH_ACCOUNT_SUSPENDED = 'AUTH_002';
    case AUTH_PHONE_NOT_VERIFIED = 'AUTH_003';
    case AUTH_OTP_EXPIRED = 'AUTH_004';
    case AUTH_OTP_INVALID = 'AUTH_005';
    case AUTH_RATE_LIMITED = 'AUTH_006';
    case AUTH_EMAIL_EXISTS = 'AUTH_007';
    case AUTH_PHONE_EXISTS = 'AUTH_008';
    case AUTH_TOKEN_EXPIRED = 'AUTH_009';
    case AUTH_TOKEN_INVALID = 'AUTH_010';
    case AUTH_REGISTRATION_REQUIRED = 'AUTH_011';
    case AUTH_DEVICE_CHALLENGE_INVALID = 'AUTH_012';
    case AUTH_SOCIAL_TOKEN_INVALID = 'AUTH_013';
    case AUTH_PASSWORD_LOGIN_DISABLED = 'AUTH_014';
    case AUTH_SOCIAL_PROVIDER_UNAVAILABLE = 'AUTH_015';
    case TURNSTILE_FAILED = 'TURNSTILE_001';

    // ── Users (Sprint 2) ────────────────────────────────────────────
    case USER_NOT_FOUND = 'USER_001';
    case USER_BLOCK_ADMIN_FORBIDDEN = 'USER_002';
    case USER_BLOCK_SELF_FORBIDDEN = 'USER_003';
    case USER_PASSWORD_CURRENT_REQUIRED = 'USER_004';
    case USER_DEACTIVATION_PASSWORD_REQUIRED = 'USER_005';

    // ── Social: follows, business profiles ─────────────────────────
    case FOLLOW_SELF_FORBIDDEN = 'FOLLOW_001';
    case FOLLOW_BLOCKED = 'FOLLOW_002';
    case BUSINESS_ACCOUNT_REQUIRED = 'BIZ_001';

    // ── Categories & Locations (Sprint 3) ───────────────────────────
    case CATEGORY_NOT_FOUND = 'CAT_001';
    case LOCATION_NOT_FOUND = 'LOC_001';

    // ── Uploads (Sprint 4) ──────────────────────────────────────────
    case UPLOAD_TOO_LARGE = 'UPLOAD_001';
    case UPLOAD_INVALID_MIME = 'UPLOAD_002';
    case UPLOAD_MAX_IMAGES_REACHED = 'UPLOAD_003';
    case UPLOAD_MAGIC_BYTES_MISMATCH = 'UPLOAD_004';

    // ── Ads (Sprint 5) ──────────────────────────────────────────────
    case AD_NOT_FOUND = 'AD_001';
    case AD_NOT_ACTIVE = 'AD_002';
    case AD_EDIT_FORBIDDEN = 'AD_003';
    case AD_INVALID_TRANSITION = 'AD_004';
    case AD_AUTO_MODERATION_REJECTED = 'AD_005';
    case AD_DAILY_PUBLISH_LIMIT = 'AD_006';
    case AD_EXPIRED = 'AD_007';
    case AD_OWN_OFFER_FORBIDDEN = 'AD_008';
    case AD_IMAGES_REQUIRED = 'AD_009';
    case AD_CUSTOM_FIELDS_INVALID = 'AD_010';
    case AD_NOT_PUBLISHABLE = 'AD_011';
    case AD_IMAGE_NOT_FOUND = 'AD_012';

    // ── Search (Sprint 6) ───────────────────────────────────────────
    case SEARCH_INDEX_UNAVAILABLE = 'SEARCH_001';
    case SEARCH_SAVED_NOT_FOUND = 'SEARCH_002';
    case SEARCH_INVALID_PARAMS = 'SEARCH_003';
    case SEARCH_SAVED_LIMIT = 'SEARCH_004';

    // ── Favorites ───────────────────────────────────────────────────
    case FAV_LIMIT_REACHED = 'FAV_001';

    // ── Messaging (Sprint 8) ────────────────────────────────────────
    case MSG_BLOCKED = 'MSG_001';
    case MSG_RATE_LIMITED = 'MSG_002';
    case MSG_FLAGGED = 'MSG_003';
    case MSG_CONVERSATION_NOT_FOUND = 'MSG_004';
    case MSG_NOT_PARTICIPANT = 'MSG_005';
    case MSG_CONVERSATION_OWN_AD = 'MSG_006';
    case MSG_NOT_FOUND = 'MSG_007';
    case MSG_CHAT_DISABLED = 'MSG_008';

    // ── Offers (Sprint 9) ───────────────────────────────────────────
    case OFFER_NOT_FOUND = 'OFFER_001';
    case OFFER_EXPIRED = 'OFFER_002';
    case OFFER_ALREADY_ACTIONED = 'OFFER_003';
    case OFFER_NOT_SELLER = 'OFFER_004';
    case OFFER_ACTIVE_EXISTS = 'OFFER_005';
    case OFFER_OWN_AD = 'OFFER_006';
    case OFFER_AD_NOT_ACTIVE = 'OFFER_007';
    case OFFER_NOT_PENDING = 'OFFER_008';
    case OFFER_FORBIDDEN = 'OFFER_009';
    case OFFER_AD_ALREADY_AGREED = 'OFFER_010';
    case OFFER_COUNTER_LIMIT_REACHED = 'OFFER_011';

    // ── Reviews ─────────────────────────────────────────────────────
    case REVIEW_NOT_ELIGIBLE = 'REVIEW_001';
    case REVIEW_ALREADY_EXISTS = 'REVIEW_002';
    case REVIEW_OWN_AD = 'REVIEW_003';

    // ── Reports (Sprint 10) ─────────────────────────────────────────
    case REPORT_SELF_FORBIDDEN = 'REPORT_001';
    case REPORT_DUPLICATE = 'REPORT_002';
    case REPORT_INVALID_TARGET = 'REPORT_003';

    // ── Notifications (Sprint 10) ───────────────────────────────────
    case NOTIF_NOT_FOUND = 'NOTIF_001';
    case NOTIF_DEVICE_TOKEN_INVALID = 'NOTIF_002';
    case NOTIF_FORBIDDEN = 'NOTIF_003';

    // ── CMS & Support (Sprint 12) ───────────────────────────────────
    case CMS_PAGE_NOT_FOUND = 'CMS_001';
    case HELP_ARTICLE_NOT_FOUND = 'HELP_001';
    case HELP_CATEGORY_NOT_FOUND = 'HELP_002';
    case TICKET_NOT_FOUND = 'TICKET_001';
    case TICKET_FORBIDDEN = 'TICKET_002';
    case TICKET_INVALID_TRANSITION = 'TICKET_003';

    // ── Saved addresses ────────────────────────────────────────────────────
    case ADDRESS_NOT_FOUND = 'ADDRESS_001';
    case ADDRESS_LIMIT_REACHED = 'ADDRESS_002';

    // ── Data export ────────────────────────────────────────────────────────
    case DATA_EXPORT_LINK_EXPIRED = 'EXPORT_001';

    // ── Account changes ────────────────────────────────────────────────────
    case ACCOUNT_REAUTH_INVALID = 'ACCOUNT_001';
    case ACCOUNT_EMAIL_CHANGE_LINK_INVALID = 'ACCOUNT_002';
    case ACCOUNT_DEBT_OUTSTANDING = 'ACCOUNT_003';
    case ACCOUNT_HAS_OPEN_ORDER = 'ACCOUNT_004';

    // ── Orders (M1b) ───────────────────────────────────────────────────────
    case ORDER_NOT_FOUND = 'ORDER_001';
    case ORDER_INVALID_TRANSITION = 'ORDER_002';
    case ORDER_AD_HAS_ACTIVE_ORDER = 'ORDER_003';
    case ORDER_SELLER_DEBT_CEILING = 'ORDER_004';
    case ORDER_FORBIDDEN = 'ORDER_005';

    // ── Wallet (M1b) ───────────────────────────────────────────────────────
    case WALLET_INSUFFICIENT_BALANCE = 'WALLET_001';
    case WALLET_EXCEEDS_COMMISSION_DEBT = 'WALLET_002';

    // ── Purchase requests and checkout (M1b) ───────────────────────────────
    case PURCHASE_REQUEST_NOT_FOUND = 'PURCHASE_001';
    case PURCHASE_REQUEST_OPEN_EXISTS = 'PURCHASE_002';
    case PURCHASE_REQUEST_NOT_PENDING = 'PURCHASE_003';
    case PURCHASE_REQUEST_FORBIDDEN = 'PURCHASE_004';
    case PURCHASE_REQUEST_OWN_AD = 'PURCHASE_005';
    case PURCHASE_REQUEST_AD_NOT_AVAILABLE = 'PURCHASE_006';
    case PURCHASE_QUANTITY_UNAVAILABLE = 'PURCHASE_007';
    case CHECKOUT_DELIVERY_UNAVAILABLE = 'CHECKOUT_001';

    /**
     * i18n key the client should look up in its translations file.
     * Convention: errors.{snake_case_code}.
     */
    public function messageKey(): string
    {
        return 'errors.' . strtolower(str_replace('_', '.', $this->name));
    }

    /**
     * Default HTTP status for this error. Controllers / handlers can override.
     */
    public function httpStatus(): int
    {
        return match ($this) {
            self::VALIDATION_FAILED,
            self::AUTH_OTP_INVALID,
            self::AUTH_EMAIL_EXISTS,
            self::AUTH_PHONE_EXISTS,
            self::AUTH_REGISTRATION_REQUIRED,
            self::TURNSTILE_FAILED,
            self::USER_PASSWORD_CURRENT_REQUIRED,
            self::USER_DEACTIVATION_PASSWORD_REQUIRED,
            self::USER_BLOCK_SELF_FORBIDDEN,
            self::FOLLOW_SELF_FORBIDDEN,
            self::UPLOAD_INVALID_MIME,
            self::UPLOAD_MAX_IMAGES_REACHED,
            self::UPLOAD_MAGIC_BYTES_MISMATCH,
            self::AD_NOT_ACTIVE,
            self::AD_INVALID_TRANSITION,
            self::AD_AUTO_MODERATION_REJECTED,
            self::AD_OWN_OFFER_FORBIDDEN,
            self::AD_IMAGES_REQUIRED,
            self::AD_CUSTOM_FIELDS_INVALID,
            self::AD_NOT_PUBLISHABLE,
            self::MSG_FLAGGED,
            self::MSG_CONVERSATION_OWN_AD,
            self::OFFER_ALREADY_ACTIONED,
            self::OFFER_ACTIVE_EXISTS,
            self::OFFER_OWN_AD,
            self::OFFER_AD_NOT_ACTIVE,
            self::OFFER_NOT_PENDING,
            self::OFFER_AD_ALREADY_AGREED,
            self::OFFER_COUNTER_LIMIT_REACHED,
            self::REPORT_SELF_FORBIDDEN,
            self::REPORT_INVALID_TARGET,
            self::NOTIF_DEVICE_TOKEN_INVALID,
            self::SEARCH_INVALID_PARAMS,
            self::SEARCH_SAVED_LIMIT,
            self::FAV_LIMIT_REACHED,
            self::REVIEW_ALREADY_EXISTS,
            self::REVIEW_OWN_AD,
            self::TICKET_INVALID_TRANSITION,
            self::ADDRESS_LIMIT_REACHED,
            self::ACCOUNT_REAUTH_INVALID,
            self::ACCOUNT_DEBT_OUTSTANDING,
            self::ACCOUNT_HAS_OPEN_ORDER,
            self::ORDER_INVALID_TRANSITION,
            self::ORDER_AD_HAS_ACTIVE_ORDER,
            self::ORDER_SELLER_DEBT_CEILING,
            self::WALLET_INSUFFICIENT_BALANCE,
            self::WALLET_EXCEEDS_COMMISSION_DEBT,
            self::PURCHASE_REQUEST_OPEN_EXISTS,
            self::PURCHASE_REQUEST_NOT_PENDING,
            self::PURCHASE_REQUEST_OWN_AD,
            self::PURCHASE_REQUEST_AD_NOT_AVAILABLE,
            self::PURCHASE_QUANTITY_UNAVAILABLE,
            self::CHECKOUT_DELIVERY_UNAVAILABLE => 422,

            self::AUTH_INVALID_CREDENTIALS,
            self::AUTH_TOKEN_EXPIRED,
            self::AUTH_TOKEN_INVALID,
            self::AUTH_DEVICE_CHALLENGE_INVALID,
            self::AUTH_SOCIAL_TOKEN_INVALID => 401,

            self::AUTH_ACCOUNT_SUSPENDED,
            self::AUTH_PHONE_NOT_VERIFIED,
            self::AUTH_PASSWORD_LOGIN_DISABLED,
            self::USER_BLOCK_ADMIN_FORBIDDEN,
            self::FOLLOW_BLOCKED,
            self::BUSINESS_ACCOUNT_REQUIRED,
            self::AD_EDIT_FORBIDDEN,
            self::MSG_BLOCKED,
            self::MSG_NOT_PARTICIPANT,
            self::MSG_CHAT_DISABLED,
            self::OFFER_NOT_SELLER,
            self::OFFER_FORBIDDEN,
            self::NOTIF_FORBIDDEN,
            self::REVIEW_NOT_ELIGIBLE,
            self::TICKET_FORBIDDEN,
            self::ORDER_FORBIDDEN,
            self::PURCHASE_REQUEST_FORBIDDEN,
            self::FORBIDDEN => 403,

            self::USER_NOT_FOUND,
            self::CATEGORY_NOT_FOUND,
            self::LOCATION_NOT_FOUND,
            self::AD_NOT_FOUND,
            self::AD_IMAGE_NOT_FOUND,
            self::SEARCH_SAVED_NOT_FOUND,
            self::MSG_CONVERSATION_NOT_FOUND,
            self::MSG_NOT_FOUND,
            self::OFFER_NOT_FOUND,
            self::NOTIF_NOT_FOUND,
            self::CMS_PAGE_NOT_FOUND,
            self::HELP_ARTICLE_NOT_FOUND,
            self::HELP_CATEGORY_NOT_FOUND,
            self::TICKET_NOT_FOUND,
            self::ADDRESS_NOT_FOUND,
            self::ORDER_NOT_FOUND,
            self::PURCHASE_REQUEST_NOT_FOUND,
            self::NOT_FOUND => 404,

            self::AUTH_OTP_EXPIRED,
            self::AD_EXPIRED,
            self::OFFER_EXPIRED,
            self::DATA_EXPORT_LINK_EXPIRED,
            self::ACCOUNT_EMAIL_CHANGE_LINK_INVALID => 410,

            self::UPLOAD_TOO_LARGE => 413,

            self::AUTH_RATE_LIMITED,
            self::RATE_LIMIT_EXCEEDED,
            self::AD_DAILY_PUBLISH_LIMIT,
            self::MSG_RATE_LIMITED,
            self::REPORT_DUPLICATE => 429,

            self::REQUEST_IN_PROGRESS => 409,

            self::SEARCH_INDEX_UNAVAILABLE,
            self::AUTH_SOCIAL_PROVIDER_UNAVAILABLE => 503,

            self::SERVER_ERROR => 500,
        };
    }
}
