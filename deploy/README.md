# QBazaar — Production Deploy

> **Production today:** one WHM/cPanel server, cPanel user `fleeteye`, repo clone at `/home/fleeteye/qbazaar`.
> Web: `https://qbazaar.fleeteye.de` · API + admin: `https://api.qbazaar.fleeteye.de`.
> **Next:** M5 moves everything to the new cPanel VPS (`srv1977263.hstgr.cloud`) behind Cloudflare, once the domain is decided (tasks `OPS-18.x` in [MILESTONES-V2.md](../qbazaar-contracts/MILESTONES-V2.md)). The earlier hosts (CloudPanel `miete.site`, then `qbazaar.taqat.space`) are gone.

First-time server setup is in [CPANEL-FLEETEYE-RUNBOOK.md](CPANEL-FLEETEYE-RUNBOOK.md) (Arabic). This file covers the day-to-day flow.

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

**Repository secrets** (`Settings → Secrets and variables → Actions`): `DEPLOY_HOST`, `DEPLOY_USER` (`fleeteye`), `DEPLOY_PORT`, `DEPLOY_SSH_KEY`. `deploy-api.yml` also accepts `DEPLOY_PASSWORD` as a bootstrap fallback; remove it once key auth works.

## What the deploy scripts do

**`deploy-api.sh`:** reset the clone to `origin/production` → maintenance mode → `composer install --no-dev` → `npm install && npm run build` (Tailwind assets for `/admin`) → `migrate --force` → rebuild config/route/view/event caches → `storage:link` if missing → restart `qbazaar-horizon` and `qbazaar-reverb` → leave maintenance mode → health probe on `/api/v1/health` (fails the deploy if not 200).

**`deploy-web.sh`:** reset to `origin/production` → `npm ci` → `next build` → restart `qbazaar-web` → probe `http://127.0.0.1:3000/`.

Both scripts put the `ea-php84` CLI first on `PATH`. The `fleeteye` user may restart only `qbazaar-web`, `qbazaar-horizon` and `qbazaar-reverb` through a sudoers drop-in.

## Services on the server

| Unit (`deploy/systemd/`) | Runs |
|--------------------------|------|
| `qbazaar-horizon.service` | `artisan horizon` (queues `default` and `low`) |
| `qbazaar-reverb.service` | `artisan reverb:start` on `127.0.0.1:8080`, proxied as `wss://api…/app` |
| `qbazaar-scheduler.service` | `artisan schedule:work` (ad and offer expiry, etc.). Use it **or** the cron entry in the runbook, never both. |
| `qbazaar-web.service` | `next start -p 3000`, proxied by Apache |
| `meilisearch.service` | Meilisearch on `127.0.0.1:7700` (section below) |

Apache includes for both vhosts are in `deploy/apache/` (install steps are in each file's header). The API include pins PHP for the vhost, serves `/storage` as static files only, and proxies the Reverb WebSocket.

## Environment files

- API: copy `env.production.template` to `qbazaar-api/.env` on the server and fill every `__FILL_ME__`. Search must stay on `SCOUT_DRIVER=meilisearch` with `SCOUT_QUEUE=true`.
- Web: copy `web.env.production.template` to `qbazaar-web/.env.production`. `NEXT_PUBLIC_*` values are compiled in, so a change needs a web deploy, not just a restart.

Both files are gitignored and survive the `git reset --hard` the scripts do.

## Pending production steps after M0

The M0 PRs (#147–#153) need these one-time steps on the server the first time they reach `production`:

1. `.env`: `SCOUT_DRIVER=meilisearch`, `SCOUT_QUEUE=true`, then `php artisan config:cache` (#147).
2. Install the scheduler unit (`cp deploy/systemd/qbazaar-scheduler.service /etc/systemd/system/ && systemctl daemon-reload && systemctl enable --now qbazaar-scheduler`) or the cron entry, and restart Horizon so it picks up the `low` queue (#147).
3. Re-copy the API Apache include into both the `std` and `ssl` userdata dirs, then `/scripts/ensure_vhost_includes --user=fleeteye && apachectl configtest && systemctl restart httpd`; needs `mod_headers` (#150). Check `storage/app/public` for old `*.php*` or `*.html` uploads.
4. `php artisan db:seed --class=RolesAndPermissionsSeeder --force`, then `php artisan permission:cache-reset` (#151). Migrations run in the deploy script.
5. Rebuild the ads index: `php artisan scout:flush "App\Models\Ad" && php artisan scout:import "App\Models\Ad"` (#152).

Clients must now authorise channels at `POST /api/v1/broadcasting/auth` with a Bearer token (#149); the web already does.

## Meilisearch (install once, as root)

```bash
curl -L https://install.meilisearch.com | sh
mv ./meilisearch /usr/local/bin/
useradd -r -s /sbin/nologin meilisearch
mkdir -p /var/lib/meilisearch && chown meilisearch: /var/lib/meilisearch

# Loopback only, master key required (shared server)
MASTER_KEY=$(openssl rand -hex 32)
cat > /etc/meilisearch.toml <<TOML
env = "production"
master_key = "${MASTER_KEY}"
db_path = "/var/lib/meilisearch"
http_addr = "127.0.0.1:7700"
TOML
chmod 600 /etc/meilisearch.toml
echo "MEILISEARCH_KEY=${MASTER_KEY}"   # goes into qbazaar-api/.env

cp /home/fleeteye/qbazaar/deploy/systemd/meilisearch.service /etc/systemd/system/
systemctl daemon-reload && systemctl enable --now meilisearch
curl -s http://127.0.0.1:7700/health   # {"status":"available"}
```

Then, as `fleeteye`:

```bash
cd ~/qbazaar/qbazaar-api
# .env: SCOUT_DRIVER=meilisearch, SCOUT_QUEUE=true, MEILISEARCH_HOST=http://127.0.0.1:7700, MEILISEARCH_KEY=<key>
php artisan config:cache
php artisan scout:sync-index-settings
php artisan scout:import "App\Models\Ad"
```

Check with a typo, which only Meilisearch tolerates: `curl -s 'https://api.qbazaar.fleeteye.de/api/v1/search?q=iphnoe' | head -c 400`.

If Meilisearch goes down, keep the driver and restart the service: search returns empty results meanwhile, and `scout:import` rebuilds the index at any time. Do not switch to the `database` driver.

## Manual deploy (if Actions is down)

```bash
ssh fleeteye@<server> "cd ~/qbazaar && bash deploy/scripts/deploy-api.sh"   # or deploy-web.sh
```

The scripts fetch and reset to `origin/production` themselves.

## Folder layout

```
deploy/
├── README.md                       this file
├── CPANEL-FLEETEYE-RUNBOOK.md      first-time setup of the current cPanel server (Arabic)
├── env.production.template         API .env template
├── web.env.production.template     web .env.production template
├── scripts/                        deploy-api.sh, deploy-web.sh (run by the workflows)
├── systemd/                        horizon, reverb, scheduler, web, meilisearch units
├── apache/                         vhost includes for qbazaar.fleeteye.de and api.qbazaar.fleeteye.de
└── keys/github-actions.pub         public key for the deploy user
```
