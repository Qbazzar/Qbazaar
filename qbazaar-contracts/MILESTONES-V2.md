# QBazaar V2: milestones and tasks

> Carries on from [`MILESTONES.md`](MILESTONES.md) (Sprints 0–12 are done). The plan and decisions are in [`V2-PLAN.md`](V2-PLAN.md).
> The same IDs and definition of done as the original file. **New:** `MB-X.Y` for the mobile app (`Qbazaar-mobile`), `AD-X.Y` for admin, `OPS-X.Y` for operations/deployment.
> Each task gets a GitHub Issue with the same ID. Backend/web/admin/ops tasks go in `Qbazzar/Qbazaar`; mobile tasks go in `Qbazzar/Qbazaar-mobile`.

**Definition of done for every backend task:** FormRequest + Resource + Policy (where applicable) + Pest tests + an OpenAPI entry + Pint/Larastan passing.
**Definition of done for every mobile task:** `tsc --noEmit` clean + no mock left on the switched path + the flow tested on a real device or in Expo Go.

---

## Sprint 13 — M0 Preparation and alignment

| ID | Task | Priority | Acceptance criteria |
|---|---|---|---|
| MB-13.1 | Finish the mobile design polish round (re-shoot at 390/430, fix the Transactions title at 360, merge `polish/mobile-ergonomics`) | [P0] | No text overflow or broken wrapping at 360/390/430; branch merged |
| MB-13.2 | Unify the app's browse and sell category trees into one taxonomy that matches the backend | [P0] | One `Category` type with the same slugs as `/categories/tree` |
| CT-13.1 | OpenAPI v1.1: add all the new M1 endpoints and mark them `x-status: planned` | [P0] | `v1.yaml` validates; each new endpoint has a request and a response |
| CT-13.2 | Add the missing `reviews` endpoints to OpenAPI | [P2] | They match the existing routes |
| OPS-13.1 | Security: revoke the Figma token; move and rotate the Firebase key; change the VPS root password and disable password SSH | [P0] | No secrets outside `.env`; SSH by key only |
| OPS-13.2 | Settle the open questions in `V2-PLAN.md §4` with the client | [P0] | Every question has a written decision |

## Sprint 14 — M1 Closing the backend gaps (no payments)

### Realtime and push
| ID | Task | Endpoint | Priority | Acceptance criteria |
|---|---|---|---|---|
| BE-14.1 | Channel authorization for Bearer clients | `POST /api/v1/broadcasting/auth` | [P0] | A Sanctum token subscribes to `user.{id}` and `conversation.{id}`; a test covers a denied channel |
| BE-14.2 | FCM push for a new message and for offer events | — | [P0] | Offline recipient gets a push; no push to the sender; test with a fake FCM |

### Catalog and search
| ID | Task | Endpoint | Priority | Acceptance criteria |
|---|---|---|---|---|
| BE-14.3 | Real `ads_count` / `today_count` for categories (counter + parent rollup) | `/categories/tree` | [P0] | Counts match the active ads, including children; cached and invalidated on publish/expiry |
| BE-14.4 | Category page with a section per child | `GET /categories/{slug}` | [P0] | `{category, parent, sub_category_count, sections[{category, ads[≤6]}]}` |
| BE-14.5 | Searching a category includes all its descendants | `/search`, `/ads` | [P0] | An ad in a grandchild category shows up when searching the grandparent |
| BE-14.6 | Fetch ads by list | `GET /ads?ids=` | [P1] | Preserves order; ignores inactive ads |
| BE-14.7 | Home in one request | `GET /home` | [P0] | categories, recommended, featured_sellers, best_selling, places; cached for 5 minutes |
| BE-14.8 | `is_favorited` on the ad (optional token) | `/ads/{id}`, lists | [P1] | true/false for the logged-in user, false for guests |
| BE-14.9 | Distance search: `_geo` in Meilisearch + `lat,lng,radius_km` | `GET /search` | [P0] | Results within the radius, sorted by distance when requested |
| BE-14.10 | `most_viewed` sort (index `views_count`) | `GET /search` | [P1] | Correct sort; works together with the filters |

