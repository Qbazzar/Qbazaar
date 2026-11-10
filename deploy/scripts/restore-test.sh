#!/usr/bin/env bash
# QBazaar — prove a database backup restores (OPS-18.7). Runs as root.
#
#   restore-test.sh                      newest local dump in $BACKUP_DIR/db
#   restore-test.sh /path/dump.sql.gz    a given dump
#   RESTORE_FROM_OFFSITE=1 restore-test.sh
#                                        newest dump in $BACKUP_OFFSITE/db/daily,
#                                        which also proves the crypt key works
#
# Loads the dump into a scratch database (RESTORE_DB, default
# qbazaar_restoretest), compares it with the live one table by table, then
# drops it. The live database is only read. Run it off-peak: the import
# writes as much as the database holds. A pass is written to
# $BACKUP_DIR/last-restore-test and pings RESTORE_HEARTBEAT_URL.

set -Eeuo pipefail
shopt -s inherit_errexit

# shellcheck source=deploy/scripts/ops-common.sh
source "$(dirname "${BASH_SOURCE[0]}")/ops-common.sh"

RESTORE_DB="${RESTORE_DB:-qbazaar_restoretest}"
HEARTBEAT_URL="${RESTORE_HEARTBEAT_URL:-}"
WORK_DIR=""
CREATED_DB=0
DUMP=""

# Tables that must hold rows in the copy whenever they hold rows live.
CORE_TABLES=(users ads media categories locations conversations messages migrations)

sql() { "$MYSQL" "${MYSQL_OPTS[@]}" -N -B -e "$1"; }

cleanup() {
    local status=$?
    if (( CREATED_DB )); then
        sql "DROP DATABASE IF EXISTS \`$RESTORE_DB\`" || log "WARN: could not drop $RESTORE_DB; drop it by hand"
    fi
    [[ -n "$WORK_DIR" ]] && rm -rf "$WORK_DIR"
    (( status == 0 )) || ping_heartbeat "$HEARTBEAT_URL" fail
}

check_names() {
    [[ "$RESTORE_DB" =~ ^[A-Za-z0-9_]+$ ]] || die "RESTORE_DB must be letters, digits and _ only"
    [[ "$RESTORE_DB" != "$DB_NAME" ]] || die "RESTORE_DB must not be the live database"
    [[ -z "$(sql "SHOW DATABASES LIKE '$RESTORE_DB'")" ]] || die "$RESTORE_DB already exists; drop it or pick another RESTORE_DB"
}

# Sets DUMP. Not run in a subshell, so cleanup() sees WORK_DIR and removes
# the downloaded copy.
pick_dump() {
    local name daily
    if [[ -n "${1:-}" ]]; then
        DUMP="$1"
    elif [[ "${RESTORE_FROM_OFFSITE:-0}" == 1 ]]; then
        [[ -n "${BACKUP_OFFSITE:-}" ]] || die "BACKUP_OFFSITE is empty"
        require_commands rclone
        daily="$(offsite_path db/daily)"
        name="$(rclone lsf --files-only "$daily" | sort | tail -n 1)"
        [[ -n "$name" ]] || die "no dump in $daily"
        WORK_DIR="$(mktemp -d "$BACKUP_DIR/restore.XXXXXX")"
        log "downloading $name"
        rclone copyto "$daily/$name" "$WORK_DIR/$name"
        DUMP="$WORK_DIR/$name"
    else
        name="$(find "$BACKUP_DIR/db" -name "$DB_NAME-*.sql.gz" -printf '%f\n' | sort | tail -n 1)"
        [[ -n "$name" ]] || die "no dump in $BACKUP_DIR/db"
        DUMP="$BACKUP_DIR/db/$name"
    fi
}

# A dump that names a database (USE / CREATE DATABASE) would load into that
# database instead of the scratch one, possibly the live one: refuse it.
check_dump() {
    local dump="$1"
    [[ -s "$dump" ]] || die "dump $dump is missing or empty"
    gzip -t "$dump"
    if zgrep -q -m 1 -E '^(USE|CREATE DATABASE) ' "$dump"; then
        die "$dump names a database; restore it by hand (runbook) instead"
    fi
}

load_dump() {
    local dump="$1"
    log "loading $dump into $RESTORE_DB"
    sql "CREATE DATABASE \`$RESTORE_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    CREATED_DB=1
    local started=$SECONDS
    zcat "$dump" | nice -n 10 "$MYSQL" "${MYSQL_OPTS[@]}" "$RESTORE_DB"
    log "loaded in $((SECONDS - started)) s"
}

# Exact counts (COUNT(*)) on the core tables only. The dump is up to a day
# older than live, so the copy may hold fewer rows (and fewer tables, if a
# release ran since), never none.
compare() {
    local live_tables copy_tables table live copy failed=0
    live_tables="$(sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$DB_NAME'")"
    copy_tables="$(sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$RESTORE_DB'")"
    log "tables: live $live_tables, copy $copy_tables"
    (( copy_tables > 0 )) || failed=1

    for table in "${CORE_TABLES[@]}"; do
        live="$(sql "SELECT COUNT(*) FROM \`$DB_NAME\`.\`$table\`" 2> /dev/null || echo missing)"
        copy="$(sql "SELECT COUNT(*) FROM \`$RESTORE_DB\`.\`$table\`" 2> /dev/null || echo missing)"
        log "  $table: live $live, copy $copy"
        if [[ "$copy" == missing && "$live" != missing ]] || [[ "$live" =~ ^[1-9] && "$copy" == 0 ]]; then
            failed=1
        fi
    done
    return "$failed"
}

main() {
    trap cleanup EXIT
    require_root
    require_commands gzip zcat zgrep
    MYSQL="$(mysql_client)" || die "no mariadb or mysql client"
    take_lock restore-test
    install -d -m 700 "$BACKUP_DIR"
    umask 077

    check_names
    pick_dump "${1:-}"
    check_dump "$DUMP"
    load_dump "$DUMP"

    if ! compare; then
        die "restore test FAILED for $(basename "$DUMP")"
    fi

    printf '%s PASS %s\n' "$(date -u +%FT%TZ)" "$(basename "$DUMP")" > "$BACKUP_DIR/last-restore-test"
    ping_heartbeat "$HEARTBEAT_URL"
    log "restore test PASSED for $(basename "$DUMP")"
}

main "$@"
