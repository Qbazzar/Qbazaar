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

CI (`.github/workflows/ci.yml` at the repository root) runs all three on every
push and pull request to `main`/`develop`. The API deploy workflow runs the same
CI first and only deploys when it passes.
