# Production deploy checklist: `main` → `production`

Run this when you promote `main` to `production`. Day-to-day flow and reference: [README.md](README.md). First-time server setup: [NEW-SERVER-RUNBOOK.md](NEW-SERVER-RUNBOOK.md).

Server: Hostinger VPS `srv1977263` (AlmaLinux 9, cPanel), cPanel user `qbazaar`, clone at `/home/qbazaar/qbazaar`, PHP `ea-php84`, MariaDB 10.11, Meilisearch 1.12.8, Redis 7. The hosts are `API_HOST` and `WEB_HOST` in `~qbazaar/.qbazaar-deploy.env` (today `api.qbazaar.187-53-139-138.sslip.io` and `qbazaar.187-53-139-138.sslip.io`, until the real domain).

- `qbazaar$` means run it as `qbazaar` in `~/qbazaar/qbazaar-api`.
- `root#` means run it as root.
- **Verify on server** marks anything the repo cannot tell us.

The examples below use `$API` and `$WEB` for the two hosts: `source ~/.qbazaar-deploy.env; API=https://$API_HOST; WEB=https://$WEB_HOST`.

---

## 1. Pre-flight

- [ ] **CI is green on the `main` commit you ship:** `gh run list --branch main --limit 3`. `deploy-api.yml` re-runs CI and refuses to deploy on a failure.
- [ ] **Rollback point and tag:** `git rev-parse origin/production` (write it down), then `git tag -a release-YYYY-MM-DD origin/main -m "<summary>" && git push origin release-YYYY-MM-DD`.
- [ ] **Window:** the API is in maintenance mode only while `deploy-api.sh` runs (5–15 min); the rest runs with the site up. Plan longer if a migration backfills a big table (§4).
- [ ] **Database dump** (keep it until the smoke tests pass and a day has gone by):
      `qbazaar$ mysqldump --single-transaction --routines --triggers -u qbazaar_app -p qbazaar_app | gzip > ~/backups/qbazaar-$(date +%F-%H%M).sql.gz`
- [ ] **Storage and env backups:** `tar czf ~/backups/storage-$(date +%F).tgz -C ~/qbazaar/qbazaar-api storage/app`, plus copies of `qbazaar-api/.env` and `qbazaar-web/.env.production`.
- [ ] **Disk:** about 2× the dump, plus about 1.5 GB for `composer`, `npm` and `next build`: `df -h /home /var/lib/mysql /var/lib/meilisearch`.
- [ ] **Services are up:** `systemctl status meilisearch qbazaar-horizon qbazaar-reverb qbazaar-scheduler qbazaar-web`; `curl -s 127.0.0.1:7700/health` → `{"status":"available"}`; `redis-cli -a "$REDIS_PASSWORD" ping` → `PONG`.
- [ ] **Runtime versions (only if the release bumps a requirement):** `/opt/cpanel/ea-php84/root/usr/bin/php -v` is 8.4; `mysql --version` is MariaDB ≥ 10.6 (10.11 today); Meilisearch ≥ 1.4 (1.12.8 today, pinned by glibc 2.34). Required PHP extensions are listed in the runbook (§2).
- [ ] **Backfill size**, if the release adds data migrations: count the affected tables (`SELECT COUNT(*) …`). Under about 200k rows each, the normal workflow path (§4A) is fine; above that use the manual path (§4B), because the workflow job has a 15-minute limit.

## 2. Env diff

Edit the `.env` files before the deploy; nothing is read until `config:cache`. `deploy/env.production.template` and `web.env.production.template` hold every key. Compare the keys:

```bash
diff <(grep -o '^[A-Z_0-9]*' ~/qbazaar/qbazaar-api/.env | sort) <(grep -o '^[A-Z_0-9]*' ~/qbazaar/deploy/env.production.template | sort)
```

Run it again after the deploy, once the template on the server is the new one. Do not hand-edit values that `/root/qbazaar-secrets/render-env.sh` owns (hosts, `APP_KEY`, Reverb keys): change `~qbazaar/.qbazaar-deploy.env` and re-run the script instead.

Values that must hold in production:

