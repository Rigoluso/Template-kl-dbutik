#!/bin/sh
set -eu
configuration=/tmp/nginx.conf
candidate=/tmp/nginx-candidate.conf
phase=http
signature=none
certificate_signature() {
    [ "${SHOP_MODE:-production}" = production ] || return 1
    directory="/etc/letsencrypt/live/${SHOP_DOMAIN:-}"
    [ -s "$directory/fullchain.pem" ] && [ -s "$directory/privkey.pem" ] || return 1
    sha256sum "$directory/fullchain.pem" "$directory/privkey.pem" | sha256sum | cut -d' ' -f1
}
if initial=$(certificate_signature); then
    # Existing valid files permit immediate HTTPS after a restart.
    if sh /opt/shop-nginx/render.sh "$candidate" tls && nginx -t -c "$candidate"; then
        phase=tls
        signature=$initial
    fi
fi
sh /opt/shop-nginx/render.sh "$configuration" "$phase"
nginx -t -c "$configuration"
nginx -c "$configuration" -g 'daemon off;' &
nginx_pid=$!
cleanup() {
    trap - TERM INT
    kill -QUIT "$nginx_pid" 2>/dev/null || true
    wait "$nginx_pid" 2>/dev/null || true
    exit 0
}
trap cleanup TERM INT
while kill -0 "$nginx_pid" 2>/dev/null; do
    if current=$(certificate_signature) && [ "$current" != "$signature" ]; then
        if sh /opt/shop-nginx/render.sh "$candidate" tls && nginx -t -c "$candidate"; then
            cp "$configuration" /tmp/nginx-previous.conf
            mv "$candidate" "$configuration"
            if nginx -s reload -c "$configuration"; then
                signature=$current
                printf '%s\n' 'NGINX enabled/reloaded HTTPS certificate.'
            else
                mv /tmp/nginx-previous.conf "$configuration"
                printf '%s\n' 'NGINX reload failed; keeping the previously active configuration.' >&2
            fi
        else
            printf '%s\n' 'Certificate/configuration validation failed; keeping the active listener.' >&2
        fi
    fi
    sleep 30 &
    wait $! || true
done
wait "$nginx_pid"
