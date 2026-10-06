# New server: first-time setup (srv1977263, 2026-10-05)

How the current production server was set up, in order, so it can be repeated or audited. Day-to-day deploys are in [README.md](README.md); promoting a release is in [DEPLOY-CHECKLIST.md](DEPLOY-CHECKLIST.md).

| | |
|---|---|
| Server | Hostinger VPS `srv1977263.hstgr.cloud`, `187.53.139.138`, AlmaLinux 9, cPanel/WHM 11.138, 7.5 GB RAM |
| cPanel user | `qbazaar` (bash shell), clone at `/home/qbazaar/qbazaar` on `production` |
| Web | `https://qbazaar.187-53-139-138.sslip.io` (account main domain, proxied to Next.js on `127.0.0.1:3000`) |
| API + admin | `https://api.qbazaar.187-53-139-138.sslip.io` (subdomain, docroot `/home/qbazaar/qbazaar/qbazaar-api/public`) |
| TLS | AutoSSL, Let's Encrypt, both hosts |
| Database | MariaDB 10.11 (cPanel default; the code needs MySQL 8 or MariaDB ≥ 10.6), database and user `qbazaar_app` |
| Secrets | `/root/qbazaar-secrets/` (root, 700) and the two `.env` files (600). Nothing secret is in the repo. |

The sslip.io names resolve to the IP in their label, so they need no DNS. Swap them for the real domain with [README → Switching to the real domain](README.md#switching-to-the-real-domain).

## 1. Account and domains (root)

```bash
whmapi1 createacct username=qbazaar domain=qbazaar.187-53-139-138.sslip.io password=<random> hasshell=1 plan=default
sudo -u qbazaar -H git clone --branch production https://github.com/Qbazzar/Qbazaar.git /home/qbazaar/qbazaar
# cPanel keeps docroots inside public_html unless this tweak is off:
whmapi1 set_tweaksetting key=publichtmlsubsonly value=0
uapi --user=qbazaar SubDomain addsubdomain domain=api rootdomain=qbazaar.187-53-139-138.sslip.io dir=qbazaar/qbazaar-api/public
```

Clone before adding the subdomain, or cPanel creates the docroot and the clone fails. cPanel drops `php.ini` and `.user.ini` into the docroot and appends a handler to `public/.htaccess`; the deploy's `git reset --hard` restores `.htaccess`, and the two ini files are harmless.

AutoSSL ran on its own when the account was created; re-run with `/usr/local/cpanel/bin/autossl_check --user=qbazaar`.

## 2. PHP 8.4 (root)

```bash
dnf install -y ea-php84 ea-php84-pear ea-php84-php-{cli,common,fpm,devel,bcmath,calendar,curl,exif,fileinfo,gd,iconv,intl,mbstring,mysqlnd,pdo,posix,process,sockets,sodium,xml,zip,opcache}
printf 'no\n%.0s' {1..6} | /opt/cpanel/ea-php84/root/usr/bin/pecl install redis     # phpredis
whmapi1 php_ini_set_directives version=ea-php84 directive-1=upload_max_filesize:10M directive-2=post_max_size:105M \
  directive-3=max_file_uploads:20 directive-4=memory_limit:256M directive-5=allow_url_fopen:On
whmapi1 php_set_vhost_versions version=ea-php84 vhost-1=qbazaar.187-53-139-138.sslip.io vhost-2=api.qbazaar.187-53-139-138.sslip.io
```

`pcntl` is built into the cPanel CLI. `allow_url_fopen` is off by default on ea-php84 and Composer needs it. Both vhosts run PHP-FPM, so cPanel pins 8.4 in the vhost itself; the API include no longer sets a PHP handler.

## 3. Tools (root)

```bash
dnf module enable -y nodejs:20 redis:7 && dnf install -y nodejs npm redis
npm install -g npm@11          # the web lockfile is written by npm 11; npm 10 rejects it in `npm ci`
# Composer: installer checked against https://composer.github.io/installer.sig, installed to /usr/local/bin/composer
```

Redis: `/etc/redis/qbazaar.conf` (included from `redis.conf`) binds to loopback and sets `requirepass`. Meilisearch: [README → Meilisearch](README.md#meilisearch-install-once-as-root) (v1.12.8 because of glibc).

## 4. Database (root)

```bash
uapi --user=qbazaar Mysql create_database name=qbazaar_app
uapi --user=qbazaar Mysql create_user name=qbazaar_app password=<random>
uapi --user=qbazaar Mysql set_privileges_on_database user=qbazaar_app database=qbazaar_app privileges='ALL PRIVILEGES'
mysql -e "ALTER DATABASE qbazaar_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

Root's `~/.my.cnf` overrides `MYSQL_PWD`, so test the app user with `mysql --no-defaults`.

## 5. Environment files (root)

Write `~qbazaar/.qbazaar-deploy.env` ([README → Server settings file](README.md#server-settings-file)), then run `bash /root/qbazaar-secrets/render-env.sh`. It generates `APP_KEY` and the Reverb key and secret once, and fills both templates. What it sets on top of the template: `REDIS_CLIENT=phpredis`, `MAIL_MAILER=log`, Turnstile, the new-device check, R2 and social sign-in off, password sign-in on, `SESSION_SECURE_COOKIE` from `URL_SCHEME`.

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

Firewall: no host firewall is enabled (only cPHulk); 22, 80, 443, 2083 and 2087 are reachable. Locking it down to Cloudflare is OPS-18.9.

## 7. First deploy and data (qbazaar)

```bash
bash ~/qbazaar/deploy/scripts/deploy-api.sh && bash ~/qbazaar/deploy/scripts/deploy-web.sh
cd ~/qbazaar/qbazaar-api
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan permission:cache-reset && php artisan cache:clear
php artisan scout:sync-index-settings
# demo data (development only): type the database name at the prompt
printf 'qbazaar_app\n' | SHELL_INTERACTIVE=1 php artisan qbazaar:demo --fresh --force --sync-media > ~/demo-run.log 2>&1
chmod 600 ~/demo-run.log      # it holds the generated demo password
php artisan scout:import "App\Models\Ad"
```

## 8. GitHub Actions

A dedicated ed25519 key: the public half is `keys/github-actions.pub` and is in `~qbazaar/.ssh/authorized_keys`; the private half exists only in the `DEPLOY_SSH_KEY` secret. Secrets: `DEPLOY_HOST=187.53.139.138`, `DEPLOY_PORT=22`, `DEPLOY_USER=qbazaar`, `DEPLOY_SSH_KEY`.
