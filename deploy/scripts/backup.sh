#!/usr/bin/env bash
# QBazaar — daily backups (OPS-18.7). Runs as root from /etc/cron.d/qbazaar-ops;
# installed root-owned by install-ops.sh (never run from the repo clone, which
# the deploy user can write).
#
# Local copies, under $BACKUP_DIR (root 700), kept BACKUP_KEEP_LOCAL_DAYS:
#   db/<DB_NAME>-<UTC stamp>.sql.gz   consistent dump (--single-transaction)
#   config/config-<UTC stamp>.tar.gz  .env files, /root/qbazaar-secrets, the
#                                     server settings file, service configs
# Off-site, when BACKUP_OFFSITE names an rclone crypt remote:
#   db/daily/ (BACKUP_OFFSITE_KEEP_DAILY_DAYS), db/monthly/ (the first dump of
#   each month, BACKUP_OFFSITE_KEEP_MONTHLY_DAYS), config/, meili/ (the latest
#   Meilisearch snapshot), media/<source>/current with every file a sync
#   removed or replaced kept under media/<source>/deleted/<date>/
#   (BACKUP_MEDIA_DELETED_KEEP_DAYS). Media sources are BACKUP_MEDIA_SOURCES
#   (the R2 buckets) plus the API's storage/app.
#
# Success pings BACKUP_HEARTBEAT_URL; a failure pings <url>/fail, so a missed
# or failed night raises an alert. Restore: restore-test.sh and
# DOCS/M5-LAUNCH-RUNBOOK.md "Restore".

set -Eeuo pipefail
shopt -s inherit_errexit

# shellcheck source=deploy/scripts/ops-common.sh
source "$(dirname "${BASH_SOURCE[0]}")/ops-common.sh"

KEEP_LOCAL_DAYS="${BACKUP_KEEP_LOCAL_DAYS:-7}"
OFFSITE="${BACKUP_OFFSITE:-}"
KEEP_DAILY_DAYS="${BACKUP_OFFSITE_KEEP_DAILY_DAYS:-14}"
KEEP_MONTHLY_DAYS="${BACKUP_OFFSITE_KEEP_MONTHLY_DAYS:-365}"
MEDIA_SOURCES="${BACKUP_MEDIA_SOURCES:-}"
MEDIA_DELETED_KEEP_DAYS="${BACKUP_MEDIA_DELETED_KEEP_DAYS:-30}"
# A sync that would delete more than this many files stops instead: an empty
# or wrong source must not empty the mirror.
MEDIA_MAX_DELETE="${BACKUP_MEDIA_MAX_DELETE:-5000}"
HEARTBEAT_URL="${BACKUP_HEARTBEAT_URL:-}"
MEILI_SNAPSHOT="${MEILI_SNAPSHOT:-/var/lib/meilisearch/snapshots/data.ms.snapshot}"

STAMP="$(date -u +%Y-%m-%dT%H%M%SZ)"
TODAY="$(date -u +%F)"

# Config files worth having after a rebuild; missing ones are skipped.
CONFIG_PATHS=(
    /root/qbazaar-secrets
    "$REPO_DIR/qbazaar-api/.env"
    "$REPO_DIR/qbazaar-web/.env.production"
    /home/qbazaar/.qbazaar-deploy.env
    /etc/qbazaar
    /etc/meilisearch.toml
    /etc/redis
    /etc/sudoers.d/qbazaar-deploy
    /etc/my.cnf.d/zz-qbazaar.cnf
    /etc/apache2/conf.d/userdata
    /etc/apache2/conf.d/includes/cloudflare.conf
    /opt/cpanel/ea-php84/root/etc/php.d
    /var/cpanel/userdata/qbazaar
)

# Any non-zero exit, from `die` or a failed command, pings <url>/fail.
on_exit() {
    local status=$?
    (( status == 0 )) && return 0
    log "backup FAILED (exit $status)"
    ping_heartbeat "$HEARTBEAT_URL" fail
}

# Free space must cover the uncompressed database size: the dump is far
# smaller, but this leaves room for tonight's files before old ones go.
check_disk() {
    local db_bytes free_bytes
    db_bytes=$("$MYSQL" "${MYSQL_OPTS[@]}" -N -B -e \
        "SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.tables WHERE table_schema = '$DB_NAME'")
    free_bytes=$(( $(df -Pk "$BACKUP_DIR" | awk 'NR == 2 { print $4 }') * 1024 ))
    (( free_bytes > db_bytes )) || die "not enough space in $BACKUP_DIR: $free_bytes bytes free, database is $db_bytes bytes"
}

dump_database() {
    local out="$BACKUP_DIR/db/$DB_NAME-$STAMP.sql.gz"
    log "dumping $DB_NAME"
    # No --databases: the dump carries no USE/CREATE DATABASE, so it can be
    # loaded into any database name (restore-test.sh relies on this).
    nice -n 10 ionice -c2 -n7 "$MYSQL_DUMP" "${MYSQL_OPTS[@]}" \
        --single-transaction --quick --routines --triggers --events --hex-blob \
        --default-character-set=utf8mb4 "$DB_NAME" | gzip -6 > "$out.partial"
    gzip -t "$out.partial"
    zcat "$out.partial" | tail -n 1 | grep -q '^-- Dump completed' || die "dump of $DB_NAME is incomplete"
    mv "$out.partial" "$out"
    log "database dump: $out ($(du -h "$out" | cut -f1))"
    DUMP_FILE="$out"
}

