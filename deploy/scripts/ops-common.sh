# shellcheck shell=bash
# Variables set here are read by the scripts that source this file.
# shellcheck disable=SC2034
# Shared setup for the root ops scripts (sourced, not run): backup.sh,
# restore-test.sh, meili-restore-test.sh and mysql-slow-digest.sh.
#
# Settings come from, in order of precedence:
#   1. the environment of the caller
#   2. OPS_ENV_FILE (default /etc/qbazaar/ops.env, root 600), KEY=VALUE lines;
#      deploy/ops.env.example lists every key
#   3. the defaults in each script
# Nothing secret lives in that file: MariaDB reads /root/.my.cnf and rclone
# reads /root/.config/rclone/rclone.conf.

load_ops_env() {
    local file="${OPS_ENV_FILE:-/etc/qbazaar/ops.env}" key value
    [[ -f "$file" ]] || return 0
    while IFS='=' read -r key value; do
        [[ "$key" =~ ^[A-Z_][A-Z0-9_]*$ ]] || continue
        [[ -n "${!key:-}" ]] && continue
        export "$key=$value"
    done < "$file"
}

load_ops_env

REPO_DIR="${REPO_DIR:-/home/qbazaar/qbazaar}"
DB_NAME="${DB_NAME:-qbazaar_app}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/qbazaar}"
# Options for every mysql/mysqldump call; --defaults-extra-file must come first.
MYSQL_OPTS=()
[[ -n "${MYSQL_DEFAULTS_FILE:-}" ]] && MYSQL_OPTS=("--defaults-extra-file=$MYSQL_DEFAULTS_FILE")

log() { printf '%s %s\n' "$(date -u +%FT%TZ)" "$*"; }
die() { log "ERROR: $*" >&2; exit 1; }

require_root() { [[ $EUID -eq 0 ]] || die "run as root"; }

# DB_NAME goes into SQL and file names; allow nothing a typo could abuse.
[[ "$DB_NAME" =~ ^[A-Za-z0-9_]+$ ]] || die "DB_NAME '$DB_NAME' must be letters, digits and _ only"

require_commands() {
    local cmd
    for cmd in "$@"; do
        command -v "$cmd" > /dev/null || die "missing command: $cmd"
    done
}

# One run at a time per job; a second cron start exits quietly.
take_lock() {
    local name="$1"
    exec {OPS_LOCK_FD}> "/run/lock/qbazaar-$name.lock"
    flock -n "$OPS_LOCK_FD" || { log "another $name run holds the lock; exiting"; exit 0; }
}

# MariaDB 10.11 ships both names; prefer the new ones.
mysql_client() { command -v mariadb || command -v mysql; }
mysql_dump_client() { command -v mariadb-dump || command -v mysqldump; }

# Pings a dead-man's-switch URL (Healthchecks.io, Better Stack heartbeat):
# `ping_heartbeat URL` on success, `ping_heartbeat URL fail` on failure.
ping_heartbeat() {
    local url="$1" suffix="${2:-}"
    [[ -n "$url" ]] || return 0
    [[ -n "$suffix" ]] && url="${url%/}/$suffix"
    curl -fsS -m 10 --retry 3 -o /dev/null "$url" || log "WARN: heartbeat ping failed: ${url%%\?*}"
}

# The rclone remote name of a path such as `offsite:qbazaar/db`.
remote_name() { echo "${1%%:*}"; }

# A path under BACKUP_OFFSITE, which may be a bare remote (`offsite:`) or a
# folder in one (`offsite:qbazaar`).
offsite_path() {
    local base="${BACKUP_OFFSITE%/}"
    if [[ "$base" == *: ]]; then
        echo "$base$1"
    else
        echo "$base/$1"
    fi
}

# Off-site copies hold personal data and secrets, so the remote must be an
# rclone `crypt` remote unless BACKUP_OFFSITE_ALLOW_PLAIN=1 says otherwise.
require_encrypted_remote() {
    local name type
    name="$(remote_name "$1")"
    type="$(rclone listremotes --long | awk -v n="$name:" '$1 == n { print $2 }')"
    [[ -n "$type" ]] || die "rclone remote '$name' is not configured"
    [[ "$type" == crypt || "${BACKUP_OFFSITE_ALLOW_PLAIN:-0}" == 1 ]] \
        || die "rclone remote '$name' is '$type', not 'crypt'; off-site copies must be encrypted"
}
