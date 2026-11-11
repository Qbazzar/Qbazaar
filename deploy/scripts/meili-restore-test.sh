#!/usr/bin/env bash
# QBazaar — prove the daily Meilisearch snapshot restores (OPS-18.15). Root.
#
#   meili-restore-test.sh                  /var/lib/meilisearch/snapshots/data.ms.snapshot
#   meili-restore-test.sh /path/x.snapshot a given snapshot (e.g. from off-site)
#
# Starts a second, throwaway Meilisearch on 127.0.0.1:$MEILI_TEST_PORT from
# the snapshot, as the meilisearch user, with half the live indexing limits,
# then compares each index's document count with the live server and stops
# it. The live server and its data are only read. Needs free disk about the
# size of /var/lib/meilisearch/data.ms.
#
# The index can always be rebuilt from MariaDB (`php artisan scout:import`);
# the snapshot only makes a restore take minutes instead of a full reindex.

set -Eeuo pipefail
shopt -s inherit_errexit

# shellcheck source=deploy/scripts/ops-common.sh
source "$(dirname "${BASH_SOURCE[0]}")/ops-common.sh"

MEILI_BIN="${MEILI_BIN:-/usr/local/bin/meilisearch}"
MEILI_CONFIG="${MEILI_CONFIG:-/etc/meilisearch.toml}"
MEILI_LIVE_URL="${MEILI_LIVE_URL:-http://127.0.0.1:7700}"
MEILI_TEST_PORT="${MEILI_TEST_PORT:-7701}"
MEILI_USER="${MEILI_USER:-meilisearch}"
SNAPSHOT="${1:-/var/lib/meilisearch/snapshots/data.ms.snapshot}"
# A snapshot is up to a day old: accept a copy holding this share of live.
MIN_SHARE_PERCENT="${MEILI_MIN_SHARE_PERCENT:-90}"

WORK_DIR=""
TEST_PID=""

cleanup() {
    [[ -n "$TEST_PID" ]] && kill "$TEST_PID" 2> /dev/null && wait "$TEST_PID" 2> /dev/null
    [[ -n "$WORK_DIR" ]] && rm -rf "$WORK_DIR"
    return 0
}

# Reads master_key from the live config without echoing it.
live_key() {
    sed -n 's/^[[:space:]]*master_key[[:space:]]*=[[:space:]]*"\(.*\)".*/\1/p' "$MEILI_CONFIG" | head -n 1
}

# Prints "uid documents" per index. The header goes through stdin so the
# live master key never shows in the process list.
index_counts() {
    local url="$1" key="$2"
    curl -fsS -m 10 -H @- "$url/stats" <<< "Authorization: Bearer $key" \
        | jq -r '.indexes | to_entries[] | "\(.key) \(.value.numberOfDocuments)"' | sort
}

start_test_instance() {
    local key="$1"
    WORK_DIR="$(mktemp -d /var/lib/meilisearch/restore-test.XXXXXX)"
    chown "$MEILI_USER": "$WORK_DIR"
    log "starting a test Meilisearch on 127.0.0.1:$MEILI_TEST_PORT from $SNAPSHOT"
    # setpriv execs (no wrapper process), so $! is Meilisearch itself.
    setpriv --reuid="$MEILI_USER" --regid="$MEILI_USER" --init-groups -- nice -n 15 "$MEILI_BIN" \
        --db-path "$WORK_DIR/data.ms" --import-snapshot "$SNAPSHOT" \
        --http-addr "127.0.0.1:$MEILI_TEST_PORT" --master-key "$key" \
        --env production --no-analytics \
        --max-indexing-memory "512 MiB" --max-indexing-threads 1 \
        --dump-dir "$WORK_DIR/dumps" --snapshot-dir "$WORK_DIR/snapshots" \
        > "$WORK_DIR/meili.log" 2>&1 &
    TEST_PID=$!

    local waited=0
    until curl -fsS -m 2 "http://127.0.0.1:$MEILI_TEST_PORT/health" > /dev/null 2>&1; do
        kill -0 "$TEST_PID" 2> /dev/null || die "test instance exited: $(tail -n 5 "$WORK_DIR/meili.log")"
        (( waited++ < 300 )) || die "test instance not healthy after 300 s"
        sleep 1
    done
    log "test instance healthy after ${waited} s"
}

compare_counts() {
    local live="$1" copy="$2" uid count copied failed=0
    while read -r uid count; do
        copied="$(awk -v u="$uid" '$1 == u { print $2 }' <<< "$copy")"
        log "  $uid: live $count, snapshot ${copied:-missing}"
        if [[ -z "$copied" ]] || (( count > 0 && copied * 100 < count * MIN_SHARE_PERCENT )); then
            failed=1
        fi
    done <<< "$live"
    return "$failed"
}

main() {
    trap cleanup EXIT
    require_root
    require_commands curl jq setpriv openssl
    [[ -x "$MEILI_BIN" ]] || die "no Meilisearch binary at $MEILI_BIN"
    [[ -s "$SNAPSHOT" ]] || die "no snapshot at $SNAPSHOT (is schedule_snapshot set in $MEILI_CONFIG?)"
    take_lock meili-restore-test

    local key test_key live copy
    key="$(live_key)"
    [[ -n "$key" ]] || die "no master_key in $MEILI_CONFIG"
    test_key="$(openssl rand -hex 24)"
    live="$(index_counts "$MEILI_LIVE_URL" "$key")"
    [[ -n "$live" ]] || die "the live server lists no index"

    start_test_instance "$test_key"
    copy="$(index_counts "http://127.0.0.1:$MEILI_TEST_PORT" "$test_key")"

    compare_counts "$live" "$copy" || die "snapshot restore test FAILED"
    log "snapshot restore test PASSED ($(du -h "$SNAPSHOT" | cut -f1))"
}

main "$@"
