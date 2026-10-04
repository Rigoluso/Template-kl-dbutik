#!/usr/bin/env bash
# Restore data only with matching .env, secrets and application image reference.
set -euo pipefail
umask 077
if [[ $# -ne 2 || "$2" != --confirm-replace-data ]]; then
    echo 'Usage: restore.sh BACKUP.tar.gz.gpg --confirm-replace-data' >&2
    echo 'This replaces the current database, uploads, private files and certificates.' >&2
    exit 2
fi
root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)
archive=$(cd -- "$(dirname -- "$1")" && pwd -P)/$(basename -- "$1")
cd -- "$root"
docker_command=${DOCKER:-docker}
dc() { "$docker_command" compose "$@"; }
for program in "$docker_command" python3 gpg tar sha256sum; do command -v "$program" >/dev/null; done
[[ -f "$archive" && -f "$archive.sha256" ]] || { echo 'Backup and its .sha256 file are required.' >&2; exit 1; }
(cd -- "$(dirname -- "$archive")" && sha256sum -c "$(basename -- "$archive").sha256")
mkdir -p -- ./backups
snapshot=$(mktemp -d "$root/backups/.restore.XXXXXXXX")
cleanup() { status=$?; trap - EXIT; rm -rf -- "$snapshot"; exit "$status"; }
trap cleanup EXIT
gpg --batch --decrypt --output "$snapshot/bundle.tar.gz" "$archive"
python3 deploy/backup_archive.py extract "$snapshot/extracted" "$snapshot/bundle.tar.gz"
dc config --format json > "$snapshot/current-compose.json"
python3 deploy/backup_archive.py check-image "$snapshot/extracted" "$snapshot/current-compose.json"
dc stop -t 120 nginx app worker certbot
# On any subsequent failure keep writers stopped; inspect and retry instead of
# reopening a partially restored store. The script does not overwrite .env/secrets.
dc up -d --wait db
dc exec -T db sh -eu -c 'case "$MARIADB_DATABASE" in ""|*[!a-zA-Z0-9_]*) exit 2;; esac; export MYSQL_PWD="$(cat "$MARIADB_ROOT_PASSWORD_FILE")"; mariadb --user=root -e "DROP DATABASE IF EXISTS \`$MARIADB_DATABASE\`;"; exec mariadb --user=root' < "$snapshot/extracted/database.sql"
dc run --pull never --no-deps --rm -T --entrypoint sh app -eu -c 'find /var/www/shop/wp-content/uploads -mindepth 1 -maxdepth 1 -exec rm -rf -- {} +; tar -xzf - -C /var/www/shop/wp-content; chown -R www-data:www-data /var/www/shop/wp-content/uploads' < "$snapshot/extracted/uploads.tar.gz"
dc run --pull never --no-deps --rm -T --entrypoint sh app -eu -c 'find /var/shop-private -mindepth 1 -maxdepth 1 -exec rm -rf -- {} +; tar -xzf - -C /var/shop-private; chown -R www-data:www-data /var/shop-private' < "$snapshot/extracted/private.tar.gz"
dc run --pull never --no-deps --rm -T --entrypoint sh certbot -eu -c 'find /etc/letsencrypt -mindepth 1 -maxdepth 1 -exec rm -rf -- {} +; tar -xzf - -C /etc' < "$snapshot/extracted/certificates.tar.gz"
mkdir "$snapshot/verified"
dc run --pull never --no-deps --rm -T --entrypoint tar app -czf - -C /var/www/shop/wp-content uploads > "$snapshot/verified/uploads.tar.gz"
dc run --pull never --no-deps --rm -T --entrypoint tar app -czf - -C /var/shop-private . > "$snapshot/verified/private.tar.gz"
dc run --pull never --no-deps --rm -T --entrypoint tar certbot -czf - -C /etc letsencrypt > "$snapshot/verified/certificates.tar.gz"
python3 deploy/backup_archive.py compare "$snapshot/extracted" "$snapshot/verified"
dc up -d --wait
echo 'Restore completed; all persistent file hashes match and Compose health checks passed.'
echo 'Perform order/payment reconciliation before enabling purchases.'
