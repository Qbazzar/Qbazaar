#!/usr/bin/env bash
# QBazaar — weekly digest of the MariaDB slow query log (OPS-18.13). Runs as
# root every Monday from /etc/cron.d/qbazaar-ops.
#
# 1. Rotates the slow log (rename, then FLUSH SLOW LOGS), so each report
#    covers one week.
# 2. Summarises the rotated log with pt-query-digest (percona-toolkit) when
#    installed, otherwise mariadb-dumpslow / mysqldumpslow.
# 3. Keeps the report and the compressed log in DIGEST_DIR (root 700) for
#    DIGEST_KEEP_WEEKS.
# 4. Mails the summary to DIGEST_MAILTO when set and sendmail exists. The
#    mail carries only abstracted query shapes (literals replaced by ? or
#    N/'S'); the full report, which shows sample queries with real values,
#    stays on the server.
#
# Review: the top entries by total time, then EXPLAIN them on a copy. The PHP
# side of a slow request is in the FPM slowlog (deploy/php/api.php-fpm.yaml).

set -Eeuo pipefail
shopt -s inherit_errexit

# shellcheck source=deploy/scripts/ops-common.sh
source "$(dirname "${BASH_SOURCE[0]}")/ops-common.sh"

SLOW_LOG="${SLOW_LOG:-/var/lib/mysql/qbazaar-slow.log}"
DIGEST_DIR="${DIGEST_DIR:-/var/log/qbazaar/slow-digest}"
KEEP_WEEKS="${DIGEST_KEEP_WEEKS:-12}"
MAILTO="${DIGEST_MAILTO:-}"
WEEK="$(date -u +%G-W%V)"

rotate_log() {
    local rotated="$DIGEST_DIR/slow-$WEEK.log"
    [[ -s "$SLOW_LOG" ]] || { log "slow log $SLOW_LOG is empty or missing; nothing to digest"; exit 0; }
    mv "$SLOW_LOG" "$rotated"
    # MariaDB keeps writing to the renamed file until it reopens the log.
    "$MYSQL" "${MYSQL_OPTS[@]}" -e 'FLUSH SLOW LOGS'
    ROTATED="$rotated"
}

# Writes the full report and prints the short summary for the mail (stdout
# is the summary, so the log line goes to stderr).
digest() {
    local report="$DIGEST_DIR/slow-$WEEK.report.txt" dumpslow
    if command -v pt-query-digest > /dev/null; then
        pt-query-digest --limit 25 "$ROTATED" > "$report"
        pt-query-digest --limit 25 --report-format header,profile "$ROTATED"
    else
        dumpslow="$(command -v mariadb-dumpslow || command -v mysqldumpslow)" || die "no pt-query-digest or mysqldumpslow"
        # Sorted by total time; literals are abstracted by default.
        "$dumpslow" -s t -t 25 "$ROTATED" | tee "$report"
    fi
    log "report: $report" >&2
}

prune() {
    find "$DIGEST_DIR" -name 'slow-*' -mtime +$((KEEP_WEEKS * 7)) -delete
}

main() {
    require_root
    MYSQL="$(mysql_client)" || die "no mariadb or mysql client"
    take_lock slow-digest
    install -d -m 700 "$DIGEST_DIR"
    umask 077

    rotate_log
    local summary
    summary="$(digest)"
    printf '%s\n' "$summary"
    gzip -f "$ROTATED"
    prune

    if [[ -n "$MAILTO" ]] && command -v sendmail > /dev/null; then
        printf 'Subject: QBazaar slow queries %s on %s\nTo: %s\n\n%s\n\nFull report: %s/slow-%s.report.txt\n' \
            "$WEEK" "$(hostname -s)" "$MAILTO" "$summary" "$DIGEST_DIR" "$WEEK" | sendmail -t
        log "summary mailed to $MAILTO"
    fi
}

main "$@"
