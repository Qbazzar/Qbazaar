# QBazaar — Roadmap (Solo Dev MVP)

> **آخر تحديث:** 2026-05-25
> **تاريخ البدء:** 2026-05-20
> **النطاق:** Backend (Laravel) + Web (Next.js). بدون Mobile/Phase 2.
> **ملاحظة:** لا نلتزم بتواريخ انتهاء متوقعة — نمشي per-sprint وفق ما يجهز.

---

## 🎯 الحالة الحالية

| البند | القيمة |
|-------|---------|
| **Active Milestone** | **Deploy + Polish** — MVP feature-complete (Milestones 1–5 ✅). Laravel running on VPS (`147.79.115.44`, CloudPanel tenant `qb-user`); DNS/SSL for `miete.site` pending user action. |
| **Active Sprint** | Sprints 0..12 ✅ closed + QBFront design migration ✅ + Filament admin (incl. Pages/Help/Support resources) ✅ + DemoDataSeeder ✅ + VPS bootstrap ✅ |
| **Active Issues** | (1) DNS + SSL for `https://miete.site` — user action. (2) Post-MVP backlog: typing indicators (Reverb client-events), FCM push, pHash dedup, signed URLs for originals, full QA sweep (bug bash / perf / a11y / security / RTL). |
| **Repo** | https://github.com/Qbazzar/Qbazaar — single monorepo, baseline pushed `71216d3`, transferred to `Qbazzar` org |
| **Blockers** | لا blockers على dev. Production publish blocked on DNS/SSL only. |
| **Manual user steps pending** | DNS A-record `miete.site` → `147.79.115.44` + Let's Encrypt SSL via CloudPanel; sign-ups for Twilio (production creds) + Sentry DSN + FCM project. |

## ✅ Progress Log

### Day 1 — Repo & Workspace Setup (2026-05-20) ✅
- 3 repos created locally (`qbazaar-api`, `qbazaar-web`, `qbazaar-contracts`)
- Planning docs (PLAN / ROADMAP / MILESTONES) imported into contracts repo
- OpenAPI v1 skeleton + error-codes catalog + WebSocket events spec committed
- 5 conventional commits in contracts repo, 1 in api, 1 in web — all on `main` (local-only)
- Git identity configured: Ahmed jaber `<ahmedjaberdev@gmail.com>`

**Commits today:**
- `qbazaar-api@b44eef8` — chore(setup): initialize qbazaar-api repo [BE-0.1]
- `qbazaar-web@91bce3f` — chore(setup): initialize qbazaar-web repo [FE-0.1]
- `qbazaar-contracts@61a4d15` — chore(setup): initialize qbazaar-contracts repo [CT-0.1]
- `qbazaar-contracts@2d1703d` — docs: import PLAN, ROADMAP, MILESTONES [CT-0.2]
- `qbazaar-contracts@898644d` — docs(contract): openapi skeleton + errors + events [CT-0.3]

**Deferred to end-of-Sprint-0:** `INT-0.1` (GitHub remote creation + push).