### Auth
| ID | Task | Endpoint | Priority | Acceptance criteria |
|---|---|---|---|---|
| BE-14.11 | OTP second factor at login from a new device (behind a config switch) | `POST /auth/login` → `challenge`, `POST /auth/login/verify` | [P0] | A new device gets a `challenge_token` and no tokens until the code is right; a known device skips it |
| BE-14.12 | Password reset by OTP (for mobile) | `POST /auth/forgot-password/otp`, `/reset-password/otp` | [P0] | Works without an email link; respects the OTP limits |
| BE-14.13 | Google + Apple login (id_token) | `POST /auth/social/{provider}` | [P1] | Verifies the token signature; links by email; creates a new user with `phone_verified=false` |
| BE-14.14 | Alias `GET /me` → the current profile | `GET /me` | [P1] | Same shape as `/account/profile` |

### Messages, offers and notifications
| ID | Task | Endpoint | Priority | Acceptance criteria |
|---|---|---|---|---|
| BE-14.15 | Image attachments in messages (`type=image`, media) | `POST /conversations/{id}/messages` multipart | [P0] | Image ≤ 10MB; the message comes back with a `media` URL; `message.sent` is broadcast |
| BE-14.16 | Hide conversations in bulk (per user) | `DELETE /conversations` `{ids}` | [P1] | Hidden only for the requester; reappears on a new message |
| BE-14.17 | Counter-offer (one round from each side) | `POST /offers/{id}/counter` | [P1] | A new `countered` state; `offer.countered` event; a limit on the number of rounds |
| BE-14.18 | New notification types (`ads.new_from_followed`, `ad.price_changed`) + filter `?category=` | `/account/notifications` | [P1] | A price drop on a favorited ad notifies whoever favorited it |

### Favorites
| ID | Task | Endpoint | Priority | Acceptance criteria |
|---|---|---|---|---|
| BE-14.19 | IDs list + idempotent add and remove | `GET /account/favorites/ids`, `PUT`/`DELETE /ads/{id}/favorite` | [P0] | Repeating add or remove doesn't fail; the old toggle stays working |

### Selling and my ads
| ID | Task | Endpoint | Priority | Acceptance criteria |
|---|---|---|---|---|
| BE-14.20 | Raise the image limit to 20 + separate the draft limiter from the publish limiter | config + `throttle:drafts` | [P0] | 20 images accepted; creating a draft doesn't count against publishing |
| BE-14.21 | New ad fields: `ad_type`, `shipping`, `postal_code`, `street`, `show_full_address` | `POST/PUT /ads` | [P0] | Migration + validation + in the Resource and in search |
| BE-14.22 | Reserved status + toggle | `POST /ads/{id}/reserve`, `DELETE …/reserve` | [P1] | `reserved` shows on the listing; can't be reserved while a draft |
| BE-14.23 | My ads with a status filter + stats per ad | `GET /account/ads?status=` | [P0] | views/favorites/messages counts for each ad |

### Settings
| ID | Task | Endpoint | Priority | Acceptance criteria |
|---|---|---|---|---|
| BE-14.24 | Partial profile update + delete the avatar | `PATCH /account/profile`, `DELETE /uploads/avatar` | [P0] | Editing the name alone works |
| BE-14.25 | Change email / phone with the password + verification | `POST /account/email`, `POST /account/phone` | [P1] | Email: link to the new address + notice to the old one. Phone: OTP |
| BE-14.26 | Saved addresses (table + CRUD) | `/account/addresses` | [P1] | Max 10; one default |
| BE-14.27 | Email preferences | `GET/PATCH /account/email-preferences` | [P1] | Respected when sending emails |