bundle_config() {
    local out="$BACKUP_DIR/config/config-$STAMP.tar.gz" path existing=() status=0
    for path in "${CONFIG_PATHS[@]}"; do
        [[ -e "$path" ]] && existing+=("$path")
    done
    # tar exits 1 when a file changed while it was read; that copy is fine.
    tar -czf "$out" --ignore-failed-read "${existing[@]}" 2> /dev/null || status=$?
    (( status <= 1 )) || die "config bundle failed (tar exit $status)"
    log "config bundle: $out"
    CONFIG_FILE="$out"
}

prune_local() {
    find "$BACKUP_DIR/db" -name "$DB_NAME-*.sql.gz" -mtime +"$KEEP_LOCAL_DAYS" -delete
    find "$BACKUP_DIR/config" -name 'config-*.tar.gz' -mtime +"$KEEP_LOCAL_DAYS" -delete
    find "$BACKUP_DIR" -name '*.partial' -mtime +1 -delete
}

# Deletes the files or folders directly under a remote folder whose name
# holds a YYYY-MM-DD older than KEEP_DAYS. Names, not modification times:
# a file moved into deleted/<date>/ keeps its original, older mtime.
prune_remote_by_date() {
    local dir="$1" keep_days="$2" kind="$3" cutoff entry day
    cutoff="$(date -u -d "-$keep_days days" +%F)"
    while IFS= read -r entry; do
        day="$(grep -oE '[0-9]{4}-[0-9]{2}-[0-9]{2}' <<< "$entry" | head -n 1)" || continue
        [[ -n "$day" && "$day" < "$cutoff" ]] || continue
        if [[ "$kind" == dirs ]]; then
            rclone purge "$dir/${entry%/}"
        else
            rclone deletefile "$dir/$entry"
        fi
    done < <(rclone lsf "--$kind-only" "$dir" 2> /dev/null || true)
}

copy_offsite() {
    local daily monthly config
    daily="$(offsite_path db/daily)"
    monthly="$(offsite_path db/monthly)"
    config="$(offsite_path config)"
    log "copying to $OFFSITE"
    rclone copyto "$DUMP_FILE" "$daily/$(basename "$DUMP_FILE")"
    # Listed first: `grep -q` closing the pipe early would fail rclone under pipefail.
    local monthly_listing
    monthly_listing="$(rclone lsf "$monthly" 2> /dev/null || true)"
    if ! grep -q -- "-${TODAY:0:7}-" <<< "$monthly_listing"; then
        rclone copyto "$DUMP_FILE" "$monthly/$(basename "$DUMP_FILE")"
    fi
    rclone copyto "$CONFIG_FILE" "$config/$(basename "$CONFIG_FILE")"
    if [[ -f "$MEILI_SNAPSHOT" ]]; then
        rclone copyto "$MEILI_SNAPSHOT" "$(offsite_path meili/data.ms.snapshot)"
    fi
    prune_remote_by_date "$daily" "$KEEP_DAILY_DAYS" files
    prune_remote_by_date "$monthly" "$KEEP_MONTHLY_DAYS" files
    prune_remote_by_date "$config" "$KEEP_DAILY_DAYS" files
}

mirror_media() {
    local source name target sources=()
    read -r -a sources <<< "$MEDIA_SOURCES"
    if [[ -d "$REPO_DIR/qbazaar-api/storage/app" ]]; then
        sources+=("$REPO_DIR/qbazaar-api/storage/app")
    fi
    for source in "${sources[@]}"; do
        name="$(tr -c 'A-Za-z0-9._-' '_' <<< "${source#/}" | sed 's/_*$//')"
        target="$(offsite_path "media/$name")"
        log "mirroring $source"
        nice -n 10 rclone sync "$source" "$target/current" \
            --backup-dir "$target/deleted/$TODAY" \
            --max-delete "$MEDIA_MAX_DELETE" --fast-list --transfers 8 --checkers 16 \
            --exclude '.gitignore'
        prune_remote_by_date "$target/deleted" "$MEDIA_DELETED_KEEP_DAYS" dirs
    done
}

main() {
    trap on_exit EXIT
    require_root
    require_commands gzip zcat flock ionice df
    MYSQL="$(mysql_client)" || die "no mariadb or mysql client"
    MYSQL_DUMP="$(mysql_dump_client)" || die "no mariadb-dump or mysqldump"
    take_lock backup

    install -d -m 700 "$BACKUP_DIR" "$BACKUP_DIR/db" "$BACKUP_DIR/config"
    umask 077

    check_disk
    dump_database
    bundle_config
    prune_local

    if [[ -n "$OFFSITE" ]]; then
        require_commands rclone
        require_encrypted_remote "$OFFSITE"
        copy_offsite
        mirror_media
    else
        log "WARN: BACKUP_OFFSITE is empty: the copies stay on this server only"
    fi

    printf '%s %s\n' "$STAMP" "$(basename "$DUMP_FILE")" > "$BACKUP_DIR/last-success"
    ping_heartbeat "$HEARTBEAT_URL"
    log "backup complete"
}

main "$@"
