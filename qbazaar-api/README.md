# QBazaar API

> The Laravel backend for QBazaar: the REST API under `/api/v1`, the admin panel under `/admin`, queues, search and realtime.

**Status (2026-09-30):** the MVP API is live and the M0 audit fixes are merged. V2 work (passwordless login, orders and wallet, follows, companies, R2, Turnstile…) starts in M1. What exists today, and what doesn't, is listed in [ROADMAP.md](../qbazaar-contracts/ROADMAP.md#what-is-shipped-today).

## What's in it

- **Auth:** register and login with a password, Sanctum access tokens (15 min) + refresh tokens bound to their session, phone OTP by SMS (Twilio), password reset, email verification.
- **Accounts:** profile, sessions, privacy, blocking, device tokens, deactivation, deletion with a 30-day grace period, data export, avatar.
- **Ads:** drafts, publish (every ad waits for admin approval), mark sold, renew, up to 10 images per ad (Spatie Media Library, BlurHash, 24h signed links to originals), auto-moderation hints (banned words, phone numbers, links, duplicate images by pHash).
- **Search:** Meilisearch through Laravel Scout (facets, suggestions, saved searches with alerts). No distance search yet.
- **Engagement:** favorites, recently viewed, chat and offers over Reverb, database notifications broadcast live, FCM push for some notification types, reports, basic reviews.
- **Content:** CMS pages, help center, support tickets.
- **Admin (`/admin`):** a custom Blade + Tailwind panel (not Filament), session login for staff, a Spatie permission on every route. Covers ads and moderation, users and roles, reports, moderation rules, categories, locations, pages, help, support, offers, conversations, saved searches, notifications and the activity log.

## Stack

- Laravel 12 (`composer.json` allows PHP 8.2+; CI and production run **PHP 8.4**)
- MySQL 8 · Redis (Predis client) for cache, sessions and queues
- Meilisearch through Laravel Scout (queued, after commit)
- Laravel Reverb (WebSocket) · Laravel Horizon (queues `default` and `low`)
- Sanctum + a custom refresh-token layer · Spatie Permission, Media Library, Activitylog, Query Builder, Data, Translatable
- Twilio (SMS) · FCM (`laravel-notification-channels/fcm`) · Sentry · Pulse · Telescope (local only)
- API docs: Swagger UI at `/docs` (and `/swagger`), rendering `qbazaar-contracts/openapi/v1.yaml` (served at `GET /api/v1/openapi.yaml`)
- Tests: Pest 3 · style: Laravel Pint · static analysis: PHPStan level 8 (Larastan)

## Local setup

The full Windows/Laragon guide is [DEV-SETUP.md](DEV-SETUP.md).

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed    # reference + demo data; admin@qbazaar.qa / password (forced change on first login)
npm install && npm run build  # Tailwind assets for /admin
php artisan serve             # http://localhost:8000

# In separate terminals as needed:
php artisan queue:work --queue=default,low   # jobs (Horizon itself needs Linux: pcntl/posix)
php artisan reverb:start                     # WebSocket on :8080
php artisan schedule:work                    # ad/offer expiry and other scheduled jobs
```

Search needs a running Meilisearch (`SCOUT_DRIVER=meilisearch`, the default in `.env.example`).

## Quality gates

```bash
vendor/bin/pint --test                     # code style (drop --test to fix)
vendor/bin/phpstan analyse                 # level 8
php vendor/bin/pest                        # full suite
php vendor/bin/pest --group=meilisearch    # search tests against a live Meilisearch
```

The default suite is hermetic: `phpunit.xml` uses SQLite in memory, the Scout `collection` driver, sync queues and array mail, and excludes the `meilisearch` group. CI runs Pint, PHPStan and Pest from the repo-root workflow `.github/workflows/ci.yml` on every push and pull request to `main`/`develop`; the API deploy runs the same workflow first.

## Documentation

- [V2-PLAN.md](../qbazaar-contracts/V2-PLAN.md), [ROADMAP.md](../qbazaar-contracts/ROADMAP.md), [MILESTONES-V2.md](../qbazaar-contracts/MILESTONES-V2.md): plan, state and tasks
- [openapi/v1.yaml](../qbazaar-contracts/openapi/v1.yaml), [error-codes.md](../qbazaar-contracts/error-codes.md), [events/](../qbazaar-contracts/events/): the contract
- [DOCS/AUDIT-2026-09-30.md](../DOCS/AUDIT-2026-09-30.md): the audit, with rules for new code (section 8)
- [deploy/README.md](../deploy/README.md): production
