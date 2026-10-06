#!/usr/bin/env bash
# QBazaar — render qbazaar-api/.env and qbazaar-web/.env.production (run as root).
#
# Holds no secret values. It reads:
#   - the templates in deploy/ (env.production.template, web.env.production.template)
#   - the server settings file ~qbazaar/.qbazaar-deploy.env (hosts, paths)
#   - one file per secret in $SECRETS_DIR (root, 700). db_password,
#     redis_password and meili_master_key are written while installing MariaDB,
#     Redis and Meilisearch; app_key, reverb_app_key and reverb_app_secret are
#     generated here on the first run and reused afterwards.
#   - optional $SECRETS_DIR/api.overrides.env and web.overrides.env (KEY=VALUE
#     lines applied last, e.g. R2 or mail credentials once they exist).
#
# Safe to re-run: the output depends only on the inputs above, so editing a
# .env by hand is lost on the next run; put lasting changes in an overrides file.
#
# Usage: bash /home/qbazaar/qbazaar/deploy/scripts/render-env.sh

set -euo pipefail

DEPLOY_USER="${DEPLOY_USER:-qbazaar}"
DEPLOY_HOME="$(getent passwd "$DEPLOY_USER" | cut -d: -f6)"
SECRETS_DIR="${SECRETS_DIR:-/root/qbazaar-secrets}"
DEPLOY_ENV_FILE="${DEPLOY_ENV_FILE:-$DEPLOY_HOME/.qbazaar-deploy.env}"

