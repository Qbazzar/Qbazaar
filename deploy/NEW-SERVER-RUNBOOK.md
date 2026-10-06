# New server: first-time setup (srv1977263, Mumbai, 2026-10-06)

How the current production server was set up, in order, so it can be repeated or audited. Day-to-day deploys are in [README.md](README.md); promoting a release is in [DEPLOY-CHECKLIST.md](DEPLOY-CHECKLIST.md).

| | |
|---|---|
| Server | Hostinger VPS `srv1977263.hstgr.cloud` in Mumbai, `187.126.119.228`, AlmaLinux 9.8, cPanel/WHM 11.138, 7.7 GB RAM |
| cPanel user | `qbazaar` (bash shell), clone at `/home/qbazaar/qbazaar` on `production` |
| Web | `https://qbazaar.qa` (account main domain, proxied to Next.js on `127.0.0.1:3000`); `www.qbazaar.qa` answers 301 to the apex |
| API + admin | `https://api.qbazaar.qa` (subdomain, docroot `/home/qbazaar/qbazaar/qbazaar-api/public`), WebSocket `wss://api.qbazaar.qa/app` |
| DNS + proxy | Cloudflare (`decker`/`tara.ns.cloudflare.com`); `A` records `@`, `www`, `api` → `187.126.119.228`, all proxied |
| TLS | AutoSSL (Let's Encrypt) on the origin for `qbazaar.qa`, `www` and `api`; Cloudflare SSL mode Full (strict) |
| Database | MariaDB 10.11 (cPanel default; the code needs MySQL 8 or MariaDB ≥ 10.6), database and user `qbazaar_app` |
| Secrets | `/root/qbazaar-secrets/` (root, 700) and the two `.env` files (600). Nothing secret is in the repo. |

The box was first built on 2026-10-05 in Kuala Lumpur (`187.53.139.138`) on temporary sslip.io names, then reinstalled in Mumbai for latency and rebuilt with this runbook directly on the real domain.

## 1. Account and domains (root)

```bash
whmapi1 createacct username=qbazaar domain=qbazaar.qa password=<random> hasshell=1 plan=default
sudo -u qbazaar -H git clone --branch production https://github.com/Qbazzar/Qbazaar.git /home/qbazaar/qbazaar
# cPanel keeps docroots inside public_html unless this tweak is off:
whmapi1 set_tweaksetting key=publichtmlsubsonly value=0
uapi --user=qbazaar SubDomain addsubdomain domain=api rootdomain=qbazaar.qa dir=qbazaar/qbazaar-api/public
```

Clone before adding the subdomain, or cPanel creates the docroot and the clone fails. cPanel drops `php.ini` and `.user.ini` into the docroot and appends a handler to `public/.htaccess`; the deploy's `git reset --hard` restores `.htaccess`, and the two ini files are harmless.

AutoSSL ran on its own when the account was created; re-run with `/usr/local/cpanel/bin/autossl_check --user=qbazaar`. Behind the Cloudflare proxy the HTTP-01 check still works as long as Cloudflare does not redirect HTTP to HTTPS before the origin has a certificate: check first that `curl -sI http://qbazaar.qa/<marker>` shows `server: cloudflare` and a marker file from `~/public_html`. The other names cPanel adds (`mail.`, `cpanel.`, `www.api.` …) have no DNS record and are skipped; that is expected.

## 2. PHP 8.4 (root)

```bash
dnf install -y ea-php84 ea-php84-pear ea-php84-php-{cli,common,fpm,devel,bcmath,calendar,curl,exif,fileinfo,gd,iconv,intl,mbstring,mysqlnd,pdo,posix,process,sockets,sodium,xml,zip,opcache}
printf 'no\n%.0s' {1..6} | /opt/cpanel/ea-php84/root/usr/bin/pecl install redis     # phpredis
whmapi1 php_ini_set_directives version=ea-php84 directive-1=upload_max_filesize:10M directive-2=post_max_size:105M \
  directive-3=max_file_uploads:20 directive-4=memory_limit:256M directive-5=allow_url_fopen:On
whmapi1 php_set_vhost_versions version=ea-php84 vhost-1=qbazaar.qa vhost-2=api.qbazaar.qa
```

`pcntl` is built into the cPanel CLI. `allow_url_fopen` is off by default on ea-php84 and Composer needs it. Both vhosts run PHP-FPM, so cPanel pins 8.4 in the vhost itself; the API include no longer sets a PHP handler.

## 3. Tools (root)

```bash
dnf module enable -y nodejs:20 redis:7 && dnf install -y nodejs npm redis
npm install -g npm@11          # the web lockfile is written by npm 11; npm 10 rejects it in `npm ci`
# Composer: installer checked against https://composer.github.io/installer.sig, installed to /usr/local/bin/composer
```

Redis: `/etc/redis/qbazaar.conf` (included from `redis.conf`, root:redis 640) binds to loopback and sets `requirepass` (from `/root/qbazaar-secrets/redis_password`), `maxmemory 1gb` and `noeviction`, so queued jobs are never evicted. Meilisearch: [README → Meilisearch](README.md#meilisearch-install-once-as-root) (v1.12.8 because of glibc); keep the master key in `/root/qbazaar-secrets/meili_master_key`.

## 4. Database (root)

```bash
uapi --user=qbazaar Mysql create_database name=qbazaar_app
uapi --user=qbazaar Mysql create_user name=qbazaar_app password="$(cat /root/qbazaar-secrets/db_password)"   # openssl rand -hex 24
uapi --user=qbazaar Mysql set_privileges_on_database user=qbazaar_app database=qbazaar_app privileges='ALL PRIVILEGES'
mysql -e "ALTER DATABASE qbazaar_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

Root's `~/.my.cnf` overrides `MYSQL_PWD`, so test the app user with `mysql --no-defaults`.

## 5. Environment files (root)

Write `~qbazaar/.qbazaar-deploy.env` ([README → Server settings file](README.md#server-settings-file)), then, as root:

```bash
bash /home/qbazaar/qbazaar/deploy/scripts/render-env.sh
```

The script holds no secrets. It reads the templates, the settings file and one file per secret in `/root/qbazaar-secrets/` (`db_password`, `redis_password` and `meili_master_key` are written in steps 3 and 4; `app_key`, `reverb_app_key` and `reverb_app_secret` are generated on its first run and reused). It writes both `.env` files owned by `qbazaar`, mode 600, and refuses to leave a `__FILL_ME__`. On top of the template it sets the hosts (`APP_URL`, `WEB_URL`, `CORS_ALLOWED_ORIGINS` with the apex and every `WEB_ALIASES` host, the web `NEXT_PUBLIC_*` values), `REDIS_CLIENT=phpredis`, `MAIL_MAILER=log`, Turnstile, the new-device check, R2 and social sign-in off, password sign-in on, and `SESSION_SECURE_COOKIE` from `URL_SCHEME`. Lasting changes (mail, R2 keys) go in `/root/qbazaar-secrets/api.overrides.env` or `web.overrides.env`, not in the `.env` files, because a re-run rewrites them.

## 6. Services, sudoers, Apache (root)

```bash
cp ~qbazaar/qbazaar/deploy/systemd/*.service /etc/systemd/system/ && systemctl daemon-reload
systemctl enable --now meilisearch qbazaar-horizon qbazaar-reverb qbazaar-scheduler qbazaar-web
cat > /etc/sudoers.d/qbazaar-deploy <<'EOF'
qbazaar ALL=(root) NOPASSWD: /usr/bin/systemctl restart qbazaar-web, /usr/bin/systemctl restart qbazaar-horizon, /usr/bin/systemctl restart qbazaar-reverb
EOF
chmod 440 /etc/sudoers.d/qbazaar-deploy
```

The scheduler runs from the unit only (no cron line). Apache includes: install commands are in the header of `apache/api.include.conf` and `apache/web.include.conf` (both `std` and `ssl` tiers).

The web include also sends `www.` to the apex with a 301 (ACME paths excepted).

Behind Cloudflare: WHM generates `/etc/apache2/conf.d/includes/cloudflare.conf` (`mod_remoteip`, `RemoteIPHeader CF-Connecting-IP`, Cloudflare's ranges), so Apache logs and PHP see the visitor's address. Laravel's `config/trustedproxy.php` trusts the same ranges, so keep `TRUSTED_PROXIES` empty.

Firewall: no host firewall is enabled (only cPHulk); 22, 80, 443, 2083 and 2087 are reachable. Locking it down to Cloudflare is OPS-18.9.

## 7. GitHub Actions

A dedicated ed25519 key: the public half is `keys/github-actions.pub` and is in `~qbazaar/.ssh/authorized_keys`; the private half exists only in the `DEPLOY_SSH_KEY` secret. Generate a new pair for each new server, set the secrets from files or stdin so nothing is echoed, then delete the local private key:

```bash
ssh-keygen -q -t ed25519 -N "" -C github-actions-deploy@qbazaar.qa -f ./id
gh secret set DEPLOY_SSH_KEY < ./id
printf '187.126.119.228' | gh secret set DEPLOY_HOST
printf '22' | gh secret set DEPLOY_PORT
printf 'qbazaar' | gh secret set DEPLOY_USER
rm ./id
```

## 8. First deploy and data

Deploy through Actions, one after the other (two builds at once fail with "another build is running"):

```bash
gh workflow run deploy-api.yml --ref production    # wait for success
gh workflow run deploy-web.yml --ref production
```

Then start the scheduler once (`systemctl start qbazaar-scheduler`, as root; the deploys start the other units) and, as `qbazaar`:

```bash
cd ~/qbazaar/qbazaar-api
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan permission:cache-reset && php artisan cache:clear
php artisan scout:sync-index-settings
# demo data (development only): type the database name at the prompt
printf 'qbazaar_app\n' | SHELL_INTERACTIVE=1 php artisan qbazaar:demo --fresh --force --sync-media > ~/demo-run.log 2>&1
chmod 600 ~/demo-run.log      # it holds the generated demo password
php artisan scout:import "App\Models\Ad"
```

## 9. Cloudflare dashboard

- SSL/TLS → Overview: **Full (strict)**, once AutoSSL has installed the origin certificate.
- SSL/TLS → Edge Certificates: **Always Use HTTPS** on. Leave it off until the origin certificate exists, or the HTTP-01 check fails.
- Network: **WebSockets** on (default) for `wss://api.qbazaar.qa/app`.
- Caching → Cache Rules: bypass the cache for hostname `api.qbazaar.qa`, except `/storage/*` if the API serves images.
- Free and Pro plans cap a request body at 100 MB; see [README → Upload body size](README.md#upload-body-size).
