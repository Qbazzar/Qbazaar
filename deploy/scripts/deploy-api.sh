#!/usr/bin/env bash
# QBazaar — API deploy (CloudPanel managed VPS)
#
# Invoked by .github/workflows/deploy-api.yml via SSH on the VPS.
# Assumes:
#  - SSH user is qb-user, panel tenant for www.miete.site
#  - Repo clone:  /home/qb-user/qbazaar  (tracks origin/production)
#  - Panel docroot:  /home/qb-user/htdocs/www.miete.site → symlink to qbazaar-api/public
#  - No sudo. PHP 8.4 / Composer / MySQL / Redis are panel-installed.

set -euo pipefail
shopt -s inherit_errexit

REPO_DIR="${REPO_DIR:-$HOME/qbazaar}"
API_DIR="$REPO_DIR/qbazaar-api"
DOCROOT="${DOCROOT:-$HOME/htdocs/www.miete.site/public}"
BRANCH="${DEPLOY_BRANCH:-production}"
HEALTH_URL="${HEALTH_URL:-https://miete.site/api/v1/health}"

log() { printf '\n\033[1;36m> %s\033[0m\n' "$*"; }

cd "$REPO_DIR"

log "Fetching origin"
git fetch origin --prune

log "Resetting to origin/$BRANCH"
git reset --hard "origin/$BRANCH"

cd "$API_DIR"

if [[ -f artisan ]]; then
    log "Entering maintenance mode"
    php artisan down --render="errors::503" --refresh=15 --retry=15 --secret="deploy-$(date +%s)" || true
fi

cleanup() {
    if [[ -f "$API_DIR/artisan" ]]; then
        ( cd "$API_DIR" && php artisan up || true )
    fi
}
trap cleanup EXIT

log "Composer install --no-dev --optimize-autoloader"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

log "Linking docroot -> qbazaar-api/public (if not already)"
if [[ ! -L "$DOCROOT" ]]; then
    if [[ -d "$DOCROOT" && -z "$(ls -A "$DOCROOT" 2>/dev/null)" ]]; then
        rmdir "$DOCROOT"
        ln -s "$API_DIR/public" "$DOCROOT"
    elif [[ -d "$DOCROOT" ]]; then
        # docroot exists with content — fall back to per-file symlinks of public/* into it
        log "  docroot exists with content; symlinking public/* into it instead"
        shopt -s dotglob nullglob
        for f in "$API_DIR"/public/*; do
            name="$(basename "$f")"
            [[ "$name" == "storage" ]] && continue   # storage link handled by artisan
            target="$DOCROOT/$name"
            [[ -L "$target" || -e "$target" ]] && rm -rf "$target"
            ln -s "$f" "$target"
        done
        shopt -u dotglob nullglob
    fi
fi

log "Running migrations"
php artisan migrate --force --no-interaction

log "Re-building config / route / view caches"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache || true

if [[ ! -L public/storage ]]; then
    log "Linking storage"
    php artisan storage:link
fi

log "Restarting queue workers"
php artisan queue:restart || true

log "Sanity probe ($HEALTH_URL)"
sleep 1
HEALTH=$(curl -fsS -m 5 -o /dev/null -w "%{http_code}" "$HEALTH_URL" || echo "000")
echo "  health => $HEALTH"
if [[ "$HEALTH" != "200" ]]; then
    echo "Health probe failed (expected 200, got $HEALTH). Investigate ~/logs/nginx/ and storage/logs/laravel.log."
    exit 1
fi

log "Deploy complete."
