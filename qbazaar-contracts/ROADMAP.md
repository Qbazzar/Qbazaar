# QBazaar — Roadmap

> **Updated:** 2026-09-30 · **Active phase:** M0 wrap-up → M1
> **Plan and decisions:** [`V2-PLAN.md`](V2-PLAN.md) · **Tasks:** [`MILESTONES-V2.md`](MILESTONES-V2.md) · **Audit:** [`DOCS/AUDIT-2026-09-30.md`](../DOCS/AUDIT-2026-09-30.md)
> We don't commit to end dates; a phase ends when its exit criteria in `MILESTONES-V2.md` are met.

QBazaar is a classifieds marketplace for Qatar (QAR, Arabic + English). The MVP (Sprints 0–12) is done. V2 connects the Expo mobile app, reskins the web on the new Figma design, adds cash orders with a commission and a seller wallet, and moves production to a new server behind Cloudflare.

## Progress

<!-- progress:start -->
**Current phase:** M0 Preparation and alignment · **Overall:** 63% (113/179 tasks)

```text
M0   ███████░░░   72% (18/25)   Preparation and alignment
M1   ██████████   97% (71/73)   Closing the backend gaps
M1b  ██████████  100% (10/10)   Orders and payments
M2   ░░░░░░░░░░    0% (0/15)    Connecting the mobile app
M3   █████████░   90% (10/11)   Web on the new design
M4   ░░░░░░░░░░    0% (0/15)    Admin additions
M5   ██░░░░░░░░   22% (4/18)    Deployment on the new server
M6   ░░░░░░░░░░    0% (0/5)     Releasing the mobile app
M7   ░░░░░░░░░░    0% (0/7)     Electronic payment and monetization
```

