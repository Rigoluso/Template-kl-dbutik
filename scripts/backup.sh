#!/usr/bin/env bash
# Linux host prerequisites: Docker/Compose, Python 3, GnuPG and a recipient key.
set -euo pipefail
umask 077
if [[ -z "${BACKUP_GPG_RECIPIENT:-}" ]]; then
    echo 'Set BACKUP_GPG_RECIPIENT to your imported backup encryption key fingerprint.' >&2
    exit 2
fi
root=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)
cd -- "$root"
docker_command=${DOCKER:-docker}
dc() { "$docker_command" compose "$@"; }
for program in "$docker_command" python3 gpg tar sha256sum; do command -v "$program" >/dev/null; done
gpg --batch --list-keys "$BACKUP_GPG_RECIPIENT" >/dev/null
retention=${BACKUP_RETENTION_DAYS:-14}
[[ "$retention" =~ ^[0-9]+$ ]] || { echo 'BACKUP_RETENTION_DAYS must be a nonnegative integer.' >&2; exit 2; }
destination=${1:-./backups}
mkdir -p -- "$destination"
destination=$(cd -- "$destination" && pwd -P)
snapshot=$(mktemp -d "$destination/.snapshot.XXXXXXXX")
running=()
while IFS= read -r service; do
    case "$service" in nginx|app|worker|certbot) running+=("$service");; esac
done < <(dc ps --services --status running)
[[ " ${running[*]} " == *' app '* ]] || { echo 'App must be running before a consistent backup.' >&2; rm -rf -- "$snapshot"; exit 1; }
frozen=0
cleanup() {
    status=$?
    trap - EXIT
    if [[ "$frozen" == 1 ]]; then dc start "${running[@]}" >/dev/null || status=1; fi
    rm -rf -- "$snapshot"
    exit "$status"
}
trap cleanup EXIT
dc config --format json > "$snapshot/resolved-compose.json"
python3 deploy/backup_archive.py capture "$snapshot" "$root"
frozen=1
dc stop -t 120 "${running[@]}"
dc exec -T db sh -eu -c 'export MYSQL_PWD="$(cat "$MARIADB_ROOT_PASSWORD_FILE")"; exec mariadb-dump --user=root --single-transaction --routines --triggers --events --hex-blob --databases "$MARIADB_DATABASE"' > "$snapshot/database.sql"
# Use the already-running deployment's images. Dev pull_policy=build would
# otherwise prepend build progress to the binary stdout archive stream.
dc run --pull never --no-deps --rm -T --entrypoint tar app -czf - -C /var/www/shop/wp-content uploads > "$snapshot/uploads.tar.gz"
dc run --pull never --no-deps --rm -T --entrypoint tar app -czf - -C /var/shop-private . > "$snapshot/private.tar.gz"
dc run --pull never --no-deps --rm -T --entrypoint tar certbot -czf - -C /etc letsencrypt > "$snapshot/certificates.tar.gz"
python3 deploy/backup_archive.py manifest "$snapshot"
name="shop-backup-$(date -u +%Y%m%dT%H%M%SZ)-$RANDOM.tar.gz.gpg"
target="$destination/$name"
tar -C "$snapshot" -czf - . | gpg --batch --yes --trust-model always --recipient "$BACKUP_GPG_RECIPIENT" --encrypt --output "$target.partial"
mv -- "$target.partial" "$target"
(cd -- "$destination" && sha256sum "$name" > "$name.sha256")
if [[ -n "${BACKUP_COPY_DIR:-}" ]]; then
    mkdir -p -- "$BACKUP_COPY_DIR"
    cp -- "$target" "$target.sha256" "$BACKUP_COPY_DIR/"
fi
# Delete only this script's files in the explicit backup directory, never recurse.
find "$destination" -maxdepth 1 -type f -name 'shop-backup-*.tar.gz.gpg*' -mtime "+$retention" -delete
echo "Encrypted backup: $target"
