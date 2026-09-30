# QBazaar

> A classifieds marketplace for Qatar (QAR, Arabic + English). This monorepo holds the Laravel API and admin panel, the Next.js web client, the API contract and planning docs, and the deploy files.

**Status (2026-09-30):** the MVP is live; V2 is under way. M0 (audit fixes, CI) is merged, M1 (backend gaps) is next. See [ROADMAP.md](qbazaar-contracts/ROADMAP.md).

## Layout

```
QB/
├── qbazaar-api/         Laravel 12 API (/api/v1) + custom Blade admin panel (/admin)
│                        MySQL · Redis · Meilisearch (Scout) · Reverb · Horizon · Sanctum · Spatie
├── qbazaar-web/         Next.js 16 web client (React 19 · TypeScript · Tailwind 4 · TanStack Query · AR/EN)
├── qbazaar-contracts/   OpenAPI spec, error codes, WebSocket events, V2 plan, roadmap, milestones
├── deploy/              Production deploy: scripts, systemd units, Apache includes, env templates, runbooks
├── DOCS/                PRD (PDF), the 2026-09-30 audit, the 2026-06-21 QA report, original (pre-build) plans, mockup assets
└── .github/workflows/   ci.yml (API quality gates), deploy-api.yml, deploy-web.yml
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
php artisan migrate --seed         # demo data; admin@qbazaar.qa / password (you must change it on first login)
npm install && npm run build       # Tailwind assets for /admin
php artisan serve                  # http://localhost:8000  (/admin, /docs, /api/v1/health)

# Web (separate terminal)
cd qbazaar-web
npm install
cp .env.example .env.local         # set NEXT_PUBLIC_API_URL=http://localhost:8000
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

CI (`.github/workflows/ci.yml`) runs Pint, PHPStan and Pest for the API on every push and pull request to `main` and `develop`. The web has no CI job yet.

## Deploy

`main` is the development branch. Pushing to the `production` branch deploys over SSH: `deploy-api.yml` runs CI first, `deploy-web.yml` builds on the server. Production today is a cPanel server (`qbazaar.fleeteye.de`, `api.qbazaar.fleeteye.de`); M5 moves it to a new VPS behind Cloudflare. Details in [deploy/README.md](deploy/README.md).

## License

Proprietary — Ahmed Jaber.
