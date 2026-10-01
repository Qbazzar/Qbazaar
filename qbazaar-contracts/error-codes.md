# QBazaar — Error Code Catalog

All API errors carry a stable `code` field. Frontend clients use the `message_key`
field for i18n lookup; the `message` is a human-readable fallback in the
requested locale.

> Format: `<DOMAIN>_<NUMBER>` — e.g. `AUTH_001`, `AD_005`.
> Domain prefixes are short upper-case words (`AUTH`, `AD`, `UPLOAD`, …); numbers are zero-padded to 3 digits.
> The code-side list is `qbazaar-api/app/Exceptions/ErrorCode.php` (codes + default HTTP status); keep this file in step with it.

## Cross-cutting

| Code | Meaning | HTTP |
|------|---------|------|
| `VALIDATION_FAILED` | Request body failed validation. `details` carries field errors. | 422 |
| `RATE_LIMIT_EXCEEDED` | Too many requests for the current rate-limit tier. | 429 |
| `SERVER_ERROR` | Unhandled server-side error. | 500 |
| `REQUEST_IN_PROGRESS` | An identical request (same idempotency key, or a concurrent image upload to the same ad) is still running; retry shortly. | 409 |

## Auth (Sprint 1)

| Code | Meaning | HTTP |
|------|---------|------|
| `AUTH_001` | Invalid credentials (email/phone + password mismatch) | 401 |
| `AUTH_002` | Account suspended | 403 |
| `AUTH_003` | Phone not verified — endpoint requires verified phone | 403 |
| `AUTH_004` | OTP expired | 410 |
| `AUTH_005` | OTP invalid (wrong code, or max attempts exceeded) | 422 |
| `AUTH_006` | Rate limit on auth: OTP send, or the login identifier is locked after repeated failed sign-ins (`details.retry_after` in seconds) | 429 |
| `AUTH_007` | Email already exists | 422 |
| `AUTH_008` | Phone already exists | 422 |
| `AUTH_009` | Token expired | 401 |
| `AUTH_010` | Token invalid (malformed / revoked) | 401 |

## Bot protection (Cloudflare Turnstile)

| Code | Meaning | HTTP |
|------|---------|------|
| `TURNSTILE_001` | Turnstile check failed: the `X-Turnstile-Token` header is missing, invalid, expired or already used. Get a fresh token from the widget and retry. Only returned while `TURNSTILE_ENABLED=true`. | 422 |

## Users (Sprint 2)

| Code | Meaning | HTTP |
|------|---------|------|
| `USER_001` | User not found | 404 |
| `USER_002` | Cannot block an admin / super admin | 403 |
| `USER_003` | Cannot block yourself | 422 |
| `USER_004` | Password change requires current password | 422 |
| `USER_005` | Account deactivation requires password confirmation | 422 |

## Categories & Locations (Sprint 3)

| Code | Meaning | HTTP |
|------|---------|------|
| `CAT_001` | Category not found | 404 |
| `LOC_001` | Location not found | 404 |

## Uploads (Sprint 4)

| Code | Meaning | HTTP |
|------|---------|------|
| `UPLOAD_001` | File too large (> 10MB) | 413 |
| `UPLOAD_002` | Unsupported MIME type | 422 |
| `UPLOAD_003` | Max images per ad reached (platform setting `ad_max_images`, default 20) | 422 |
| `UPLOAD_004` | File magic bytes mismatch declared MIME | 422 |

## Ads (Sprint 5)

| Code | Meaning | HTTP |
|------|---------|------|
| `AD_001` | Ad not found | 404 |
| `AD_002` | Ad not active — cannot perform this action | 422 |
| `AD_003` | Cannot edit ad you do not own | 403 |
| `AD_004` | Invalid state transition for ad | 422 |
| `AD_005` | Auto-moderation rejected the ad — see rejection_reason | 422 |
| `AD_006` | Daily publish limit reached (platform setting `ad_daily_publish_limit`, rolling 24 h) | 429 |
| `AD_007` | Ad expired | 410 |
| `AD_008` | Cannot perform offers on your own ad | 422 |
| `AD_009` | Ad images required (`qbazaar.ads.min_images`, default 1) | 422 |
| `AD_010` | Custom fields for category did not validate | 422 |
| `AD_011` | Ad cannot be published in its current state | 422 |
| `AD_012` | Image not found for this ad | 404 |

