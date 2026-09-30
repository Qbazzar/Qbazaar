# Mobile app gaps against the current API

> Compares what the app `Qbazaar-mobile` needs (from `src/repositories/*`) with what exists in `qbazaar-api` (96 routes under `/api/v1`, verified with `php artisan route:list --path=api/v1` on 2026-09-30).
> ✅ exists · 🟡 partial (difference in shape or params) · ❌ missing. The task that fixes each item is in the last column (see `MILESTONES-V2.md`).

## API conventions the app has to adapt to
- **Success envelope:** `{ success, data, meta? }`, JSON in **snake_case**, IDs are **ULID**, dates ISO-8601, money `price` + `currency` + `price_formatted`.
- **Pagination:** `meta{current_page, per_page, total, last_page, has_more, next_cursor}`. The default is 20. Messages use cursor pagination (`before`).
- **Error envelope:** `{ success:false, error{ code, message_key, message, details, request_id } }`. Codes are in `error-codes.md`, and `details` holds the field errors.
- **Headers:** `Authorization: Bearer`, `Accept-Language` (ar/en), `X-Client-Platform` (`mobile_ios|mobile_android`), `X-Idempotency-Key` on publish.
- **Tokens:** access token 15 minutes, refresh token 30 days (`POST /auth/refresh`).
- **Rate limits:** api 120/min · auth 5/min · otp 3/min · messages 30/min · publish 10/day.

## Catalog and search
| App need | Status | In the API now | Task |
|---|---|---|---|
| Category tree with counts | 🟡 | `GET /categories/tree`, but `ads_count` is always 0 and there's no `today_count` | BE-14.3 |
| Category page with sections | ❌ | — | BE-14.4 |
| Category ads including its children | 🟡 | `/search?category_slug=` matches only the exact category | BE-14.5 |
| Ad detail | 🟡 | `GET /ads/{id}` doesn't count the view (`POST /ads/{id}/view` is separate) and has no `is_favorited` | BE-14.8 |
| Ads by list of IDs | ❌ | — | BE-14.6 |
| Home in one request | ❌ | only `/ads/featured` | BE-14.7 |
| Search + filters | 🟡 | `GET /search` (q, category_slug, location_slug, price_min/max, sort, condition, price_type, custom_fields) — no distance and no `most_viewed` | BE-14.9, BE-14.10 |

## Auth
| App need | Status | In the API now | Task |
|---|---|---|---|
| Registration | 🟡 | `POST /auth/register` needs `full_name`, `phone`, `account_type=private|business`, `accepted_terms`, and a strong password | MB-15.5 (the app adapts) |
| Passwordless login (email code) + SMS from a new device | ❌ | Login with a password only, returns tokens straight away | BE-14.11, BE-14.12 |
| Send/verify a code | ✅ | `/auth/send-otp`, `verify-otp`, `resend-otp` | — |
| Password reset | — | No longer needed (passwordless login) | — |
| Google/Apple | ❌ | — | BE-14.13 |
| `/me` | 🟡 | `GET /account/profile` | BE-14.14 |
| Logout / refresh | ✅ | `/auth/logout`, `/auth/refresh` | — |

## Messages, offers and notifications
| App need | Status | In the API now | Task |
|---|---|---|---|
| Conversation list / thread / start / send / mark read | ✅ | `/conversations…` (text only) | — |
| Images in chat | ❌ | — | BE-14.15 |
| Delete conversations | ❌ | — | BE-14.16 |
| Offers: create / accept / reject / withdraw | ✅ | `/conversations/{id}/offers`, `/offers/{id}/…` (expire after 7 days) | — |
| Counter-offer | ❌ | — | BE-14.17 |
| Realtime from mobile | 🟡 **blocker** | `broadcasting/auth` on web only | BE-14.1 |
| Push for message/offer | ❌ | FCM is used only for ads, search and the system | BE-14.2 |
| Notifications + filter by type | 🟡 | `/account/notifications?unread=1` only | BE-14.18 |

## Favorites
| App need | Status | In the API now | Task |
|---|---|---|---|
| List | ✅ | `GET /account/favorites` | — |
| IDs + idempotent add/remove | 🟡 | a toggle only: `POST /ads/{id}/favorite` | BE-14.19 |

## Selling and my ads
| App need | Status | In the API now | Task |
|---|---|---|---|
| Drafts create/update/fetch | ✅ | `POST /ads`, `PUT /ads/{id}`, `GET /ads/{id}` (but shares the publish limiter) | BE-14.20 |
| Photo upload (20) | 🟡 | `POST /ads/{id}/images`, max 10 | BE-14.20 |
| Publish | 🟡 | `POST /ads/{id}/publish` → `pending` (review) | the app shows "Under review" |
| New fields (type, delivery, postal code, street) | ❌ | — | BE-14.21 |
| Reserve | ❌ | — (`mark-sold` and `renew` exist) | BE-14.22 |
| My ads + status filter + stats | 🟡 | `GET /account/ads` with no filter | BE-14.23 |
| Cities | ✅ | `GET /locations/qatar` | — |
| Paid promotion | ❌ (deferred) | — | M7 |

## Settings
| App need | Status | In the API now | Task |
|---|---|---|---|
| Edit the name | 🟡 | `PUT /account/profile` needs every field | BE-14.24 |
| Upload / delete the avatar | 🟡 | upload ✅ `/uploads/avatar`, delete ❌ | BE-14.24 |
| Change email/phone | ❌ | — | BE-14.25 |
| Change password | ✅ | `PUT /account/password` | — |
| Addresses | ❌ | — | BE-14.26 |
| Email preferences | ❌ | (`/account/privacy-settings` exists) | BE-14.27 |
| Delete account + reason | ✅ | `DELETE /account/delete-request` (30-day grace period) | — |

## Social
| App need | Status | In the API now | Task |
|---|---|---|---|
| Seller profile | 🟡 | `/users/{id}/public-profile` has no about, legal info or follow status | BE-14.30 |
| Seller ads | ✅ | `/users/{id}/ads` | — |
| Follows | ❌ | — | BE-14.28 |
| Companies directory | ❌ | — | BE-14.29 |
| Saved searches | ✅ | `/account/saved-searches` (max 10 in code, 20 in config) | — |
| Toggle alerts | ❌ | — | BE-14.31 |
| Block / report | ✅ | `/users/{id}/block`, `POST /reports` | — |
| Register device token | ✅ | `/account/device-tokens` (native FCM) | MB-15.7 |

## Payments, wallet and billing
By decision (2026-09-30): **orders + cash + an admin-set commission + a wallet + settlements** from the first launch, **with no electronic gateway** (`PaymentGateway` interface + `CashGateway`). Nothing exists in the API today. The tasks: BE-14.33 → BE-14.41, AD-17.7, MB-15.11, FE-16.8.
