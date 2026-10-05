#!/usr/bin/env bash
# QBazaar — Web (Next.js) deploy (WHM/cPanel VPS, cPanel user `qbazaar`)
#
# Invoked by .github/workflows/deploy-web.yml over SSH as the deploy user.
# Builds in place, then restarts the qbazaar-web systemd unit (the sudoers
# drop-in permits the restart). Reads qbazaar-web/.env.production (untracked,
# survives `git reset` because it's gitignored). Settings: see common.sh.

set -euo pipefail
shopt -s inherit_errexit

# shellcheck source=deploy/scripts/common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

WEB_DIR="$REPO_DIR/qbazaar-web"
HEALTH_URL="${HEALTH_URL:-http://127.0.0.1:3000/}"

reset_to_branch

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
