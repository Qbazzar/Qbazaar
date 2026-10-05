#!/usr/bin/env bash
# QBazaar — API deploy (WHM/cPanel VPS, cPanel user `qbazaar`)
#
# Invoked by .github/workflows/deploy-api.yml over SSH as the deploy user.
# Hosts, paths and the PHP binary come from common.sh (env, then
# ~/.qbazaar-deploy.env, then defaults). Assumes:
#  - Repo clone at $REPO_DIR (tracks origin/production)
#  - The API_HOST docroot points at qbazaar-api/public (cPanel)
#  - PHP 8.4 CLI + Composer; MySQL, Redis and Meilisearch running
#  - systemd units qbazaar-horizon / qbazaar-reverb exist, and the deploy user
#    has a sudoers drop-in allowing `systemctl restart` of them (no password).

set -euo pipefail
shopt -s inherit_errexit

# shellcheck source=deploy/scripts/common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

API_DIR="$REPO_DIR/qbazaar-api"
HEALTH_HOST="${HEALTH_HOST:-$API_HOST}"

reset_to_branch

cd "$API_DIR"

if [[ -f artisan ]]; then
    log "Maintenance mode on"
    php artisan down --render="errors::503" --retry=15 --secret="deploy-$(date +%s)" || true
fi
cleanup() { [[ -f "$API_DIR/artisan" ]] && ( cd "$API_DIR" && php artisan up || true ); }
trap cleanup EXIT

log "composer install --no-dev --optimize-autoloader"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Build the Vite bundle for the server-rendered surfaces (the custom /manage
# admin panel + welcome page). Assets are gitignored, so they must be compiled
# on the box. Node/npm are already present on this VPS (the web deploy uses them).
# `npm install` (not `ci`): the API has no committed package-lock.json, and this
# dependency set only feeds the Vite/Tailwind build for server-rendered views.
log "npm install + vite build"
npm install --no-audit --no-fund
NODE_OPTIONS="--max-old-space-size=1536" npm run build

log "Migrations"
php artisan migrate --force --no-interaction

log "Rebuilding caches"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache || true

if [[ ! -L public/storage ]]; then
    log "Linking storage"
    php artisan storage:link
fi

log "Restarting workers (horizon + reverb)"
sudo systemctl restart qbazaar-horizon
sudo systemctl restart qbazaar-reverb

# Leave maintenance mode BEFORE probing (the trap also runs `up`, but the probe
# must hit a live app, not the 503 maintenance page).
log "Bringing application up"
php artisan up || true

# Probe Apache on the box's own primary IPv4 with the vhost Host header. We
# avoid the public FQDN because the server resolves its own domains to ::1
# (a self-signed default vhost), which would false-fail the probe.
log "Health probe (Host: $HEALTH_HOST)"
sleep 1
SERVER_IP=$(hostname -I | tr ' ' '\n' | grep -m1 -E '^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$')
HEALTH=$(curl -fsS -m 5 -o /dev/null -w "%{http_code}" -H "Host: $HEALTH_HOST" "http://${SERVER_IP}/api/v1/health" || echo "000")
echo "  health => $HEALTH"
if [[ "$HEALTH" != "200" ]]; then
    echo "Health probe failed (expected 200, got $HEALTH). Check storage/logs/laravel.log + Apache error logs."
    exit 1
fi

log "API deploy complete."
