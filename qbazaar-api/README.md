# QBazaar API

> Backend API for **QBazaar** — Qatar's friendly classifieds marketplace.

[![Status](https://img.shields.io/badge/status-MVP%20feature--complete-success)](../qbazaar-contracts/ROADMAP.md)
[![Phase](https://img.shields.io/badge/phase-launch%20prep-yellow)](../qbazaar-contracts/ROADMAP.md)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-red)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4)](https://www.php.net)

---

## 📊 Status

**MVP feature-complete — now in launch prep.** All 13 MVP sprints have landed. The API surface is live across every core domain:

- **Auth** — register/login/logout, refresh tokens, OTP, password reset, email verification
- **Accounts** — profile, sessions, privacy, data export, public profiles
- **Ads + media + auto-moderation** — listing CRUD, image uploads, automated moderation pipeline
- **Search** — Meilisearch via Laravel Scout (facets, geo, saved searches)
- **Favorites & recents**
- **Messaging** — real-time conversations over Laravel Reverb
- **Offers**
- **Notifications & reports**
- **Filament v4 admin** — moderation, user management, CMS
- **CMS / help / support** — content pages, help center, support tickets

Quality bar: **~270 Pest tests**, **PHPStan level 8** (Larastan), **Laravel Pint** code style — all green.

See [ROADMAP.md](../qbazaar-contracts/ROADMAP.md) and [MILESTONES.md](../qbazaar-contracts/MILESTONES.md) for the full per-sprint detail.

---

## 🏗️ Stack

- **Framework:** Laravel 12 (PHP 8.3+) — Laravel 13 not yet released
- **Database:** MySQL 8 (Laragon)
- **Cache / Queue / Sessions:** Redis via Laragon's bundled redis-server (with Predis client)
- **Search:** Meilisearch 1.44 (via Laravel Scout)
- **Real-time:** Laravel Reverb (WebSocket) — powers messaging
- **Admin Panel:** Filament v4
- **Auth:** Laravel Sanctum + custom refresh token layer
- **Tracing/Profiling:** Telescope (dev) + Pulse (everywhere)
- **API Docs:** Scribe at `/docs`
- **Testing:** Pest 3
- **Style/Static analysis:** Laravel Pint + PHPStan level 8 (Larastan)

---

## 🚀 Local Setup (Laragon — Windows)

See [DEV-SETUP.md](DEV-SETUP.md) for the full Windows guide.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve         # API on http://localhost:8000

# In separate terminals as needed:
php artisan queue:work    # Process jobs (Horizon dashboard works, supervisor needs Linux)
php artisan reverb:start  # WebSocket server on :8080
```

Required local services:
- MySQL 8 (via Laragon) — DB name: `qbazaar` ✅
- Redis (via Laragon's bundled redis-server) — `127.0.0.1:6379` ✅
- Meilisearch — `http://127.0.0.1:7700` ✅ — run `c:\meilisearch\start.bat`

---

## ✅ Quality Gates

```bash
./vendor/bin/pint                    # Code style (Laravel preset + strict types)
./vendor/bin/phpstan analyse         # Static analysis level 8 — currently 0 errors
./vendor/bin/pest                    # Test suite (Pest 3)
php artisan scribe:generate          # API docs at /docs
```

CI runs all three on every push (see the repo-root `.github/workflows/ci.yml` — a monorepo workflow with `api` and `web` jobs).

---

## 📚 Documentation

The full project plan, roadmap, and per-sprint task breakdown lives in the **`qbazaar-contracts`** repository:

- [PLAN.md](../qbazaar-contracts/PLAN.md) — architectural decisions, design system, execution protocol
- [ROADMAP.md](../qbazaar-contracts/ROADMAP.md) — live status, sprint retros, decisions log
- [MILESTONES.md](../qbazaar-contracts/MILESTONES.md) — every user story, flow and task ID

---

## 🔗 Related Repositories

- **[`qbazaar-web`](../qbazaar-web)** — Next.js 16 web client
- **[`qbazaar-contracts`](../qbazaar-contracts)** — OpenAPI spec, error catalogue, WebSocket events spec, planning docs
