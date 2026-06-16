#!/usr/bin/env bash
# QBazaar — Web (Next.js) deploy (WHM/cPanel VPS · qbazaar.fleeteye.de)
#
# Invoked by .github/workflows/deploy-web.yml over SSH as the `fleeteye` user.
# Builds in place, then restarts the qbazaar-web systemd unit (the sudoers
# drop-in permits the restart). Reads qbazaar-web/.env.production (untracked,
# survives `git reset` because it's gitignored).

set -euo pipefail
shopt -s inherit_errexit

# cPanel: put the ea-php84 CLI binary + composer first so bare `php`/`composer`
# resolve to the 8.4 CLI SAPI in the minimal non-login PATH that GitHub
# Actions's SSH shell provides (/usr/bin/php is a cgi-fcgi wrapper).
export PATH="/opt/cpanel/ea-php84/root/usr/bin:/usr/local/bin:$PATH"

REPO_DIR="${REPO_DIR:-$HOME/qbazaar}"
WEB_DIR="$REPO_DIR/qbazaar-web"
BRANCH="${DEPLOY_BRANCH:-production}"
HEALTH_URL="${HEALTH_URL:-http://127.0.0.1:3000/}"

log() { printf '\n\033[1;36m> %s\033[0m\n' "$*"; }

cd "$REPO_DIR"
log "Fetching origin"
git fetch origin --prune
log "Resetting to origin/$BRANCH"
git reset --hard "origin/$BRANCH"

cd "$WEB_DIR"

log "npm ci"
npm ci --no-audit --no-fund

log "next build"
NODE_OPTIONS="--max-old-space-size=1536" NEXT_TELEMETRY_DISABLED=1 npm run build

log "Restarting qbazaar-web"
sudo systemctl restart qbazaar-web

# Probe the Next.js server directly (Apache reverse-proxies to it). Avoids the
# box resolving its own public FQDN to ::1 / a self-signed default vhost.
log "Health probe ($HEALTH_URL)"
sleep 2
STATUS=$(curl -fsS -m 8 -o /dev/null -w "%{http_code}" "$HEALTH_URL" || echo "000")
echo "  web => $STATUS"
if [[ "$STATUS" != "200" && "$STATUS" != "307" && "$STATUS" != "308" ]]; then
    echo "Frontend probe failed (expected 200/307/308, got $STATUS). Check: journalctl -u qbazaar-web -n 50."
    exit 1
fi

log "Web deploy complete."
