# QBazaar

> Qatar's friendly classifieds marketplace — monorepo containing the Laravel API, the Next.js web client, the OpenAPI/contract spec, and the original design + planning docs.

[![Sprint](https://img.shields.io/badge/sprint-0-blue)](qbazaar-contracts/ROADMAP.md)
[![Status](https://img.shields.io/badge/status-Day%205%20done%20·%20Day%206%20in%20progress-yellow)](qbazaar-contracts/ROADMAP.md)
[![Laravel](https://img.shields.io/badge/API-Laravel%2012-red)](qbazaar-api/README.md)
[![Next.js](https://img.shields.io/badge/Web-Next.js%2015-black)](qbazaar-web/README.md)

---

## 🗂️ Layout

```
QB/
├── qbazaar-api/         # Laravel 12 backend  (PHP 8.4 · MySQL 8 · Redis · Meilisearch · Reverb · Filament v4)
├── qbazaar-web/         # Next.js 15 frontend (TypeScript · Tailwind 4 · shadcn/ui · TanStack Query)
├── qbazaar-contracts/   # OpenAPI 3 spec, error catalogue, WebSocket events, ROADMAP, MILESTONES, PLAN
└── DOCS/                # Original architecture + backend plan + Bazzar React mockup + brand assets (reference, frozen)
```

## 📍 Where to look first

| Need | File |
|------|------|
| **Current progress, blockers, decisions log** | [qbazaar-contracts/ROADMAP.md](qbazaar-contracts/ROADMAP.md) |
| **Per-sprint user stories + tasks** | [qbazaar-contracts/MILESTONES.md](qbazaar-contracts/MILESTONES.md) |
| **Architectural plan + design system** | [qbazaar-contracts/PLAN.md](qbazaar-contracts/PLAN.md) |
| **API spec** | [qbazaar-contracts/openapi/v1.yaml](qbazaar-contracts/openapi/v1.yaml) |
| **How to run the API locally** | [qbazaar-api/DEV-SETUP.md](qbazaar-api/DEV-SETUP.md) |
| **Design tokens / mockup pages** | [DOCS/bazzar/](DOCS/bazzar/) |

## 🚀 Quick start (after `git clone`)

```bash
# Backend
cd qbazaar-api
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan serve            # http://localhost:8000

# Contract mock (separate terminal)
cd qbazaar-contracts
npm install
npm run mock                 # http://localhost:4010

# Frontend (separate terminal)
cd qbazaar-web
npm install
npm run dev                  # http://localhost:3000
```

See [qbazaar-api/DEV-SETUP.md](qbazaar-api/DEV-SETUP.md) for Memurai + Meilisearch setup on Windows.

## 📋 Sprint 0 progress

| Day | What | Where it landed |
|-----|------|-----------------|
| 1 | Repo init + planning docs imported | all three subdirs |
| 2 | Laravel 12 scaffolded + 20+ packages | `qbazaar-api/` |
| 3 | MySQL DB + Redis + Meilisearch + .env | `qbazaar-api/` |
| 4 | Config, enums, ErrorCode, middleware, routes, exception handler, /health endpoint | `qbazaar-api/` |
| 5 | Pint, PHPStan level 8, Pest, Scribe, Sentry, GitHub Actions CI | `qbazaar-api/` + `qbazaar-contracts/` |
| 6 | Next.js skeleton + Bazzar design system | `qbazaar-web/` (in progress) |
| 7 | Prism mock + Sprint 1 issues + first push | `qbazaar-contracts/` |

## 📝 License

Proprietary — Ahmed Jaber.