| Key | Value | Why |
|---|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` | API docs and the OTP fixed code are off only in `production` |
| `OTP_FIXED_CODE` | *(empty)* | Ignored and logged as critical in production |
| `CACHE_STORE` / `QUEUE_CONNECTION` | `redis` / `redis` | Unique locks, digest windows and the view buffer need Redis |
| `SCOUT_DRIVER` / `SCOUT_QUEUE` | `meilisearch` / `true` | Other drivers return empty search results |
| `MEILISEARCH_HOST` / `MEILISEARCH_KEY` | `http://127.0.0.1:7700` / master key from `/etc/meilisearch.toml` | |
| `REDIS_QUEUE_RETRY_AFTER` / `REDIS_LONG_QUEUE_RETRY_AFTER` | `150` / `1900` | Must stay above the 120 s and 1800 s queue timeouts |
| `MEDIA_QUEUE` | `media`, or delete the line | Any other value strands image jobs |
| `WEB_URL` / `CORS_ALLOWED_ORIGINS` | `https://$WEB_HOST` | If both are missing CORS falls back to `http://localhost:3000` and the web breaks |
| `TRUSTED_PROXIES` | *(empty)* | Empty trusts Cloudflare's ranges; set only for another proxy |
| `AUTH_PASSWORD_LOGIN_ENABLED` | `true` | The web signs in with passwords; `false` locks everyone out |
| Web `NEXT_PUBLIC_APP_URL` | `https://$WEB_HOST` | Canonical, OG and sitemap URLs. `NEXT_PUBLIC_*` is compiled in, so a change needs a web deploy |
| Web `QBAZAAR_API_URL` | *(unset, not empty)* | An empty value counts as set |

Switches that stay off until their credentials exist (R2, Turnstile, new-device check, Google/Apple sign-in, Twilio) are in the templates; turn each on only with its own PR. While Twilio is empty the phone OTP code only goes to `storage/logs/laravel.log`, and `phone.verified` gates publishing, chatting and offers.

## 3. Drain the queues (only when the queue topology or job payloads change)

Skip this for a release that adds no queue, supervisor or job-constructor change.

1. [ ] Look at the backlog: `redis-cli -a "$REDIS_PASSWORD" -n 0`, then `LLEN <prefix>queues:<name>` and `ZCARD` on `…:delayed` / `…:reserved` for each queue. **Verify on server** the key prefix (`REDIS_PREFIX`, by default `qbazaar-database-`).
2. [ ] About 10 minutes before the deploy: `root# systemctl stop qbazaar-scheduler`, then wait until the queues and their `:reserved` sets reach 0 (`php artisan horizon:status`).
3. [ ] Inspect anything stuck: `LRANGE <prefix>queues:low 0 20 | grep -o '"displayName":"[^"]*"' | sort | uniq -c`. Delete only jobs whose class or constructor changed, and tell affected users.
4. [ ] Right before §4: `root# systemctl stop qbazaar-horizon`, so old workers do not run against a schema mid-migration. The deploy script starts the new Horizon.

## 4. Deploy the code

### A. Normal path: the workflow

```bash
git fetch origin && git switch production && git merge --ff-only origin/main && git push origin production
```

A push that touches both `qbazaar-api/**` and `qbazaar-web/**` starts **both workflows at once**, and they share one clone on the server. Watch with `gh run watch`.

| Workflow | Does on the server | Not done, do by hand |
|---|---|---|
| `deploy-web.yml` (no CI gate, starts at once) | `git reset --hard origin/production` → `npm ci` → `next build` → `restart qbazaar-web` → probe `127.0.0.1:3000` | `.env.production` edits |
| `deploy-api.yml` (runs `ci.yml` first, a few minutes) | reset → `artisan down` → `composer install --no-dev` → `npm install && npm run build` (admin CSS) → **`migrate --force`** → caches → `storage:link` if missing → `restart qbazaar-horizon` and `qbazaar-reverb` → `artisan up` → probe `/api/v1/health` | seeders, Scout settings and import, `ANALYZE`, `.env`, Apache, systemd (§5, §6) |

- The new web runs against the old API for a few minutes; pages that call new endpoints may error until the API job finishes.
- **Because both jobs share one clone and the Next build is not re-entrant, a web failure with Next's "another build is running" (or an `index.lock` error) is fixed by re-running `deploy-web`:** `gh workflow run deploy-web.yml --ref production`.
- `next build` and the API's `npm run build` can overlap; on a 7.5 GB box that is fine, but check `free -h` if a build is killed.

### B. Manual path: big backfills, or Actions down

If the SSH session dies, `migrate` stops half-way, so use `tmux`.

