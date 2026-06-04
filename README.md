# QBazaar

> Qatar's friendly classifieds marketplace — monorepo containing the Laravel API, the Next.js web client, the OpenAPI/contract spec, and the original design + planning docs.

[![Status](https://img.shields.io/badge/status-MVP%20feature--complete-success)](qbazaar-contracts/ROADMAP.md)
[![Phase](https://img.shields.io/badge/phase-launch%20prep-yellow)](qbazaar-contracts/ROADMAP.md)
[![Laravel](https://img.shields.io/badge/API-Laravel%2012-red)](qbazaar-api/README.md)
[![Next.js](https://img.shields.io/badge/Web-Next.js%2016-black)](qbazaar-web/README.md)

---

## 🗂️ Layout

```
QB/
├── qbazaar-api/         # Laravel 12 backend  (PHP 8.4 · MySQL 8 · Redis · Meilisearch · Reverb · Filament v4)
├── qbazaar-web/         # Next.js 16 frontend (TypeScript · Tailwind 4 · shadcn/ui · TanStack Query · AR/EN i18n)
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

## 📋 Status

**MVP feature-complete.** All 13 sprints landed — auth, accounts, categories &
locations, ads + media + auto-moderation, Meilisearch search, favorites &
recently-viewed, Reverb messaging, offers, notifications & reports, the Filament
v4 admin panel, and CMS / help center / support tickets — on top of the QBFront
design system, AR/EN cookie-based i18n, and a Milestone-6 SEO/PWA pass
(sitemap, robots, OpenGraph, JSON-LD, manifest, error boundaries, analytics).

Now in **launch prep**: DNS + SSL for the production domain and the production
secrets (Twilio, mail, Sentry, Reverb) are the remaining blockers.

| Need | File |
|------|------|
| **Live progress log + decisions** | [ROADMAP.md](qbazaar-contracts/ROADMAP.md) |
| **Per-task status (Milestones 1–7)** | [MILESTONES.md](qbazaar-contracts/MILESTONES.md) |

## 📝 License

Proprietary — Ahmed Jaber.
