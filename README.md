# QBazaar

> A classifieds marketplace for Qatar (QAR, Arabic + English). This monorepo holds the Laravel API and admin panel, the Next.js web client, the API contract and planning docs, and the deploy files.

## Live

| What | URL |
|------|-----|
| Web (Arabic by default, English through the language switcher) | https://qbazaar.qa |
| API | https://api.qbazaar.qa/api/v1 (health: `/api/v1/health`) |
| Admin panel (staff login) | https://api.qbazaar.qa/admin |
| Media CDN (Cloudflare R2) | https://cdn.qbazaar.qa |

Swagger UI (`/docs`) is switched off in production; run the API locally or read [the OpenAPI spec](qbazaar-contracts/openapi/v1.yaml).

**Status (2026-11-11):** production runs commit `837f1d0` (the web at https://qbazaar.qa, the API and `/admin` at https://api.qbazaar.qa). It includes the whole admin M4 batch, the final design-parity rounds, web performance part 2 and the design-based demo data.

Live now:

- **API and admin:** the MVP backend, the V2 M1 gaps and the cash order cycle (orders, wallet, settlements, paid promotions). Catalog filters with facet counts, a "recommended" feed and CMS footer pages (#243-#248). The admin has settings tabs, a TipTap editor, role-aware charts, a wide page shell, the ad deal panel and the revenue page (#249-#273). The M4 batch adds:
  - companies, business applications and verification, featured companies, and manual or automatic home sections;
  - one category tree with a per-category fields editor, and safe category and location deletes;
  - acting from a report, mandatory sanction reasons with notifications, a staff inbox and bell, a FIFO queue with SLA and bulk actions, and offer and dispute handling;
  - staff 2FA, conversation access with a stated reason, a broadcast composer, a permission matrix editor, and follow and message stats on the dashboard;
  - payment settings (commission, debt ceiling, promotion prices) and an orders list.
- **Web (M3):** the whole site is on the new Figma design (exact colours, Poppins, Noto Kufi Arabic, Montserrat, Acme; SEO). Design parity phases 1 to 3 are merged (final rounds #283-#285, gate fixes #286 and #288), and web performance part 2 (server-rendered first results, LCP priority, lazy map) is in #282.
- **Demo data:** the demo content now follows the original design: 32 listings with the design photos, titles, prices and sellers, curated home rail and featured companies (#287). It was refreshed on production on 2026-11-11, and the placeholder ads are gone.
- **Contract:** OpenAPI v1.1 validates and covers every live route, and CI lints it (#290).
- **Server (M5):** a cPanel VPS behind Cloudflare (Full strict), Meilisearch, Horizon and Reverb as systemd services, media on Cloudflare R2 (`cdn.qbazaar.qa`), deploys only through GitHub Actions on a push to `production`. The launch-readiness work (runbook, backups, health check, tuning files) is prepared in PR #292 and waits for the server steps; none of it is applied yet.
- **Mobile (M2):** the API layer, session, repository switch and catalog are on the real API (Qbazaar-mobile #24, tab bar and account hub #25). Passwordless sign-in, the new-device SMS check and Turnstile are merged (#26); Google/Apple sign-in is not wired yet.

**Admin security:** `super_admin` accounts must enrol in 2FA (TOTP) on their next login. Keep the recovery codes somewhere safe. Reading a conversation transcript in the admin needs a stated reason, and every access is logged.

Next:

- Mobile M2 (app and API): chat, push, selling and orders, then Google/Apple sign-in once the credentials exist.
- Server M5: apply the runbook steps from #292 (backups first, then monitoring and alerts, tuning and a load test).
- Web: a clean parity-gate run on every page, a repeat Lighthouse run, and the axe report.
- M6: the store release.
- Payments (M7), later.

See [ROADMAP.md](qbazaar-contracts/ROADMAP.md) for the full picture.

## Progress

<!-- progress:start -->
**Current phase:** M0 Preparation and alignment · **Overall:** 77% (154/199 tasks)

```text
M0   ████████░░   80% (20/25)   Preparation and alignment
M1   ██████████   97% (73/75)   Closing the backend gaps
M1b  ██████████  100% (10/10)   Orders and payments
M2   ███░░░░░░░   26% (4/15)    Connecting the mobile app
M3   ████████░░   83% (15/18)   Web on the new design
M4   ██████████  100% (26/26)   Admin additions
M5   ███░░░░░░░   33% (6/18)    Deployment on the new server
M6   ░░░░░░░░░░    0% (0/5)     Releasing the mobile app
M7   ░░░░░░░░░░    0% (0/7)     Electronic payment and monetization
```

_Generated from [`MILESTONES-V2.md`](https://github.com/Qbazzar/Qbazaar/blob/main/qbazaar-contracts/MILESTONES-V2.md) by `node scripts/progress.mjs`; a task counts as done when it sits in a **Done** table._
<!-- progress:end -->

After moving tasks in `MILESTONES-V2.md`, run `node scripts/progress.mjs` to refresh this block, the one in the roadmap and the milestone Status table (`--check` only reports whether they are stale; CI runs it).

## Layout

```
QB/
├── qbazaar-api/         Laravel 12 API (/api/v1) + custom Blade admin panel (/admin)
│                        MySQL · Redis · Meilisearch (Scout) · Reverb · Horizon · Sanctum · Spatie
├── qbazaar-web/         Next.js 16 web client (React 19 · TypeScript · Tailwind 4 · TanStack Query · AR/EN)
├── qbazaar-contracts/   OpenAPI spec, error codes, WebSocket events, V2 plan, roadmap, milestones
├── deploy/              Production deploy: scripts, systemd units, Apache includes, env templates, runbooks
├── DOCS/                PRD (PDF), the 2026-09-30 audit, the 2026-06-21 QA report, original (pre-build) plans, mockup assets
├── scripts/             progress.mjs (the progress block above)
└── .github/workflows/   ci.yml (API quality gates), web-ci.yml (web checks), contract.yml (OpenAPI lint), progress.yml, deploy-api.yml, deploy-web.yml
```

Related repos: [`Qbazzar/Qbazaar-mobile`](https://github.com/Qbazzar/Qbazaar-mobile) (Expo app) and [`Qbazzar/Qbazaar-front`](https://github.com/Qbazzar/Qbazaar-front) (static prototype of the new design, the pixel reference for the web reskin).

## Where to look first

| Need | File |
|------|------|
| V2 plan and settled decisions | [qbazaar-contracts/V2-PLAN.md](qbazaar-contracts/V2-PLAN.md) |
| What's shipped, current phase, open questions | [qbazaar-contracts/ROADMAP.md](qbazaar-contracts/ROADMAP.md) |
| Task list by phase (IDs = GitHub issues) | [qbazaar-contracts/MILESTONES-V2.md](qbazaar-contracts/MILESTONES-V2.md) |
| Audit findings (Arabic) | [DOCS/AUDIT-2026-09-30.md](DOCS/AUDIT-2026-09-30.md) |
| API spec | [qbazaar-contracts/openapi/v1.yaml](qbazaar-contracts/openapi/v1.yaml) (Swagger UI at `/docs` on the API) |
| Run the API locally (Windows/Laragon) | [qbazaar-api/DEV-SETUP.md](qbazaar-api/DEV-SETUP.md) |
| Deploy | [deploy/README.md](deploy/README.md) |

## Quick start

Needs PHP 8.4 (8.2+ works for development), Composer, Node 20+, MySQL 8, Redis, and Meilisearch for search.

```bash
# API + admin
cd qbazaar-api
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed         # demo data; seeded admin admin@qbazaar.qa (password: ask the owner; change it on first login)
npm install && npm run build       # Tailwind assets for /admin
php artisan serve                  # http://localhost:8000  (/admin, /docs, /api/v1/health)

# Web (separate terminal)
cd qbazaar-web
npm install
cp .env.example .env.local         # points at the API on http://localhost:8000
npm run dev                        # http://localhost:3000
```

Queues, WebSocket, the scheduler and Meilisearch are covered in [DEV-SETUP.md](qbazaar-api/DEV-SETUP.md). The web can also run against the Prism mock (`cd qbazaar-contracts && npm install && npm run mock`, port 4010).

## Tests and CI

```bash
cd qbazaar-api
php vendor/bin/pest                        # full suite, no external services needed
php vendor/bin/pest --group=meilisearch    # the search tests; needs a running Meilisearch
vendor/bin/pint --test                     # code style
vendor/bin/phpstan analyse                 # static analysis, level 8

cd qbazaar-web
npm run typecheck && npm test              # tsc + Vitest
```

CI (`.github/workflows/ci.yml`) runs Pint, PHPStan and Pest for the API on every push and pull request to `main` and `develop`. `progress.yml` fails when the progress block is stale. `web-ci.yml` runs the web typecheck, Vitest and `next build` on pull requests and on pushes to `main` and `develop` that touch `qbazaar-web/`. `contract.yml` lints `qbazaar-contracts/openapi/v1.yaml` with Redocly and boots the Prism mock when the spec changes.

## Production and deploy

- **Branches:** `main` is development; `production` is live.
- **Promotion:** merge `main` into `production`. The push triggers `deploy-api.yml` (runs CI first) and `deploy-web.yml` (builds on the server).
- **Server stack:** PHP 8.4, MariaDB, Redis, Meilisearch, Horizon, Reverb, Next.js under systemd, Apache behind Cloudflare.

Scripts, units, runbooks and checklists are in [deploy/README.md](deploy/README.md).

## Admin settings

`/admin/settings` is split into tabs (general, commission, contact, integrations and more). Integration keys (Resend, Twilio, Google, Apple, FCM, Turnstile and the payment gateway) are entered on the Integrations tab. They are stored encrypted in the database, only a super admin can see or change them, and they are never committed to the repo or put in `.env.example`.

## Design rules

- Colours are the design's exact values and are never darkened.
- Fonts: Poppins (Latin), Noto Kufi Arabic (Arabic), Montserrat (small labels), Acme (wordmark). Cairo is not used.
- The pixel reference is [`Qbazzar/Qbazaar-front`](https://github.com/Qbazzar/Qbazaar-front).

## Demo accounts

The demo data (listings, chats, offers, reports) is built by the demo seeder. Every account below is on `@demo.qbazaar.qa`.

| Role | Email | What it can do |
|------|-------|----------------|
| Super admin | `super-admin@demo.qbazaar.qa` | Full admin panel: users (ban, delete, impersonate), roles, ads, categories, locations, CMS, settings, finance |
| Moderator | `moderator@demo.qbazaar.qa` | Ad moderation (approve, reject, suspend, feature), reports, user bans, moderation rules, support replies; no user deletion, impersonation, CMS or finance |
| Support | `support@demo.qbazaar.qa` | Support tickets and broadcasts; read-only on users, ads and reports |
| Buyer | `buyer@demo.qbazaar.qa` | Private member (Arabic): browses, buys, chats and makes offers |
| Seller | `seller@demo.qbazaar.qa` | Business member with a company profile: posts listings and handles offers |
| Members | `member01@demo.qbazaar.qa`, `member02@...` | More private and business members with mixed languages and verification states |

Password: ask the owner. All demo accounts share one password, and it is never written in this repo. `php artisan qbazaar:demo --fresh --force` generates it randomly on production and prints it once. Staff accounts sign in at the admin panel, members at the web client.

`qbazaar:demo --fresh` wipes and rebuilds the demo data.

## License

Proprietary — Ahmed Jaber.