```bash
gh workflow disable deploy-api.yml && gh workflow disable deploy-web.yml   # so the push does not also deploy
git switch production && git merge --ff-only origin/main && git push origin production
ssh qbazaar@187.53.139.138
tmux new -s deploy
cd ~/qbazaar && bash deploy/scripts/deploy-api.sh && bash deploy/scripts/deploy-web.sh
# afterwards, from your machine:
gh workflow enable deploy-api.yml && gh workflow enable deploy-web.yml
```

The scripts fetch and reset to `origin/production` themselves. A failure leaves maintenance mode off (the `EXIT` trap runs `artisan up`), so go straight to §8 if `migrate` failed.

## 5. Post-deploy commands (`qbazaar$`)

The script already ran `migrate --force`, the caches and the Horizon/Reverb restart. Confirm: `php artisan migrate:status | grep -c Pending` → `0`.

| # | Command | When / note |
|---|---|---|
| 1 | `php artisan migrate --force` | No-op if the script got through; do not skip it after a failed run. Watch long or locking migrations (backfills, table rebuilds, index DDL); budget minutes per million rows. |
| 2 | `php artisan db:seed --class=RolesAndPermissionsSeeder --force` | When the release adds permissions. Idempotent, but it **re-syncs** the moderator and support roles to the code's lists, so permissions hand-granted to those *roles* are reset (grants to users are kept). |
| 3 | `php artisan permission:cache-reset` | After step 2. |
| 4 | `php artisan cache:clear` | When cached shapes changed. It clears only the cache DB (`REDIS_CACHE_DB=1`), not the queues or the view buffer (DB 0); rate-limit counters reset. |
| 5 | `php artisan config:cache && php artisan route:cache && php artisan event:cache` | Only if you edited `.env` after the script. |
| 6 | `php artisan scout:sync-index-settings` | When `config/scout.php` index settings changed. Seconds. |
| 7 | `php artisan scout:import "App\Models\Ad"` | When `Ad::toSearchableArray()` changed, or after a category/location move. Needs Horizon up; watch `queues:search` drain, then `curl -s -H "Authorization: Bearer $KEY" 127.0.0.1:7700/indexes/ads_index/stats`. Use `scout:flush "App\Models\Ad"` first only if stale documents must go (search is empty until the import finishes). |
| 8 | `mysql … -e "ANALYZE TABLE ads, media, messages, offers, users, activity_log, notifications, saved_searches, conversation_participants;"` | After index migrations. Seconds. |
| 9 | Retention backlog, off-peak, only on big tables: `php artisan sanctum:prune-expired --hours=24`, `php artisan model:prune --model="App\Models\RefreshToken" --model="App\Models\RecentView" --model="App\Models\DatabaseNotification"`, `php artisan activitylog:clean --force` | The scheduled runs do this anyway. |
| 10 | `php artisan horizon:terminate`; `sudo systemctl restart qbazaar-reverb` | Only if `.env` or config changed after the script's restart. systemd brings Horizon back. |

