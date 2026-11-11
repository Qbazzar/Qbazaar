#!/usr/bin/env bash
# QBazaar — measure the API's PHP-FPM workers and suggest pm_max_children
# (OPS-18.11). Read-only.
#
# Run as root after the API has served real traffic for a while (a day after
# launch, then after each big release):
#   bash ~qbazaar/qbazaar/deploy/scripts/fpm-pool-size.sh
#   FPM_BUDGET_MB=1600 bash ~qbazaar/qbazaar/deploy/scripts/fpm-pool-size.sh
#
# RSS counts the shared OPcache pages in every worker, so it overstates the
# cost; PSS splits shared pages between the processes that use them and is
# the better guide. The budget is the pool's share of RAM in deploy/README.md
# "PHP-FPM pool". Put the result in deploy/php/api.php-fpm.yaml.

set -euo pipefail

POOL_USER="${POOL_USER:-qbazaar}"
BUDGET_MB="${FPM_BUDGET_MB:-1300}"

# A worker can exit between `ps` and the read; that counts as 0.
pss_kb() {
    local kb
    kb=$(awk '/^Pss:/ { kb = $2 } END { print kb + 0 }' "/proc/$1/smaps_rollup" 2>/dev/null) || kb=0
    echo "${kb:-0}"
}

main() {
    local pid rss count=0 rss_total=0 rss_max=0 pss_total=0

    while read -r pid rss; do
        count=$((count + 1))
        rss_total=$((rss_total + rss))
        (( rss > rss_max )) && rss_max=$rss
        pss_total=$((pss_total + $(pss_kb "$pid")))
    done < <(ps -u "$POOL_USER" -o pid=,rss=,comm= | awk '$3 ~ /^php-fpm/ { print $1, $2 }')

    if (( count == 0 )); then
        echo "No php-fpm workers are running as $POOL_USER." >&2
        exit 1
    fi

    local rss_avg_mb=$((rss_total / count / 1024)) pss_avg_mb=$((pss_total / count / 1024))
    echo "workers running : $count"
    echo "RSS avg / max   : ${rss_avg_mb} MB / $((rss_max / 1024)) MB"
    echo "PSS avg         : ${pss_avg_mb} MB (0 means smaps_rollup was not readable; run as root)"
    echo "budget          : ${BUDGET_MB} MB"
    (( rss_avg_mb > 0 )) && echo "max_children by RSS (cautious) : $((BUDGET_MB / rss_avg_mb))"
    (( pss_avg_mb > 0 )) && echo "max_children by PSS            : $((BUDGET_MB / pss_avg_mb))"
    echo "Keep max_children + Horizon workers + 20 under MariaDB max_connections (deploy/mariadb/zz-qbazaar.cnf)."
}

main "$@"
