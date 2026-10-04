#!/bin/sh
# Run once from the installation folder. Existing configuration is preserved.
set -eu
umask 077
cd "$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
case "${1:-}" in
    --demo) example=.env.demo.example ;;
    --production) example=.env.example ;;
    *) printf '%s\n' 'Usage: sh scripts/setup.sh --demo|--production' >&2; exit 2 ;;
esac
if [ ! -f .env ]; then cp "$example" .env; fi
chmod 0600 .env
mkdir -p secrets
chmod 0700 secrets
for name in db_password db_root_password admin_password shop_secret; do
    if [ ! -f "secrets/$name" ]; then
        od -An -N32 -tx1 /dev/urandom | tr -d ' \n' > "secrets/$name"
        printf '\n' >> "secrets/$name"
    fi
    chmod 0600 "secrets/$name"
done
printf '%s\n' 'Configuration and secret files prepared. Existing values were preserved.'
printf '%s\n' 'Administrator password: secrets/admin_password (read locally; do not paste into logs/chat).'
if [ "$example" = .env.demo.example ]; then
    printf '%s\n' 'Start: docker compose -f compose.yaml -f compose.dev.yaml up --build -d'
    printf '%s\n' 'Demo: http://localhost:18080 ; admin: http://localhost:18080/wp-admin/'
else
    printf '%s\n' 'Complete .env domain, administrator email and actual published SHOP_APP_IMAGE before starting.'
    printf '%s\n' 'After image publication and one-time configuration: sudo docker compose pull && sudo docker compose up -d'
fi
