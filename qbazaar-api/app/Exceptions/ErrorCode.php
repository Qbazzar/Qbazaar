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

    // ── Users (Sprint 2) ────────────────────────────────────────────
    case USER_NOT_FOUND = 'USER_001';
    case USER_BLOCK_ADMIN_FORBIDDEN = 'USER_002';
    case USER_BLOCK_SELF_FORBIDDEN = 'USER_003';
    case USER_PASSWORD_CURRENT_REQUIRED = 'USER_004';
    case USER_DEACTIVATION_PASSWORD_REQUIRED = 'USER_005';

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

    // ── Search (Sprint 6) ───────────────────────────────────────────
    case SEARCH_INDEX_UNAVAILABLE = 'SEARCH_001';
    case SEARCH_SAVED_NOT_FOUND = 'SEARCH_002';

    // ── Messaging (Sprint 8) ────────────────────────────────────────
    case MSG_BLOCKED = 'MSG_001';
    case MSG_RATE_LIMITED = 'MSG_002';
    case MSG_FLAGGED = 'MSG_003';
    case MSG_CONVERSATION_NOT_FOUND = 'MSG_004';
    case MSG_NOT_PARTICIPANT = 'MSG_005';

    // ── Offers (Sprint 9) ───────────────────────────────────────────
    case OFFER_NOT_FOUND = 'OFFER_001';
    case OFFER_EXPIRED = 'OFFER_002';
    case OFFER_ALREADY_ACTIONED = 'OFFER_003';
    case OFFER_NOT_SELLER = 'OFFER_004';

    // ── Reports (Sprint 10) ─────────────────────────────────────────
    case REPORT_SELF_FORBIDDEN = 'REPORT_001';
    case REPORT_DUPLICATE = 'REPORT_002';

    // ── Notifications (Sprint 10) ───────────────────────────────────
    case NOTIF_NOT_FOUND = 'NOTIF_001';
    case NOTIF_DEVICE_TOKEN_INVALID = 'NOTIF_002';

    // ── CMS & Support (Sprint 12) ───────────────────────────────────
    case CMS_PAGE_NOT_FOUND = 'CMS_001';
    case HELP_ARTICLE_NOT_FOUND = 'HELP_001';
    case TICKET_NOT_FOUND = 'TICKET_001';

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
            self::USER_PASSWORD_CURRENT_REQUIRED,
            self::USER_DEACTIVATION_PASSWORD_REQUIRED,
            self::USER_BLOCK_SELF_FORBIDDEN,
            self::UPLOAD_INVALID_MIME,
            self::UPLOAD_MAX_IMAGES_REACHED,
            self::UPLOAD_MAGIC_BYTES_MISMATCH,
            self::AD_NOT_ACTIVE,
            self::AD_INVALID_TRANSITION,
            self::AD_AUTO_MODERATION_REJECTED,
            self::AD_OWN_OFFER_FORBIDDEN,
            self::AD_IMAGES_REQUIRED,
            self::AD_CUSTOM_FIELDS_INVALID,
            self::MSG_FLAGGED,
            self::OFFER_ALREADY_ACTIONED,
            self::REPORT_SELF_FORBIDDEN,
            self::REPORT_DUPLICATE,
            self::NOTIF_DEVICE_TOKEN_INVALID => 422,

            self::AUTH_INVALID_CREDENTIALS,
            self::AUTH_TOKEN_EXPIRED,
            self::AUTH_TOKEN_INVALID => 401,

            self::AUTH_ACCOUNT_SUSPENDED,
            self::AUTH_PHONE_NOT_VERIFIED,
            self::USER_BLOCK_ADMIN_FORBIDDEN,
            self::AD_EDIT_FORBIDDEN,
            self::MSG_BLOCKED,
            self::MSG_NOT_PARTICIPANT,
            self::OFFER_NOT_SELLER => 403,

            self::USER_NOT_FOUND,
            self::CATEGORY_NOT_FOUND,
            self::LOCATION_NOT_FOUND,
            self::AD_NOT_FOUND,
            self::SEARCH_SAVED_NOT_FOUND,
            self::MSG_CONVERSATION_NOT_FOUND,
            self::OFFER_NOT_FOUND,
            self::NOTIF_NOT_FOUND,
            self::CMS_PAGE_NOT_FOUND,
            self::HELP_ARTICLE_NOT_FOUND,
            self::TICKET_NOT_FOUND => 404,

            self::AUTH_OTP_EXPIRED,
            self::AD_EXPIRED,
            self::OFFER_EXPIRED => 410,

            self::UPLOAD_TOO_LARGE => 413,

            self::AUTH_RATE_LIMITED,
            self::RATE_LIMIT_EXCEEDED,
            self::AD_DAILY_PUBLISH_LIMIT,
            self::MSG_RATE_LIMITED => 429,

            self::SEARCH_INDEX_UNAVAILABLE => 503,

            self::SERVER_ERROR => 500,
        };
    }
}