die() { echo "render-env: $*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || die "run as root"
[[ -f "$DEPLOY_ENV_FILE" ]] || die "missing $DEPLOY_ENV_FILE"
# shellcheck source=/dev/null
source "$DEPLOY_ENV_FILE"

: "${API_HOST:?API_HOST missing in $DEPLOY_ENV_FILE}"
: "${WEB_HOST:?WEB_HOST missing in $DEPLOY_ENV_FILE}"
URL_SCHEME="${URL_SCHEME:-https}"
REPO_DIR="${REPO_DIR:-$DEPLOY_HOME/qbazaar}"
DB_NAME="${DB_NAME:-qbazaar_app}"
DB_USER="${DB_USER:-qbazaar_app}"
# Extra browser origins for CORS, space separated host names (e.g. www.<domain>).
WEB_ALIASES="${WEB_ALIASES:-}"

secret() {
    local file="$SECRETS_DIR/$1"
    [[ -s "$file" ]] || die "missing secret $file"
    tr -d '\n' < "$file"
}

# Generates a secret once; later runs reuse the stored value.
ensure_secret() {
    local file="$SECRETS_DIR/$1" generator="$2"
    [[ -s "$file" ]] && return 0
    ( umask 077 && eval "$generator" > "$file" )
}

# Sets KEY=VALUE in a file, replacing the line or appending it. awk reads the
# value from the environment, so no character in it needs escaping.
set_kv() {
    local file="$1" key="$2"
    KV_KEY="$key" KV_VALUE="$3" awk '
        BEGIN { k = ENVIRON["KV_KEY"]; line = k "=" ENVIRON["KV_VALUE"] }
        index($0, k "=") == 1 { print line; done = 1; next }
        { print }
        END { if (!done) print line }
    ' "$file" > "$file.tmp" && mv "$file.tmp" "$file"
}

apply_overrides() {
    local file="$1" overrides="$2" key value
    [[ -f "$overrides" ]] || return 0
    while IFS='=' read -r key value; do
        [[ "$key" =~ ^[A-Z_][A-Z0-9_]*$ ]] || continue
        set_kv "$file" "$key" "$value"
    done < "$overrides"
}

cors_origins() {
    local origins="$URL_SCHEME://$WEB_HOST" alias
    for alias in $WEB_ALIASES; do origins="$origins,$URL_SCHEME://$alias"; done
    echo "$origins"
}

render_api() {
    local out="$1" secure=true
    [[ "$URL_SCHEME" == https ]] || secure=false
    cp "$REPO_DIR/deploy/env.production.template" "$out"
    set_kv "$out" APP_KEY "$(secret app_key)"
    set_kv "$out" APP_URL "$URL_SCHEME://$API_HOST"
    set_kv "$out" WEB_URL "$URL_SCHEME://$WEB_HOST"
    set_kv "$out" CORS_ALLOWED_ORIGINS "$(cors_origins)"
    set_kv "$out" TRUSTED_PROXIES ""
    set_kv "$out" DB_DATABASE "$DB_NAME"
    set_kv "$out" DB_USERNAME "$DB_USER"
    set_kv "$out" DB_PASSWORD "$(secret db_password)"
    set_kv "$out" SESSION_SECURE_COOKIE "$secure"
    set_kv "$out" REDIS_CLIENT phpredis
    set_kv "$out" REDIS_PASSWORD "$(secret redis_password)"
    render_api_services "$out"
}

render_api_services() {
    local out="$1"
    set_kv "$out" MAIL_MAILER log
    set_kv "$out" MAIL_HOST 127.0.0.1
    set_kv "$out" MAIL_USERNAME ""
    set_kv "$out" MAIL_PASSWORD ""
    set_kv "$out" MAIL_FROM_ADDRESS "\"no-reply@$WEB_HOST\""
    set_kv "$out" MEILISEARCH_KEY "$(secret meili_master_key)"
    set_kv "$out" REVERB_APP_KEY "$(secret reverb_app_key)"
    set_kv "$out" REVERB_APP_SECRET "$(secret reverb_app_secret)"
    set_kv "$out" TURNSTILE_ENABLED false
    set_kv "$out" AUTH_NEW_DEVICE_CHECK_ENABLED false
    set_kv "$out" AUTH_PASSWORD_LOGIN_ENABLED true
    set_kv "$out" MEDIA_DISK public
    set_kv "$out" MEDIA_PUBLIC_DISK public
    apply_overrides "$out" "$SECRETS_DIR/api.overrides.env"
}

render_web() {
    local out="$1" port=443
    [[ "$URL_SCHEME" == https ]] || port=80
    cp "$REPO_DIR/deploy/web.env.production.template" "$out"
    set_kv "$out" NEXT_PUBLIC_API_URL "$URL_SCHEME://$API_HOST"
    set_kv "$out" NEXT_PUBLIC_APP_URL "$URL_SCHEME://$WEB_HOST"
    set_kv "$out" NEXT_PUBLIC_REVERB_APP_KEY "$(secret reverb_app_key)"
    set_kv "$out" NEXT_PUBLIC_REVERB_HOST "$API_HOST"
    set_kv "$out" NEXT_PUBLIC_REVERB_PORT "$port"
    set_kv "$out" NEXT_PUBLIC_REVERB_SCHEME "$([[ $port == 443 ]] && echo https || echo http)"
    apply_overrides "$out" "$SECRETS_DIR/web.overrides.env"
}

# Renders into a private temp file, refuses a leftover placeholder, then
# installs the result owned by the deploy user with mode 600.
install_env() {
    local target="$1" renderer="$2" tmp
    tmp="$(mktemp "$SECRETS_DIR/render.XXXXXX")"
    "$renderer" "$tmp"
    if grep -qE '^[A-Z_][A-Z0-9_]*=.*__FILL_ME__' "$tmp"; then
        rm -f "$tmp"
        die "$target would keep a __FILL_ME__ placeholder"
    fi
    install -m 600 -o "$DEPLOY_USER" -g "$DEPLOY_USER" "$tmp" "$target"
    rm -f "$tmp"
    echo "render-env: wrote $target"
}

main() {
    install -d -m 700 "$SECRETS_DIR"
    ensure_secret app_key 'echo "base64:$(openssl rand -base64 32)"'
    ensure_secret reverb_app_key 'openssl rand -hex 16'
    ensure_secret reverb_app_secret 'openssl rand -hex 32'
    install_env "$REPO_DIR/qbazaar-api/.env" render_api
    install_env "$REPO_DIR/qbazaar-web/.env.production" render_web
}

main "$@"
