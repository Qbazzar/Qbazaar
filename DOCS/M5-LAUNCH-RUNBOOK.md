# M5 launch runbook: server readiness (OPS-18.5, 18.7–18.9, 18.11–18.18)

Everything here is prepared in the repo and **applied by hand by the owner**, one step at a time, on the production box (`<server-hostname>`, AlmaLinux 9, cPanel, ea-php84 FPM, MariaDB 10.11, Redis 7, Meilisearch 1.12.8, Horizon, Reverb, Next.js behind Apache, Cloudflare in front, media on R2). Nothing in this file has been run on the server.

Reference for every file named here: [deploy/README.md → Server tuning and operations (M5)](../deploy/README.md#server-tuning-and-operations-m5). Conventions: `root#` runs as root; `qbazaar$` runs as `qbazaar` in `~/qbazaar/qbazaar-api`. Load the host names first: `source ~qbazaar/.qbazaar-deploy.env`.

## What merging this PR changes by itself

Merging and deploying `production` changes only application code; every server file below stays as it is until its step is run.

| Change | Effect on the next API deploy |
|---|---|
| `GET /api/v1/health/ready` | New endpoint; nothing calls it until the monitors in step 10 |
| Sentry `Integration::handles()` | Nothing is sent while `SENTRY_LARAVEL_DSN` is empty |
| `REDIS_CACHE_*` fallbacks in `config/database.php` | Same connection as today until `REDIS_CACHE_PORT` is set (step 5) |
| Published `config/reverb.php` | Same values as the package defaults |
| `deploy-api.sh` reloads PHP-FPM | Until step 2 the `qbazaar` user may not, so the deploy logs one refused `sudo` and a warning, and carries on |

## Before you start

- [ ] Root SSH works, **and** the Hostinger browser console (VNC) works: steps 5, 6 and 13 restart services, and the console is the way back in if SSH ever breaks.
- [ ] A fresh dump exists (see [DEPLOY-CHECKLIST.md §1](../deploy/DEPLOY-CHECKLIST.md#1-pre-flight)), until step 9 makes backups automatic.
- [ ] Swap: `swapon --show` lists one. If not: `fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile && echo '/swapfile none swap sw 0 0' >> /etc/fstab`. The memory budget is tight (deploy/README.md) and the deploy's builds only fit with swap. Expected output of `swapon --show`: a line like `/swapfile file 2G 0B -2`. **Do not go on until it shows.**
- [ ] The clone is on the merged `production`: `git -C ~qbazaar/qbazaar log -1 --oneline`.
- [ ] Root runs scripts from the `qbazaar` user's clone (as `render-env.sh` already does). Read `git log -p -3 -- deploy/` first: anything that account could change runs as root.

Take the steps in order. Each ends with a check; stop at the first one that fails and roll that step back.

## Order at a glance

| Step | Task | Downtime | Risk |
|---|---|---|---|
| 1 | OPS-18.5 secrets | none | low |
| 2 | Sudoers: deploy may reload PHP-FPM | none | low |
| 3 | OPcache ini (`validate_timestamps=0`) | none (graceful reload) | medium: stale code if a reload is skipped |
| 4 | PHP-FPM pool | none (graceful) | low |
| 5 | Redis roles | none | medium: misconfigured cache password = 500s |
| 6 | MariaDB tuning + slow log | none (restart optional) | low |
| 7 | Meilisearch limits + snapshots | search empty ~5 s | low |
| 8 | Reverb capacity | chat reconnects ~5 s | low |
| 9 | Backups + restore test | none | low |
| 10 | Health checks, Sentry, uptime alerts | none | none |
| 11 | Pulse off | none | none |
| 12 | Cloudflare WAF, rate-limit, cache rules | none | medium: a wrong rule blocks real users |
| 13 | Origin answers only Cloudflare | none | medium: lock-out if Cloudflare is turned off |
| 14 | Load test on staging | staging only | none for production |

---

## 1. Secrets (OPS-18.5)

Names only; values live in the places listed, never in the repo, a PR or a chat. The owner has postponed the provider accounts, so this is the list to fill when they exist.

Most integrations can be set either in the admin panel (**Admin → Integrations**, stored encrypted with `APP_KEY`; an empty field falls back to `.env`) or in `/root/qbazaar-secrets/api.overrides.env` (then `render-env.sh`, `config:cache`, reload PHP-FPM, restart Horizon).

| Service | Keys | Where | Done when |
|---|---|---|---|
| Twilio (SMS) | `sms_account_sid`, `sms_auth_token`, `sms_messaging_service_sid` or `sms_from_number` (admin) · or `TWILIO_SID`, `TWILIO_TOKEN`, `TWILIO_FROM` | Admin → Integrations | A real SMS code arrives; then `AUTH_NEW_DEVICE_CHECK_ENABLED=true` |
| Firebase (push) | `push_service_account` (admin) · or `FIREBASE_CREDENTIALS` = path to the service-account JSON (owner `qbazaar`, mode 600, outside the repo) | Admin → Integrations | A real push arrives on web and mobile |
| Firebase (web push) | `NEXT_PUBLIC_FCM_API_KEY`, `NEXT_PUBLIC_FCM_PROJECT_ID`, `NEXT_PUBLIC_FCM_SENDER_ID`, `NEXT_PUBLIC_FCM_APP_ID`, `NEXT_PUBLIC_FCM_VAPID_KEY` | `/root/qbazaar-secrets/web.overrides.env`, then a web deploy | The "enable notifications" prompt shows |
| Mail | `email_api_key`, `email_from_address`, `email_from_name` (admin, Resend) · or `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `RESEND_API_KEY` | Admin → Integrations | A password-reset mail arrives (Horizon must run) |
| Sentry | `SENTRY_LARAVEL_DSN` | `api.overrides.env` | `php artisan sentry:test` shows in Sentry |
| Turnstile | `turnstile_site_key`, `turnstile_secret_key`, `turnstile_enabled` | Admin → Integrations | Only after the apps send the token |
| Google / Apple sign-in | `google_client_id`, `google_client_secret`, `apple_*` (admin) · `GOOGLE_CLIENT_IDS`, `APPLE_CLIENT_IDS` | Admin → Integrations / `api.overrides.env` | Sign-in works on each platform |
| Cache Redis | `redis_cache_password` | `/root/qbazaar-secrets/` (step 5) | `redis-cli -p 6380` needs it |
| Backups | off-site storage keys, crypt password and salt, R2 read-only token | `/root/.config/rclone/rclone.conf` (step 9) | `restore-test.sh` passes from off-site |
| Backups | `BACKUP_HEARTBEAT_URL`, `RESTORE_HEARTBEAT_URL` | `/etc/qbazaar/ops.env` | The monitor shows the first ping |
| Cloudflare | API token (Analytics Read; WAF and Cache Rules Edit only while applying) | the shell environment, then revoke | — |
| GitHub Actions | `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PORT`, `DEPLOY_SSH_KEY` | repository secrets (exist) | — |

Keep a copy of `APP_KEY` (`/root/qbazaar-secrets/app_key`) and of the rclone crypt password and salt **outside the server** (a password manager). Without `APP_KEY` the restored database cannot decrypt the admin integration secrets or refresh tokens; without the crypt password the off-site backups cannot be read at all.

**Rollback:** clear the field (admin) or remove the line and re-run `render-env.sh` + `config:cache` + reload PHP-FPM.

## 2. Sudoers: let the deploy reload PHP-FPM (OPS-18.11)

```bash
root# systemctl cat ea-php84-php-fpm | grep -i ExecReload        # must exist: reload is a graceful USR2
root# diff /etc/sudoers.d/qbazaar-deploy ~qbazaar/qbazaar/deploy/sudoers/qbazaar-deploy    # read what differs first
root# cp -p /etc/sudoers.d/qbazaar-deploy /root/qbazaar-deploy.sudoers.bak
root# install -m 440 -o root -g root ~qbazaar/qbazaar/deploy/sudoers/qbazaar-deploy /root/qbazaar-deploy.sudoers.new
root# visudo -cf /root/qbazaar-deploy.sudoers.new &&       install -m 440 -o root -g root /root/qbazaar-deploy.sudoers.new /etc/sudoers.d/qbazaar-deploy
root# sudo -l -U qbazaar          # lists the three restarts and the reload
```

**Check:** `qbazaar$ sudo -n systemctl reload ea-php84-php-fpm && echo ok`.
**Rollback:** `install -m 440 /root/qbazaar-deploy.sudoers.bak /etc/sudoers.d/qbazaar-deploy`.

## 3. OPcache and realpath cache (OPS-18.11)

Only after step 2: from here on, code changes run only after a PHP-FPM reload.

```bash
root# install -m 644 ~qbazaar/qbazaar/deploy/php/zz-qbazaar-opcache.ini /opt/cpanel/ea-php84/root/etc/php.d/zz-qbazaar-opcache.ini
root# systemctl reload ea-php84-php-fpm
```

**Check:** `/opt/cpanel/ea-php84/root/usr/bin/php -i | grep -E 'opcache.validate_timestamps|opcache.memory_consumption'` shows `Off` and `256`; the site answers. Then run a deploy (`gh workflow run deploy-api.yml --ref production`) and confirm its log shows "Reloading PHP-FPM" without a warning.
**Rollback:** `rm /opt/cpanel/ea-php84/root/etc/php.d/zz-qbazaar-opcache.ini && systemctl reload ea-php84-php-fpm`.
**Risk:** any manual change outside a deploy (`.env` + `config:cache`, a hot fix) now also needs `systemctl reload ea-php84-php-fpm`, or PHP keeps the old code.

## 4. PHP-FPM pool for the API (OPS-18.11)

```bash
root# f=/var/cpanel/userdata/qbazaar/$API_HOST.php-fpm.yaml
root# [ -f "$f" ] && cp -p "$f" "/root/$(basename "$f").bak"
root# install -m 644 ~qbazaar/qbazaar/deploy/php/api.php-fpm.yaml "$f"
root# /scripts/php_fpm_config --rebuild --domain="$API_HOST"
root# /scripts/restartsrv_apache_php_fpm
root# grep -E '^(pm|request_|slowlog|php_admin)' /opt/cpanel/ea-php84/root/etc/php-fpm.d/$API_HOST.conf
```

**Check:** the pool file shows `pm = dynamic`, `pm.max_children = 12`, `pm.max_requests = 1000`, `request_terminate_timeout = 120s`, `request_slowlog_timeout = 5s` and the slowlog path. If a directive is missing or misspelled, set it in WHM → MultiPHP Manager → PHP-FPM settings for the domain instead and tell the repo. The site and `/admin` answer. A day later: `bash ~qbazaar/qbazaar/deploy/scripts/fpm-pool-size.sh` and adjust `pm_max_children`.
**Rollback:** restore the `.bak` (or delete the file to return to cPanel's defaults), rebuild, restart as above.

## 5. Two Redis roles (OPS-18.12)

**5a. Queue Redis: AOF on, live, then in the config.**

```bash
root# RPASS=$(cat /root/qbazaar-secrets/redis_password)
root# REDISCLI_AUTH=$RPASS redis-cli info memory | grep -E '^used_memory_human|^maxmemory_human'
root# REDISCLI_AUTH=$RPASS redis-cli config set appendonly yes
root# REDISCLI_AUTH=$RPASS redis-cli info persistence | grep -E 'aof_enabled|aof_rewrite_in_progress'   # wait for aof_rewrite_in_progress:0
root# install -m 640 -o root -g redis ~qbazaar/qbazaar/deploy/redis/queue.conf /etc/redis/qbazaar-queue.conf
root# grep -q '^include /etc/redis/qbazaar-queue.conf' /etc/redis/redis.conf || echo 'include /etc/redis/qbazaar-queue.conf' >> /etc/redis/redis.conf
root# REDISCLI_AUTH=$RPASS redis-cli config set appendfsync everysec
```

The include must come after the existing `include /etc/redis/qbazaar.conf` (it does, when appended). No restart is needed now; the next restart reads the AOF.

Also set `vm.overcommit_memory=1`, because the AOF rewrite forks and the default can refuse it on a busy box:

```bash
root# echo 'vm.overcommit_memory = 1' > /etc/sysctl.d/90-qbazaar-redis.conf && sysctl --system | grep overcommit
vm.overcommit_memory = 1
```

**5b. Cache Redis on 6380.**

```bash
root# install -m 640 -o root -g redis ~qbazaar/qbazaar/deploy/redis/cache.conf /etc/redis/qbazaar-cache.conf
root# ( umask 077; openssl rand -hex 32 > /root/qbazaar-secrets/redis_cache_password )
root# printf 'requirepass %s\n' "$(cat /root/qbazaar-secrets/redis_cache_password)" > /etc/redis/qbazaar-cache-auth.conf
root# chown root:redis /etc/redis/qbazaar-cache-auth.conf && chmod 640 /etc/redis/qbazaar-cache-auth.conf
root# install -m 644 ~qbazaar/qbazaar/deploy/systemd/redis-cache.service /etc/systemd/system/
root# systemctl daemon-reload && systemctl enable --now redis-cache
root# REDISCLI_AUTH=$(cat /root/qbazaar-secrets/redis_cache_password) redis-cli -p 6380 config get maxmemory-policy   # allkeys-lru
```

**5c. Point Laravel's cache at it.**

```bash
root# echo 'REDIS_CACHE_PORT=6380' >> ~qbazaar/.qbazaar-deploy.env
root# bash ~qbazaar/qbazaar/deploy/scripts/render-env.sh
root# grep -E '^REDIS_(CLIENT|CACHE_HOST|CACHE_PORT)=' ~qbazaar/qbazaar/qbazaar-api/.env     # phpredis, 127.0.0.1, 6380
qbazaar$ php artisan config:cache && sudo systemctl reload ea-php84-php-fpm && sudo systemctl restart qbazaar-horizon
root# systemctl restart qbazaar-scheduler qbazaar-reverb       # chat reconnects within seconds
```

**Check:** `curl -s https://$API_HOST/api/v1/health/ready` shows `"cache":"ok"`; the home page loads; `REDISCLI_AUTH=… redis-cli -p 6380 dbsize` grows; `php -m | grep -x redis` (phpredis). After a day, free the old cache database on the queue server: `REDISCLI_AUTH=$RPASS redis-cli -n 1 flushdb` (database 1 only; queues are in 0).
**Rollback:** remove `REDIS_CACHE_PORT` from the settings file, re-run `render-env.sh`, `config:cache`, reload PHP-FPM, restart Horizon, scheduler and Reverb; then `systemctl disable --now redis-cache`. AOF: `config set appendonly no`, remove the include line.
**Risk:** a wrong cache password turns every cached read into a 500. Keep a second shell open and run the check right after 5c.

## 6. MariaDB tuning and slow query log (OPS-18.13)

```bash
root# mysql -NBe "SELECT @@innodb_buffer_pool_size, @@max_connections, @@slow_query_log, @@long_query_time" | tee /root/mariadb-before.txt
root# install -m 644 ~qbazaar/qbazaar/deploy/mariadb/zz-qbazaar.cnf /etc/my.cnf.d/zz-qbazaar.cnf
root# my_print_defaults --mysqld | tail -25          # our values last
root# mysql -e "SET GLOBAL innodb_buffer_pool_size = 1280*1024*1024; SET GLOBAL max_connections = 150;
                SET GLOBAL thread_cache_size = 32; SET GLOBAL table_open_cache = 2000; SET GLOBAL table_definition_cache = 1400;
                SET GLOBAL tmp_table_size = 64*1024*1024; SET GLOBAL max_heap_table_size = 64*1024*1024;
                SET GLOBAL innodb_io_capacity = 1000; SET GLOBAL innodb_io_capacity_max = 2000;
                SET GLOBAL slow_query_log_file = '/var/lib/mysql/qbazaar-slow.log'; SET GLOBAL long_query_time = 0.5;
                SET GLOBAL log_slow_verbosity = 'query_plan'; SET GLOBAL slow_query_log = ON;"
```

`innodb_log_file_size`, `innodb_log_buffer_size` and `innodb_flush_method` take effect at the next MariaDB restart; do it in a quiet hour with the API in maintenance mode (`/scripts/restartsrv_mysql`, about a minute) or leave it for the next reboot. If WHM → *Edit Database Configuration* is ever used, check it did not write conflicting values into `/etc/my.cnf`.

**Check:** `mysql -NBe "SHOW GLOBAL VARIABLES LIKE 'slow_query_log%'"`; after a while `/var/lib/mysql/qbazaar-slow.log` exists. The weekly digest is installed in step 9.
**Rollback:** delete the file and `SET GLOBAL` the values from `/root/mariadb-before.txt`.

## 7. Meilisearch limits and snapshots (OPS-18.15)

```bash
root# cp -p /etc/meilisearch.toml /root/meilisearch.toml.bak
root# grep -E '^(max_indexing|schedule_snapshot)' /etc/meilisearch.toml        # must print nothing
root# grep -v '^#' ~qbazaar/qbazaar/deploy/meilisearch/limits.toml >> /etc/meilisearch.toml
root# sed -i "s/^max_indexing_threads = .*/max_indexing_threads = $(( $(nproc) > 2 ? $(nproc) / 2 : 1 ))/" /etc/meilisearch.toml
root# cp ~qbazaar/qbazaar/deploy/systemd/meilisearch.service /etc/systemd/system/ && systemctl daemon-reload
root# systemctl restart meilisearch && sleep 3 && curl -s 127.0.0.1:7700/health
```

**Check:** `{"status":"available"}`; a search on the site returns results; `systemctl show meilisearch -p Nice -p CPUWeight -p MemoryHigh`. The next day `/var/lib/meilisearch/snapshots/data.ms.snapshot` exists; then run `bash /usr/local/lib/qbazaar-ops/meili-restore-test.sh` (after step 9) once: that is the acceptance "restore tested once".
**Rollback:** restore `/root/meilisearch.toml.bak`, delete the `Nice`, `CPUWeight`, `IOWeight` and `MemoryHigh` lines from `/etc/systemd/system/meilisearch.service`, daemon-reload, restart.

## 8. Reverb capacity (OPS-18.14)

```bash
root# httpd -V | grep -i 'server mpm'                                    # want: event
root# cp ~qbazaar/qbazaar/deploy/systemd/qbazaar-reverb.service /etc/systemd/system/ && systemctl daemon-reload
root# systemctl restart qbazaar-reverb
root# cat /proc/$(systemctl show -p MainPID --value qbazaar-reverb)/limits | grep 'open files'   # 65535
```

Then, for more than ~1000 sockets:

1. An event loop without the 1024-descriptor cap: `dnf install -y libuv-devel` (EPEL) and `/opt/cpanel/ea-php84/root/usr/bin/pecl install uv-beta` (the extension only has beta releases; check it builds on PHP 8.4). If it does not, `pecl install ev` works too: ReactPHP, under Reverb, uses uv, ev or event, whichever is loaded. Enable it for ea-php84, check `php -m | grep -xE 'uv|ev'`, restart Reverb.
2. If the MPM is not `event`: switch it in WHM → EasyApache 4 (prefork holds a whole process per socket). In WHM → Apache Configuration → Global Configuration set `ServerLimit 16`, `ThreadsPerChild 64`, `MaxRequestWorkers 1024`.
3. Run `tools/load/k6/chat.js` on staging (step 14). If Apache is the limit, plan the `ws.` host (deploy/README.md → Reverb capacity).

**Check:** the chat on the web connects (browser dev tools → WS → 101) and live messages arrive.
**Rollback:** delete the `LimitNOFILE` and `Nice` lines from `/etc/systemd/system/qbazaar-reverb.service`, daemon-reload, restart; `pecl uninstall uv` (or `ev`).

## 9. Backups and the restore test (OPS-18.7)

**9a. Tools and remotes.**

```bash
root# dnf install -y rclone jq           # or the rclone.org RPM if the repo's is old
root# rclone config
```

Create three remotes in `/root/.config/rclone/rclone.conf` (mode 600):

- `r2`: type `s3`, provider Cloudflare, with an R2 API token that has **Object Read** on the two media buckets only.
- `offsite-raw`: the off-site storage (another provider than Cloudflare, e.g. Backblaze B2 or Hetzner Storage Box), in a bucket or folder for QBazaar only.
- `offsite`: type **`crypt`** over `offsite-raw:qbazaar`, with a generated password and salt. Store both outside the server now (step 1).

**9b. Install the ops scripts, cron and logrotate.**

```bash
root# bash ~qbazaar/qbazaar/deploy/scripts/install-ops.sh
root# vi /etc/qbazaar/ops.env
#   BACKUP_OFFSITE=offsite:
#   BACKUP_MEDIA_SOURCES=r2:<R2_BUCKET> r2:<R2_PUBLIC_BUCKET>
#   BACKUP_HEARTBEAT_URL=<from the monitor, step 10>
root# /usr/local/lib/qbazaar-ops/backup.sh                  # first run: the media mirror copies everything once
```

**9c. Acceptance: a successful restore on a copy.**

```bash
root# /usr/local/lib/qbazaar-ops/restore-test.sh                          # newest local dump
root# RESTORE_FROM_OFFSITE=1 /usr/local/lib/qbazaar-ops/restore-test.sh   # newest off-site dump: proves the crypt keys
root# /usr/local/lib/qbazaar-ops/meili-restore-test.sh                    # once a snapshot exists (step 7)
```

Each ends with `PASSED`. Note how long the restore took; when it is acceptable, uncomment the two monthly lines in `/etc/cron.d/qbazaar-ops`. Re-run `install-ops.sh` after any change to `deploy/scripts/`, `deploy/cron/` or `deploy/logrotate/`: cron runs the installed copies.

**Check:** `/var/log/qbazaar/backup.log` shows `backup complete` the next morning; the heartbeat monitor is green.
**Rollback:** `rm /etc/cron.d/qbazaar-ops`; the scripts change nothing else.
**Risk:** the first media mirror reads every R2 object once (R2 egress is free; Class B reads are billed per million) and writes them all to the off-site store; run it off-peak.

## 10. Health checks, Sentry and uptime alerts (OPS-18.8)

1. `curl -s https://$API_HOST/api/v1/health/ready` → `{"status":"ok","checks":{…}}`. A `fail` or `stale` names the dependency to look at; the reason is in `storage/logs/laravel.log` ("Readiness check failed").
2. Create the monitors listed in [deploy/README.md → Health checks and alerts](../deploy/README.md#health-checks-and-alerts) (UptimeRobot or Better Stack, free plans): the web home, `/api/v1/health/ready` for 200, the same URL for the keyword `"status":"ok"` after 5 minutes, and the backup heartbeat. Alert by e-mail and a phone channel.
3. Sentry: create the project (Laravel), put the DSN in `api.overrides.env` (step 1), `render-env.sh`, `config:cache`, reload PHP-FPM, restart Horizon, `php artisan sentry:test`. Alert rules: new issue, and more than 50 events in 5 minutes.

**Check (the acceptance "an alert arrives when it goes down"):** use the monitor's "send test alert", then a real one in a quiet minute: `root# systemctl stop qbazaar-horizon`, wait for the `degraded` alert (about 5 minutes), `systemctl start qbazaar-horizon`, wait for the recovery message.
**Rollback:** remove the DSN; pause the monitors.

## 11. Pulse off (OPS-18.16)

The template now has `PULSE_ENABLED=false`, so the next `render-env.sh` run writes it. Without waiting for one: add `PULSE_ENABLED=false` to `/root/qbazaar-secrets/api.overrides.env`, run `render-env.sh`, `config:cache`, reload PHP-FPM, restart Horizon.

**Check:** `qbazaar$ php artisan tinker --execute="var_dump(config('pulse.enabled'));"` → `false`; `SELECT COUNT(*) FROM pulse_entries` stops growing. Old rows can go: `TRUNCATE pulse_entries; TRUNCATE pulse_aggregates; TRUNCATE pulse_values;`.
**Rollback:** set it to `true` the same way.

## 12. Cloudflare rules (OPS-18.18, OPS-18.9)

Follow [deploy/cloudflare/README.md](../deploy/cloudflare/README.md): WAF custom rules, the one rate-limiting rule, the three cache rules, Smart Tiered Cache, managed rules on, Bot Fight Mode off. Create each rule in *Log* mode (or with the action "Log" where the plan allows) for a day where possible, then switch to the action in `rules.json`.

**Check:** `curl -s -o /dev/null -w '%{http_code}\n' https://$API_HOST/wp-login.php` → 403; 25 quick `POST`s to `/api/v1/auth/login` from one IP get 429 from Cloudflare (the response has `cf-ray` and no Laravel envelope); a cdn. image shows `cf-cache-status: HIT` on the second request; the web app and the mobile app sign in, search, chat and upload. After a day: `bash deploy/cloudflare/cache-hit-ratio.sh` (media above 90%).
**Rollback:** disable the rule in the dashboard (takes effect in seconds).

## 13. The origin answers only Cloudflare (OPS-18.9)

The API vhost has had an Apache-level lock since 2026-10-21 (`zz-cloudflare-only.conf`, made by hand). This step replaces it with the reviewed copy and adds the same lock to the web vhost:

```bash
root# diff <(grep -o "ipmatch '[^']*'" /etc/apache2/conf.d/userdata/ssl/2_4/qbazaar/$API_HOST/zz-cloudflare-only.conf | sort) \
           <(grep -o "ipmatch '[^']*'" ~qbazaar/qbazaar/deploy/apache/cloudflare-only.conf | sort)    # review differences first
# then the install commands in the header of deploy/apache/cloudflare-only.conf
```

**Check:** through Cloudflare both hosts answer 200; from a machine outside Cloudflare, `curl -sk -o /dev/null -w '%{http_code}\n' --resolve $API_HOST:443:<server IP> https://$API_HOST/up` → 403, and the same for `$WEB_HOST`; the next deploy's health probe still passes (it calls the box's own IP, which the file allows).
**Rollback:** delete the new files (keep the API one if you want the old state), `/scripts/ensure_vhost_includes --user=qbazaar`, `apachectl configtest && systemctl reload httpd`.
**Risk:** while this is on, turning a record to "DNS only" (grey cloud) in Cloudflare makes that host answer 403 to everyone.

**Recommended, right after step 13: a host firewall.** No firewall runs today (only cPHulk). The server's real IP address is in this repo's git history from before it was replaced by placeholders, so assume it is known: only a firewall that admits 80/443 solely from Cloudflare, and restricts SSH, WHM (2087) and cPanel (2083), actually closes that door. It is a bigger change on a cPanel box and needs the console at hand:

1. `dnf install -y firewalld && systemctl enable --now firewalld`, then `/scripts/configure_firewall_for_cpanel` so SSH (22), WHM (2087), cPanel (2083) and mail stay open.
2. Two ipsets `cloudflare4`/`cloudflare6` filled from `https://www.cloudflare.com/ips-v4` and `ips-v6`; rich rules accepting 80/443 from them; remove the `http`/`https` services from the public zone. Try it with runtime rules first (no `--permanent`): `firewall-cmd --reload` undoes them if something breaks.
3. SSH stays open to all (GitHub Actions deploys from changing addresses) with keys only: `sshd -T | grep -E 'passwordauthentication|permitrootlogin'` should show `no` / `prohibit-password`. WHM/cPanel: restrict to the owner's addresses in WHM → Host Access Control if they are stable.

**Rollback:** `systemctl disable --now firewalld`.

## 14. Load test on staging (OPS-18.17)

Never against production. [tools/load/k6/README.md](../tools/load/k6/README.md) has the staging plan (a copy of this box, 100k ads and 1M media rows, test users with tokens), the scripts (browse, search, chat, upload) and the report template: p95 per endpoint and the arrival rate where each one breaks. The results set the numbers in steps 4, 5, 6 and 8; record them in the PR that closes OPS-18.17.

---

## Restore (for real)

**Database** (from `/var/backups/qbazaar/db/` or `rclone copy offsite:db/daily/<file> /var/backups/qbazaar/db/`):

```bash
qbazaar$ php artisan down --render="errors::503" --retry=60
root#    systemctl stop qbazaar-horizon qbazaar-scheduler
root#    zcat /var/backups/qbazaar/db/qbazaar_app-<stamp>.sql.gz | mysql qbazaar_app   # drops and recreates each table
qbazaar$ php artisan migrate --force && php artisan config:cache
root#    systemctl reload ea-php84-php-fpm && systemctl start qbazaar-horizon qbazaar-scheduler
qbazaar$ php artisan scout:import "App\Models\Ad"
qbazaar$ php artisan up
```

Everything written after the dump is lost (up to a day). If `APP_KEY` changed since the dump, restore it from the config bundle first.

**Search:** restore the snapshot (`rclone copy offsite:meili/data.ms.snapshot /var/lib/meilisearch/snapshots/`, stop Meilisearch, move `data.ms` aside, start once with `--import-snapshot`), or rebuild: `scout:sync-index-settings` + `scout:import`.

**Media:** `rclone copy offsite:media/<source>/current <source>` (e.g. `r2:<bucket>`), or one file from `offsite:media/<source>/deleted/<date>/`.

**Config:** `rclone copy offsite:config/<file> /root/` then `tar -xzf … -C /tmp/restore` and copy back what is needed. `/root/qbazaar-secrets` and both `.env` files are in it.

## Risk notes

- **OPcache without timestamp checks** (step 3) is the one change that alters day-to-day work: every code or config change needs a PHP-FPM reload. The deploy does it and fails loudly when it cannot.
- **The cache password** (step 5) is the most likely way to cause an outage here; the readiness check shows it within a minute.
- **Memory is tight** (7.7 GB, budget in deploy/README.md). Watch `free -m` and `dmesg | grep -i oom` for a week after steps 4–7; lower `pm_max_children` or the InnoDB buffer pool first.
- **Two copies of the Cloudflare ranges** (WHM's `includes/cloudflare.conf` for real visitor IPs, and `cloudflare-only.conf`) must stay equal when Cloudflare adds a range.
- **Root runs repo files** (`render-env.sh`, `install-ops.sh`): review `deploy/` changes before running them; cron itself only runs the installed root-owned copies.
- **Backups contain personal data:** they leave the box only through the `crypt` remote, and the local copies are root-only.
- **Rate limits at the edge are per IP** and Qatari mobile networks share addresses; the thresholds are loose on purpose. Watch *Security → Events* for blocked real users in the first week.
