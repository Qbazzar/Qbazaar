# QBazaar API

> Backend API for **QBazaar** — Qatar's friendly classifieds marketplace.

[![Sprint](https://img.shields.io/badge/sprint-0-blue)](../qbazaar-contracts/ROADMAP.md)
[![Status](https://img.shields.io/badge/status-Day%205%20in%20progress-yellow)](../qbazaar-contracts/ROADMAP.md)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-red)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4)](https://www.php.net)

---

## 📊 Current Progress

**Sprint:** 0 — Infrastructure & Foundation
**Days completed:** Day 1 ✅ · Day 2 ✅ · Day 3 ✅ · Day 4 ✅ · **Day 5 (in progress)**

### What's already done

| Sprint Day | Done | Task IDs |
|------------|------|----------|
| **Day 1** | ✅ | `BE-0.1` repo init + README + .gitignore |
| **Day 2** | ✅ | `BE-0.2` → `BE-0.9` — Laravel 12 + 20+ packages + Filament/Telescope/Horizon/Pest/Scribe scaffolded |
| **Day 3** | ✅ | `BE-0.10` MySQL DB · `BE-0.11` Redis + Predis · `BE-0.12` Meilisearch binary · `BE-0.13`+`BE-0.14` .env files |
| **Day 4** | ✅ | `BE-0.15` config/qbazaar.php · `BE-0.16` 9 enums · `BE-0.17` ErrorCode (48 codes) · `BE-0.18` 3 middleware · `BE-0.19`+`BE-0.20`+`BE-0.21` routes + exception handler + health endpoint |
| **Day 5** | 🟡 | `BE-0.22` Pint ✅ · `BE-0.23` PHPStan level 8 ✅ · `BE-0.24` Pest health test ✅ · `BE-0.25` Scribe ⏳ · `BE-0.27` Sentry ⏳ · `BE-0.28` GitHub Actions CI ⏳ |
| **Day 6** | ⚪ | Next.js skeleton + Bazzar design system (lives in `qbazaar-web`) |
| **Day 7** | ⚪ | Prism mock + Sprint 1 planning (lives in `qbazaar-contracts`) |

### Endpoints live

| Method | Path | Status |
|--------|------|--------|
| `GET`  | `/up` | ✅ Laravel health check |
| `GET`  | `/api/v1/health` | ✅ Returns `{ success: true, data: { status, version, timestamp } }` |

### What's next (Sprint 1 — Auth)

`auth/register`, `auth/login`, `auth/logout`, `auth/refresh`, `auth/send-otp`, `auth/verify-otp`, `auth/forgot-password`, `auth/reset-password`, `auth/send-email-verification`. See [MILESTONES.md](../qbazaar-contracts/MILESTONES.md#sprint-1--auth-3-أيام).

---

## 🏗️ Stack

- **Framework:** Laravel 12 (PHP 8.3+) — Laravel 13 not yet released
- **Database:** MySQL 8 (Laragon)
- **Cache / Queue / Sessions:** Redis via Laragon's bundled redis-server (with Predis client)
- **Search:** Meilisearch 1.44 (via Laravel Scout)
- **Real-time:** Laravel Reverb (WebSocket) — wired Day 2, used Sprint 8
- **Admin Panel:** Filament v4
- **Auth:** Laravel Sanctum + custom refresh token layer (Sprint 1)
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

Required local services (already configured if you ran Sprint 0):
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

CI runs all three on every push (see `.github/workflows/ci.yml` — added Day 5).

---

## 📚 Documentation

The full project plan, roadmap, and per-sprint task breakdown lives in the **`qbazaar-contracts`** repository:

- [PLAN.md](../qbazaar-contracts/PLAN.md) — architectural decisions, design system, execution protocol
- [ROADMAP.md](../qbazaar-contracts/ROADMAP.md) — live status, sprint retros, decisions log
- [MILESTONES.md](../qbazaar-contracts/MILESTONES.md) — every user story, flow and task ID

---

## 🔗 Related Repositories

- **[`qbazaar-web`](../qbazaar-web)** — Next.js 15 frontend (Sprint 0 — Day 1 done, Day 6 pending)
- **[`qbazaar-contracts`](../qbazaar-contracts)** — OpenAPI spec, error catalogue, WebSocket events spec, planning docs (Sprint 0 — Day 1 done, Day 7 pending)