### Social
| ID | Task | Endpoint | Priority | Acceptance criteria |
|---|---|---|---|---|
| BE-14.28 | Follows (table + follow/unfollow + lists + counts + remove a follower) | `/users/{id}/follow`, `/account/followers`, `/account/following` | [P0] | Can't follow yourself; blocking removes the follow in both directions |
| BE-14.29 | Companies directory (business accounts) with search and pagination | `GET /companies` | [P0] | Only `business` accounts that are active |
| BE-14.30 | Business seller profile: about, legal info, contact, hours, cover image + `is_following` | `GET /users/{id}/public-profile`, `PUT /account/business-profile` | [P1] | Fields editable by the owner only; shown per privacy settings |
| BE-14.31 | Toggle for saved-search alerts | `PATCH /account/saved-searches/{id}` | [P1] | Alerts stop when it's `false` |

### Operations fixes
| ID | Task | Priority | Acceptance criteria |
|---|---|---|---|
| OPS-14.1 | Register `schedule:run` in cron and document it in the runbook | [P0] | `ExpireOldAdsJob` and `ExpireOldOffersJob` run every day |

## Sprint 15 — M2 Connecting the mobile app (`Qbazaar-mobile`)

| ID | Task | Priority | Acceptance criteria |
|---|---|---|---|
| MB-15.1 | HTTP client: base URL from env, envelope, snake↔camel, error mapping, `Accept-Language`, `X-Client-Platform` | [P0] | Unit tests for the mappers; a 422 turns into field errors |
| MB-15.2 | Session: tokens in `expo-secure-store`, automatic refresh, restore on launch (`/me`) | [P0] | The app remembers the user after a restart; an expired token refreshes without disturbing the user |
| MB-15.3 | Repository switch `mock | api` from the environment | [P0] | One line in `src/repositories/index.ts` |
| MB-15.4 | Catalog on the API: home, categories, category page, listing, product, search + distance | [P0] | All browse screens run on real data |
| MB-15.5 | Real login: registration, login + OTP on a new device, password reset by OTP, Google/Apple | [P0] | Full login journeys on a device |
| MB-15.6 | Messages and offers on the API + realtime via Reverb (`laravel-echo` + `pusher-js`) | [P0] | A new message shows up without refreshing; offers update live |
| MB-15.7 | Push notifications: native FCM/APNs token registration + opening the right screen from a notification | [P0] | A notification opens the chat or the ad |
| MB-15.8 | Selling on the API: drafts, 20-image upload with `expo-image-picker`, publish → "Under review" | [P0] | Real images get uploaded; publishing shows the review state |
| MB-15.9 | Favorites, saved searches, follows, companies, seller profile on the API | [P0] | All work with the real data |
| MB-15.10 | Settings on the API: name, photo, email, phone, password, addresses, email preferences, delete account | [P1] | Every screen saves for real |
| MB-15.11 | Switch off the payment screens (Buy Now, checkout, wallet, billing, paid promotion) with `features.payments=false` | [P0] | No path in the app leads to them while it's switched off |
| MB-15.12 | Arabic + RTL: `ar/*.json` files, mirrored arrows, language setting saved on the device and sent to the API | [P0] | Every screen works right in Arabic at 360 |
| MB-15.13 | Deep links: `qbazaar://ad/{id}` + universal links once the domain is decided | [P1] | A shared ad link opens in the app |

## Sprint 16 — M3 Web on the new design (`qbazaar-web`)

| ID | Task | Priority | Acceptance criteria |
|---|---|---|---|
| FE-16.1 | Map every Next.js page to its matching page in `Qbazaar-front` (a table in `qbazaar-web/DESIGN-MAP.md`) | [P0] | No page without a design reference |
| FE-16.2 | Design system: the Figma tokens (colors, Poppins, spacing, shadows) in Tailwind + shared components | [P0] | Remove `qbfront.css` in stages without breaking anything |
| FE-16.3 | Header, footer, home, categories, category page, search, product | [P0] | Matches the reference at 1440/744/390 |
| FE-16.4 | Login, account, my ads, messages, notifications, favorites, saved searches, settings | [P0] | Matches the reference |
| FE-16.5 | Post-an-ad flow on the new design | [P0] | Draft → preview → publish |
| FE-16.6 | Seller profile, companies, follows | [P1] | — |
| FE-16.7 | RTL + Lighthouse (≥ 90 performance on mobile) + axe with no serious violations | [P1] | Report attached |

