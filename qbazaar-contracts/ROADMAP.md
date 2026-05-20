# QBazaar — Roadmap (Solo Dev MVP)

> **آخر تحديث:** 2026-05-20 (نهاية Day 1)
> **تاريخ البدء:** 2026-05-20
> **التاريخ المتوقع للإطلاق:** 2026-08-26 (~14 أسبوع)
> **النطاق:** Backend (Laravel) + Web (Next.js). بدون Mobile/Phase 2.

---

## 🎯 الحالة الحالية

| البند | القيمة |
|-------|---------|
| **Active Milestone** | Milestone 1 — Backend Foundation |
| **Active Sprint** | Sprint 0 — Infrastructure & Foundation |
| **Active Day** | Day 6 — Next.js + Design System (Days 1–5 ✅) |
| **Blockers** | لا يوجد |
| **Next Decision Needed** | تسجيل Sentry account (Sprint 0 — Day 5) |

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

---

## 📊 ملخص المايلستونس

| # | Milestone | المدة | البدء | الانتهاء المتوقع | الحالة |
|---|-----------|-------|-------|------------------|--------|
| 1 | Backend Foundation | 2 أسبوع | 2026-05-20 | 2026-06-02 | 🟡 جاري |
| 2 | Marketplace Core | 3 أسابيع | 2026-06-03 | 2026-06-23 | ⚪ منتظر |
| 3 | Engagement | 3 أسابيع | 2026-06-24 | 2026-07-14 | ⚪ منتظر |
| 4 | Trust & Admin | 2 أسبوع | 2026-07-15 | 2026-07-28 | ⚪ منتظر |
| 5 | Content & Polish | 1 أسبوع | 2026-07-29 | 2026-08-04 | ⚪ منتظر |
| 6 | Web Frontend (parallel) | — | 2026-06-03 | 2026-08-11 | ⚪ منتظر |
| 7 | Launch Prep | 1 أسبوع | 2026-08-12 | 2026-08-26 | ⚪ منتظر |

**Legend:** ✅ مكتمل · 🟢 جاهز للإغلاق · 🟡 جاري · ⚪ منتظر · 🔴 blocked

---

## 🗺️ Milestone 1 — Backend Foundation (أسبوعين)

> الأساس قبل أي feature. كل ما بعدها يعتمد عليها.

### Sprint 0 — Infrastructure & Foundation (1 أسبوع · 2026-05-20 → 2026-05-26)

7 أيام، 3 tracks متوازية. **راجع `MILESTONES.md` للتاسكات التفصيلية.**

- [x] Day 1: Repos + Workspace setup ✅ (2026-05-20)
- [x] Day 2: Laravel Bootstrap + packages ✅ (2026-05-20)
- [x] Day 3: Local services (Redis/Meilisearch/MySQL) ✅ (2026-05-20)
- [x] Day 4: Project structure (config, enums, middleware) ✅ (2026-05-20)
- [x] Day 5: Tooling (Pint, PHPStan, Pest, Scribe, Sentry, CI) ✅ (2026-05-20)
- [ ] Day 6: Next.js skeleton + Design system
- [ ] Day 7: Prism mock + Workflow + Sprint 1 planning

**DoD:** كل verification items من `PLAN.md` بنجاح.

---

### Sprint 1 — Auth (3 أيام · 2026-05-27 → 2026-05-29)

- [ ] **Backend:** Register, Login, Logout, Refresh, OTP (send/verify/resend), Forgot/Reset password, Email verification, UserObserver
- [ ] **Frontend:** Login/Register/OTP/Forgot pages, Auth store, axios interceptors, protected routes
- [ ] **Contract:** Auth endpoints in openapi/v1.yaml كاملة
- [ ] **Tests:** Pest > 70% للـ Auth module

---

### Sprint 2 — Users (3 أيام · 2026-05-30 → 2026-06-01)

- [ ] **Backend:** Profile CRUD, Public profile, Sessions, Privacy settings, Block/Unblock, Avatar upload, Account deactivation
- [ ] **Frontend:** Account dashboard, Profile edit, Settings page, Privacy controls, Blocked users list
- [ ] **Contract:** User endpoints

---

### Sprint 3 — Categories & Locations (2 أيام · 2026-06-02)

- [ ] **Backend:** Category tree (cached), filters, custom fields per category. Qatar locations seeder
- [ ] **Frontend:** Category browser, Location picker
- [ ] **Contract:** Categories + Locations endpoints
- [ ] **Data:** Seeders لـ ~50 category + كل مناطق قطر

---

## 🛒 Milestone 2 — Marketplace Core (3 أسابيع)

> القلب — الإعلانات والبحث. أهم Milestone في الـ MVP.

### Sprint 4 — Uploads (2 أيام · 2026-06-03 → 2026-06-04)

- [ ] **Backend:** Spatie MediaLibrary setup, multi-size conversions (thumbnail/medium/large/original-webp), Cloudflare R2 connection, BlurHash, pHash for dedup
- [ ] **Frontend:** Image picker + uploader, multi-file, progress, drag-reorder, BlurHash placeholder
- [ ] **Contract:** Upload endpoints + media response schema

---

