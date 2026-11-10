# QBazaar — Production Deploy

> **Production today (since 2026-10-06):** the Hostinger VPS `srv1977263.hstgr.cloud` in Mumbai (`187.126.119.228`, AlmaLinux 9), cPanel user `qbazaar`, repo clone at `/home/qbazaar/qbazaar`.
> Web: `https://qbazaar.qa` (`www.` redirects to it) · API + admin: `https://api.qbazaar.qa` · WebSocket: `wss://api.qbazaar.qa/app`. DNS and the proxy are on Cloudflare; the origin has AutoSSL certificates.
> The Kuala Lumpur box (`187.53.139.138`, sslip.io names) and the old `fleeteye` server are retired. The rest of the Cloudflare work (firewall to Cloudflare only, WAF, `cdn.`) is `OPS-18.9` in [MILESTONES-V2.md](../qbazaar-contracts/MILESTONES-V2.md).

First-time setup of this server is in [NEW-SERVER-RUNBOOK.md](NEW-SERVER-RUNBOOK.md). [CPANEL-FLEETEYE-RUNBOOK.md](CPANEL-FLEETEYE-RUNBOOK.md) (Arabic) is the older runbook for the retired server. Promoting `main` to `production`: follow [DEPLOY-CHECKLIST.md](DEPLOY-CHECKLIST.md). This file covers the day-to-day flow.

## Branches and workflows

| Branch | Purpose |
|--------|---------|
| `main` | Development. CI (`.github/workflows/ci.yml`: Pint + PHPStan + Pest for the API) runs on every push and PR. |
| `production` | What is live. A push here deploys. |

Promote with `git switch production && git merge --ff-only main && git push origin production`, or run a deploy workflow by hand (`workflow_dispatch`).

| Workflow | Runs when | Does |
|----------|-----------|------|
| `deploy-api.yml` | Push to `production` touching `qbazaar-api/**`, `deploy/**` or the workflow | Runs `ci.yml` first; if it passes, SSH → `deploy/scripts/deploy-api.sh` |
| `deploy-web.yml` | Push to `production` touching `qbazaar-web/**`, `deploy/**` or the workflow | SSH → `deploy/scripts/deploy-web.sh` (no CI gate: `web-ci.yml` checks pull requests and `main`, not the deploy) |

Changes that only touch `qbazaar-contracts/`, `DOCS/` or the READMEs don't deploy.

**Repository secrets** (`Settings → Secrets and variables → Actions`): `DEPLOY_HOST`, `DEPLOY_USER` (`qbazaar`), `DEPLOY_PORT`, `DEPLOY_SSH_KEY` (private half of `keys/github-actions.pub`). Key auth only; there is no password fallback.

## Server settings file

Hosts and paths live in one non-secret file on the server, `~qbazaar/.qbazaar-deploy.env`:

```bash
API_HOST=api.qbazaar.qa
WEB_HOST=qbazaar.qa
WEB_ALIASES=www.qbazaar.qa
URL_SCHEME=https
REPO_DIR=/home/qbazaar/qbazaar
PHP_BIN_DIR=/opt/cpanel/ea-php84/root/usr/bin
```

The deploy scripts read it (an explicit environment variable wins, e.g. `HEALTH_HOST=… bash deploy/scripts/deploy-api.sh`), and the defaults in `common.sh` match it. `HEALTH_HOST` defaults to `API_HOST`. `WEB_ALIASES` (space separated) lists extra web hosts allowed by CORS. `FPM_SERVICE` (default `ea-php84-php-fpm`) is the PHP-FPM unit the API deploy reloads. `REDIS_CACHE_PORT` (e.g. `6380`), once the cache Redis exists, makes `render-env.sh` point Laravel's cache at it ([Redis roles](#redis-roles)). `scripts/render-env.sh`, run as root, builds both `.env` files from the templates, this file and the secrets in `/root/qbazaar-secrets/` ([runbook §5](NEW-SERVER-RUNBOOK.md#5-environment-files-root)).

## Changing the domain

