# QBazaar V2: milestones and tasks

> **Updated:** 2026-09-30 · **Plan and decisions:** [`V2-PLAN.md`](V2-PLAN.md) · **Roadmap:** [`ROADMAP.md`](ROADMAP.md) · **Audit:** [`DOCS/AUDIT-2026-09-30.md`](../DOCS/AUDIT-2026-09-30.md) · **Mobile gaps:** [`GAP-ANALYSIS-MOBILE.md`](GAP-ANALYSIS-MOBILE.md)
> The MVP (Sprints 0–12) is archived in [`MILESTONES.md`](MILESTONES.md).

## How to read this file

- The phases below run in execution order, M0 → M7. Each phase is a GitHub milestone, and each table row is a GitHub issue titled `<ID> <task>`.
- Backend, web, admin, contract and ops tasks live in `Qbazzar/Qbazaar`. Mobile tasks (`MB-*`) live in `Qbazzar/Qbazaar-mobile`.
- A script syncs the issues from this file. Keep the `## Sprint N — Mx …` headings, the table headers and the ID cells exactly as they are. Tasks can move between tables; IDs are never renumbered or reused.
- A task is open unless it sits in a **Done** table.

**Priority:** `[P0]` needed for the phase to exit · `[P1]` should land in the phase · `[P2]` can slip to a later phase.
**ID prefixes:** `SEC` security · `BE` backend · `CT` contract (OpenAPI) · `QA` tests · `OPS` operations/deployment · `AD` admin panel · `MB` mobile app · `FE` web.

**Definition of done**
- **Backend:** FormRequest + Resource + Policy (where it applies) + Pest tests + an OpenAPI entry + Pint and PHPStan passing in CI (`.github/workflows/ci.yml`).
- **Mobile:** `tsc --noEmit` clean + no mock left on the switched path + the flow tested on a real device or in Expo Go.
- **Web:** `npm run typecheck` + `npm test` + `npm run build` pass, and the page matches its `Qbazaar-front` reference.

## Status