## Search (Sprint 6)

| Code | Meaning | HTTP |
|------|---------|------|
| `SEARCH_001` | Search index temporarily unavailable | 503 |
| `SEARCH_002` | Saved search not found | 404 |
| `SEARCH_003` | Invalid search parameters | 422 |
| `SEARCH_004` | Saved-search limit per user reached | 422 |

## Messaging (Sprint 8)

| Code | Meaning | HTTP |
|------|---------|------|
| `MSG_001` | Recipient has blocked you (or vice versa) | 403 |
| `MSG_002` | Message rate limit exceeded (30/min) | 429 |
| `MSG_003` | Message contains flagged content — review required | 422 |
| `MSG_004` | Conversation not found | 404 |
| `MSG_005` | You are not a participant of this conversation | 403 |
| `MSG_006` | Cannot start a conversation about your own ad | 422 |
| `MSG_007` | Message not found (e.g. invalid `before` cursor) | 404 |
| `MSG_008` | The seller does not accept chat messages or offers | 403 |

## Offers (Sprint 9)

| Code | Meaning | HTTP |
|------|---------|------|
| `OFFER_001` | Offer not found | 404 |
| `OFFER_002` | Offer expired | 410 |
| `OFFER_003` | Offer already actioned (accepted / rejected) | 422 |
| `OFFER_004` | Only the seller can accept / reject | 403 |
| `OFFER_005` | Buyer already has a pending offer on this ad | 422 |
| `OFFER_006` | Cannot make an offer on your own ad | 422 |
| `OFFER_007` | Ad must be active to receive an offer | 422 |
| `OFFER_008` | Offer is not in a pending state | 422 |
| `OFFER_009` | Not authorised to act on this offer | 403 |
| `OFFER_010` | Another offer on this ad has already been accepted | 422 |
| `OFFER_011` | No counter-offer rounds left for this side | 422 |

## Reports (Sprint 10)

| Code | Meaning | HTTP |
|------|---------|------|
| `REPORT_001` | Cannot report yourself | 422 |
| `REPORT_002` | Already reported this target within the duplicate-window (7 days default) | 429 |
| `REPORT_003` | The target_type/target_id pair does not resolve to an existing record | 422 |

## Notifications (Sprint 10)

| Code | Meaning | HTTP |
|------|---------|------|
| `NOTIF_001` | Notification not found | 404 |
| `NOTIF_002` | Device token invalid | 422 |
| `NOTIF_003` | Notification exists but belongs to another user | 403 |

## CMS & Support (Sprint 12)

| Code | Meaning | HTTP |
|------|---------|------|
| `CMS_001` | Page not found | 404 |
| `HELP_001` | Article not found | 404 |
| `HELP_002` | Help category not found | 404 |
| `TICKET_001` | Support ticket not found | 404 |
| `TICKET_002` | Not allowed to act on this ticket | 403 |
| `TICKET_003` | Invalid ticket status change | 422 |

## Reviews

| Code | Meaning | HTTP |
|------|---------|------|
| `REVIEW_001` | Not eligible: a review needs a completed deal (accepted offer) on the ad | 403 |
| `REVIEW_002` | This ad has already been reviewed by you | 422 |
| `REVIEW_003` | Cannot review your own ad | 422 |

## Saved addresses

| Code | Meaning | HTTP |
|------|---------|------|
| `ADDRESS_001` | Address not found (or owned by someone else) | 404 |
| `ADDRESS_002` | The saved-address limit (`qbazaar.account.max_addresses`, 10) is reached | 422 |

## Data export

| Code | Meaning | HTTP |
|------|---------|------|
| `EXPORT_001` | The export download link was already used, has expired, or the export is not ready | 410 |

---

## Guidelines

- **Stability:** Once a code ships, it MUST NOT change meaning. If the meaning changes, introduce a new code.
- **Frontend behavior:** clients should switch on `code`, not on `message`. The message is for fallback display only.
- **i18n:** `message_key` maps 1:1 to a key in `lang/{ar,en}/errors.php` on the backend, and to a key in `i18n/{ar,en}.json` on the frontend.
- **Adding a new code:** add to this file in the relevant sprint section, increment the number, and reference the spec PR.