1. DNS (Cloudflare): `A` records for the web host, `www` and `api` → the server, proxied. Keep "Always Use HTTPS" off until step 2 has a certificate.
2. WHM: add the new domain to the `qbazaar` account (or `whmapi1 modifyacct user=qbazaar DNS=<domain>`), then add the `api` subdomain with docroot `qbazaar/qbazaar-api/public`. Run AutoSSL (`/usr/local/cpanel/bin/autossl_check --user=qbazaar`).
3. Edit `API_HOST`, `WEB_HOST` and `WEB_ALIASES` in `~qbazaar/.qbazaar-deploy.env`.
4. As root: `bash ~qbazaar/qbazaar/deploy/scripts/render-env.sh` (rewrites `APP_URL`, `WEB_URL`, `CORS_ALLOWED_ORIGINS`, mail sender and the web `NEXT_PUBLIC_*` hosts).
5. Install the Apache includes under the new host directories (commands in each file's header), `ensure_vhost_includes`, `configtest`, restart httpd.
6. Redeploy both, one after the other (`gh workflow run deploy-api.yml --ref production`, then web): the web bundle inlines the hosts at build time.
7. Cloudflare: SSL/TLS Full (strict), then Always Use HTTPS ([runbook §9](NEW-SERVER-RUNBOOK.md#9-cloudflare-dashboard)). Keep `TRUSTED_PROXIES` empty.

## What the deploy scripts do

**`deploy-api.sh`:** reset the clone to `origin/production` → maintenance mode → `composer install --no-dev` → `npm install && npm run build` (Tailwind assets for `/admin`) → `migrate --force` → rebuild config/route/view/event caches → reload PHP-FPM (`FPM_SERVICE`, default `ea-php84-php-fpm`; see [PHP-FPM and OPcache](#php-fpm-and-opcache)) → `storage:link` if missing → restart `qbazaar-horizon` and `qbazaar-reverb` → leave maintenance mode → health probe on `/api/v1/health` (fails the deploy if not 200).

**`deploy-web.sh`:** reset to `origin/production` → `npm ci` → `next build` → restart `qbazaar-web` → probe `http://127.0.0.1:3000/`.

Both scripts source `scripts/common.sh`, which reads the server settings file (below) and puts the `ea-php84` CLI and `/usr/local/bin` (Composer, npm 11) first on `PATH`. The `qbazaar` user may restart only `qbazaar-web`, `qbazaar-horizon` and `qbazaar-reverb`, and reload `ea-php84-php-fpm`, through the sudoers drop-in [`sudoers/qbazaar-deploy`](sudoers/qbazaar-deploy).

## Services on the server

| Unit (`deploy/systemd/`) | Runs |
|--------------------------|------|
| `qbazaar-horizon.service` | `artisan horizon`, one supervisor per queue (see Queues below) |
| `qbazaar-reverb.service` | `artisan reverb:start` on `127.0.0.1:8080`, proxied as `wss://api…/app` |
| `qbazaar-scheduler.service` | `artisan schedule:work` (ad and offer expiry, etc.). Use it **or** the cron entry in the runbook, never both. |
| `qbazaar-web.service` | `next start -p 3000`, proxied by Apache |
| `meilisearch.service` | Meilisearch on `127.0.0.1:7700` (section below), at lower CPU and disk priority than the web stack |
| `redis-cache.service` | The cache Redis on `127.0.0.1:6380` ([Redis roles](#redis-roles)); the queue Redis is the distribution's `redis.service` on 6379 |

### Queues

Horizon runs one supervisor per queue (`config/horizon.php`), so a backlog on one queue never delays another:

| Queue | Carries | Connection | Workers (prod) | Timeout |
|-------|---------|------------|----------------|---------|
| `realtime` | WebSocket broadcasts, chat pushes | `redis` | 1–4 | 30 s |
| `notifications` | mail, push, SMS, inbox notifications and their audience fan-out | `redis` | 1–3 | 120 s |
| `default` | chat screening and other listeners | `redis` | 1–2 | 60 s |
| `search` | Scout index writes | `redis` | 1–2 | 60 s |
| `media` | image conversions, hashes, original downscale, ad auto-moderation | `redis-long` | 1–2 | 300 s |
| `low` | expiry sweeps, exports, account deletion, view-count sync | `redis-long` | 1 | 1800 s |

`redis-long` reads the same Redis lists as `redis` with a longer `retry_after` (`REDIS_LONG_QUEUE_RETRY_AFTER`, 1900 s), so a job is never started twice while it is still running. Both connections push only after the surrounding database transaction commits. Peak memory is about 14 workers × 128–512 MB; lower `maxProcesses` in `config/horizon.php` on a smaller box.

Adding a queue means adding a supervisor; `HorizonQueueCoverageTest` fails until every queue the app dispatches onto is consumed.

Apache includes for both vhosts are in `deploy/apache/` (install steps are in each file's header). The API include pins PHP for the vhost, serves `/storage` as static files only, and proxies the Reverb WebSocket.

## Environment files

- API: copy `env.production.template` to `qbazaar-api/.env` on the server and fill every `__FILL_ME__`. Search must stay on `SCOUT_DRIVER=meilisearch` with `SCOUT_QUEUE=true`.
- Web: copy `web.env.production.template` to `qbazaar-web/.env.production`. `NEXT_PUBLIC_*` values are compiled in, so a change needs a web deploy, not just a restart.

Both files are gitignored and survive the `git reset --hard` the scripts do.

## Deploying a release

The one-time steps that the M0, M1 and M1-performance PRs need on the server are in **[DEPLOY-CHECKLIST.md](DEPLOY-CHECKLIST.md)**, in the order an operator runs them: pre-flight, env diff, queue drain, deploy, post-deploy commands, server config, smoke tests and rollback. They replace the earlier "pending production steps" lists. Update that file whenever a PR adds a deploy step.

Clients authorise private channels at `POST /api/v1/broadcasting/auth` with a Bearer token (#149); the old session route `/broadcasting/auth` is gone. The web client already uses the new route.

## Auth limits, proxies and refresh tokens (BE-13.26, BE-13.32, BE-13.35)

- Rate limits are keyed by account, refresh token or inbox, with a looser per-IP ceiling (`qbazaar.auth.rate_limits`, `qbazaar.api`). Their client IP comes from `config/trustedproxy.php`, which trusts Cloudflare's published ranges by default; leave `TRUSTED_PROXIES` empty behind Cloudflare, or list your own proxy. Only `X-Forwarded-For` and `X-Forwarded-Proto` are read. Re-check the list against https://www.cloudflare.com/ips/ when moving to Cloudflare (OPS-18.9).
- Refresh tokens are now HMAC-SHA256 digests keyed by `APP_KEY`. Existing bcrypt rows still work and are replaced on their next refresh, so nothing has to be migrated. If `APP_KEY` is ever rotated, put the old key in `APP_PREVIOUS_KEYS` or every device is signed out.
- Password-reset and verification mails go through the `notifications` queue, so Horizon must be running for them to arrive.

## Data retention (scheduled pruning)

The scheduler keeps the fast-growing tables bounded. All jobs need the scheduler unit (or the cron entry) running; nothing extra goes on Horizon.

| Schedule name | Runs | Deletes |
|---|---|---|
| `auth.prune-access-tokens` | hourly | Sanctum access tokens more than 24 h past expiry |
| `auth.prune-refresh-tokens` | hourly | refresh tokens more than 24 h past expiry |
| `auth.prune` | daily 03:00 | spent OTP codes, idle trusted devices |
| `retention.prune-history` | daily 03:30 | guest recently-viewed rows older than 30 days, notifications read more than `READ_NOTIFICATIONS_RETENTION_DAYS` (90) days ago |
| `retention.clean-activity-log` | daily 03:45 | activity log older than `ACTIVITY_LOG_RETENTION_DAYS` (365), in batches of 1000 |
| `retention.prune-failed-jobs` | daily 04:00 | failed jobs older than 7 days |
| `auth.clear-password-resets` | every 15 min | expired password reset tokens |
| `horizon.snapshot` | every 5 min | nothing; records Horizon metrics |

First deploy: the migration adds the indexes these deletes use. The first runs clear the whole backlog, so on a large database run them once by hand off-peak before the scheduler does: `php artisan sanctum:prune-expired --hours=24`, `php artisan model:prune --model="App\Models\RefreshToken" --model="App\Models\RecentView" --model="App\Models\DatabaseNotification"`, `php artisan activitylog:clean --force`.

## Meilisearch (install once, as root)

```bash
# AlmaLinux 9 has glibc 2.34; Meilisearch 1.13+ binaries need 2.35, so install
# the last 1.12 release (the app needs >= 1.4). Re-check when the OS moves on.
curl -fsSL -o meilisearch https://github.com/meilisearch/meilisearch/releases/download/v1.12.8/meilisearch-linux-amd64
install -m 755 meilisearch /usr/local/bin/meilisearch
useradd -r -s /sbin/nologin meilisearch
mkdir -p /var/lib/meilisearch && chown meilisearch: /var/lib/meilisearch

# Loopback only, master key required (shared server)
MASTER_KEY=$(openssl rand -hex 32)
cat > /etc/meilisearch.toml <<TOML
env = "production"
master_key = "${MASTER_KEY}"
db_path = "/var/lib/meilisearch"
http_addr = "127.0.0.1:7700"
dump_dir = "/var/lib/meilisearch/dumps"
snapshot_dir = "/var/lib/meilisearch/snapshots"
TOML
chmod 600 /etc/meilisearch.toml
echo "MEILISEARCH_KEY=${MASTER_KEY}"   # goes into qbazaar-api/.env

cp /home/qbazaar/qbazaar/deploy/systemd/meilisearch.service /etc/systemd/system/
systemctl daemon-reload && systemctl enable --now meilisearch
curl -s http://127.0.0.1:7700/health   # {"status":"available"}
```

Then, as `qbazaar`:

```bash
cd ~/qbazaar/qbazaar-api
# .env: SCOUT_DRIVER=meilisearch, SCOUT_QUEUE=true, MEILISEARCH_HOST=http://127.0.0.1:7700, MEILISEARCH_KEY=<key>
php artisan config:cache
php artisan scout:sync-index-settings
php artisan scout:import "App\Models\Ad"
```

Check with a typo, which only Meilisearch tolerates: `curl -s 'https://api.qbazaar.qa/api/v1/search?q=iphnoe' | head -c 400`.

Run the same two commands after any release that changes the `ads` index settings in `config/scout.php` or the fields in `Ad::toSearchableArray()`. Each ad carries `category_path` and `location_path` (its ancestors), so also run `scout:import` after moving a category or location under a new parent in the admin; until then, filtering by the old parent still matches those ads.

Distance search reads `_geo` (the ad's pin, or its location's `lat`/`lng`), so also run `scout:import` after filling in coordinates for locations. View counters reach the index through the `search.sync-view-counts` scheduled job (every `qbazaar.search.views_sync_minutes`, on the `low` queue) rather than on each view, so `sort=most_viewed` lags by up to that interval.

Each `/search` request is one Meilisearch call (hits, total and facets together) and is limited to 60 per minute per user or IP (`throttle:search`). The ads index declares only the attributes the API filters, sorts or facets on and returns only `id` and `title`, so after deploying BE-13.33 run `php artisan scout:sync-index-settings` once (no reimport needed). Saves that change no indexed column no longer queue a Scout job.

If Meilisearch goes down, keep the driver and restart the service: search returns empty results meanwhile, and `scout:import` rebuilds the index at any time. Do not switch to the `database` driver.

## Catalog cache and view counters

Requests never rebuild the home feed or the category and place counters: the `catalog.warm-cache` job does, every `qbazaar.catalog.warm_every_minutes` (2) on the `low` queue. Ad views are buffered in Redis and `ads.flush-view-counts` writes them every minute, also on `low`. Both need the scheduler and Horizon running; `php artisan schedule:list` must show `catalog.warm-cache` and `ads.flush-view-counts`. If the scheduler stops, the home feed and counters age until their 15-minute TTL, then the first request rebuilds them under a lock; buffered views wait in `ads:views:buffer` and are written once it runs again.

After the BE-13.31 index migrations reach production, run `ANALYZE TABLE ads, media, messages, offers, users, activity_log;` so MySQL picks up the new indexes.

## Upload body size

`POST /api/v1/ads/{ad}/images` takes up to `qbazaar.ads.max_images_per_upload` (10) files per request, each up to `qbazaar.uploads.max_image_size_kb` (5 MB by default, `UPLOAD_MAX_IMAGE_SIZE_KB`; the web and mobile apps resize photos before upload). Each user may send `UPLOAD_REQUESTS_PER_MINUTE` (20) upload requests a minute (`throttle:uploads`). An ad holds up to 20 images in total (admin setting `ad_max_images`), so the apps send them in batches. Every layer must accept one full batch, or the request fails before Laravel can answer with a JSON error:

| Layer | Setting | Value |
|---|---|---|
| Apache (API include) | `LimitRequestBody` | `110100480` (105 MiB), set in `deploy/apache/api.include.conf` |
| PHP 8.4 (cPanel MultiPHP INI Editor, `ea-php84`) | `upload_max_filesize` | `10M` |
| | `post_max_size` | `105M` |
| | `max_file_uploads` | `20` or more |
| | `memory_limit` | `128M` is enough: the request only stores the files, every size is rendered on the queue |
| Cloudflare / any proxy in front | max upload size | 105 MiB or more. Cloudflare Free and Pro cap a request at 100 MB, just under a worst-case batch of ten 10 MB files; if the API is proxied there, set `max_images_per_upload` to 9 |

The limits above still fit a 10 MB per-file override. If one of them has to be lower, lower `max_images_per_upload` to match (`floor(post_max_size / max_image_size)`) rather than the per-file size.

## Image queue and CDN

All image sizes (`thumbnail` included), the BlurHash/pHash metadata, the downscale of large originals (`UPLOAD_ORIGINAL_MAX_SIDE_PX`, 2560 px) and the ad auto-moderation (`ModerateAdJob`) run on the `media` queue, consumed by the `supervisor-media` Horizon supervisor. MediaLibrary queues its conversions there too (`MEDIA_QUEUE`, `media` by default). Deploy once Horizon has drained its queues: a `DetectDuplicateImagesJob` or pending-ad alert queued by the old code fails after the switch, and that ad then misses its duplicate hint or reviewer alert (it still shows in the pending list). After deploying run `php artisan config:cache` and `php artisan horizon:terminate`. Until a size exists the API serves the signed original; until `ModerateAdJob` runs the admin ad page shows "check still running" and reviewers are not alerted yet.

Public conversions are served from `MEDIA_CDN_URL` when it is set: the base URL that maps to the root of the conversions disk, e.g. `https://cdn.<domain>/storage` for a proxied host in front of the local `public` disk, or the R2 custom domain (same value as `R2_PUBLIC_URL`) on R2. Conversion paths never change, so they are sent with `Cache-Control: public, max-age=31536000, immutable` (Apache include for `/storage/*/conversions/`, the `media-library.remote.extra_headers` override on R2). Signed original links appear on ad detail only and expire on the hour, so one URL is reused for a whole hour. Files uploaded to R2 before this release keep their old `max-age=604800` header.

## Expiry sweeps

`ads.expire-old` runs at minute 0 of every hour and `offers.expire-old` at minute 30. Each sweep only reads due ids and queues one batch job per `qbazaar.sweeps.batch_size` (200) rows on the `low` queue, so Horizon must be running for ads and offers to expire. The sweeps are `ShouldBeUnique` (lock held for up to an hour), which needs a cache store with atomic locks (`redis` in production). Nothing to do on deploy beyond the usual `config:cache`; `php artisan schedule:list` shows both tasks as hourly.

## Saved-search alerts

Each newly live ad queues one fan-out per ad (unique while queued, `notifications` queue). Candidates come from one indexed query on `saved_searches` (`saved_searches_alerts_category_location_idx`). A user gets at most one saved-search push per `qbazaar.search.saved_search_check_interval_minutes` (60); later matches in the window only reach the bell and one `search.digest` push follows when the window closes (a delayed `SendSavedSearchDigestJob` on `notifications`). The window and the pending counts live in the cache, so it must be `redis` in production. Nothing to run on deploy beyond `migrate`.

## Chat inbox

The inbox and the unread badge read `conversation_participants` (one row per side: hide stamp, unread counter, sort keys). The BE-13.30 migration backfills it from `conversations` and `messages` with two `INSERT … SELECT` statements and then drops `conversations.buyer_hidden_at` / `seller_hidden_at`; run it in the deploy window as usual (`migrate --force`), it holds no long locks at current volumes. Badge changes go out as `messages.unread` on `private-user.{id}` from the `realtime` queue, so Reverb and Horizon must be running for live badges; the web client keeps polling `GET /conversations/unread-count` as a fallback.

## Server tuning and operations (M5)

The files below are applied by hand, once, in the order of [DOCS/M5-LAUNCH-RUNBOOK.md](../DOCS/M5-LAUNCH-RUNBOOK.md), which also has the rollback for each. A deploy changes none of them; the only server-side step a deploy adds is the PHP-FPM reload.

| Task | Files |
|---|---|
| OPS-18.7 backups | `scripts/backup.sh`, `scripts/restore-test.sh`, `scripts/install-ops.sh`, `ops.env.example`, `cron/qbazaar-ops`, `logrotate/qbazaar-ops` |
| OPS-18.8 health and alerts | `GET /api/v1/health/ready`, Sentry in `bootstrap/app.php` (off without a DSN) |
| OPS-18.11 PHP-FPM | `php/api.php-fpm.yaml`, `php/zz-qbazaar-opcache.ini`, `sudoers/qbazaar-deploy`, `scripts/fpm-pool-size.sh` |
| OPS-18.12 Redis | `redis/queue.conf`, `redis/cache.conf`, `systemd/redis-cache.service` |
| OPS-18.13 MariaDB | `mariadb/zz-qbazaar.cnf`, `scripts/mysql-slow-digest.sh` |
| OPS-18.14 Reverb | `systemd/qbazaar-reverb.service`, `qbazaar-api/config/reverb.php` |
| OPS-18.15 Meilisearch | `meilisearch/limits.toml`, `systemd/meilisearch.service`, `scripts/meili-restore-test.sh` |
| OPS-18.16 Pulse | `PULSE_ENABLED=false` in `env.production.template` |
| OPS-18.17 load test | `../tools/load/k6/` |
| OPS-18.18, OPS-18.9 Cloudflare | `cloudflare/`, `apache/cloudflare-only.conf` |

### Memory budget

The box has 7.7 GB of RAM. Every limit below comes from this split; change one and re-check the total.

| Component | Budget | Set in |
|---|---|---|
| MariaDB (buffer pool 1.5 GB + connections) | 2.0 GB | `mariadb/zz-qbazaar.cnf` |
| PHP-FPM, API pool (16 workers × ~80 MB) | 1.3 GB | `php/api.php-fpm.yaml` |
| Horizon (up to 14 workers, ~80 MB each when idle) | 1.2 GB | `config/horizon.php` |
| Meilisearch (indexing capped at 768 MiB) | 1.0 GB | `meilisearch/limits.toml` |
| Redis: queue (typically < 100 MB, ceiling 1 GB) + cache (384 MB) | 0.5 GB | `redis/*.conf` |
| Next.js | 0.5 GB | |
| OPcache shared memory | 0.3 GB | `php/zz-qbazaar-opcache.ini` |
| Reverb, Apache, cPanel, the OS | 0.9 GB | |

A deploy also runs `vite build` and `next build` (up to 1.5 GB heap each, one after the other). Keep a 2 GB swap file so that peak never meets the OOM killer: `swapon --show` must list one.

### PHP-FPM and OPcache

cPanel's default pool (`pm = ondemand`, `max_children = 5`, `max_requests = 20`) lets five slow requests block the whole API and restarts every worker after 20 requests. [`php/api.php-fpm.yaml`](php/api.php-fpm.yaml) sets a `dynamic` pool of 16 workers that live for 1000 requests, a 120 s hard limit (an upload batch streams through its worker), and a slowlog of every request over 5 s in `/opt/cpanel/ea-php84/root/usr/var/log/php-fpm/api-slow.log`. After a day of real traffic, run `scripts/fpm-pool-size.sh` as root: it measures the workers and suggests `pm_max_children` for the budget.

[`php/zz-qbazaar-opcache.ini`](php/zz-qbazaar-opcache.ini) gives OPcache room for the ~17k PHP files, a 4 MB realpath cache, and turns off `opcache.validate_timestamps`: PHP stops checking files for changes, so **new code only runs after PHP-FPM reloads**. `deploy-api.sh` reloads it after rebuilding the caches; when the reload fails while `validate_timestamps=0`, the deploy fails rather than leave the previous release running. Anything done by hand outside a deploy (`config:cache` after an `.env` change, a hot fix) needs the same: `systemctl reload ea-php84-php-fpm` as root. Install the sudoers drop-in before the ini, never the other way round.

### Redis roles

Until now one Redis held the queues and the cache, with `noeviction`: when cache keys filled it, every write failed, queue pushes included. Production now runs two:

| Instance | Port | Policy | Holds |
|---|---|---|---|
| queue (`redis.service`, [`redis/queue.conf`](redis/queue.conf)) | 6379 | `noeviction`, AOF every second | queued jobs, Horizon, admin sessions, cache locks (`lock_connection = default`), the ad-view buffer |
| cache (`redis-cache.service`, [`redis/cache.conf`](redis/cache.conf)) | 6380 | `allkeys-lru`, 384 MB, no persistence | Laravel's cache store only: home feed, counters, rate limits, settings |

Laravel reaches the cache instance through `REDIS_CACHE_HOST`, `REDIS_CACHE_PORT` and `REDIS_CACHE_PASSWORD` (`config/database.php`; each falls back to the shared `REDIS_*` value). `render-env.sh` writes them once `REDIS_CACHE_PORT=6380` is in `~qbazaar/.qbazaar-deploy.env` and the password is in `/root/qbazaar-secrets/redis_cache_password`. A restart of the cache instance empties it; the catalog warmer refills the home feed within two minutes and rate-limit counters start again from zero.

The PHP client is phpredis (the `redis` extension, installed with `pecl` for ea-php84 in the runbook); `render-env.sh` sets `REDIS_CLIENT=phpredis`. `predis/predis` stays in `composer.json` only for machines without the extension. Check: `php -m | grep -x redis` and `php artisan tinker --execute="echo get_class(Redis::connection()->client());"` prints `Redis`.

### MariaDB

[`mariadb/zz-qbazaar.cnf`](mariadb/zz-qbazaar.cnf) sizes InnoDB for the budget above, keeps `innodb_flush_log_at_trx_commit = 1` (the wallet ledger needs every commit on disk), allows 150 connections (16 FPM + 14 Horizon + the rest) and logs every query slower than 0.5 s to `/var/lib/mysql/qbazaar-slow.log`. Every Monday `scripts/mysql-slow-digest.sh` rotates that log and summarises it with `pt-query-digest` (percona-toolkit, from Percona's repository) or, without it, `mariadb-dumpslow`. Reports stay in `/var/log/qbazaar/slow-digest/` (root only, 12 weeks); with `DIGEST_MAILTO` set, a summary of query shapes (no literal values) is mailed. Review the top five by total time each week; the PHP side of a slow request is in the FPM slowlog.

### Reverb capacity

Each open WebSocket costs Reverb one file descriptor, and costs Apache one worker thread for as long as `mod_proxy_wstunnel` tunnels it.

- `qbazaar-reverb.service` raises `LimitNOFILE` to 65535 and runs Reverb at a slightly higher priority (`Nice=-2`).
- Past about 1000 connections Reverb needs an event-loop extension: its default `stream_select` loop is capped at 1024 descriptors whatever the limit. ReactPHP (under Reverb) uses `uv` (`pecl install uv-beta`, with `libuv-devel` from EPEL), `ev` or `event` on its own once one is loaded in the CLI (`php -m`).
- Apache must run the `event` MPM (`httpd -V | grep -i mpm`; WHM → EasyApache 4) with `MaxRequestWorkers` above the expected sockets plus the normal HTTP load, e.g. `ServerLimit 16`, `ThreadsPerChild 64`, `MaxRequestWorkers 1024` (WHM → Apache Configuration → Global Configuration). Threads cost little memory; the risk of a low value is that sockets take every worker and HTTP requests queue.
- If the load test shows Apache as the limit, move the sockets off it: a `ws.` host proxied by Cloudflare to Reverb on its own TLS port (an Origin Rule sends edge port 443 to, say, 8443; Reverb serves the Cloudflare Origin Certificate; only Cloudflare may reach that port). The web and mobile clients then use `ws.` as `REVERB_HOST`.
- [`config/reverb.php`](../qbazaar-api/config/reverb.php) is published, every value env-driven: `REVERB_APP_MAX_CONNECTIONS` (unset = no cap; never set it empty), `REVERB_APP_ACTIVITY_TIMEOUT` (30), `REVERB_APP_PING_INTERVAL` (60), `REVERB_APP_MAX_MESSAGE_SIZE` and `REVERB_MAX_REQUEST_SIZE` (10 000 bytes), `REVERB_ALLOWED_ORIGINS` (`*`; a narrower list refuses clients that send no `Origin` header, which may include the mobile app, so test it first).

`tools/load/k6/chat.js` holds thousands of sockets open while sending 100 messages a second; that is the acceptance test.

### Meilisearch limits and snapshots

[`meilisearch/limits.toml`](meilisearch/limits.toml) caps indexing at 768 MiB and half the vCPUs and turns on a daily snapshot in `/var/lib/meilisearch/snapshots/data.ms.snapshot`. `meilisearch.service` runs it at a lower CPU and disk weight than the web stack and throttles it past 1.5 GB (`MemoryHigh`, no kill). A full `scout:import` gets slower; the site does not. `backup.sh` copies the snapshot off-site and `scripts/meili-restore-test.sh` proves it loads, on a throwaway instance on port 7701. The index can always be rebuilt from MariaDB with `scout:import`; the snapshot only makes that take minutes.

### Pulse

Off in production (`PULSE_ENABLED=false`): with the default `storage` ingest it writes to MariaDB on every request, and nobody can open its dashboard there (no `viewPulse` gate is defined). To use it later: add the gate, set `PULSE_INGEST_DRIVER=redis`, sample rates of 0.1 (`PULSE_*_SAMPLE_RATE`), turn off cache interactions, and run `artisan pulse:work` as a systemd unit like Horizon.

### Health checks and alerts

| URL | Answers | Use |
|---|---|---|
| `https://<WEB_HOST>/` | the web home | site monitor |
| `https://<API_HOST>/up` | 200 while Laravel boots | Laravel's own check |
| `https://<API_HOST>/api/v1/health` | 200, touches nothing | the deploy probe |
| `https://<API_HOST>/api/v1/health/ready` | 200 `ok` / `degraded`, 503 `down` | uptime monitors |

`/api/v1/health/ready` checks, each with a short timeout: the database, the queue Redis and the cache (critical, 503 when one fails), and Meilisearch, the scheduler heartbeat and Horizon's workers (200 `degraded`). The body lists each check as `ok`, `fail`, `stale`, `unknown` or `disabled`; failures are logged with the reason, never shown.

Uptime monitoring runs outside the server (UptimeRobot or Better Stack free plans), every minute:

1. `https://qbazaar.qa/`, expect 200, alert after 2 failed checks.
2. `https://api.qbazaar.qa/api/v1/health/ready`, expect 200: pages when the API is down.
3. The same URL as a keyword monitor for `"status":"ok"`, alert after 5 minutes: catches a stopped scheduler, Horizon or Meilisearch. Right after a deploy the scheduler reads `unknown` for up to a minute (the cache is cleared), which the 5-minute delay absorbs.
4. Heartbeat (cron) monitors for `BACKUP_HEARTBEAT_URL` (daily, 2 h grace) and `RESTORE_HEARTBEAT_URL` (monthly, once enabled).

Send alerts to e-mail plus a phone channel (SMS, the app's push, or Telegram) that reaches the owner at night.

**Sentry:** unhandled exceptions are reported through `Integration::handles()` in `bootstrap/app.php`; without `SENTRY_LARAVEL_DSN` the SDK sends nothing. Put the DSN in `/root/qbazaar-secrets/api.overrides.env`, re-run `render-env.sh`, `config:cache`, reload PHP-FPM, restart Horizon, then `php artisan sentry:test`. 5% of requests are traced (`SENTRY_TRACES_SAMPLE_RATE=0.05`) and no personal data is sent (`SENTRY_SEND_DEFAULT_PII=false`). In Sentry, alert on new issues and on a spike in events.

### Backups

`scripts/backup.sh` runs as root every night at 04:30 Qatar time, after the pruning jobs. Cron runs the root-owned copy that `scripts/install-ops.sh` puts in `/usr/local/lib/qbazaar-ops/`, never the clone the deploy user can write.

| What | Local (`/var/backups/qbazaar`, 7 days) | Off-site (`BACKUP_OFFSITE`) |
|---|---|---|
| Database: consistent dump, no lock (`--single-transaction`), checked complete | `db/` | `db/daily/` 14 days, `db/monthly/` one per month for a year |
| Config: both `.env` files, `/root/qbazaar-secrets`, the settings file, service and Apache configs | `config/` | `config/` 14 days |
| Meilisearch snapshot | (in `/var/lib/meilisearch/snapshots`) | `meili/`, latest |
| Media: the R2 buckets (`BACKUP_MEDIA_SOURCES`) and `storage/app` | — | `media/<source>/current`, with removed or replaced files kept 30 days under `deleted/<date>/` |

Off-site copies go through rclone to a **`crypt`** remote (the script refuses any other): the dumps hold personal data and the config bundle holds secrets. R2 is read through its own read-only token. A media sync that would delete more than `BACKUP_MEDIA_MAX_DELETE` files stops instead, so a wrong or empty source cannot empty the mirror. Every run pings `BACKUP_HEARTBEAT_URL`, or `<url>/fail` when it fails.

**Restore tests:** `scripts/restore-test.sh` loads the newest dump (or, with `RESTORE_FROM_OFFSITE=1`, the newest off-site one, which also proves the crypt password) into a scratch database, compares it with live table by table, and drops it. `scripts/meili-restore-test.sh` does the same for the search snapshot. Run both once before launch (the OPS-18.7 acceptance), then monthly from cron.

**Restoring for real** (runbook "Restore" section): put the API in maintenance mode, stop Horizon and the scheduler, load the dump into `qbazaar_app` (it drops and recreates each table), `migrate --force`, `scout:import`, start the workers, leave maintenance mode. Media comes back with `rclone copy <offsite>/media/<source>/current <source>`; a single lost file can be taken from `deleted/<date>/`.

## Manual deploy (if Actions is down)

```bash
ssh qbazaar@187.126.119.228 "cd ~/qbazaar && bash deploy/scripts/deploy-api.sh"   # or deploy-web.sh
```

The scripts fetch and reset to `origin/production` themselves.

## Folder layout

```
deploy/
├── README.md                       this file
├── DEPLOY-CHECKLIST.md             ordered production deploy checklist
├── NEW-SERVER-RUNBOOK.md          first-time setup of the current server
├── CPANEL-FLEETEYE-RUNBOOK.md      first-time setup of the retired fleeteye server (Arabic)
├── env.production.template         API .env template
├── web.env.production.template     web .env.production template
├── ops.env.example                 /etc/qbazaar/ops.env: settings of the root ops scripts (names only)
├── scripts/                        deploy-api.sh, deploy-web.sh (run by the workflows), common.sh, render-env.sh (root, writes both .env files);
│                                   root ops: install-ops.sh, backup.sh, restore-test.sh, meili-restore-test.sh, mysql-slow-digest.sh, fpm-pool-size.sh, ops-common.sh
├── systemd/                        horizon, reverb, scheduler, web, meilisearch, redis-cache units
├── apache/                         web.include.conf (WEB_HOST), api.include.conf (API_HOST), cloudflare-only.conf (both)
├── php/                            api.php-fpm.yaml (cPanel pool), zz-qbazaar-opcache.ini
├── redis/                          queue.conf (6379), cache.conf (6380)
├── mariadb/                        zz-qbazaar.cnf
├── meilisearch/                    limits.toml (lines for /etc/meilisearch.toml)
├── sudoers/                        qbazaar-deploy
├── cron/, logrotate/               qbazaar-ops (installed by install-ops.sh)
├── cloudflare/                     WAF, rate-limit and cache rules; cache-hit-ratio.sh
└── keys/github-actions.pub         public key for the deploy user
```
