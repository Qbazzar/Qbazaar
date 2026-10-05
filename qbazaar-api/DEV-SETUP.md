# QBazaar API — Dev Setup on Windows (Laragon)

Local services the API uses:

| Service | How | Check |
|---------|-----|-------|
| **MySQL 8** | Bundled with Laragon ("Start All"). Create a `qbazaar` database. | `php artisan migrate:status` |
| **Redis** | Laragon's bundled `redis-server` is enough; Memurai or WSL2 Redis also work (see below). | `php artisan tinker --execute="echo \Illuminate\Support\Facades\Redis::ping();"` prints `1`/`+PONG` |
| **Meilisearch** | A local binary, e.g. `c:\meilisearch\meilisearch.exe` (section 2). Needed for search in the app, not for the default test suite. | http://localhost:7700/health returns `{"status":"available"}` |
| **Mail** | `MAIL_MAILER=log` in `.env.example`: mails and OTP codes go to `storage/logs/laravel.log`. | — |

PHP 8.4 matches CI and production; the Laravel app itself accepts 8.2+.

---

## 1. Redis alternatives

Laragon ships `redis-server`, and `.env.example` uses the Predis client, so no PHP extension is needed. If you'd rather not use Laragon's Redis:

- **Memurai** (Redis-compatible Windows service): install the Developer Edition from https://www.memurai.com/get-memurai and check `Get-Service Memurai`.
- **WSL2:** `sudo apt install -y redis-server && sudo service redis-server start`. `REDIS_HOST=127.0.0.1` still works because WSL2 forwards localhost.

---

## 2. Meilisearch

```bash
c:\meilisearch\meilisearch.exe --http-addr 127.0.0.1:7700
```

`MEILISEARCH_KEY` stays empty in development; production uses a master key (see `deploy/README.md`). After the first migration, build the index:

```bash
php artisan scout:sync-index-settings
php artisan scout:import "App\Models\Ad"
```

Indexing is queued (`SCOUT_QUEUE=true`), so a queue worker must be running for new or changed ads to reach the index.

---

## 3. Run the API

```bash
cd c:\laragon\www\QB\qbazaar-api
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed           # admin@qbazaar.qa / password; the panel forces a new password on first login
npm install && npm run build         # Tailwind assets for /admin and the welcome page
php artisan serve                    # http://localhost:8000

# In separate terminals as needed:
php artisan queue:work --queue=default,low   # jobs: images, expiry, exports, notifications, Scout
php artisan reverb:start                     # WebSocket server on :8080
php artisan schedule:work                    # scheduled jobs (ad and offer expiry, …)
php artisan pail                             # tail the logs
```

Horizon is what runs the queues in production, but it needs the `pcntl`/`posix` extensions, so on Windows use `queue:work` as above.

### Demo data

`migrate --seed` only loads reference data (categories, Qatar locations, roles, CMS pages, help). To click through a realistic marketplace, generate the demo on top:

```bash
php artisan qbazaar:demo --fresh                 # drop all tables, migrate, reference data, then the demo
php artisan qbazaar:demo --fresh --users=15 --ads=60   # a smaller, faster demo
php artisan queue:work --queue=media,default     # until the photo conversions are done
```

| Option | Default | |
|---|---|---|
| `--fresh` | off | Rebuilds the schema first. Without it the command refuses if demo accounts already exist. |
| `--users` | 60 | Marketplace members (4–2000); one staff account per role comes on top. |
| `--ads` | 400 | Listings (10–20000). |
| `--sync-media` | off | Runs the image conversions inline instead of queueing them (much slower). |
| `--force` | off | Needed in production, together with typing the database name back. |

What it builds, all through the production actions and services so every invariant and ledger posting is real:

- staff for every role and members (private and business with profiles and covers, Arabic and English names, `+974` numbers, verified and unverified, a few suspended);
- listings in every status across the main categories, with QAR prices and custom fields that fit the category, coordinates inside their Qatar district, and 1–8 photos drawn locally with GD (no network) through Media Library;
- follows, favourites, recently viewed, saved searches, reviews, chats with text and photos, blocked pairs;
- offers in every state (including countered), "Buy now" requests in every state, orders in every state with checkout (delivery and pickup), cancellations and disputes (open and ruled), commission booked through `LedgerRecipes`;
- commission settlements (pending, approved, rejected, wallet netting), bank accounts, withdrawals (pending, paid, rejected, one netting the seller's debt), paid promotions (active, expired, pending bank transfer, rejected);
- reports, support tickets with replies, notifications and the admin audit trail.

It ends with the ledger reconciliation and fails loudly if anything does not balance, then imports the ads into Meilisearch when `SCOUT_DRIVER=meilisearch` (a stopped server only costs a warning). Every demo account lives on `@demo.qbazaar.qa` and the summary prints the logins: `super-admin@`, `moderator@`, `support@`, `buyer@`, `seller@` and `member01@…`, all with the password `qbazaar-demo`. Runs are seeded, so the same counts give the same data.

In production the command refuses unless `--force` is given and the database name is typed back; the seeder itself refuses there when run through `db:seed`, and the accounts get a random password that is printed once.

Escrow orders do not exist yet (cash is the only payment method until a gateway lands in M7), so the escrow release sweep runs but finds nothing to release.

---

## 4. Useful URLs (local)

| URL | What |
|-----|------|
| `http://localhost:8000/api/v1/health` | Health check (JSON envelope) |
| `http://localhost:8000/admin` | Admin panel (staff login) |
| `http://localhost:8000/docs` | Swagger UI for `qbazaar-contracts/openapi/v1.yaml` (also `/swagger`) |
| `http://localhost:8000/horizon` | Horizon dashboard (needs Horizon running, so Linux/WSL) |
| `http://localhost:8000/telescope` | Telescope (local only) |
| `http://localhost:8000/pulse` | Pulse dashboard |
| `http://localhost:7700` | Meilisearch |
| `http://localhost:4010` | Prism mock of the spec (`cd qbazaar-contracts && npm run mock`) |

---

## 5. Quality gates (run before pushing)

```bash
vendor/bin/pint --test          # code style (drop --test to fix)
vendor/bin/phpstan analyse      # static analysis (level 8)
php vendor/bin/pest             # test suite
```

The default suite is hermetic: `phpunit.xml` uses SQLite in memory and the in-process Scout
`collection` driver, and it excludes the `meilisearch` group, so no MySQL, Redis or search
server is needed. The tests that exercise the real Meilisearch index live in that group;
start Meilisearch (section 2) and run them explicitly:

```bash
php vendor/bin/pest --group=meilisearch
```

The `mysql` group proves the ledger and order row locks on a real InnoDB (SQLite has no
row locks). Point it at an empty MySQL 8 database; it migrates and truncates it:

```bash
DB_CONNECTION=mysql DB_DATABASE=qbazaar_test php vendor/bin/pest --group=mysql
```

CI (`.github/workflows/ci.yml` at the repository root) runs all three on every
push and pull request to `main`/`develop`, plus the `mysql` group against a MySQL 8.4 service. The API deploy workflow runs the same
CI first and only deploys when it passes.
