#!/bin/sh
set -eu
trap 'exit 0' TERM INT
pause() { sleep "$1" & wait $! || true; }
if [ "${SHOP_MODE:-production}" = demo ]; then
    while :; do pause 86400; done
fi
[ "${SHOP_MODE:-}" = production ] || { echo 'Invalid SHOP_MODE.' >&2; exit 1; }
[ -n "${SHOP_DOMAIN:-}" ] && [ -n "${ACME_EMAIL:-}" ] || { echo 'Production requires SHOP_DOMAIN and ACME_EMAIL.' >&2; exit 1; }
# NGINX validates the same domain before this dependency becomes healthy.
set -- --non-interactive --agree-tos --email "$ACME_EMAIL" --webroot --webroot-path /var/www/acme --cert-name "$SHOP_DOMAIN" -d "$SHOP_DOMAIN"
if [ -n "${SHOP_WWW_DOMAIN:-}" ]; then set -- "$@" -d "$SHOP_WWW_DOMAIN"; fi
if [ "${ACME_STAGING:-0}" = 1 ]; then set -- "$@" --staging; fi
while [ ! -s "/etc/letsencrypt/live/$SHOP_DOMAIN/fullchain.pem" ]; do
    if ! certbot certonly "$@"; then
        echo 'ACME issuance failed. Check DNS, A/AAAA records and inbound port 80; retrying in one hour.' >&2
        pause 3600
    fi
done
while :; do
    if ! certbot renew --non-interactive --webroot --webroot-path /var/www/acme; then
        echo 'Certificate renewal failed; inspect certbot logs and certificate expiry.' >&2
    fi
    # NGINX detects the new certificate hash and validates/reloads without a Docker socket.
    pause 43200
done
