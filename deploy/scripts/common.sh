# Shared setup for deploy-api.sh and deploy-web.sh (sourced, not run).
#
# Settings come from, in order of precedence:
#   1. the environment of the caller (e.g. HEALTH_HOST=... bash deploy-api.sh)
#   2. DEPLOY_ENV_FILE (default ~/.qbazaar-deploy.env), plain KEY=VALUE lines
#   3. the defaults below, which match the current production server
#
# Keys: API_HOST, WEB_HOST, REPO_DIR, PHP_BIN_DIR, DEPLOY_BRANCH, HEALTH_HOST,
# HEALTH_URL. See deploy/README.md "Server settings file".

load_deploy_env() {
    local file="${DEPLOY_ENV_FILE:-$HOME/.qbazaar-deploy.env}" key value
    [[ -f "$file" ]] || return 0
    while IFS='=' read -r key value; do
        [[ "$key" =~ ^[A-Z_][A-Z0-9_]*$ ]] || continue
        [[ -n "${!key:-}" ]] && continue
        export "$key=$value"
    done < "$file"
}

load_deploy_env

API_HOST="${API_HOST:-api.qbazaar.187-53-139-138.sslip.io}"
WEB_HOST="${WEB_HOST:-qbazaar.187-53-139-138.sslip.io}"
REPO_DIR="${REPO_DIR:-$HOME/qbazaar}"
PHP_BIN_DIR="${PHP_BIN_DIR:-/opt/cpanel/ea-php84/root/usr/bin}"
BRANCH="${DEPLOY_BRANCH:-production}"

# cPanel: put the ea-php84 CLI binary + composer first so bare `php`/`composer`
# resolve to the 8.4 CLI SAPI in the minimal non-login PATH that GitHub
# Actions's SSH shell provides (/usr/bin/php is a cgi-fcgi wrapper).
export PATH="$PHP_BIN_DIR:/usr/local/bin:$PATH"

log() { printf '\n\033[1;36m> %s\033[0m\n' "$*"; }

reset_to_branch() {
    cd "$REPO_DIR"
    log "Fetching origin"
    git fetch origin --prune
    log "Resetting to origin/$BRANCH"
    git reset --hard "origin/$BRANCH"
}