### Sprint 5 — Ads (أسبوعين · 2026-06-05 → 2026-06-18)

- [ ] **Backend:**
  - Ad CRUD (draft + publish)
  - State machine (Draft → Pending → Active → Sold/Expired/Rejected/Blocked)
  - Auto-moderation engine (banned words, phone detection, external links, image dedup)
  - Renew + Mark sold actions
  - View tracking (throttled)
  - Latest + Featured + Similar feeds
  - Expire job (scheduled daily)
  - AdPolicy + AdObserver
- [ ] **Frontend:**
  - Home page (Hero variant) - من home.jsx mockup
  - Ad Details page (SSR) - من detail.jsx
  - Post Ad multi-step flow (4 خطوات) - من post.jsx
  - My Ads page (drafts + active)
  - Renew/Mark sold/Delete actions
- [ ] **Contract:** Full Ad schemas + endpoints
- [ ] **Tests:** Auto-moderation edge cases (Arabic banned words, phone numbers in different formats)

---

### Sprint 6 — Search (3 أيام · 2026-06-19 → 2026-06-21)

- [ ] **Backend:**
  - Laravel Scout + Meilisearch setup
  - Searchable trait on Ad model
  - Synonyms dictionary (Arabic)
  - Saved searches CRUD
  - Saved search alert job (scheduled)
  - Search suggestions endpoint
- [ ] **Frontend:**
  - Search page (search.jsx mockup)
  - Filters bottom sheet
  - Sort options (priceAsc/priceDesc/newest)
  - Saved searches UI
  - Recent searches in localStorage
- [ ] **Contract:** Search endpoints + filters schema

---

## 💞 Milestone 3 — Engagement (3 أسابيع)

> ما يحول المستخدم من زائر لمستخدم نشط.

### Sprint 7 — Favorites & Recently Viewed (1 يوم · 2026-06-24)

- [ ] **Backend:** Favorites CRUD, Recently viewed (cap 50/user), cleanup job
- [ ] **Frontend:** Saved page (saved.jsx), Favorite toggle on cards, Recently viewed strip
- [ ] **Contract:** Favorites endpoints

---

### Sprint 8 — Messaging via Reverb (أسبوعين · 2026-06-25 → 2026-07-08)

- [ ] **Backend:**
  - Reverb setup (config + supervisor instructions)
  - Conversations + Messages CRUD
  - Broadcasting channels (private conversations)
  - MessageSent event
  - Content safety service (phone, links, banned words detection)
  - ConversationPolicy
  - Read/unread tracking
- [ ] **Frontend:**
  - Messages inbox (messages.jsx)
  - Chat UI (real-time via Echo)
  - Typing indicators (لاحقاً)
  - Conversation report action
  - Unread badges
- [ ] **Contract:** Messages endpoints + WebSocket events spec
- [ ] **Infra:** Document how to run Reverb on Windows for dev

---

### Sprint 9 — Offers (1 يوم · 2026-07-09)

- [ ] **Backend:** Offer CRUD, Accept/Reject/Counter actions, Offer expiry (7 days), Notification triggers
- [ ] **Frontend:** Make offer modal on ad page, Offers list in chat
- [ ] **Contract:** Offers endpoints

---

## 🛡️ Milestone 4 — Trust & Admin (أسبوعين)

> الموثوقية + الإدارة.

### Sprint 10 — Reports & Notifications (1 أسبوع · 2026-07-10 → 2026-07-16)

- [ ] **Backend:**
  - Reports CRUD (Ad/User/Conversation)
  - Laravel Notifications (database + mail + FCM channels)
  - Notification preferences per user
  - Device tokens management
  - FCM Channel integration
- [ ] **Frontend:**
  - Report modal (3 types)
  - Notifications inbox
  - Notification preferences page
  - Push notification permission flow
- [ ] **Contract:** Reports + Notifications endpoints
- [ ] **Account:** تسجيل Firebase project لـ FCM

---

### Sprint 11 — Filament Admin Panel (1 أسبوع · 2026-07-17 → 2026-07-23)

- [ ] **Resources (16):** User, Ad, Report, Category, Location, BusinessApplication, SupportTicket, ModerationRule, CmsPage, HelpArticle, NotificationTemplate, AdminUser, + 4 more
- [ ] **Pages:** Dashboard with widgets, SystemSettings, AuditLogsPage
- [ ] **Widgets:** StatsOverview, AdsChart, PendingReportsWidget
- [ ] **Auth:** 2FA plugin, custom admin guard
- [ ] **Polish:** Approve/Reject/Block actions on ads with reason input

---

## 📚 Milestone 5 — Content & Polish (أسبوع)

### Sprint 12 — CMS, Help, Support (2 أيام · 2026-07-24 → 2026-07-25)

- [ ] **Backend:** CMS Pages (about, terms, privacy, safety), Help articles, Support tickets
- [ ] **Frontend:** CMS pages, Help center (help.jsx), Support contact form
- [ ] **Contract:** CMS endpoints
- [ ] **Filament:** CMS + Help + Support resources

### QA + Buffer (3 أيام · 2026-07-26 → 2026-07-28)

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

## 🚀 Milestone 7 — Launch Prep (أسبوع · 2026-08-12 → 2026-08-26)

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
