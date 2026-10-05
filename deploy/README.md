# QBazaar — Production Deploy

> **Production today (since 2026-10-05):** the new cPanel VPS `srv1977263.hstgr.cloud` (`187.53.139.138`, AlmaLinux 9), cPanel user `qbazaar`, repo clone at `/home/qbazaar/qbazaar`.
> Web: `https://qbazaar.187-53-139-138.sslip.io` · API + admin: `https://api.qbazaar.187-53-139-138.sslip.io`. These are temporary [sslip.io](https://sslip.io) names until the real domain exists; see [Switching to the real domain](#switching-to-the-real-domain).
> The old server (`fleeteye`, `qbazaar.fleeteye.de`) is retired. Cloudflare and the real domain follow in M5 (`OPS-18.x` in [MILESTONES-V2.md](../qbazaar-contracts/MILESTONES-V2.md)).

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
| `deploy-web.yml` | Push to `production` touching `qbazaar-web/**`, `deploy/**` or the workflow | SSH → `deploy/scripts/deploy-web.sh` (no CI gate: the web has no CI job yet) |

Changes that only touch `qbazaar-contracts/`, `DOCS/` or the READMEs don't deploy.

**Repository secrets** (`Settings → Secrets and variables → Actions`): `DEPLOY_HOST`, `DEPLOY_USER` (`qbazaar`), `DEPLOY_PORT`, `DEPLOY_SSH_KEY` (private half of `keys/github-actions.pub`). Key auth only; there is no password fallback.

## Server settings file

Hosts and paths live in one non-secret file on the server, `~qbazaar/.qbazaar-deploy.env`:

```bash
API_HOST=api.qbazaar.187-53-139-138.sslip.io
WEB_HOST=qbazaar.187-53-139-138.sslip.io
URL_SCHEME=https
REPO_DIR=/home/qbazaar/qbazaar
PHP_BIN_DIR=/opt/cpanel/ea-php84/root/usr/bin
```

The deploy scripts read it (an explicit environment variable wins, e.g. `HEALTH_HOST=… bash deploy/scripts/deploy-api.sh`), and the defaults in `common.sh` match it. `HEALTH_HOST` defaults to `API_HOST`. The root-only `/root/qbazaar-secrets/render-env.sh` builds both `.env` files from the templates, this file and the generated secrets in `/root/qbazaar-secrets/`.

## Switching to the real domain

1. DNS: `A` records for the web and `api.` hosts → `187.53.139.138` (or Cloudflare, OPS-18.9).
2. WHM: add the new domain to the `qbazaar` account (or `whmapi1 modifyacct user=qbazaar DNS=<domain>`), then add the `api` subdomain with docroot `qbazaar/qbazaar-api/public`. Run AutoSSL (`/usr/local/cpanel/bin/autossl_check --user=qbazaar`).
3. Edit `API_HOST` / `WEB_HOST` in `~qbazaar/.qbazaar-deploy.env`.
4. As root: `bash /root/qbazaar-secrets/render-env.sh` (rewrites `APP_URL`, `WEB_URL`, `CORS_ALLOWED_ORIGINS`, mail sender and the web `NEXT_PUBLIC_*` hosts).
5. Install the Apache includes under the new host directories (commands in each file's header), `ensure_vhost_includes`, `configtest`, restart httpd.
6. Redeploy both (`gh workflow run deploy-api.yml --ref production`, same for web): the web bundle inlines the hosts at build time.
7. Behind Cloudflare, keep `TRUSTED_PROXIES` empty; see OPS-18.9.

## What the deploy scripts do

**`deploy-api.sh`:** reset the clone to `origin/production` → maintenance mode → `composer install --no-dev` → `npm install && npm run build` (Tailwind assets for `/admin`) → `migrate --force` → rebuild config/route/view/event caches → `storage:link` if missing → restart `qbazaar-horizon` and `qbazaar-reverb` → leave maintenance mode → health probe on `/api/v1/health` (fails the deploy if not 200).

**`deploy-web.sh`:** reset to `origin/production` → `npm ci` → `next build` → restart `qbazaar-web` → probe `http://127.0.0.1:3000/`.

Both scripts source `scripts/common.sh`, which reads the server settings file (below) and puts the `ea-php84` CLI and `/usr/local/bin` (Composer, npm 11) first on `PATH`. The `qbazaar` user may restart only `qbazaar-web`, `qbazaar-horizon` and `qbazaar-reverb` through a sudoers drop-in.

## Services on the server

| Unit (`deploy/systemd/`) | Runs |
|--------------------------|------|
| `qbazaar-horizon.service` | `artisan horizon`, one supervisor per queue (see Queues below) |
| `qbazaar-reverb.service` | `artisan reverb:start` on `127.0.0.1:8080`, proxied as `wss://api…/app` |
| `qbazaar-scheduler.service` | `artisan schedule:work` (ad and offer expiry, etc.). Use it **or** the cron entry in the runbook, never both. |
| `qbazaar-web.service` | `next start -p 3000`, proxied by Apache |
| `meilisearch.service` | Meilisearch on `127.0.0.1:7700` (section below) |

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

Check with a typo, which only Meilisearch tolerates: `curl -s 'https://api.qbazaar.187-53-139-138.sslip.io/api/v1/search?q=iphnoe' | head -c 400`.

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

## Manual deploy (if Actions is down)

```bash
ssh qbazaar@187.53.139.138 "cd ~/qbazaar && bash deploy/scripts/deploy-api.sh"   # or deploy-web.sh
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
├── scripts/                        deploy-api.sh, deploy-web.sh (run by the workflows), common.sh
├── systemd/                        horizon, reverb, scheduler, web, meilisearch units
├── apache/                         web.include.conf (WEB_HOST) and api.include.conf (API_HOST)
└── keys/github-actions.pub         public key for the deploy user
```