Scheduler: `php artisan schedule:list` must list every task in [README → Data retention](README.md#data-retention-scheduled-pruning) plus `ads.expire-old`, `offers.expire-old`, `search.sync-view-counts`, `catalog.warm-cache`, `ads.flush-view-counts`, `accounts.sweep-deletions`, `accounts.prune-data-exports`.

## 6. Server config changes (root, only when the release touches them)

- [ ] **systemd units** (`deploy/systemd/`): `cp ~qbazaar/qbazaar/deploy/systemd/<unit>.service /etc/systemd/system/ && systemctl daemon-reload && systemctl restart <unit>`.
- [ ] **Scheduler runs as a systemd unit only:** `systemctl enable --now qbazaar-scheduler`. There is no cron line on this server; never run both (jobs would fire twice). Without it nothing expires, the home feed and counters go stale after 15 minutes, and views never reach the database. It needs no restart on deploy, because `schedule:work` loads fresh code every minute; restart it only after changing the unit.
- [ ] **Apache includes** (`deploy/apache/api.include.conf`, `web.include.conf`): install commands are in each file's header, for both the `std` and `ssl` tiers; then `/scripts/ensure_vhost_includes --user=qbazaar && apachectl configtest && systemctl restart httpd`. The API include needs `mod_headers`, `proxy_wstunnel` and `rewrite`.
- [ ] **PHP INI** (`ea-php84`): `upload_max_filesize=10M`, `post_max_size=105M`, `max_file_uploads>=20`. **Verify on server** after any PHP change.
- [ ] **Uploads sanity:** `find ~qbazaar/qbazaar/qbazaar-api/storage/app/public -iregex '.*\.\(php[0-9]*\|phtml\|phar\|html?\|svgz?\)$'` → review and delete anything found.
- [ ] **sudoers** (`/etc/sudoers.d/qbazaar-deploy`) stays as is: `restart` of `qbazaar-web`, `qbazaar-horizon` and `qbazaar-reverb` only.

## 7. Smoke tests

| Check | How | Pass |
|---|---|---|
| Health | `curl -s $API/api/v1/health` and `curl -sI $WEB/` | `{"status":"ok"}`; 200 |
| Email-code sign-in | `curl -s -XPOST $API/api/v1/auth/email-otp/send -H 'Content-Type: application/json' -d '{"email":"<your inbox>"}'`, then `…/auth/email-otp/verify` with `{"email","code"}` | Mail arrives through the `notifications` queue (with `MAIL_MAILER=log` it is in the log); verify returns tokens |
| Password sign-in (web) | Log in on the web, refresh, log out | Works; no CORS error in the console |
| Publish → pending → approve | As a phone-verified test user: draft, upload 1+ images, publish (`accepted_terms: true`) | Ad is `pending`; the admin page shows the moderation result; approve in `/admin`; the ad shows on the web and in search |
| Image pipeline | After the upload above | Within about a minute `sizes.thumbnail/medium/large` exist and the media row has `phash`, `phash_int` and a BlurHash. `/storage/…/conversions/…` returns `Cache-Control: public, max-age=31536000, immutable`; any `.php` under `/storage` → 403 |
| Search | `$API/api/v1/search?q=iphnoe` (typo), `…&category=<parent slug>`, `…&lat=25.28&lng=51.52&radius_km=10&sort=distance` | Typo matches; the parent finds its children's ads; geo needs locations with coordinates; no 500s |
| Home and categories | `$API/api/v1/home`, `$API/api/v1/categories/tree` | Non-zero `ads_count`; fast on the second call |
| Realtime chat | Two browsers, two users: send a message, then a photo | Message appears live over `wss://$API_HOST/app`; the unread badge updates; the photo preview appears after the `media` job |
| Admin and permissions | `/admin/login` as super_admin, then as a moderator | Super_admin sees Settings and Roles; a moderator does not; forbidden pages return 403 |
| Horizon | `php artisan horizon:status` → running; `php artisan horizon:supervisors` | **Six supervisors**: realtime, notifications, default, search, media, low. `/horizon` returns 403 in production (empty `viewHorizon` list), so check from the CLI. `failed_jobs` does not grow |
| Scheduler | `systemctl status qbazaar-scheduler`; after 2–3 min `LLEN <prefix>queues:low` drains | `catalog.warm-cache` and `ads.flush-view-counts` show recent runs |
| Logs | `tail -n 100 storage/logs/laravel.log`, `journalctl -u qbazaar-horizon -u qbazaar-reverb -n 50` | No new errors |

## 8. Rollback

**Decide within the window.** After real traffic writes to new tables, a rollback loses that data.

1. **Code only (no schema change in the release):** `git push --force-with-lease origin <rollback-sha>:production`. The workflows redeploy the old code; if they do not, run `deploy-api.sh` and `deploy-web.sh` by hand. The old code ignores new columns and tables, but a rollback past a migration that renames or drops columns is not clean: prefer step 2 or 3.
2. **Roll back migrations, then the code:** on the **new** code run `php artisan migrate:rollback --step=<n> --force`, *before* the code rollback. Read each migration's `down()` first: one-way ones (no-op `down()`) and ones that drop data or fail on rows the old schema cannot hold (for example making a column NOT NULL again) need a backup restore instead.
3. **Restore from backup (cleanest after a failed migrate):**
   1. `php artisan down`.
   2. Stop Horizon and the scheduler.
   3. Restore the dump: `gunzip -c ~/backups/qbazaar-….sql.gz | mysql --no-defaults -u qbazaar_app -p qbazaar_app`.
   4. Push the old SHA to `production` and deploy.
   5. Restore `.env` from `~/backups`.
   6. `config:cache`, then start Horizon and the scheduler.
   7. Run `scout:import "App\Models\Ad"` on the old code.
   8. `php artisan up`.
4. **Server config:** new Apache includes and systemd units normally work with the old code; leave them unless they caused the failure.
5. **Meilisearch:** a re-imported index is harmless to the old code. Re-run `scout:import` after any database restore.
