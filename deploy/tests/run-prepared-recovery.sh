#!/usr/bin/env bash
# Reproducible launcher for a prepared native-Linux test checkout.
set -euo pipefail
source_root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd -P)
native_root=${1:?Provide the prepared native Linux shop-src test checkout.}
case "$native_root" in /tmp/*/shop-src) ;; *) echo 'Use the prepared temporary /tmp/.../shop-src checkout.' >&2; exit 2;; esac
[[ -f "$native_root/.env" && -d "$native_root/deploy/tests" ]] || { echo 'Native test checkout is not prepared.' >&2; exit 2; }
[[ -n "${SHOP_TEST_RECOVERY_LOG:-}" ]] || { echo 'Set SHOP_TEST_RECOVERY_LOG.' >&2; exit 2; }
# Root may have updated the backup scripts alongside application path changes.
# Never replace those scripts or the native .env/secrets here.
for file in deploy/backup_archive.py deploy/tests/backup_fixture.php deploy/tests/backup-recovery.sh; do
    cp -- "$source_root/$file" "$native_root/$file"
done
{
    date -u '+Recovery verification started: %Y-%m-%dT%H:%M:%SZ'
    sha256sum "$native_root/scripts/backup.sh" "$native_root/scripts/restore.sh" "$native_root/deploy/backup_archive.py" "$native_root/deploy/tests/backup_fixture.php" "$native_root/deploy/tests/backup-recovery.sh"
    bash "$native_root/deploy/tests/backup-recovery.sh"
    date -u '+Recovery verification finished: %Y-%m-%dT%H:%M:%SZ'
} 2>&1 | tee "$SHOP_TEST_RECOVERY_LOG"
