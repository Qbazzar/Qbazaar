#!/usr/bin/env bash
# QBazaar — API deploy (WHM/cPanel VPS · api.qbazzar.fleeteye.de)
#
# Invoked by .github/workflows/deploy-api.yml over SSH as the `fleeteye` user.
# Assumes:
#  - Repo clone:   /home/fleeteye/qbazaar  (tracks origin/production)
#  - The api.* subdomain docroot already points at qbazaar-api/public (cPanel)
#  - PHP 8.4 CLI + Composer on PATH; MySQL + Redis panel-installed
#  - systemd units qbazaar-horizon / qbazaar-reverb exist, and `fleeteye` has a
#    sudoers drop-in allowing `systemctl restart` of them (no password).

set -euo pipefail
shopt -s inherit_errexit

REPO_DIR="${REPO_DIR:-$HOME/qbazaar}"
API_DIR="$REPO_DIR/qbazaar-api"
BRANCH="${DEPLOY_BRANCH:-production}"
HEALTH_URL="${HEALTH_URL:-https://api.qbazzar.fleeteye.de/api/v1/health}"

log() { printf '\n\033[1;36m> %s\033[0m\n' "$*"; }

cd "$REPO_DIR"
log "Fetching origin"
git fetch origin --prune
log "Resetting to origin/$BRANCH"
git reset --hard "origin/$BRANCH"

cd "$API_DIR"

if [[ -f artisan ]]; then
    log "Maintenance mode on"
    php artisan down --render="errors::503" --retry=15 --secret="deploy-$(date +%s)" || true
fi
cleanup() { [[ -f "$API_DIR/artisan" ]] && ( cd "$API_DIR" && php artisan up || true ); }
trap cleanup EXIT

log "composer install --no-dev --optimize-autoloader"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

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

log "Health probe ($HEALTH_URL)"
sleep 1
HEALTH=$(curl -fsS -m 5 -o /dev/null -w "%{http_code}" "$HEALTH_URL" || echo "000")
echo "  health => $HEALTH"
if [[ "$HEALTH" != "200" ]]; then
    echo "Health probe failed (expected 200, got $HEALTH). Check storage/logs/laravel.log + Apache error logs."
    exit 1
fi

log "API deploy complete."