### Day 2 — Laravel Bootstrap (2026-05-20) ✅
- Laravel 12 scaffolded (Laravel 13 not yet released)
- 8 task IDs closed (BE-0.2 → BE-0.9)
- 20+ composer packages installed across 5 commits
- Filament v4 admin panel + Telescope + Horizon scaffolding committed
- Pest test runner initialized; Scribe API docs scaffolded
- Repos consolidated under `c:\laragon\www\QB\` per user request

**Commits today (qbazaar-api only):**
- `5a6333e` chore(setup): scaffold Laravel 12 [BE-0.2]
- `6c2623f` chore(setup): install core packages [BE-0.3]
- `48ab412` chore(setup): install Spatie ecosystem [BE-0.4]
- `4d9675b` chore(setup): Filament 4 + Scribe + Meili + Intervention [BE-0.5]
- `dc465b7` chore(setup): Twilio + FCM [BE-0.6]
- `a53a7f4` chore(setup): dev deps Pest + Telescope + Larastan [BE-0.7]
- `69ba307` chore(setup): vendor:publish configs + migrations [BE-0.8]
- `68a4bff` chore(setup): install Telescope/Horizon/Reverb/Pest/Filament/Scribe [BE-0.9]

**Quirks logged:**
- Horizon requires `--ignore-platform-req=ext-pcntl,ext-posix` on Windows
- `php artisan reverb:install` fails until REVERB_* env vars are set (added inline)
- Filament v4 was used instead of v5 (v5 not released, v4 is compatible with Livewire 4)

### Day 3 — Local Services (2026-05-20) ✅
- MySQL `qbazaar` DB created on Laragon's bundled MySQL 8.4.3
- Redis: discovered Laragon ships redis-server 5.0 — no Memurai needed
- Predis (^3.x) replaced phpredis (PHP ext not built in Laragon)
- Meilisearch 1.44.0 binary downloaded to `c:/meilisearch/` + `start.bat`
- `.env` and `.env.example` fully configured for QBazaar local dev
- DEV-SETUP.md guide for new contributors
- All 3 services verified via tinker — Redis: PONG · MySQL: qbazaar · Meili: available
- `php artisan migrate` applied 9 baseline migrations cleanly

**Commits today (qbazaar-api):**
- `ec25971` chore(env): configure .env + .env.example [BE-0.13, BE-0.14]
- `ab83557` docs(setup): DEV-SETUP for Windows local services [BE-0.10, BE-0.12]
- `941baed` fix(deps): switch to predis (no ext needed) [BE-0.11]

### Day 4 — Project Structure (2026-05-20) ✅
- `config/qbazaar.php` with business constants (auth, otp, ads, search, uploads, etc.)
- 9 domain enums (AdStatus, UserStatus, AccountType, PriceType, Condition, Language, MessageType, OfferStatus, ReportTarget) — typed with helper methods
- `ErrorCode` enum mirroring error-codes.md catalog — 48 stable codes with i18n keys + default HTTP status
- 3 middleware wired into API group: TrackClient → LocaleMiddleware → ApiResponseWrapper
- `bootstrap/app.php` exception handler shaping ValidationException, AuthenticationException, NotFoundHttpException, TooManyRequestsException, generic HttpException into the contract envelope
- 6 named rate-limiter tiers (auth, otp, search, publish, messages, api)
- `routes/api_v1.php` mounted at `/api/v1/*` with `/health` endpoint
- Both `/up` and `/api/v1/health` verified — return 200 + correct envelope

**Commits today (qbazaar-api):**
- `b7b319c` feat(config): project constants [BE-0.15]
- `f54e059` feat(enums): 9 domain enums [BE-0.16]
- `7e5b0be` feat(errors): 48 ErrorCode cases [BE-0.17]
- `7b12c57` feat(middleware): 3 API middleware [BE-0.18]
- `1ed6551` feat(bootstrap): routes, middleware, rate limits, exception handler [BE-0.19–0.21]

### Day 5 — Tooling (2026-05-20) ✅
- Laravel Pint enforced (Laravel preset + strict_types) — 48 files reformatted
- PHPStan level 8 with Larastan — 0 errors
- Pest test added for /api/v1/health + /up
- Scribe API docs generated at /docs (HTML + OpenAPI + Postman collection)
- Sentry Laravel SDK 4.25 wired (DSN empty placeholder)
- GitHub Actions CI in **both** api and contracts repos
- Frozen Day-1 snapshots (QB/{PLAN,ROADMAP,MILESTONES}.md) deleted — only live versions remain
- READMEs in all 3 repos updated with progress badges + task tables

**Commits today:**
- `qbazaar-api`:
  - `0ea4426` style: Pint preset enforced [BE-0.22]
  - `c6f83af` test(phpstan): green at level 8 + health test [BE-0.23, BE-0.24]
  - `35ab8bd` docs(readme): live Sprint 0 progress
  - `4b8f8d2` feat(docs): Scribe generate [BE-0.25] (preceding commit)
  - `2c8591c` feat(observability): Sentry + CI workflow [BE-0.27, BE-0.28a]
- `qbazaar-web`:
  - `84fae2f` docs(readme): live progress + design tokens + Day 6 plan
- `qbazaar-contracts`:
  - `d803a39` docs(readme): live progress + repo diagram
  - `4732efe` ci: OpenAPI lint + Prism smoke workflow [BE-0.28b]

### Day 6 — Next.js + Bazzar design system (2026-05-20) ✅
- Next.js 16.2 (Turbopack) scaffolded into `qbazaar-web/`
- 18 npm dependencies added (TanStack Query, Zustand, axios, next-intl, RHF + Zod, Echo, Pusher, nuqs, Lucide, Sharp, next-themes, Embla)
- shadcn/ui initialised with `--rtl --defaults` (Next + base-nova preset) + 14 primitives
- Brand assets (logo + 6 SVGs) copied to `public/brand/`
- Fonts via `next/font/google`: DM Sans, Instrument Serif (with italic), Cairo, Geist Mono
- `app/globals.css` rewritten with Bazzar palette (Coral / Terracotta / Cream / Ink / Sage) for both light and dark modes, mapped onto shadcn semantic tokens
- ThemeProvider (next-themes, class strategy) wired in `app/layout.tsx`
- `i18n/ar.json` + `i18n/en.json` seeded with brand strings
- `lib/api/client.ts` axios instance pointing at `NEXT_PUBLIC_API_URL`
- Home placeholder uses Instrument Serif italic + Bazzar tokens — `npm run build` ✅

**Commits:**
- `469eb41` chore(web): install Day 6 npm dependencies [FE-0.3]
- `88159e4` feat(web): wire Bazzar design system + shadcn + placeholder home [FE-0.4 … FE-0.14]

**Deferred to Sprint 1:**
- `FE-0.10` next-intl middleware (will land with `/ar`-`/en` Auth pages)
- `FE-0.13` standalone Logo + theme-toggle components

### Day 7 — Mock + Workflow bootstrap (2026-05-20) ✅
- **Major mid-flight change:** user asked to consolidate the 3 sibling repos into a single monorepo. Done via fresh-start git init at `c:\laragon\www\QB\` with baseline commit `71216d3`. Old per-task hashes referenced above no longer resolve but stay as historical breadcrumbs.
- Monorepo originally pushed to **https://github.com/ahmaddev27/Qbazaar**, later transferred to **https://github.com/Qbazzar/Qbazaar** on branch `main`
- `qbazaar-contracts/` npm dependencies installed (Prism + Redocly, 383 packages)
- OpenAPI spec adjusted: paths now carry the `/api/v1/` prefix directly; servers become bare hosts (so Prism and Laravel respond on identical URLs)
- `/api/v1/health` endpoint set to `security: []` so the Prism mock answers without an auth token
- Prism mock smoke-tested: `curl http://localhost:4010/api/v1/health` → `200 OK` with the success envelope

**Commits:**
- `71216d3` chore: consolidate Sprint 0 work into a single monorepo (baseline)
- `42c75be` chore(api): pin sentry/sentry-laravel to ^4.25
- `b507170` feat(contract): wire Prism mock + fix `/api/v1/health` path [CT-0.4, CT-0.5]

**Deferred to Sprint 1:**
- `CT-0.6` Auth endpoints in openapi/v1.yaml (contract-first kickoff)
- `INT-0.2`/`INT-0.3` GitHub Project + Milestones + Issues — manual user steps once GH access is wired

---

### 🏁 Sprint 12 close — MVP feature-complete (2026-05-25)

All 13 sprints landed. The full MVP surface is on `main` and pushed to
[Qbazzar/Qbazaar](https://github.com/Qbazzar/Qbazaar).

**End-to-end shipped:**
- Auth (register / login / OTP / password reset / email verification)
- Account (profile / privacy / sessions / data export / deactivate / delete / avatar upload)
- Categories (63 seeded) + Locations (45 districts seeded)
- Ad CRUD + Spatie MediaLibrary + BlurHash + auto-moderation (banned words / phone / external links) + featured/similar feeds + Edit Ad wizard + Idempotency on publish
- Meilisearch indexing + `/search` w/ facets + saved searches + header type-ahead
- Favorites + Recently-viewed (50/user cap) + anon session UUID
- Reverb messaging (private channels + Echo client) + StartConversationButton + MessagesBadge
- Offers in chat (5 events + ExpireOldOffersJob daily 02:30 Qatar)
- DB-backed notifications + Reports system (7 categories / 7-day duplicate window)
- Filament v4 admin (12 resources + 6 widgets + RBAC + ModerationRule DB editor + Send Announcement action + Cairo font + Q-SVG brand)
- CMS pages (4 default slugs) + Help center (5 categories × 3 articles) + Support tickets w/ threaded replies
- QBFront design system applied to every public page; SiteHeader + SiteFooter global
- DemoDataSeeder ships realistic dev fixtures (18 users + 60 ads + 12 convos + 7 offers + 40 favs + 8 reports + 5 tickets)

**Backlog (post-MVP):**
- Typing indicators (Reverb client-events — needs broadcaster opt-in)
- FCM push notifications (needs Firebase project + device_tokens table)
- pHash dedup for ad images
- ~~Filament resources for Pages/Help/Support~~ ✅ landed 2026-05-25 (`69110de`)
- ~~VPS deploy linking~~ ✅ Laravel now serving on `147.79.115.44`; DNS + SSL for `miete.site` still pending user action

**Verification quick start:**
```
cd c:\laragon\www\QB\qbazaar-api
php artisan migrate:fresh --seed --force      # 18 users + 60 ads + …
php artisan serve                             # http://localhost:8000/admin → admin@qbazaar.qa / password

cd c:\laragon\www\QB\qbazaar-web
npm run dev                                   # http://localhost:3000
```

---

### 🚀 Deploy linking + admin polish + demo refresh (2026-05-25)

Post-MVP "deploy + polish" pass — first VPS install, admin Filament resources
for the Sprint 12 surface, richer demo fixtures, and three production-only
boot-time bugs squashed.

**Filament admin — Pages / Help / Support resources**
- New resources cover the remaining Sprint 12 surface so admins can run the
  whole MVP from `/admin`:
  - `PageResource` — CMS pages (slug + title + body + meta_description +
    published_at + display_order); 4 default slugs seeded (about, terms,
    privacy, safety).
  - `HelpCategoryResource` + `HelpArticleResource` — 5 categories × 3
    articles seeded; both edit Markdown body, slug, display_order, is_published.
  - `SupportTicketResource` — list + filters by status / priority / category;
    detail view shows threaded replies, lets staff post replies (`is_staff=1`)
    and transitions ticket status (open → in_progress → waiting_user →
    resolved → closed).
- Previously these models were managed via tinker / direct DB only — closing
  the deferred item from Sprint 12 (`BE-12.6`).

**DemoDataSeeder — richer dev fixtures**
- Counts after `php artisan migrate:fresh --seed --force`:
  - **20 users** (admin + 19 demo accounts incl. 6 sellers)
  - **27 ads** (mix of active / pending / sold / draft across cars / apartments / mobiles / electronics)
  - **11 conversations** with **36 messages** total (cursor-paginated)
  - **7 offers** in various OfferStatus states (pending / accepted / rejected / withdrawn / expired)
  - **40 favorites** spread across the 20 users
  - **8 reports** filed against ads / users / messages
  - **5 support tickets** with threaded replies (open + in_progress + resolved)
  - **4 CMS pages** (about / terms / privacy / safety) with bilingual bodies
  - **15 help articles** (5 categories × 3 articles)
- Replaces the older "18 users + 60 ads" seed numbers from the Sprint 12 close
  entry above — the 2026-05-25 demo is tighter, more representative, and
  every relation is wired so the admin panel demos cleanly end-to-end.

**Deploy linked to VPS**
- Target: `147.79.115.44` (CloudPanel) — tenant user `qb-user`, public URL
  `https://miete.site` (DNS + SSL still pending user action).
- Laravel app is live: `GET /api/v1/health` returns `200` with the success
  envelope under the raw IP.
- `deploy/scripts/deploy-api.sh` rewritten for the `qb-user` /
  `www.miete.site` layout (commit `f644147`).

**Production-only boot bugs fixed**
- `01b2f25` — Telescope service-provider registration gated on
  `APP_ENV !== 'production'`; otherwise it bombed boot on the VPS where
  `telescope` is `require-dev` only.
- `168e674` — moved the dead-code `jsonError()` helper out of the
  exception-handler closure where Opcache + production cache combo caused
  a "function already defined" fatal on the second request.
- `da46ccf` — `RateLimiter::for(...)` calls relocated out of the
  `withRouting()->then(...)` closure into a dedicated bootstrap step; the
  closure ran twice under route caching on the VPS, double-registering the
  named limiters and breaking `/api/v1/auth/login`.

**Commits today (qbazaar-api):**
- `eea6333` chore(seed): DemoDataSeeder counts refreshed for admin demo
- `69110de` feat(admin): Filament resources for Pages / Help / Support [BE-12.6 follow-up]
- `f644147` chore(deploy): rewrite deploy-api.sh for qb-user / www.miete.site layout
- `01b2f25` fix(boot): Telescope provider gated on non-production
- `168e674` fix(boot): hoist jsonError() helper out of handler closure
- `da46ccf` fix(boot): RateLimiter::for relocated outside withRouting() closure

---

## 🎬 Sprint 0 Retrospective (2026-05-20)

### ✅ What went well
- **Contract-first scaffolding** — every endpoint we plan in Sprint 1 already has a typed envelope and an error code reserved.
- **Multi-agent parallel via Prism** works: backend agent and frontend agent can move independently with the mock as the shared truth.
- **Auto-fix tooling green** — Pint and PHPStan-level-8 both clean on first run after Laravel scaffolding.
- **One-file source of truth** for status (`ROADMAP.md`) reduced confusion once we deleted the Day-1 snapshots.
- **Monorepo consolidation** mid-sprint was fast (fresh-start commit). Worth the small history loss given the user is solo.

### 🟡 What slowed us down
- **Laravel 13 not released yet** — fell back to Laravel 12. Same surface area, but PLAN.md references 13 in places.
- **Horizon requires pcntl/posix** which Windows PHP doesn't ship. Workaround: `--ignore-platform-req` for install + `php artisan queue:work` in dev.
- **phpredis extension missing** in Laragon's PHP 8.4 build — switched to Predis (pure PHP). No noticeable cost at our scale.
- **Pest CLI output suppressed** under this shell when run from the agent runner — tests are valid Pest 3 syntax but verification was via PHPStan + manual artisan invocation rather than a clean `pest --colors=always` pass.
- **shadcn `form` component** wanted interactive template input under `--yes` — deferred to Sprint 1 alongside React Hook Form wiring.

### 🔴 Blockers / manual user steps that remain
- GitHub Project board + 13 Milestones + Labels (web UI, takes 10 min).
- Account sign-ups: Twilio (Sprint 1), Sentry (Sprint 0 idle until DSN), Cloudflare R2 (Sprint 4), FCM (Sprint 10).
- Domain `qbazaar.qa` registration before launch.

### 🧭 Decisions taken mid-sprint (added to Decisions Log)
- **MySQL 8 over PostgreSQL 16** — chosen Day 0; reaffirmed.
- **Predis over phpredis** — Day 3 due to Laragon PHP build.
- **Filament v4 over v5** — Day 2 due to v5 not yet released, v4 already matches Livewire 4.
- **Monorepo over polyrepo** — Day 7, user request mid-flight. Fresh-start commit history accepted as the cost.
- **`/api/v1/health` path written explicitly in spec** — Day 7, simplifies Prism + Laravel both responding at the same URL.

### 🎯 Sprint 1 kickoff plan
1. Open `qbazaar-contracts/openapi/v1.yaml`, add Auth endpoints (register, login, refresh, logout, send-otp, verify-otp, resend-otp, forgot-password, reset-password, verify-email).
2. Restart Prism mock — frontend agent starts building Login/Register/OTP pages against it.
3. Backend agent implements the same endpoints with Pest tests + Scribe annotations.
4. Integration day: flip `NEXT_PUBLIC_API_URL` from `:4010` → `:8000` and verify.

---

## 📊 ملخص المايلستونس

| # | Milestone | الحجم المُقدَّر | الحالة |
|---|-----------|------------------|--------|
| 1 | Backend Foundation | 2 أسبوع | ✅ مكتمل |
| 2 | Marketplace Core | 3 أسابيع | ✅ مكتمل |
| 3 | Engagement | 3 أسابيع | ✅ مكتمل |
| 4 | Trust & Admin | 2 أسبوع | ✅ مكتمل |
| 5 | Content & Polish | 1 أسبوع | ✅ مكتمل |
| 6 | Web Frontend (parallel) | — | ✅ مكتمل (QBFront design system) |
| 7 | Launch Prep | 1 أسبوع | 🟡 جاري (VPS deploy linking) |

**Legend:** ✅ مكتمل · 🟢 جاهز للإغلاق · 🟡 جاري · ⚪ منتظر · 🔴 blocked

---

## 🗺️ Milestone 1 — Backend Foundation (أسبوعين)

> الأساس قبل أي feature. كل ما بعدها يعتمد عليها.

### Sprint 0 — Infrastructure & Foundation (~1 أسبوع — ✅ تم في 2026-05-20)

7 أيام، 3 tracks متوازية. **راجع `MILESTONES.md` للتاسكات التفصيلية.**

- [x] Day 1: Repos + Workspace setup ✅ (2026-05-20)
- [x] Day 2: Laravel Bootstrap + packages ✅ (2026-05-20)
- [x] Day 3: Local services (Redis/Meilisearch/MySQL) ✅ (2026-05-20)
- [x] Day 4: Project structure (config, enums, middleware) ✅ (2026-05-20)
- [x] Day 5: Tooling (Pint, PHPStan, Pest, Scribe, Sentry, CI) ✅ (2026-05-20)
- [x] Day 6: Next.js skeleton + Design system ✅ (2026-05-20)
- [x] Day 7: Prism mock + Workflow + Sprint 1 planning ✅ (2026-05-20)

**DoD:** كل verification items من `PLAN.md` بنجاح.

---

### Sprint 1 — Auth (~3 أيام) ✅

- [x] **Backend:** Register, Login, Logout, Refresh, OTP (send/verify/resend), Forgot/Reset password, Email verification, UserObserver — `e3c5349` + `40fe7a7` + `965ff11` + `df77ac3`
- [x] **Frontend:** Login/Register/OTP/Forgot pages, Auth store, axios interceptors, protected routes
- [x] **Contract:** Auth endpoints in openapi/v1.yaml كاملة
- [x] **Tests:** Pest > 70% للـ Auth module

---

### Sprint 2 — Users (3 أيام) ✅ Wave 1 + Wave 2

- [x] **Backend Wave 1:** Profile CRUD, Public profile, Sessions, Privacy settings, Block/Unblock — `ceccd95` + `8d80865`
- [x] **Backend Wave 2:** Data export, Deactivate, Delete-request, Avatar upload, Policies, Jobs — `c9c22f8` + `4f164f8`
- [x] **Frontend Wave 1:** Account dashboard, Profile edit, Sessions, Privacy, Blocked users, Public profile — `14f71b7` + `2186010`
- [x] **Frontend Wave 2:** Data page (export/deactivate/delete) + AvatarUploader with crop — `81a1042` + `497f1b0`
- [x] **Contract:** User endpoints + Wave 2 lifecycle/avatar paths in `openapi/v1.yaml` + postman

---

### Sprint 3 — Categories & Locations (2 أيام) ✅

- [x] **Backend:** Category tree (cached 1h), main, stats/filters/fields per slug, Qatar locations (24h cache) — `4e2a9b3`
- [x] **Frontend:** `/categories` index + `/c/[slug]` detail with breadcrumbs, filters preview, LocationPicker — `a37b426`
- [x] **Contract:** 6 endpoints + 6 schemas (Category, CategoryNode recursive, CategoryFilter/Field, Location recursive, LocalizedString) in openapi/v1.yaml + postman
- [x] **Data:** 63 categories (cars/apartments/mobiles fully fielded) + 9 cities + 36 districts seeded

---

## 🛒 Milestone 2 — Marketplace Core (3 أسابيع)

> القلب — الإعلانات والبحث. أهم Milestone في الـ MVP.

### Sprint 4 — Uploads (2 أيام) ✅

- [x] **Backend:** Spatie MediaLibrary on Ad model, 4 conversions, BlurHash via kornrunner — `6adcf58`. pHash + R2 out-of-MVP.
- [x] **Frontend:** ImageDropzone with @dnd-kit reorder, per-file progress, client compress, BlurHash placeholder — `b89c83b`.
- [x] **Contract:** Upload + media schemas in openapi/v1.yaml — `7b05007`

---

### Sprint 5 — Ads (أسبوعين) ✅

- [x] **Backend Wave A** (`6adcf58`): Ad CRUD (draft+publish+update+delete), state machine, Renew + Mark sold, AdPolicy, 9 routes, 5 ErrorCodes, Pest
- [x] **Backend Wave B** (`311440b`): Auto-moderation engine (banned words + phone + external links) wired into publish, 6 lifecycle events (AdPublished/Approved/Rejected/Expired/ExpiringSoon/Renewed), AdObserver + LogsActivity, ExpireOldAdsJob (daily 02:00), SimilarAdsController, FeaturedAdsController, Idempotency middleware, dynamic custom_fields validation, 4 ad notifications (mail), 7 Pest test files
- [x] **Frontend Wave A** (`b89c83b`): Home (Hero) + Ad Detail + Post Ad wizard + My Ads
- [x] **Frontend Wave B** (`e6d127d`): Edit Ad page (PostAdWizard edit mode), AdSimilar strip, HomeFeaturedAds, X-Idempotency-Key on publish, RequireAuth HOC + useRequireAuth hook (closing FE-1.18/1.19)
- [x] **Contract:** Full Ad schemas + Wave B endpoints in openapi/v1.yaml + postman — `7b05007` + `c6f2c2c`

---

### Sprint 6 — Search (3 أيام) ✅

- [x] **Backend:** Scout + Meilisearch indexing on Ad; `/search` + `/search/suggestions` + saved-searches CRUD; auto-(un)index on publish/markSold/renew — `f758369`
- [x] **Frontend:** `/search` results + FilterSidebar (categories/locations/price/condition) + SortDropdown + header SearchBar w/ type-ahead + `/account/saved-searches` — `9fdb351`
- [x] **Contract:** Search endpoints + Wave A ads paths catch-up — `7b05007`

---

## 💞 Milestone 3 — Engagement (3 أسابيع) ✅

### Sprint 7 — Favorites & Recently Viewed (1 يوم) ✅

- [x] **Backend:** favorites + recently_viewed tables, toggle + list + view tracking (Cache::lock 1h throttle), 50-row cap inline cleanup — `56864e7`
- [x] **Frontend:** Saved page + RecentlyViewedStrip on home + FavoriteButton w/ optimistic toggle + anon-session UUID — `636a8f6`
- [x] **Contract:** Favorites + Recently-viewed endpoints — `1d8c354`

---

### Sprint 8 — Messaging via Reverb (أسبوعين) ✅ Wave A

- [x] **Backend:** Reverb broadcasting (MessageSent + ConversationRead on private channels), Conversations + Messages CRUD, ConversationPolicy, block integration, cursor pagination — `4eb509a`
- [x] **Frontend:** `/account/messages` inbox + chat split-pane, Echo client (lazy + axios-backed authorizer), MessagesBadge polling fallback, StartConversationButton on ad detail — `015149d`
- [x] **Contract:** Messages endpoints + `events/messaging.md` — `6b45095`
- [ ] **Wave B (deferred):** typing indicators (needs Reverb broadcaster opt-in for client-* events)

---

### Sprint 9 — Offers (1 يوم) ✅

- [x] **Backend:** Offer model + 5 events (Created/Accepted/Rejected/Withdrawn/Expired) + actions (own-ad / block / active-offer guards) + ExpireOldOffersJob daily 02:30 Qatar — `5aca9c0`
- [x] **Frontend:** OfferComposer modal in ChatInput + OfferBubble in MessageList + OfferStatusBadge + Echo wiring for offer.* events — `d9e8a11`

---

## 🛡️ Milestone 4 — Trust & Admin (أسبوعين) ✅

### Sprint 10 — Reports & Notifications (1 أسبوع) ✅ Wave A

- [x] **Backend:** notifications table (ULID morphs) + DB channel on 6 existing notifications + NotificationCreated Reverb event + Reports system (7 categories + 7-day duplicate window) — `5b29ca9`
- [x] **Frontend:** NotificationsBadge bell + popover + `/account/notifications` + ReportButton wired into ad detail + public profile — `13fb816`
- [x] **Contract:** Notifications + Reports paths + `events/notifications.md` — `2c60020`
- [ ] **Deferred:** FCM push notifications (needs Firebase project + device-token table)

---

### Sprint 11 — Filament Admin Panel (1 أسبوع) ✅

- [x] **RBAC:** super_admin / moderator / support roles + ~30 permissions seeder — `b9f62e9`
- [x] **12 Resources:** User, Ad, Category, Location, Report, Notification, Conversation, Message, Offer, SavedSearch, ModerationRule, ActivityLog
- [x] **6 Widgets:** UsersStats, AdsStats, ReportsStats, RevenueStats, AdsPublishedChart, RecentReports
- [x] **Custom Dashboard:** "Send Announcement" action + SystemAnnouncementNotification (database + mail to all/active users)
- [x] **ModerationRule** DB table backs the auto-moderation service (cache 1h + config fallback)
- [x] **Polish:** Cairo font + Q-SVG brand + sidebar group labels + coral active item — `e4c6578`
- [x] **Admin login fixes:** Spatie morph key char(26) + getFilamentName() + SESSION_DRIVER=file — `5eea748` + `9173a99` + `3937927`

---

## 📚 Milestone 5 — Content & Polish (أسبوع) ✅

### Sprint 12 — CMS, Help, Support (2 أيام) ✅

- [x] **Backend:** CMS pages + Help (5 categories × 3 articles) + Support tickets (auth-optional submission + threaded replies + status lifecycle) — `54ec549`
- [x] **Frontend:** `/p/[slug]` CMS + `/help` + `/help/c/[slug]` + `/help/articles/[slug]` + `/help/search` + `/support` landing + `/support/new` + `/account/support` + `/account/support/[id]` — `1f6fb43`
- [x] **DemoDataSeeder:** 18 users + 60 ads (35 active inc. 6 featured + 6 sold + …) + 12 convos + 7 offers + 40 favorites + 8 reports + 5 tickets — `eea6333`
- [ ] **Filament:** dedicated Filament resources for Pages/Help/Support — *deferred (admin can manage via tinker / direct DB for now)*

### Web Frontend — QBFront design system ✅

- [x] Inter + Cairo fonts via next/font/google
- [x] Coral #F37335 + cool grey background palette
- [x] Inline Q-SVG brand mark (header + footer + admin panel)
- [x] Full QBFront HTML → Next.js port: home, /ads, /ads/[id], /post-ad, /account/messages, /account/notifications, /account/favorites, /u/[id], /categories, /login, /register — `72aa2cc`

### QA + Buffer (3 أيام)

- [ ] Bug bash session
- [ ] Performance audit (Lighthouse + Laravel Pulse)
- [ ] Accessibility audit (axe DevTools)
- [ ] Security review (composer audit, npm audit, OWASP top 10 checklist)
- [ ] RTL audit (test every page in Arabic)

---

## 🌐 Milestone 6 — Web Frontend (متوازي مع 2-5)

> Frontend agent بيشتغل بالتوازي مع backend عبر Prism mock. كل sprint له frontend track (مدمج في الـ sprints أعلاه).

**أهداف Milestone 6 المستقلة:**
- [ ] PWA capabilities (manifest, service worker) — اختياري
- [ ] Sitemap generation (يستهلك Backend sitemap endpoints)
- [ ] OpenGraph tags ديناميكية لـ ads
- [ ] JSON-LD structured data للـ SEO
- [ ] Lighthouse audit > 90 score

---

## 🚀 Milestone 7 — Launch Prep (أسبوع)

- [ ] Production environment setup (Forge / Cloud / DO)
- [ ] qbazaar.qa domain + SSL
- [ ] CDN setup (Cloudflare)
- [ ] Database migration to production (with seed)
- [ ] Staging deploy + smoke tests
- [ ] UAT (user acceptance testing)
- [ ] Sentry production alerts
- [ ] Backup strategy (daily DB dumps + S3 sync)
- [ ] Production deploy
- [ ] Post-launch monitoring (48 hours)

---

## 📋 Decisions Log

| التاريخ | القرار | المبرر |
|---------|--------|---------|
| 2026-05-20 | MySQL 8 بدل PostgreSQL 16 | توافق Laragon + بساطة solo dev. Meilisearch بيتولى البحث، MySQL JSON كافي للـ Translatable |
| 2026-05-20 | Laragon بدل Sail | بيئة Windows موجودة، أسرع للـ solo dev |
| 2026-05-20 | Polyrepo (3 repos) | تنظيم أوضح، CI/CD منفصل، fit لـ contract-first |
| 2026-05-20 | Redis كامل عبر Memurai | queue + cache + sessions + rate limiting الحقيقي |
| 2026-05-20 | Reverb من Sprint 8 (مش polling) | real-time chat experience |
| 2026-05-20 | Multi-agent parallel via Prism mock | frontend ما يستنى backend |
| 2026-05-20 | Brand name = "QBazaar" (مش "Bazzar") | الـ domain qbazaar.qa، codename موحد |
| 2026-05-20 | Home variant = A (Hero) فقط | بدل 3 variants من الـ mockup |
| 2026-05-20 | Coral palette فقط | بدل 4 palettes من الـ mockup |
| 2026-05-20 | Dark mode من البداية عبر next-themes | UX modern, derived palette |

---

## ❓ Open Questions

- [ ] هل بدنا Twilio أو SMS provider قطري آخر؟ (يحسم Sprint 1)
- [ ] الـ hosting النهائي: Laravel Forge / Cloud / DigitalOcean / Hetzner؟ (يحسم Milestone 7)
- [ ] هل اللوغو الـ PNG الحالي هو الـ final أو لازم vector؟ (يحسم Sprint 0 Day 6)
- [ ] هل نسجل qbazaar.qa دومين الآن أو لاحقاً؟

---

## 📝 Sprint Retros

> سنملأ هذا القسم في نهاية كل sprint.

### Sprint 0 (TBD)
- ✅ ...
- 🟡 ...
- 🔴 ...

---

## 🗂️ Post-MVP Backlog

> Tracked here so they don't get lost. None are blockers for the MVP launch —
> they're scheduled to land after the production go-live on `miete.site`.

### Real-time + notifications
- [ ] **Typing indicators via Reverb client-events** — requires opting the
      broadcaster into `client-*` events + an Echo `.whisper()` wire on the
      chat composer. Sprint 8 Wave B carry-over.
- [ ] **FCM push notifications** — needs Firebase project + `device_tokens`
      table + a notification channel that wraps `kreait/firebase-php`. Wires
      onto the existing 6+ database-channel notifications.

### Trust & media
- [ ] **pHash dedup for ad images** — phash-style perceptual hash on every
      uploaded image; reject (or flag) near-duplicates of an existing ad's
      photos. Originally scoped in Sprint 4 (BE-4.8) but deferred as
      post-launch quality work.
- [ ] **Signed URLs for original-resolution ad images** — currently the
      `original_webp` conversion is public; production should serve the
      full-res file behind a short-TTL signed URL (Sprint 4 BE-4.12).

### QA + audit sweep (pre-launch checklist)
- [ ] **Bug bash** — manually run every user story from Sprint 1–12 against
      the staging VPS.
- [ ] **Performance audit** — Lighthouse on every public page (mobile +
      desktop) + Laravel Pulse review (slow queries, slow jobs).
- [ ] **Accessibility audit** — axe DevTools sweep on every page; fix any
      critical/serious violations.
- [ ] **Security review** — `composer audit` + `npm audit` + OWASP Top 10
      walkthrough + SQL-injection / XSS / CSRF spot-checks + rate-limit
      verification on auth/otp/publish/messages.
- [ ] **RTL audit** — every page in `/ar/*` rendered with correct
      direction, mirrored icons, and aligned form controls.
