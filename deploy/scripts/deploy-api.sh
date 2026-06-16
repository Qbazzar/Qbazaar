#!/usr/bin/env bash
# QBazaar — API deploy (WHM/cPanel VPS · api.qbazaar.fleeteye.de)
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

# cPanel: put the ea-php84 CLI binary + composer first so bare `php`/`composer`
# resolve to the 8.4 CLI SAPI in the minimal non-login PATH that GitHub
# Actions's SSH shell provides (/usr/bin/php is a cgi-fcgi wrapper).
export PATH="/opt/cpanel/ea-php84/root/usr/bin:/usr/local/bin:$PATH"

REPO_DIR="${REPO_DIR:-$HOME/qbazaar}"
API_DIR="$REPO_DIR/qbazaar-api"
BRANCH="${DEPLOY_BRANCH:-production}"
HEALTH_HOST="${HEALTH_HOST:-api.qbazaar.fleeteye.de}"

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
