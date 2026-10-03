# Production deploy checklist: `main` → `production`

This release ships every PR merged since the last deploy (2026-07-07, `production` at `1d4248b`): M0 (#147–#157), M1 batches 1–2 (#160–#164, #193–#197) and the M1 performance batch (#199–#203). There are 51 commits and 40 new migrations, and production fast-forwards to `main`.

Run the sections in order. Server: cPanel user `fleeteye`, clone at `/home/fleeteye/qbazaar`. API: `https://api.qbazaar.fleeteye.de`. Web: `https://qbazaar.fleeteye.de`.

- `fleeteye$` means run it as `fleeteye` in `~/qbazaar/qbazaar-api`.
- `root#` means run it as root.
- **Verify on server** marks anything the repo cannot tell us.

Background and reference: [README.md](README.md). First-time setup: [CPANEL-FLEETEYE-RUNBOOK.md](CPANEL-FLEETEYE-RUNBOOK.md).

---

## 1. Pre-flight (the day before, or at least an hour before the window)

- [ ] **CI is green on the `main` commit you ship.** `gh run list --branch main --limit 3` should show `CI success`. `deploy-api.yml` re-runs CI and refuses to deploy on a failure.
- [ ] **Tag the release:** `git tag -a release-2026-10-XX origin/main -m "M0+M1" && git push origin release-2026-10-XX`. Also record the rollback point: `git rev-parse origin/production` (today `1d4248b`).
- [ ] **Maintenance window:** plan 45–60 min off-peak. The API is in maintenance mode only while `deploy-api.sh` runs (5–15 min). The other steps run with the site up.
- [ ] **Database dump** (keep it until the smoke tests pass and a day has gone by):
      `fleeteye$ mysqldump --single-transaction --routines --triggers -u <user> -p <db> | gzip > ~/backups/qbazaar-$(date +%F-%H%M).sql.gz`
- [ ] **Storage backup:** `fleeteye$ tar czf ~/backups/storage-$(date +%F).tgz -C ~/qbazaar/qbazaar-api storage/app`
- [ ] **Env backups:** `cp qbazaar-api/.env ~/backups/api.env.$(date +%F)` and `cp qbazaar-web/.env.production ~/backups/web.env.$(date +%F)`.
- [ ] **Disk space:** about 2× the dump size, plus about 1.5 GB for `composer`, `npm` and `next build`. Check with `df -h /home /var/lib/mysql /var/lib/meilisearch`.
- [ ] **PHP 8.4 CLI and FPM** (`ea-php84`). `/opt/cpanel/ea-php84/root/usr/bin/php -v` must report 8.4, because the CMS sanitizer uses PHP 8.4's `Dom\HTMLDocument`. Then run `… -m` and check for these extensions: `pcntl posix` (Horizon), plus `dom simplexml curl openssl mbstring fileinfo exif gd sodium zip`. `simplexml` is new, for the AWS SDK.
- [ ] **PHP INI for `ea-php84`** (MultiPHP INI Editor): `upload_max_filesize=10M`, `post_max_size=105M`, `max_file_uploads>=20`, `memory_limit=128M`. **Verify on server.**
- [ ] **MySQL ≥ 8.0, or MariaDB ≥ 10.6.** The new code uses window functions, `BIT_COUNT` on BIGINT, column `->change()` and `insertUsing`. **Verify on server** with `mysql --version`.
- [ ] **Redis is up and authenticated:** `redis-cli -a "$REDIS_PASSWORD" ping` → `PONG`.
- [ ] **Meilisearch is installed and up.** Production still ran `SCOUT_DRIVER=database` in July, so **verify on server** that the service exists: `systemctl status meilisearch` and `curl -s 127.0.0.1:7700/health` → `{"status":"available"}`. If it is missing, install it now with [README → Meilisearch](README.md#meilisearch-install-once-as-root) (root, about 10 min) and note the master key. The code needs Meilisearch ≥ 1.4 (`proximityPrecision`, `_geo`). Check with `curl -s -H "Authorization: Bearer $KEY" 127.0.0.1:7700/version`.
- [ ] **Which scheduler runs today?** **Verify on server:** `systemctl status qbazaar-scheduler` and `crontab -l -u fleeteye | grep schedule:run`. Exactly one of them must be active after the deploy (§6).
- [ ] **Size the backfills.** Run this and note the counts; it tells you how long §5 takes:
      ```sql
      SELECT 'ads',COUNT(*) FROM ads UNION ALL SELECT 'media',COUNT(*) FROM media
      UNION ALL SELECT 'messages',COUNT(*) FROM messages UNION ALL SELECT 'conversations',COUNT(*) FROM conversations
      UNION ALL SELECT 'notifications',COUNT(*) FROM notifications UNION ALL SELECT 'activity_log',COUNT(*) FROM activity_log
      UNION ALL SELECT 'recently_viewed',COUNT(*) FROM recently_viewed UNION ALL SELECT 'personal_access_tokens',COUNT(*) FROM personal_access_tokens;
      ```
      Every table under about 200k rows: the migrations finish in a minute or two, so use the normal workflow path (§4A). Anything bigger: use the manual path (§4B), because the workflow job has a hard 15-minute limit.
- [ ] **Check the admin account.** If `admin@qbazaar.qa` does not exist, the seeder in §5 creates it with the password `password` (flagged must-change). Log in and change that password straight after the deploy.

## 2. Env diff (edit `.env` files before the deploy; nothing is read until `config:cache`)

`deploy/env.production.template` and `web.env.production.template` hold every key below. Diff your live file against them: `diff <(grep -o '^[A-Z_0-9]*' ~/qbazaar/qbazaar-api/.env | sort) <(grep -o '^[A-Z_0-9]*' ~/qbazaar/deploy/env.production.template | sort)`. Run it again after §4, once the template on the server is the new one.

### API `qbazaar-api/.env`: required (set or check explicitly)

| Key | Production value | Why / PR |
|---|---|---|
| `SCOUT_DRIVER` | `meilisearch` | Other drivers return empty search results (#147) |
| `SCOUT_QUEUE` | `true` | Index writes go to the `search` queue (#147, #199) |
| `MEILISEARCH_HOST` | `http://127.0.0.1:7700` | |
| `MEILISEARCH_KEY` | master key from `/etc/meilisearch.toml` | |
| `REDIS_QUEUE_RETRY_AFTER` | `150` | Must stay above the 120 s `notifications` timeout. Delete any older `90` line (#199) |
| `REDIS_LONG_QUEUE_RETRY_AFTER` | `1900` | Must stay above the 1800 s `low` timeout (#199) |
| `MEDIA_QUEUE` | `media`, or delete the line | Any other value strands image jobs (#199, #202) |
| `CORS_ALLOWED_ORIGINS` | `https://qbazaar.fleeteye.de` | Falls back to `WEB_URL`; if both are missing it is `http://localhost:3000` and the web breaks (#163) |
| `WEB_URL` | `https://qbazaar.fleeteye.de` | Already in the old template. **Verify on server** |
| `CACHE_STORE` / `QUEUE_CONNECTION` | `redis` / `redis` | Unique locks, digest windows and the view buffer need Redis |
| `APP_ENV` / `APP_DEBUG` | `production` / `false` | API docs and the OTP fixed code are off only in `production` |
| `OTP_FIXED_CODE` | *(empty)* | Ignored and logged as critical in production (#163) |

### API: optional, leave at the default

| Key | Value | Note |
|---|---|---|
| `API_DOCS_ENABLED` | `false` | Hides `/docs`, `/swagger` and `/api/v1/openapi.yaml` |
| `TRUSTED_PROXIES` | *(empty)* | Empty trusts Cloudflare's ranges. Set it only if another proxy sits in front. **Verify on server** whether `api.` is behind any proxy (#199) |
| `APP_PREVIOUS_KEYS` | *(empty)* | Needed only when `APP_KEY` is rotated (refresh tokens are HMAC-keyed) |
| `AD_VIEWS_BUFFER` | `redis` | `AD_VIEWS_REDIS_CONNECTION` defaults to `default`. Leave it unset (#203) |
| `UPLOAD_MAX_IMAGE_SIZE_KB` / `UPLOAD_REQUESTS_PER_MINUTE` / `UPLOAD_ORIGINAL_MAX_SIDE_PX` | `5120` / `20` / `2560` | #202 |
| `MEDIA_CDN_URL` | *(empty)* | Set only once a `cdn.` host exists |
| `ACTIVITY_LOG_RETENTION_DAYS` / `READ_NOTIFICATIONS_RETENTION_DAYS` | `365` / `90` | #200 |
| `MODERATION_DUPLICATE_WINDOW_DAYS` / `MODERATION_DUPLICATE_SAME_ROOT_CATEGORY` | `90` / `true` | Not in the template; the defaults apply (#202) |
| `CHAT_PUSH_SKIP_ONLINE` / `CHAT_AUTO_REPORT_FLAGGED` | `true` / `true` | Not in the template; the defaults apply (#160) |

### API: switches that stay OFF in this release (credentials deferred)

| Key | Value | Turning it on later |
|---|---|---|
| `MEDIA_DISK` / `MEDIA_PUBLIC_DISK` | `public` / `public` | R2: fill `R2_*`, switch to `r2` / `r2_public`, `config:cache`, then `media:migrate-storage --dry-run` and run it for real |
| `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_ENDPOINT`, `R2_BUCKET`, `R2_PUBLIC_BUCKET`, `R2_PUBLIC_URL` | *(empty)* | |
| `TURNSTILE_ENABLED` / `TURNSTILE_SECRET_KEY` | `false` / *(empty)* | Turn it on only after the web and mobile apps send `X-Turnstile-Token`. It fails closed |
| `AUTH_NEW_DEVICE_CHECK_ENABLED` | `false` | Needs live SMS |
| `AUTH_PASSWORD_LOGIN_ENABLED` | **`true`** | The web still signs in with passwords. `false` locks everyone out |
| `GOOGLE_CLIENT_IDS` / `APPLE_CLIENT_IDS` | *(empty)* | Empty refuses social sign-in (`AUTH_015`) |
| `TWILIO_SID` / `TWILIO_TOKEN` | unchanged (empty = log only) | See the note below |

> **Decision needed: SMS.** Since #148 the phone OTP goes **by SMS only**, and `phone.verified` now gates publishing an ad, starting a chat, sending a message and making an offer (`403 AUTH_003`). With `TWILIO_SID` empty the code only goes to `storage/logs/laravel.log`. Users who have not verified their phone cannot do any of those four things until SMS is live.

### Web `qbazaar-web/.env.production`

| Key | Value | Note |
|---|---|---|
| `NEXT_PUBLIC_APP_URL` | `https://qbazaar.fleeteye.de` | **Required.** Canonical, OG and sitemap URLs fell back to the old `miete.site` host without it (#155) |
| `QBAZAAR_API_URL` | *(leave unset, not empty)* | An empty value counts as set |

No other web keys changed. `NEXT_PUBLIC_*` values are compiled in, so any change needs a web deploy.

## 3. Drain the queues (the queue topology changes)

Production runs one Horizon supervisor on `default` only (`supervisor-1`). **Nothing consumes `low` today**, so ad image processing, expiry sweeps, exports and deletions may have piled up there. The new code runs six supervisors (`realtime`, `notifications`, `default`, `search`, `media`, `low`). Jobs serialized by the old code can fail after the switch. Examples: the removed `IndexAdInSearch` and `RemoveAdFromSearch` listeners, and `ExportUserDataJob`, whose constructor changed.

1. [ ] Look at the backlog. Run `redis-cli -a "$REDIS_PASSWORD" -n 0` and then:
       `KEYS *queues:*` followed by `LLEN <prefix>queues:default`, `LLEN <prefix>queues:low`, and `ZCARD` on `…:delayed` and `…:reserved` for each queue. **Verify on server** the key prefix (`REDIS_PREFIX`, by default `qbazaar-database-`).
2. [ ] Stop the producers about 10 minutes before the deploy. As root, stop the scheduler (`systemctl stop qbazaar-scheduler`, or comment out the cron line). Then wait until `queues:default` and its `:reserved` reach 0. Watch it with `php artisan horizon:status` and the `LLEN` above. User traffic still adds a trickle; that is fine.
3. [ ] `queues:low` will **not** drain under the old Horizon. If you can, list what is in it:
       `LRANGE <prefix>queues:low 0 20 | grep -o '"displayName":"[^"]*"' | sort | uniq -c`.
       - `ProcessAdImagesJob`, `ExpireOldAdsJob`, `ExpireOldOffersJob` and `DeleteAccountJob` still exist with the same payload. Leave them: `supervisor-low` runs them after the deploy.
       - `ExportUserDataJob` items from the old code must go. Delete the whole list only if nothing else in it matters (`DEL <prefix>queues:low`), and tell those users to request the export again.
4. [ ] Right before §4, stop the old workers so they don't run old code against a schema mid-migration. As root: `systemctl stop qbazaar-horizon`. The deploy script's `restart` starts the new Horizon. Reverb can keep running.

## 4. Deploy the code

### A. Normal path (small database): the workflow

```bash
git fetch origin && git switch production && git merge --ff-only origin/main && git push origin production
```

The push touches `qbazaar-api/**`, `qbazaar-web/**` and `deploy/**`, so **both workflows start at once.**

| Workflow | Does on the server | Not done, do by hand |
|---|---|---|
| `deploy-web.yml` (no CI gate, starts immediately) | `git reset --hard origin/production` → `npm ci` (adds `isomorphic-dompurify`) → `next build` → `restart qbazaar-web` → probe `127.0.0.1:3000` | the `.env.production` edits |
| `deploy-api.yml` (runs `ci.yml` first, a few minutes) | reset → `artisan down` → `composer install --no-dev` (adds `league/flysystem-aws-s3-v3`) → `npm install && npm run build` (admin CSS) → **`migrate --force`** → `optimize:clear` + config/route/view/event cache → `storage:link` if missing → `restart qbazaar-horizon` + `qbazaar-reverb` → `artisan up` → probe `/api/v1/health` | seeders, Scout settings and import, `ANALYZE`, `.env`, Apache, systemd, scheduler, cache clear (§5, §6) |

Watch both: `gh run watch` (or the Actions tab).

- The new web runs against the old API for a few minutes. Pages that call new endpoints may error until the API job finishes.
- Both jobs `git reset` the same clone. If one fails on `index.lock`, re-run it with `gh workflow run deploy-web.yml --ref production`.
- `next build` and the API's `npm run build` can overlap. On a small box that is an OOM risk; the swap file is the safety net. **Verify on server:** `free -h`.

### B. Manual path (large database, or Actions down)

Use this when the backfills could take longer than the workflow's 15 minutes. If the SSH session is killed, `migrate` stops half-way.

```bash
gh workflow disable deploy-api.yml && gh workflow disable deploy-web.yml   # so the push does not also deploy
git switch production && git merge --ff-only origin/main && git push origin production
ssh fleeteye@<server>
tmux new -s deploy
cd ~/qbazaar && bash deploy/scripts/deploy-api.sh && bash deploy/scripts/deploy-web.sh
# afterwards, from your machine:
gh workflow enable deploy-api.yml && gh workflow enable deploy-web.yml
```

The scripts fetch and reset to `origin/production` themselves. A failure leaves maintenance mode off (the `EXIT` trap runs `artisan up`), so go straight to §8 if `migrate` failed.

## 5. Post-deploy commands (`fleeteye$`, in this order)

The script already ran `migrate --force`, the caches and the Horizon/Reverb restart. First confirm that: `php artisan migrate:status | grep -c Pending` → `0`.

| # | Command | Time / risk |
|---|---|---|
| 1 | `php artisan migrate --force` | No-op if the script got through. Do not skip it after a failed run. **Long or locking migrations:** `create_conversation_participants_table` (two `INSERT…SELECT` plus an unread `COUNT` per conversation, then drops columns on `conversations`); `add_phash_int_to_media_table` (chunked 1000-row backfill); `backfill_chat_message_keys`; `add_category_to_notifications_table`; `add_alert_criteria_to_saved_searches_table`; `add_active_ads_count_to_users_table` and `add_seller_active_to_ads_table` (UPDATE across all users and ads); `drop_guest_recent_views` (deletes in batches of 5000); `allow_image_messages` (rebuilds `messages.type`, a table copy); `add_perf_audit_indexes` and the other index migrations (online DDL on `ads`, `media`, `messages`, `offers`, `users`, `activity_log`). Seconds at today's volume; budget minutes per million rows. |
| 2 | `php artisan db:seed --class=RolesAndPermissionsSeeder --force` | Idempotent. It adds `roles.manage`, `ads.suspend`, `offers.view`, `support.*`, `conversations.view`, `activity.view` and `settings.manage`. It **re-syncs** the moderator and support roles to the code's lists, so permissions hand-granted to those *roles* in the panel are reset; grants made directly to users are kept. Until it runs, moderators lose access to support, conversations, offers and suspend. |
| 3 | `php artisan permission:cache-reset` | |
| 4 | `php artisan cache:clear` | Recommended once. It drops cache entries written by the old code (catalog, home and schema keys changed shape). It clears only the cache DB (`REDIS_CACHE_DB=1`), not the queues or the view buffer (DB 0). Rate-limit counters reset. |
| 5 | `php artisan config:cache && php artisan route:cache && php artisan event:cache` | Re-run only if you edited `.env` after the script. |
| 6 | `php artisan scout:sync-index-settings` | Adds `category_path`, `location_path`, `_geo`, `ad_type`, `shipping`, `views_count` and the leaner displayed/filterable sets (#164, #193, #195, #201). Seconds. |
| 7 | `php artisan scout:flush "App\Models\Ad"` | **Search is empty from here until step 8's jobs finish.** Needed so ads of suspended sellers drop out (#152). |
| 8 | `php artisan scout:import "App\Models\Ad"` | Queues `MakeSearchable` batches on `search`, so Horizon must be up. Watch `queues:search` drain. Then check `curl -s -H "Authorization: Bearer $KEY" 127.0.0.1:7700/indexes/ads_index/stats` → `numberOfDocuments` ≈ the number of active ads of active sellers. |
| 9 | `mysql … -e "ANALYZE TABLE ads, media, messages, offers, users, activity_log, notifications, saved_searches, conversation_participants;"` | So the optimizer sees the new indexes (#203). Seconds. |
| 10 | **pHash gap (verify on server).** `SELECT COUNT(*) FROM media WHERE model_type='App\\Models\\Ad' AND collection_name='images' AND phash IS NULL;` | The migration converts only existing hashes. Images uploaded while `low` had no consumer have none, so they never match as duplicates and may lack BlurHash or sizes. If the count is above 0 and small, requeue in `php artisan tinker`: `DB::table('media')->where('model_type', App\Models\Ad::class)->whereNull('phash')->pluck('id')->chunk(50)->each(fn ($ids) => App\Jobs\ProcessAdImagesJob::dispatch($ids->all()));`. It runs on `media`. There is no artisan command for this. |
| 11 | Retention backlog, off-peak, only if the tables are big: `php artisan sanctum:prune-expired --hours=24`, `php artisan model:prune --model="App\Models\RefreshToken" --model="App\Models\RecentView" --model="App\Models\DatabaseNotification"`, `php artisan activitylog:clean --force` | The first scheduled runs do this anyway. Running them by hand keeps the 03:00 jobs short. |
| 12 | `php artisan horizon:terminate` | Only if you changed `.env` or config after the script's restart. systemd starts Horizon again. |
| 13 | Restart Reverb: `sudo systemctl restart qbazaar-reverb` | Only if `.env` changed after the script. |

Scheduler: start it now that the new code is live (§6 has the install). Run `php artisan schedule:list`. It must list `ads.expire-old`, `offers.expire-old`, `search.sync-view-counts`, `catalog.warm-cache`, `ads.flush-view-counts`, `accounts.sweep-deletions`, `accounts.prune-data-exports`, `auth.prune`, `auth.prune-access-tokens`, `auth.prune-refresh-tokens`, `retention.prune-history`, `retention.clean-activity-log`, `retention.prune-failed-jobs`, `auth.clear-password-resets` and `horizon.snapshot`.

## 6. Server config changes (root)

- [ ] **Horizon unit** (adds `TimeoutStopSec=330`):
      `cp ~fleeteye/qbazaar/deploy/systemd/qbazaar-horizon.service /etc/systemd/system/ && systemctl daemon-reload && systemctl restart qbazaar-horizon`
- [ ] **Scheduler**: use one of the two, never both.
      - unit: `cp ~fleeteye/qbazaar/deploy/systemd/qbazaar-scheduler.service /etc/systemd/system/ && systemctl daemon-reload && systemctl enable --now qbazaar-scheduler`
      - or cron as `fleeteye`: `* * * * * cd /home/fleeteye/qbazaar/qbazaar-api && /opt/cpanel/ea-php84/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1`

      Without a scheduler, nothing expires, the home feed and counters go stale after 15 minutes, and views never reach MySQL.
- [ ] **API Apache include.** It changes the PHP handler (`FilesMatch`), `/storage` static-only plus headers, the year-long cache on conversions and `LimitRequestBody 105 MiB`. Install it into **both** tiers:
      ```bash
      for t in std ssl; do d=/etc/apache2/conf.d/userdata/$t/2_4/fleeteye/api.qbazaar.fleeteye.de; mkdir -p "$d"; cp ~fleeteye/qbazaar/deploy/apache/api.qbazaar.fleeteye.de.include.conf "$d/qbazaar.conf"; done
      /scripts/ensure_vhost_includes --user=fleeteye && apachectl configtest && systemctl restart httpd
      ```
      It needs `mod_headers`, `proxy_wstunnel` and `rewrite`.
      **Verify on server:** the include's `<Directory "/home/fleeteye/public_html/qbazaar/qbazaar-api/public">` must match the real docroot. The runbook says `/home/fleeteye/qbazaar/qbazaar-api/public`. If they differ, fix the path in the copied file; otherwise the PHP pin and the body limit silently do not apply.
- [ ] **Old uploads:** `find ~fleeteye/qbazaar/qbazaar-api/storage/app/public -iregex '.*\.\(php[0-9]*\|phtml\|phar\|html?\|svgz?\)$'` → review and delete anything found (#150).
- [ ] **sudoers** stays as it is (`restart` of web, horizon and reverb). The scheduler needs no restart on deploy, because `schedule:work` loads fresh code every minute.
- [ ] **Web Apache include:** unchanged; nothing to do.

## 7. Smoke tests

| Check | How | Pass |
|---|---|---|
| Health | `curl -s https://api.qbazaar.fleeteye.de/api/v1/health` and `curl -sI https://qbazaar.fleeteye.de/` | `{"status":"ok"}`; 200 |
| Email-code sign-in | `curl -s -XPOST …/api/v1/auth/email-otp/send -H 'Content-Type: application/json' -d '{"email":"<your inbox>"}'`, then `…/auth/email-otp/verify` with `{"email","code"}` | The mail arrives (it goes through the `notifications` queue); verify returns tokens |
| Password sign-in (web) | Log in on the web, refresh the page, log out | Works; no CORS error in the console |
| Publish → pending → approve | With a phone-verified test user: create a draft, upload 1+ images, publish (`accepted_terms: true`) | Ad is `pending`; the admin page shows "check still running" and then the moderation result; reviewers get the bell. Approve in `/admin`; the ad shows on the web and in search |
| Image pipeline | After the upload above | Within about a minute `sizes.thumbnail/medium/large` exist and the media row has `phash`, `phash_int` and a BlurHash. `/storage/…/conversions/…` returns `Cache-Control: public, max-age=31536000, immutable`; any `.php` under `/storage` → 403 |
| Search | `…/api/v1/search?q=iphnoe` (typo), `…&category=<parent slug>`, `…&lat=25.28&lng=51.52&radius_km=10&sort=distance`, `…&sort=most_viewed` | Typo matches; the parent finds its children's ads; geo returns ads, **only if locations have coordinates (verify on server)**; no 500s |
| Home and categories | `…/api/v1/home`, `…/api/v1/categories/tree` | Non-zero `ads_count`; fast on the second call |
| Realtime chat | Two browsers, two users: open a chat and send a message, then a photo | The message appears live over `wss://api…/app`; the unread badge updates (`messages.unread`); the photo preview appears after the `media` job |
| Admin and permissions | Log in at `/admin/login` as super_admin, then as a moderator | Super_admin sees Settings and Roles. A moderator sees Support, Conversations and Offers but not Settings; forbidden pages return 403 |
| Horizon | `php artisan horizon:status` → running; `php artisan horizon:supervisors` | **Six supervisors**: realtime, notifications, default, search, media, low. `/horizon` returns 403 in production because the `viewHorizon` gate has an empty list, so check from the CLI. `failed_jobs` does not grow |
| Scheduler | `php artisan schedule:list`; after 2–3 min, `redis-cli … LLEN <prefix>queues:low` drains and `storage/logs/laravel.log` is clean; `systemctl status qbazaar-scheduler` | `catalog.warm-cache` and `ads.flush-view-counts` show recent runs |
| Logs | `tail -n 100 storage/logs/laravel.log`, `journalctl -u qbazaar-horizon -u qbazaar-reverb -n 50` | No new errors |

## 8. Rollback

**Decide within the window.** After real traffic writes to the new tables, a rollback loses that data.

1. **Code only (no schema problem):** `git push --force-with-lease origin <rollback-sha>:production` (today `1d4248b`). The workflows redeploy the old code. If they don't, run `deploy-api.sh` and `deploy-web.sh` by hand. The old code ignores the new columns and tables, but some breakage is expected:
   - **Inbox hide state:** `conversations.buyer_hidden_at` and `seller_hidden_at` no longer exist.
   - **The renamed `otp_codes.recipient` column:** old code that reads `phone` fails.

   So a code-only rollback is not clean past the chat and auth migrations. Prefer step 2 or 3.
2. **Roll back migrations, then the code:** run `php artisan migrate:rollback --step=40 --force` on the **new** code, *before* the code rollback. All 40 migrations have a `down()`, but these lose data:
   - `sanitize_existing_cms_bodies`, `backfill_chat_message_keys`, `drop_guest_recent_views`: one-way (no-op `down()`; stripped HTML, guest history and keys are not restored). Harmless to the old code.
   - `add_transition_guards_and_counter_offers_to_offers_table`: `countered` offers become `rejected`.
   - `make_reporter_nullable_on_reports_table`: **deletes** reports with no reporter.
   - `make_password_nullable_on_users_table`: **fails** if any passwordless (email-code or social) user exists. Set their `password` first or restore from backup.
   - `allow_image_messages`: image messages become `text`.
   - `create_conversation_participants_table`: restores the hide stamps; unread counters are recomputed by the old code.
   - Every new table (`settings`, `trusted_devices`, `social_accounts`, `user_addresses`, `data_exports`, `follows`, `business_profiles`, `conversation_participants`) is dropped with its rows.
3. **Restore from backup (cleanest after a failed migrate):**
   1. `php artisan down`.
   2. Stop Horizon and the scheduler.
   3. Restore the dump: `gunzip -c ~/backups/qbazaar-….sql.gz | mysql …`.
   4. Push the old SHA to `production` and deploy.
   5. Restore `.env` from `~/backups`.
   6. `config:cache`, then start Horizon. **Do not re-enable the scheduler** unless it ran before the deploy.
   7. Run `scout:import "App\Models\Ad"` on the old code, or `SCOUT_DRIVER` as it was.
   8. `php artisan up`.
4. **Server config:** the new Apache include and Horizon unit work with the old code; leave them. Remove the scheduler unit or the cron line only if you go back to the pre-deploy state on purpose.
5. **Meilisearch:** the flushed and re-imported index is harmless to the old code. Re-run `scout:import` after any database restore.
