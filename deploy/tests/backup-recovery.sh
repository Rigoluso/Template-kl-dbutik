#!/usr/bin/env bash
# Execute only against the already-running isolated demo after other tests finish.
set -euo pipefail
umask 077
root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd -P)
cd -- "$root"
case "${COMPOSE_PROJECT_NAME:-}" in shopverify|shopverify-*) ;; *) echo 'Require isolated COMPOSE_PROJECT_NAME=shopverify or shopverify-*.' >&2; exit 2;; esac
[[ -n "${BACKUP_GPG_RECIPIENT:-}" && -n "${GNUPGHOME:-}" && -n "${SHOP_BACKUP_TEST_DIR:-}" ]] || { echo 'Use a temporary test GPG home/recipient and SHOP_BACKUP_TEST_DIR.' >&2; exit 2; }
export COMPOSE_FILE=compose.yaml:compose.dev.yaml
docker_command=${DOCKER:-docker}
dc() { "$docker_command" compose "$@"; }
mode=$(dc config --format json | python3 -c 'import json,sys; print(json.load(sys.stdin)["services"]["app"]["environment"]["SHOP_MODE"])')
[[ "$mode" == demo ]] || { echo 'Recovery exercise requires demo mode.' >&2; exit 2; }
fixture() {
    dc exec -T app sh -c 'cat > /tmp/backup-fixture.php' < deploy/tests/backup_fixture.php
    dc exec -T app wp --allow-root eval-file /tmp/backup-fixture.php "$1"
}
fixture create
# Exercise real certificate-volume files and Certbot's relative symlink layout.
# This payload tests archival only; it is not a certificate for serving HTTPS.
dc run --no-deps --rm -T --entrypoint sh certbot -eu -c 'mkdir -p /etc/letsencrypt/archive/backup-verification.test /etc/letsencrypt/live/backup-verification.test; printf "original-certificate-archive-payload\n" > /etc/letsencrypt/archive/backup-verification.test/fullchain1.pem; ln -sf ../../archive/backup-verification.test/fullchain1.pem /etc/letsencrypt/live/backup-verification.test/fullchain.pem'
bash scripts/backup.sh "$SHOP_BACKUP_TEST_DIR"
dc up -d --wait
archive=$(find "$SHOP_BACKUP_TEST_DIR" -maxdepth 1 -type f -name 'shop-backup-*.tar.gz.gpg' | sort | tail -n 1)
[[ -n "$archive" ]] || { echo 'Backup was not created.' >&2; exit 1; }
fixture mutate
dc run --no-deps --rm -T --entrypoint sh certbot -eu -c 'printf "changed-after-backup\n" > /etc/letsencrypt/archive/backup-verification.test/fullchain1.pem'
bash scripts/restore.sh "$archive" --confirm-replace-data
fixture verify
dc run --no-deps --rm -T --entrypoint sh certbot -eu -c 'test "$(cat /etc/letsencrypt/live/backup-verification.test/fullchain.pem)" = original-certificate-archive-payload; test "$(readlink /etc/letsencrypt/live/backup-verification.test/fullchain.pem)" = ../../archive/backup-verification.test/fullchain1.pem'
echo 'Actual encrypted backup, deliberate data mutation and recovery exercise passed.'