| Phase | Sprint | Tasks | Done | Open | State |
|---|---|---|---|---|---|
| M0 Preparation and alignment | 13 | 25 | 18 | 7 | Audit fixes merged (#147–#153); mobile, contract and ops items left |
| M1 Closing the backend gaps | 14 | 57 | 0 | 57 | Next |
| M1b Orders and payments | 14 | 10 | 1 | 9 | Settings store done (#156); the rest after the M1 entry items below |
| M2 Connecting the mobile app | 15 | 14 | 0 | 14 | Waits for M1 |
| M3 Web on the new design | 16 | 10 | 1 | 9 | Phone-verification flow done (#157); the rest once the M1 endpoints it needs exist |
| M4 Admin additions | 17 | 15 | 0 | 15 | Waits for M1 (AD-17.7 waits for M1b) |
| M5 Deployment on the new server | 18 | 10 | 0 | 10 | Waits for M1–M4 and the domain |
| M6 Releasing the mobile app | 19 | 5 | 0 | 5 | Waits for M2 and M5 |
| M7 Electronic payment (later) | 20+ | 7 | 0 | 7 | Waits for a gateway contract |
| **Total** | | **153** | **20** | **133** | |

---

## Sprint 13 — M0 Preparation and alignment

**Goal:** make the current system safe and predictable before any V2 work: close the critical audit findings, get CI gating deploys, and align the mobile app and the contract with the backend.

**Exit criteria:** every [P0] below is done · CI is green on `main` · the production steps listed in [`deploy/README.md`](../deploy/README.md#pending-production-steps-after-m0) have been run.

### Done (merged 2026-09-30)

> Status: ✅ done. #147 → OPS-13.3, OPS-13.4, OPS-13.5, OPS-13.6, BE-13.1 · #148 → SEC-13.6, SEC-13.7 · #149 → BE-13.2, BE-13.3 · #150 → SEC-13.1, SEC-13.2 · #151 → SEC-13.3, SEC-13.4, AD-17.8 · #152 → SEC-13.5, SEC-13.8 · #153 → QA-13.1, OPS-13.7

| ID | Task | Priority | Acceptance criteria |
|---|---|---|---|
| SEC-13.1 | Escape JSON-LD + reject markup in ad title/description + </script> regression test | [P0] | Fixes audit finding SEC-02 (DOCS/AUDIT-2026-09-30.md) |
| SEC-13.2 | Server-generated upload filenames; FilesMatch instead of AddHandler; disable PHP in /storage | [P0] | Fixes audit finding SEC-01 (DOCS/AUDIT-2026-09-30.md) |
| SEC-13.3 | Enforce Spatie permissions per admin route + no actions on higher/equal staff + role tests | [P0] | Fixes audit finding SEC-03, ADM-01, ADM-02 (DOCS/AUDIT-2026-09-30.md) |
| SEC-13.4 | Admin login throttle + status check in login/EnsureStaff + force change of seeded password | [P0] | Fixes audit finding ADM-03, ADM-02 (DOCS/AUDIT-2026-09-30.md) |
| SEC-13.5 | Re-moderation on edit/image add of ACTIVE ads | [P0] | Fixes audit finding SEC-06, F2 (DOCS/AUDIT-2026-09-30.md) |
| SEC-13.6 | Phone OTP via SMS only + purpose/channel column + apply phone.verified | [P0] | Fixes audit finding SEC-04, F6 (DOCS/AUDIT-2026-09-30.md) |
| SEC-13.7 | Link refresh tokens to sessions; revoke both; status check in rotate; burn on suspend | [P0] | Fixes audit finding SEC-05, F4 (DOCS/AUDIT-2026-09-30.md) |
| SEC-13.8 | Suspending a user hides their ads; block chat/offers on inactive seller/ad or allow_chat=false | [P0] | Fixes audit finding ADM-06, F7, PRD-06 (DOCS/AUDIT-2026-09-30.md) |
| OPS-13.3 | Horizon consumes default+low + queue coverage test | [P0] | Fixes audit finding RT-1 (DOCS/AUDIT-2026-09-30.md) |
| OPS-13.4 | Scheduler systemd unit (promote OPS-14.1) | [P0] | Fixes audit finding RT-2 (DOCS/AUDIT-2026-09-30.md) |
| OPS-13.5 | SCOUT_DRIVER=meilisearch default, drop database rollback, driver guard + null-safe toSearchableArray | [P0] | Fixes audit finding RT-4, PERF-01 (DOCS/AUDIT-2026-09-30.md) |
| OPS-13.6 | SCOUT_QUEUE=true + after_commit, remove double sync | [P1] | Fixes audit finding RT-3, PERF-07 (DOCS/AUDIT-2026-09-30.md) |
| OPS-13.7 | Merge ci/monorepo-workflows; deploy-api depends on CI | [P0] | Fixes audit finding RT-5 (DOCS/AUDIT-2026-09-30.md) |
| BE-13.1 | Remove double listener registration + listener count test | [P0] | Fixes audit finding F1 (DOCS/AUDIT-2026-09-30.md) |
| BE-13.2 | Promote BE-14.1: broadcasting auth under api/v1 with auth:sanctum | [P0] | Fixes audit finding F3 (DOCS/AUDIT-2026-09-30.md) |
| BE-13.3 | Fix support ticket guard + reply/new-ticket notifications + tests | [P0] | Fixes audit finding F8, PRD-12 (DOCS/AUDIT-2026-09-30.md) |
| QA-13.1 | Hermetic tests: SCOUT_DRIVER=collection + meilisearch group | [P0] | Fixes audit finding ARCH-02, F18, RT-8 (DOCS/AUDIT-2026-09-30.md) |
| AD-17.8 | Fix admin ads bulk delete (ULID) via model delete | [P1] | Fixes audit finding ARCH-04, ADM-05 (DOCS/AUDIT-2026-09-30.md) |

### Still open

> OPS-13.8 is approved by the owner (2026-09-30) and pending: the merged branches (`fix/m0-*`, `docs/v2-plan`, the 2026-06 feature branches, `copilot/code-review-report`) are still on the remote, and `ci/monorepo-workflows` is now superseded by #153. OPS-13.2: every question in `V2-PLAN.md §4` has a decision except the domain.

| ID | Task | Priority | Acceptance criteria |
|---|---|---|---|
| OPS-13.1 | Security: revoke the Figma token; move and rotate the Firebase key; change the VPS root password and disable password SSH | [P0] | No secrets outside `.env`; SSH by key only |
| OPS-13.2 | Settle the open questions in `V2-PLAN.md §4` with the client | [P0] | Every question has a written decision |
| MB-13.1 | Finish the mobile design polish round (re-shoot at 390/430, fix the Transactions title at 360, merge `polish/mobile-ergonomics`) | [P0] | No text overflow or broken wrapping at 360/390/430; branch merged |
| MB-13.2 | Unify the app's browse and sell category trees into one taxonomy that matches the backend | [P0] | One `Category` type with the same slugs as `/categories/tree` |
| CT-13.1 | OpenAPI v1.1: add all the new M1 endpoints and mark them `x-status: planned` | [P0] | `v1.yaml` validates; each new endpoint has a request and a response |
| CT-13.2 | Add the missing `reviews` endpoints to OpenAPI | [P2] | They match the existing routes |
| OPS-13.8 | Branch cleanup (delete merged, merge docs/v2-plan, drop stale) | [P2] | Fixes audit finding الفروع (DOCS/AUDIT-2026-09-30.md) |

## Sprint 14 — M1 Closing the backend gaps (no payments)

**Goal:** give the mobile app and the new web everything they need from the API, and close the remaining audit findings on the way, so M1b builds money features on a sound base. Every new endpoint goes into OpenAPI (CT-13.1) before it's built.

**Entry:** M0 [P0] tasks done. **Do first:** BE-13.4 and SEC-13.9 (both block M1b), then the security block.

**Exit criteria:** every [P0] below is done and in OpenAPI · the M1 rows in `GAP-ANALYSIS-MOBILE.md` are ✅ · new uploads land on R2 · Turnstile protects registration and code requests.

### Security hardening

| ID | Task | Priority | Acceptance criteria |
|---|---|---|---|
| SEC-13.9 | Admin audit logger with correct causer; short-lived reasoned impersonation | [P0] | Fixes audit finding SEC-09, ADM-04, ARCH-08 (DOCS/AUDIT-2026-09-30.md) |
| SEC-13.10 | Rate limits: conversations/offers, OTP by IP+phone, per-identifier login limiter | [P0] | Fixes audit finding SEC-07 (DOCS/AUDIT-2026-09-30.md) |
| SEC-13.11 | Sanitize CMS/help HTML on write + DOMPurify | [P1] | Fixes audit finding SEC-08 (DOCS/AUDIT-2026-09-30.md) |
| SEC-13.12 | Hardening: scope Gate::before, fillable cleanup, OTP_FIXED_CODE guard, view cap, CORS, hide swagger | [P2] | Fixes audit finding SEC-12, SEC-13 (DOCS/AUDIT-2026-09-30.md) |

### Auth (passwordless)

| ID | Task | Endpoint | Priority | Acceptance criteria |
|---|---|---|---|---|
| BE-14.11 | Passwordless login: a code to the email on every login (`/auth/email-otp/send` + `/verify`), and passwordless registration (name, email, phone, account type) | `POST /auth/email-otp/send`, `POST /auth/email-otp/verify` | [P0] | No password field; code is 6 digits, expires in 10 minutes, max 5 attempts, rate-limited; returns tokens on success |
| BE-14.12 | SMS code to the phone when logging in from a new device (device fingerprint + `challenge_token`) | `POST /auth/device/verify` | [P0] | A new device gets no tokens until the SMS code is right; a known device skips it; a `security.new_device` notification |
| BE-14.13 | Google + Apple login (id_token) | `POST /auth/social/{provider}` | [P1] | Verifies the token signature; links by email; creates a new user with `phone_verified=false` |
| BE-14.14 | Alias `GET /me` → the current profile | `GET /me` | [P1] | Same shape as `/account/profile` |

### Cloudflare (storage and bot protection)

> BE-14.42 needs an R2 bucket and keys for development; OPS-18.10 creates the production ones.

| ID | Task | Endpoint | Priority | Acceptance criteria |
|---|---|---|---|---|
| BE-14.42 | Store ad images, avatars and chat images on Cloudflare R2 (an S3 disk with the R2 endpoint) through Spatie Media Library, with the thumbnails there too, a script to move existing images, and short-lived signed links for the original images | — (config + `FILESYSTEM_DISK`/`MEDIA_DISK`) | [P0] | A new upload lands in R2 and is served from it; the old images move over with no broken links; storage settings come from `.env` only; a test with a fake disk |
| BE-14.43 | Cloudflare Turnstile on registration and requesting a code (email and phone): server-side token verification, an on/off switch, bypass in testing | `POST /auth/register`, `/auth/email-otp/send`, `/auth/send-otp` | [P0] | A request without a valid token is rejected with a clear error code; the Turnstile secret is in `.env` only; tests with a fake verifier |

### Catalog and search

> Pairs: BE-13.10 with BE-14.5 (both are hierarchical category filtering), BE-13.11 with BE-14.3.

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

| ID | Task | Priority | Acceptance criteria |
|---|---|---|---|
| BE-13.10 | Hierarchical category/location filtering + leaf/active validation | [P1] | Fixes audit finding PERF-11, PRD-07 (DOCS/AUDIT-2026-09-30.md) |
| BE-13.11 | Remove N+1 (category tree, inbox, primary media) + preventLazyLoading | [P1] | Fixes audit finding PERF-04, PERF-06, PERF-12 (DOCS/AUDIT-2026-09-30.md) |
| BE-13.12 | Cache invalidation (per-slug fields/filters, featured/similar status filter) | [P2] | Fixes audit finding PERF-10, ARCH-10 (DOCS/AUDIT-2026-09-30.md) |
| BE-13.22 | Missing indexes (ads(status,price), notifications created_at, users last_login_at) | [P2] | Fixes audit finding PERF-14 (DOCS/AUDIT-2026-09-30.md) |

### Selling, my ads and moderation

> Pairs: BE-13.7 with BE-14.20 (both raise the image limit to 20; today it is 10), BE-13.20 with BE-14.23 (My Ads status filter). The admin notification in BE-14.32 already exists as a database notification (`NotifyAdminsOfPendingAd`); email and the panel counter are what's left.

| ID | Task | Endpoint | Priority | Acceptance criteria |
|---|---|---|---|---|
| BE-14.20 | Raise the image limit to 20 + separate the draft limiter from the publish limiter | config + `throttle:drafts` | [P0] | 20 images accepted; creating a draft doesn't count against publishing |
| BE-14.21 | New ad fields: `ad_type`, `shipping`, `postal_code`, `street`, `show_full_address` | `POST/PUT /ads` | [P0] | Migration + validation + in the Resource and in search |
| BE-14.22 | Reserved status + toggle | `POST /ads/{id}/reserve`, `DELETE …/reserve` | [P1] | `reserved` shows on the listing; can't be reserved while a draft |
| BE-14.23 | My ads with a status filter + stats per ad | `GET /account/ads?status=` | [P0] | views/favorites/messages counts for each ad |
| BE-14.32 | Notify the admins about every ad waiting for review (database + email + a counter in the panel) | — | [P0] | Every admin with the moderation permission gets a notification the moment an ad is submitted |

| ID | Task | Priority | Acceptance criteria |
|---|---|---|---|
| BE-13.6 | AdLifecycleService: guarded transitions, remove status from fillable, min_images/daily limit, publish no-op, idempotency lock | [P1] | Fixes audit finding ARCH-05, ADM-07, F12, F13 (DOCS/AUDIT-2026-09-30.md) |
| BE-13.7 | Queued image conversions, max_images=20, locked count, body size alignment | [P1] | Fixes audit finding RT-7, PERF-03 (DOCS/AUDIT-2026-09-30.md) |
| BE-13.8 | Fix expiry chunkById + ads(status,expires_at) index + expiring_notified_at | [P1] | Fixes audit finding PERF-05, F11 (DOCS/AUDIT-2026-09-30.md) |
| BE-13.20 | My Ads status filter, full summary, accepted_terms on publish | [P2] | Fixes audit finding PRD-15, PRD-14 (DOCS/AUDIT-2026-09-30.md) |
| BE-13.21 | Async pHash duplicate detection + persisted moderation result | [P2] | Fixes audit finding PERF-02 (DOCS/AUDIT-2026-09-30.md) |

### Messages, offers, notifications and push

> BE-13.4 must land before M1b: orders are created from accepted offers. Channel authorization for Bearer clients is done (BE-13.2, #149).

| ID | Task | Priority | Acceptance criteria |
|---|---|---|---|
| BE-13.4 | Locked OfferTransitionService, ad-status check, close competing offers, unique pending offer | [P0] | Fixes audit finding SEC-10, F5, ARCH-09 (DOCS/AUDIT-2026-09-30.md) |
| BE-13.15 | Apply moderation rules to chat messages | [P1] | Fixes audit finding PRD-10 (DOCS/AUDIT-2026-09-30.md) |

| ID | Task | Endpoint | Priority | Acceptance criteria |
|---|---|---|---|---|
| BE-14.2 | FCM push for a new message and for offer events | — | [P0] | Offline recipient gets a push; no push to the sender; test with a fake FCM |
| BE-14.15 | Image attachments in messages (`type=image`, media) | `POST /conversations/{id}/messages` multipart | [P0] | Image ≤ 10MB; the message comes back with a `media` URL; `message.sent` is broadcast |
| BE-14.16 | Hide conversations in bulk (per user) | `DELETE /conversations` `{ids}` | [P1] | Hidden only for the requester; reappears on a new message |
| BE-14.17 | Counter-offer (one round from each side) | `POST /offers/{id}/counter` | [P1] | A new `countered` state; `offer.countered` event; a limit on the number of rounds |
| BE-14.18 | New notification types (`ads.new_from_followed`, `ad.price_changed`) + filter `?category=` | `/account/notifications` | [P1] | A price drop on a favorited ad notifies whoever favorited it |

### Favorites and saved searches

> Pair: BE-13.13 with BE-14.31 (both add the saved-search alert switch).

| ID | Task | Endpoint | Priority | Acceptance criteria |
|---|---|---|---|---|
| BE-14.19 | IDs list + idempotent add and remove | `GET /account/favorites/ids`, `PUT`/`DELETE /ads/{id}/favorite` | [P0] | Repeating add or remove doesn't fail; the old toggle stays working |
| BE-14.31 | Toggle for saved-search alerts | `PATCH /account/saved-searches/{id}` | [P1] | Alerts stop when it's `false` |

| ID | Task | Priority | Acceptance criteria |
|---|---|---|---|
| BE-13.13 | Saved-search matcher fix + alerts_enabled + PUT | [P1] | Fixes audit finding F10, PRD-08 (DOCS/AUDIT-2026-09-30.md) |

### Account and settings

> Pair: BE-13.14 with BE-14.27 (notification and email preferences share one settings store).

| ID | Task | Endpoint | Priority | Acceptance criteria |
|---|---|---|---|---|
| BE-14.24 | Partial profile update + delete the avatar | `PATCH /account/profile`, `DELETE /uploads/avatar` | [P0] | Editing the name alone works |
| BE-14.25 | Change email / phone with the password + verification | `POST /account/email`, `POST /account/phone` | [P1] | Email: link to the new address + notice to the old one. Phone: OTP |
| BE-14.26 | Saved addresses (table + CRUD) | `/account/addresses` | [P1] | Max 10; one default |
| BE-14.27 | Email preferences | `GET/PATCH /account/email-preferences` | [P1] | Respected when sending emails |

| ID | Task | Priority | Acceptance criteria |
|---|---|---|---|
| BE-13.5 | Account deletion via Eloquent + grace check + daily sweep + handle() test | [P1] | Fixes audit finding SEC-11, PERF-09, F9 (DOCS/AUDIT-2026-09-30.md) |
| BE-13.14 | Notification preferences GET/PUT checked in via() | [P1] | Fixes audit finding PRD-08 (DOCS/AUDIT-2026-09-30.md) |
| BE-13.16 | LocaleMiddleware uses sanctum user + real HTTP test | [P2] | Fixes audit finding ARCH-07 (DOCS/AUDIT-2026-09-30.md) |
| BE-13.18 | Browser-openable data export link + complete export | [P2] | Fixes audit finding F15 (DOCS/AUDIT-2026-09-30.md) |

### Social: follows, companies, business profiles

| ID | Task | Endpoint | Priority | Acceptance criteria |
|---|---|---|---|---|
| BE-14.28 | Follows (table + follow/unfollow + lists + counts + remove a follower) | `/users/{id}/follow`, `/account/followers`, `/account/following` | [P0] | Can't follow yourself; blocking removes the follow in both directions |
| BE-14.29 | Companies directory (business accounts) with search and pagination | `GET /companies` | [P0] | Only `business` accounts that are active |
| BE-14.30 | Business seller profile: about, legal info, contact, hours, cover image + `is_following` | `GET /users/{id}/public-profile`, `PUT /account/business-profile` | [P1] | Fields editable by the owner only; shown per privacy settings |

### Platform upkeep

| ID | Task | Priority | Acceptance criteria |
|---|---|---|---|
| BE-13.9 | Scheduled pruning (sanctum, refresh/otp, activitylog, guest recents) | [P1] | Fixes audit finding RT-6, PERF-13, F17 (DOCS/AUDIT-2026-09-30.md) |
| BE-13.17 | NOT_FOUND/FORBIDDEN error codes + config-driven validation limits | [P2] | Fixes audit finding ARCH-11, ARCH-12 (DOCS/AUDIT-2026-09-30.md) |
| BE-13.19 | Small fixes: reviews withTrashed, queued reset/verify mails, ReportCreated listener | [P2] | Fixes audit finding PERF-15, PERF-16, F16 (DOCS/AUDIT-2026-09-30.md) |
| BE-13.23 | Layering refactor: Review/Support actions, UserModerationService, resources | [P2] | Fixes audit finding ARCH-06 (DOCS/AUDIT-2026-09-30.md) |

## Sprint 14 — M1b Orders and payments (no gateway)

**Goal:** orders from the first launch without an online gateway: cash on delivery or at handover, a QBazaar commission set by the admin, a seller wallet, and admin-approved settlements and withdrawals. Everything goes through a `PaymentGateway` interface (only `CashGateway` today) so an electronic gateway can be added in M7 without touching orders. Stripe is excluded.

**Entry:** BE-13.4 (locked offer transitions) and SEC-13.9 (admin audit log) from M1 are done. AD-17.9 comes first inside this phase.

**Exit criteria:** a full cycle works through the API with tests: request → accept → checkout → handover confirmation → sale and commission entries in the wallet → settlement approved by an admin. The admin screens for it are AD-17.7 (M4).

### Done (merged 2026-09-30)

> Status: ✅ done. #156 → AD-17.9 (the expiry-warning part of BE-13.8 also landed there; BE-13.8 stays open for its index)

| ID | Task | Priority | Acceptance criteria |
|---|---|---|---|
| AD-17.9 | Settings store + SettingsService + settings.manage permission; the commission debt ceiling, the settlement deadline and the ad-expiry warning days are admin-controlled settings (owner decision 2026-09-30) | [P0] | Fixes audit finding PRD-11 (DOCS/AUDIT-2026-09-30.md); each of the three values is editable in `/admin`, read through SettingsService, and defaults to the config value (expiry warning: 3 days) |

### Open

| ID | Task | Endpoint | Priority | Acceptance criteria |
|---|---|---|---|---|
| BE-14.33 | Platform settings editable from admin: commission % (general + optional per category), commission debt ceiling, settlement deadline, promotion prices | — (settings table + cache) | [P0] | Changing a value takes effect immediately on new orders; changes are logged in the activity log |
| BE-14.34 | Purchase request ("Buy Now") inside the chat: create / edit / cancel (buyer) and accept / reject (seller), as a card message | `POST /ads/{id}/purchase-requests`, `POST /purchase-requests/{id}/accept, reject, cancel`, `PUT /purchase-requests/{id}` | [P0] | Card states match the design (pending, accepted, rejected, cancelled, paid); Reverb + push events |
| BE-14.35 | Orders + state machine: created from an accepted request or accepted offer → `awaiting_handover` → `completed` or `cancelled` / `disputed` | `GET /account/orders`, `GET /orders/{id}` | [P0] | One active order per ad; the ad becomes `reserved` automatically; tests for every transition |
| BE-14.36 | `PaymentGateway` interface + `CashGateway`: a cash order is completed by seller confirmation (and optionally the buyer's), with the order amount and commission frozen at creation time | `POST /orders/{id}/confirm-handover` | [P0] | Completing an order posts ledger entries; switching gateway later needs no change to orders |
| BE-14.37 | Checkout: address, delivery or pickup, shipping fee (set by the seller on the ad), total, choosing the payment method (cash only now) | `GET /orders/{id}/checkout`, `POST /orders/{id}/checkout` | [P0] | Fees and total are calculated on the server only |
| BE-14.38 | Wallet with a ledger (entries: sale, commission, settlement, adjustment): balance, commission debt, history | `GET /account/wallet`, `GET /account/wallet/transactions` | [P0] | The balance always equals the sum of the entries (a test); no direct edits |
| BE-14.39 | Settlements and withdrawals: the seller pays off the commission debt (bank transfer + reference number), requests a withdrawal of a positive balance (IBAN), and the admin approves or rejects | `POST /account/wallet/settlements`, `POST /account/wallet/withdrawals`, `GET/POST /account/bank-accounts` | [P0] | A notification at every step; once the debt ceiling is exceeded the seller can't accept new orders until they settle |
| BE-14.40 | Paid promotion (highlight / push up / premium / gallery): request + activate after payment is confirmed (bank transfer or deducted from the balance) + expiry | `GET /promotions`, `POST /ads/{id}/promotions` | [P1] | Prices come from the admin settings; a promoted ad rises in search and on the home page for the duration |
| BE-14.41 | Order notifications (new request, accepted, rejected, handover done, commission due, settlement approved) via Reverb + FCM + email | — | [P0] | Every event reaches both parties through the right channels |

## Sprint 15 — M2 Connecting the mobile app (`Qbazaar-mobile`)

**Goal:** the Expo app runs on the real API instead of mock data, with realtime chat, push and Arabic. The app keeps its screens; each domain gets an `ApiRepository` in `src/repositories/api/*`, switched in `src/repositories/index.ts`.

**Entry:** the M1 endpoints each task uses exist (and M1b for MB-15.11). Realtime for Bearer clients already works (`POST /api/v1/broadcasting/auth`, BE-13.2).

**Exit criteria:** every [P0] flow works on a real device against a staging API, in Arabic and English, with no mock left on the switched paths.

| ID | Task | Priority | Acceptance criteria |
|---|---|---|---|
| MB-15.1 | HTTP client: base URL from env, envelope, snake↔camel, error mapping, `Accept-Language`, `X-Client-Platform` | [P0] | Unit tests for the mappers; a 422 turns into field errors |
| MB-15.2 | Session: tokens in `expo-secure-store`, automatic refresh, restore on launch (`/me`) | [P0] | The app remembers the user after a restart; an expired token refreshes without disturbing the user |
| MB-15.3 | Repository switch `mock` / `api` from the environment | [P0] | One line in `src/repositories/index.ts` |
| MB-15.4 | Catalog on the API: home, categories, category page, listing, product, search + distance | [P0] | All browse screens run on real data |
| MB-15.5 | Real login: passwordless registration, login with an email code, SMS code from a new device, Google/Apple (the password fields are removed from the screens) | [P0] | Full login journeys on a device |
| MB-15.14 | Turnstile in the app's registration and code-request screens (inside a small WebView) and send the token with the request | [P0] | Registration and code requests work with protection on; the widget is invisible to a normal user |
| MB-15.6 | Messages and offers on the API + realtime via Reverb (`laravel-echo` + `pusher-js`) | [P0] | A new message shows up without refreshing; offers update live |
| MB-15.7 | Push notifications: native FCM/APNs token registration + opening the right screen from a notification | [P0] | A notification opens the chat or the ad |
| MB-15.8 | Selling on the API: drafts, 20-image upload with `expo-image-picker`, publish → "Under review" | [P0] | Real images get uploaded; publishing shows the review state |
| MB-15.9 | Favorites, saved searches, follows, companies, seller profile on the API | [P0] | All work with the real data |
| MB-15.10 | Settings on the API: name, photo, email, phone, password, addresses, email preferences, delete account | [P1] | Every screen saves for real |
| MB-15.11 | Connect orders and payments: purchase request and offer cards in chat, checkout (cash only; the other methods hidden until a gateway exists), orders, wallet, settlements and withdrawals, paid promotion | [P0] | Full cycle on a device: request → accept → checkout → handover confirmation → the balance and commission show in the wallet |
| MB-15.12 | Arabic + RTL: `ar/*.json` files, mirrored arrows, language setting saved on the device and sent to the API | [P0] | Every screen works right in Arabic at 360 |
| MB-15.13 | Deep links: `qbazaar://ad/{id}` + universal links once the domain is decided | [P1] | A shared ad link opens in the app |

## Sprint 16 — M3 Web on the new design (`qbazaar-web`)

**Goal:** reskin the existing Next.js app to the new Figma design, using `Qbazaar-front` as the pixel reference. The routing, data layer and API connection stay; the old QBFront styles (`styles/qbfront.css`) go away in stages.

**Entry:** FE-16.1 to FE-16.3 and FE-16.10 can start now; the rest needs the matching M1 (and M1b for FE-16.8) endpoints.

**Exit criteria:** every page matches its reference at 1440/744/390 in both languages · the cash order cycle works on the web · Lighthouse and axe reports attached.

### Done (merged 2026-09-30)

> Status: ✅ done. #157 → FE-16.10

| ID | Task | Priority | Acceptance criteria |
|---|---|---|---|
| FE-16.10 | Web phone-verification flow: route AUTH_003 and gated actions (post ad, chat, offers) to verification and back, all scenarios | [P0] | A user without a verified phone who publishes an ad, starts a chat, sends a message or makes an offer is taken to phone verification and returned to the same action; any `AUTH_003` response does the same; guests go to login first; tests for every entry point |

### Open

| ID | Task | Priority | Acceptance criteria |
|---|---|---|---|
| FE-16.1 | Map every Next.js page to its matching page in `Qbazaar-front` (a table in `qbazaar-web/DESIGN-MAP.md`) | [P0] | No page without a design reference |
| FE-16.2 | Design system: the Figma tokens (colors, Poppins, spacing, shadows) in Tailwind + shared components | [P0] | Remove `qbfront.css` in stages without breaking anything |
| FE-16.3 | Header, footer, home, categories, category page, search, product | [P0] | Matches the reference at 1440/744/390 |
| FE-16.4 | Login, account, my ads, messages, notifications, favorites, saved searches, settings | [P0] | Matches the reference |
| FE-16.9 | Turnstile on the web's registration and code-request pages | [P0] | The token goes with the request; clear error on failure |
| FE-16.5 | Post-an-ad flow on the new design | [P0] | Draft → preview → publish |
| FE-16.6 | Seller profile, companies, follows | [P1] | — |
| FE-16.8 | Orders and payments on the web: request/offer cards, checkout (cash), orders, wallet, settlements | [P0] | Same cycle as the app |
| FE-16.7 | RTL + Lighthouse (≥ 90 performance on mobile) + axe with no serious violations | [P1] | Report attached |

## Sprint 17 — M4 Admin additions (`/admin`)

**Goal:** extend the existing custom Blade panel at `/admin` (no Filament, no new design) with what V2 needs: companies and verification, home sections, the category field editor, payments, and the remaining admin audit findings.

**Entry:** M1 for most tasks; AD-17.7 needs the M1b API (it can start as soon as BE-14.33 and BE-14.39 land).

**Exit criteria:** an admin can run companies, home sections, moderation, reports and payments from the panel, with every action permission-checked and logged.

### Moderation and staff tools

| ID | Task | Priority | Acceptance criteria |
|---|---|---|---|
| AD-17.10 | Staff notification bell/inbox + FIFO moderation queue with SLA + bulk approve/reject | [P1] | Fixes audit finding ADM-08 (DOCS/AUDIT-2026-09-30.md) |
| AD-17.13 | Mandatory sanction reasons + AdSuspended/UserSuspended notifications | [P1] | Fixes audit finding ADM-11 (DOCS/AUDIT-2026-09-30.md) |
| AD-17.11 | conversations.view permission with reason, logging, pagination | [P1] | Fixes audit finding ADM-09 (DOCS/AUDIT-2026-09-30.md) |
| AD-17.14 | Staff 2FA | [P1] | Fixes audit finding ADM-03 (DOCS/AUDIT-2026-09-30.md) |
| AD-17.15 | Broadcast composer, permission matrix editor, route-protect /admin/roles | [P2] | Fixes audit finding ADM-12 (DOCS/AUDIT-2026-09-30.md) |
| AD-17.16 | Admin i18n flash strings, remove Filament comments, mutation tests | [P2] | Fixes audit finding ARCH-13 (DOCS/AUDIT-2026-09-30.md) |

| ID | Task | Priority | Acceptance criteria |
|---|---|---|---|
| AD-17.5 | Act directly from a report (suspend the ad/ban the user from the report screen) | [P1] | One button with a confirmation |
| AD-17.4 | Handle an offer from the panel (cancel/resolve a dispute) | [P2] | Logged in the activity log |
| AD-17.6 | Follow and message stats on the dashboard | [P2] | — |

### Catalog, companies and home

| ID | Task | Priority | Acceptance criteria |
|---|---|---|---|
| AD-17.3 | Unified category tree + fields per category (fields editor) | [P0] | Every category has a clear attribute schema |
| AD-17.1 | Companies: verify a business account, featured companies with an order | [P0] | Shows up in `/home` and `/companies` |
| AD-17.2 | Home sections: choose/order recommended and best selling (or automatic mode) | [P1] | `/home` respects the setting |

| ID | Task | Priority | Acceptance criteria |
|---|---|---|---|
| AD-17.12 | Safe category/location delete (usage check or deactivate) | [P1] | Fixes audit finding ADM-10 (DOCS/AUDIT-2026-09-30.md) |
| AD-17.17 | Business applications and verification flow | [P1] | Fixes audit finding PRD-11 (DOCS/AUDIT-2026-09-30.md) |

### Payments

| ID | Task | Priority | Acceptance criteria |
|---|---|---|---|
| AD-17.7 | Payments section in the admin panel: platform settings (commission, debt ceiling, promotion prices), orders, settlement/withdrawal queue with approve/reject, disputes, revenue report | [P0] | The admin changes the commission and it applies to the next order; approving a settlement updates the seller's wallet |

## Sprint 18 — M5 Deployment on the new server

**Goal:** the whole system (API, web, workers, search, WebSocket) running natively on the new cPanel VPS (`srv1977263.hstgr.cloud`, no Docker) behind Cloudflare, deployed from GitHub Actions. Production today is the older cPanel server (`qbazaar.fleeteye.de`); the units and scripts in `deploy/` target it and move over in OPS-18.6.

**Entry:** M1–M4 feature-complete. The domain must be decided before OPS-18.9 (V2-PLAN §2.6); `.qa` domains are bought from a Qatari registrar and their DNS moved to Cloudflare.

**Exit criteria:** HTTPS on the final domain through Cloudflare · a push to `production` deploys after CI passes · real SMS and push arrive · a backup has been restored on a copy · alerts fire when the site goes down.

### Server and runtime

| ID | Task | Priority | Acceptance criteria |
|---|---|---|---|
| OPS-18.1 | cPanel account + domain (or the server name for now) + AutoSSL for `api.` and the main domain | [P0] | HTTPS works on both |
| OPS-18.2 | EA-PHP 8.4 + required extensions, MySQL, Redis | [P0] | `php artisan about` is clean |
| OPS-18.3 | Meilisearch + Horizon + Reverb as systemd services + `mod_proxy_wstunnel` for Reverb | [P0] | WebSocket connects from mobile and web |
| OPS-18.4 | Next.js on systemd behind an Apache proxy; the static prototype moves off the root | [P0] | The real web on the domain |
| OPS-18.5 | Secrets: Twilio, Firebase, Sentry, Mail | [P0] | A real SMS arrives; a real push arrives |
| OPS-18.6 | Point `deploy-api.yml` at the new server + unify the paths in `deploy/` | [P0] | A push to `production` deploys automatically |

### Resilience

| ID | Task | Priority | Acceptance criteria |
|---|---|---|---|
| OPS-18.7 | Daily database + media backups + a restore test | [P0] | A successful restore on a copy |
| OPS-18.8 | Health checks + Sentry + uptime alerts | [P1] | An alert arrives when it goes down |

### Cloudflare

| ID | Task | Priority | Acceptance criteria |
|---|---|---|---|
| OPS-18.10 | Cloudflare R2: create the bucket and access keys, and set the CORS rules for uploading | [P0] | The keys are in the production `.env` only |
| OPS-18.9 | Move the domain to Cloudflare: DNS + proxy + SSL "Full (strict)" with an Origin Certificate + WAF and rate-limiting rules + caching static files + a custom domain for R2 images (`cdn.`) + WebSocket for Reverb + the server firewall only accepts Cloudflare IPs + real visitor IPs in Laravel (`TrustProxies`) | [P0] | The site and API go through Cloudflare; the server's IP address doesn't answer directly; the chat works; images come from `cdn.` |

## Sprint 19 — M6 Releasing the mobile app

**Goal:** ship the app to TestFlight and Google Play internal testing, then to the stores.

**Entry:** M2 done and the production API live (M5).

**Exit criteria:** the app is published on both stores.

| ID | Task | Priority | Acceptance criteria |
|---|---|---|---|
| MB-19.1 | EAS Build: development/preview/production profiles + environment variables | [P0] | Successful iOS and Android builds |
| MB-19.2 | App icon, splash screen, name, bundle ids `com.qbazaar.app` | [P0] | — |
| MB-19.3 | TestFlight + Google Play internal testing | [P0] | Testers install the app |
| MB-19.4 | Privacy policy, store listings (Arabic and English), screenshots | [P0] | Ready for review |
| MB-19.5 | Submit for production review and release | [P0] | The app is on the stores |

## Sprint 20+ — M7 Electronic payment and monetization (later phase)

**Goal:** add electronic payment on top of the M1b order and wallet system. Orders, wallet, commission and settlements already exist by then; this phase only plugs a gateway in behind `PaymentGateway`, plus escrow (Safe Pay), a premium subscription and seller reviews.

**Entry:** M5 live and a signed contract with a gateway that supports Qatar (QNB or another; Stripe is excluded). Subscriptions and digital promotions may fall under Apple/Google in-app purchase rules. A basic review endpoint already exists (`POST /ads/{ad}/reviews`, documented by CT-13.2); BE-20.7 ties reviews to completed orders.

| ID | Task | Priority |
|---|---|---|
| BE-20.1 | Choose the electronic gateway (QNB or another gateway that supports Qatar) + contract + test environment | [P0] |
| BE-20.2 | Safe Pay: hold the amount and release it after the buyer confirms receipt, or automatically after N days (setting) + disputes and refunds | [P0] |
| BE-20.3 | Implement the chosen gateway behind the `PaymentGateway` interface + a webhook + reconciliation | [P0] |
| BE-20.6 | Premium subscription (bearing in mind Apple/Google in-app purchase rules) | [P2] |
| BE-20.7 | Seller reviews after a completed order | [P2] |
| MB-20.1 | Turn on electronic payment methods in the app | [P0] |
| FE-20.1 | Turn on electronic payment methods on the web | [P0] |
