#!/usr/bin/env bash
# QBazaar — install the root ops tooling from the repo (OPS-18.7, OPS-18.13,
# OPS-18.15). Run as root, again after any change to the files below:
#   bash ~qbazaar/qbazaar/deploy/scripts/install-ops.sh
#
# Installs root-owned copies, so cron never runs a file the deploy user can
# edit:
#   deploy/scripts/<ops scripts>  -> /usr/local/lib/qbazaar-ops/  (700)
#   deploy/cron/qbazaar-ops       -> /etc/cron.d/qbazaar-ops      (644)
#   deploy/logrotate/qbazaar-ops  -> /etc/logrotate.d/qbazaar-ops (644)
#   deploy/ops.env.example        -> /etc/qbazaar/ops.env         (600, first run only)
# and creates /var/log/qbazaar (700). Idempotent. Undo: delete those paths.

set -euo pipefail

DEPLOY_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LIB_DIR=/usr/local/lib/qbazaar-ops
SCRIPTS=(ops-common.sh backup.sh restore-test.sh meili-restore-test.sh mysql-slow-digest.sh fpm-pool-size.sh)

die() { echo "install-ops: $*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || die "run as root"

install -d -m 755 -o root -g root "$LIB_DIR"
for script in "${SCRIPTS[@]}"; do
    install -m 700 -o root -g root "$DEPLOY_DIR/scripts/$script" "$LIB_DIR/$script"
done

install -d -m 700 -o root -g root /etc/qbazaar /var/log/qbazaar
if [[ ! -e /etc/qbazaar/ops.env ]]; then
    install -m 600 -o root -g root "$DEPLOY_DIR/ops.env.example" /etc/qbazaar/ops.env
    echo "install-ops: wrote /etc/qbazaar/ops.env from the example; set BACKUP_OFFSITE and the heartbeat URLs"
fi

install -m 644 -o root -g root "$DEPLOY_DIR/cron/qbazaar-ops" /etc/cron.d/qbazaar-ops
install -m 644 -o root -g root "$DEPLOY_DIR/logrotate/qbazaar-ops" /etc/logrotate.d/qbazaar-ops
logrotate --debug /etc/logrotate.d/qbazaar-ops > /dev/null 2>&1 || die "logrotate rejects /etc/logrotate.d/qbazaar-ops"

echo "install-ops: installed ${#SCRIPTS[@]} scripts in $LIB_DIR, the cron file and the logrotate file"