## Sprint 17 — M4 Admin additions (`/admin`)

| ID | Task | Priority | Acceptance criteria |
|---|---|---|---|
| AD-17.1 | Companies: verify a business account, featured companies with an order | [P0] | Shows up in `/home` and `/companies` |
| AD-17.2 | Home sections: choose/order recommended and best selling (or automatic mode) | [P1] | `/home` respects the setting |
| AD-17.3 | Unified category tree + fields per category (fields editor) | [P0] | Every category has a clear attribute schema |
| AD-17.4 | Handle an offer from the panel (cancel/resolve a dispute) | [P2] | Logged in the activity log |
| AD-17.5 | Act directly from a report (suspend the ad/ban the user from the report screen) | [P1] | One button with a confirmation |
| AD-17.6 | Follow and message stats on the dashboard | [P2] | — |

## Sprint 18 — M5 Deployment on the new server

| ID | Task | Priority | Acceptance criteria |
|---|---|---|---|
| OPS-18.1 | cPanel account + domain (or the server name for now) + AutoSSL for `api.` and the main domain | [P0] | HTTPS works on both |
| OPS-18.2 | EA-PHP 8.4 + required extensions, MySQL, Redis | [P0] | `php artisan about` is clean |
| OPS-18.3 | Meilisearch + Horizon + Reverb as systemd services + `mod_proxy_wstunnel` for Reverb | [P0] | WebSocket connects from mobile and web |
| OPS-18.4 | Next.js on systemd behind an Apache proxy; the static prototype moves off the root | [P0] | The real web on the domain |
| OPS-18.5 | Secrets: Twilio, Firebase, Sentry, Mail | [P0] | A real SMS arrives; a real push arrives |
| OPS-18.6 | Point `deploy-api.yml` at the new server + unify the paths in `deploy/` | [P0] | A push to `production` deploys automatically |
| OPS-18.7 | Daily database + media backups + a restore test | [P0] | A successful restore on a copy |
| OPS-18.8 | Health checks + Sentry + uptime alerts | [P1] | An alert arrives when it goes down |

## Sprint 19 — M6 Releasing the mobile app

| ID | Task | Priority | Acceptance criteria |
|---|---|---|---|
| MB-19.1 | EAS Build: development/preview/production profiles + environment variables | [P0] | Successful iOS and Android builds |
| MB-19.2 | App icon, splash screen, name, bundle ids `com.qbazaar.app` | [P0] | — |
| MB-19.3 | TestFlight + Google Play internal testing | [P0] | Testers install the app |
| MB-19.4 | Privacy policy, store listings (Arabic and English), screenshots | [P0] | Ready for review |
| MB-19.5 | Submit for production review and release | [P0] | The app is on the stores |

## Sprint 20+ — M7 Payments and monetization (later phase)

| ID | Task | Priority |
|---|---|---|
| BE-20.1 | Choose the gateway (QNB / others) + contract + test environment | [P0] |
| BE-20.2 | Orders + state machine (paid → shipped → delivered → released) + Safe Pay escrow | [P0] |
| BE-20.3 | Checkout + addresses + fees + invoice | [P0] |
| BE-20.4 | Wallet, withdrawals and bank accounts + an admin approval flow | [P0] |
| BE-20.5 | Buying promotions (highlight / push up / premium / gallery) + expiry | [P1] |
| BE-20.6 | Premium subscription (bearing in mind Apple/Google in-app purchase rules) | [P2] |
| BE-20.7 | Seller reviews after a completed order | [P2] |
| MB-20.1 | Turn on the payment screens in the app and connect them | [P0] |
| FE-20.1 | Payment screens on the web | [P0] |