_Generated from [`MILESTONES-V2.md`](https://github.com/Qbazzar/Qbazaar/blob/main/qbazaar-contracts/MILESTONES-V2.md) by `node scripts/progress.mjs`; a task counts as done when it sits in a **Done** table._
<!-- progress:end -->

## What is shipped today

Verified against the code on `main` (2026-09-30).

**API — `qbazaar-api`** (Laravel 12, PHP 8.4 in CI and production, MySQL, Redis, Meilisearch, Reverb, Horizon; all routes under `/api/v1`)
- **Auth:** register and login with a password, Sanctum access tokens (15 min) plus refresh tokens bound to their session, phone OTP by SMS (Twilio), password reset, email verification.
- **Account:** profile, password, sessions, privacy settings, blocked users, device tokens, deactivation, deletion request (30-day grace), data export, avatar upload.
- **Catalog:** category tree, per-category fields and filters, Qatar locations.
- **Ads:** drafts, publish, mark sold, renew, up to **10** images (BlurHash, signed 24h links to originals). Every published ad waits for admin approval, and editing a live ad sends it back to review. Auto-moderation flags banned words, phone numbers, external links and duplicate images (pHash). Featured and similar ads, view counting, idempotent publish.
- **Search:** Meilisearch through Scout, with facets, suggestions and saved searches with alerts. No distance search yet.
- **Engagement:** favorites, recently viewed, text chat over Reverb (web typing indicator via client whispers), offers (create, accept, reject, withdraw; expire after 7 days), notifications (database + broadcast; FCM push for ad, search, export, support-reply and announcement notifications once `FIREBASE_CREDENTIALS` is set), reports, basic reviews, public profiles, blocking.
- **Content:** CMS pages, help center, support tickets.
- **API docs:** Swagger UI at `/docs` (and `/swagger`) rendering `qbazaar-contracts/openapi/v1.yaml`.

**Admin — `/admin`** in the same Laravel app: a custom Blade + Tailwind panel (Filament was removed in July 2026). Session login for staff, a Spatie permission on every route, a forced password change for the seeded admin.

**Web — `qbazaar-web`** (Next.js 16, React 19, Tailwind 4): every MVP page on the API, still on the **old QBFront design**. Arabic/English through a cookie, light theme only, SEO/PWA (sitemap, robots, manifest, Open Graph, JSON-LD), web push when the FCM env vars are set.

**Quality and ops:** 400+ Pest tests that run without external services (`php vendor/bin/pest`; the 10 Meilisearch tests are in the `meilisearch` group). CI at the repo root (`.github/workflows/ci.yml`) runs Pint, PHPStan level 8 and Pest on pushes and PRs to `main`/`develop`. Pushing to `production` deploys over SSH; the API deploy runs CI first. Production today is one cPanel server: `qbazaar.fleeteye.de` (web) and `api.qbazaar.fleeteye.de` (API), with systemd units for Horizon, Reverb, the scheduler, Next.js and Meilisearch (see [`deploy/`](../deploy/README.md)).

**Mobile — `Qbazzar/Qbazaar-mobile`:** Expo SDK 57, about 45 screens on the new design, running on **mock data**.

### Not built yet

What V2 needs and the code doesn't have: passwordless email-code login, new-device SMS check, Google/Apple login, orders, cash checkout, commission, wallet and settlements, follows, the companies directory and business profiles, 20 images per ad, distance search, a one-request home endpoint, chat images, counter-offers, FCM push for messages and offers, Cloudflare R2 storage (uploads are on the local disk), Turnstile, and the new web design. None of the new V2 endpoints are in OpenAPI yet (CT-13.1).

## Phases

The order and dependencies come from [`V2-PLAN.md §5`](V2-PLAN.md#5-milestones-details-in-milestones-v2md). Task counts and exit criteria are in [`MILESTONES-V2.md`](MILESTONES-V2.md).

| Phase | Sprint | Goal | Depends on | State |
|---|---|---|---|---|
| **M0** Preparation and alignment | 13 | Critical audit fixes, CI gating deploys, mobile polish, one category tree, OpenAPI v1.1 | — | 18 of 25 done (#147–#153). Open: OPS-13.1 (secret rotation), OPS-13.2 (domain question), OPS-13.8 (branch cleanup), MB-13.1, MB-13.2, CT-13.1, CT-13.2 |
| **M1** Closing the backend gaps | 14 | Everything the app and the new web need, the remaining audit fixes, Cloudflare R2 + Turnstile | M0 | Next. Start with BE-13.4 and SEC-13.9 |
| **M1b** Orders and payments (no gateway) | 14 | Orders, cash, admin-set commission, wallet, admin-approved settlements, paid promotion | M0 + BE-13.4, SEC-13.9 | Not started |
| **M2** Connecting the mobile app | 15 | The app on the real API, realtime, push, Arabic | M1 (M1b for orders) | Not started |
| **M3** Web on the new design | 16 | Reskin Next.js to match `Qbazaar-front` | M1 (partly) | Not started |
| **M4** Admin additions | 17 | Companies, home sections, category fields, payments admin, remaining admin audit fixes | M1 (AD-17.7 on M1b) | Not started |
| **M5** Deployment | 18 | Full system on the new VPS (`srv1977263.hstgr.cloud`) behind Cloudflare, with a domain and SSL | M1–M4 + domain | Not started |
| **M6** Releasing the app | 19 | TestFlight / Play internal testing, then the stores | M2, M5 | Not started |
| **M7** Electronic payment (later) | 20+ | A gateway behind `PaymentGateway`, Safe Pay, premium subscription, reviews after orders | M5 + a gateway contract | Not started |

M1 and M1b share Sprint 14. M1b can't start before BE-13.4 (locked offer transitions) and SEC-13.9 (admin audit log) are in, because orders come from accepted offers and settlements are admin actions.

## Decisions in force

The V2 product decisions (2026-09-30) are in [`V2-PLAN.md §2 and §4`](V2-PLAN.md#2-decisions-2026-09-30). Earlier decisions that still hold:

- One monorepo (`Qbazzar/Qbazaar`): `qbazaar-api`, `qbazaar-web`, `qbazaar-contracts`, `deploy`, `DOCS`. The mobile app and the static design prototype are separate repos.
- Contract first: OpenAPI in `qbazaar-contracts/openapi/v1.yaml` is the API reference; the response envelope is `{success, data, meta}`, errors carry a stable `code` from [`error-codes.md`](error-codes.md). IDs are ULIDs.
- Swagger UI is the only API docs surface (Scribe was removed in May 2026).
- The admin panel is the custom Blade one at `/admin` (Filament was removed in July 2026).
- Every published ad needs admin approval (June 2026).
- Search needs Meilisearch in every environment except the test suite. Never switch production to the `database` driver.
- Deploys go from `main` to the `production` branch, never straight to the server.
- The commission debt ceiling, the settlement deadline and the ad-expiry warning days are admin settings (AD-17.9); the warning defaults to 3 days (2026-09-30).
- Publishing, chat and offers need a verified phone (`AUTH_003` otherwise); the web routes the user through verification and back (FE-16.10).

## Open questions for the owner

- **Domain:** not decided. Blocks OPS-18.9 (Cloudflare), universal links (MB-15.13) and the final mail sender.
- **In-app purchase rules** for paid promotion and the premium subscription on iOS/Android.
- **OPS-13.1:** confirm the Figma token, the Firebase key and the VPS root password have been rotated.

## History

- **2026-05-20 → 2026-05-25:** Sprints 0–12, MVP feature-complete. Details in [`MILESTONES.md`](MILESTONES.md) (archived).
- **2026-06:** backend correctness pass, AR/EN switch, SEO/PWA, pHash dedup, typing indicators, FCM scaffold, Meilisearch runbook, QA sweep ([`DOCS/QA-REPORT-2026-06-21.md`](../DOCS/QA-REPORT-2026-06-21.md)). Production moved to the `fleeteye` cPanel server on 2026-06-17.
- **2026-06 → 07:** mandatory ad approval, light-only web, the custom `/admin` panel replaced Filament.
- **2026-09-30:** V2 plan approved; audit; M0 fixes merged (#147–#153).

The day-by-day MVP log that used to live in this file is in git history: `git show c5e02fc:qbazaar-contracts/ROADMAP.md`.
