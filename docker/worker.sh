#!/bin/sh
set -eu
sleep_pid=
stop() {
    if [ -n "$sleep_pid" ]; then kill "$sleep_pid" 2>/dev/null || true; fi
    exit 0
}
trap stop TERM INT
while :; do
    if wp --allow-root --path=/var/www/shop cron event run --due-now; then
        touch /tmp/shop-worker-heartbeat
    else
        printf '%s\n' 'Background job failed; retrying in 30 seconds.' >&2
    fi
    sleep 30 &
    sleep_pid=$!
    wait "$sleep_pid" || true
    sleep_pid=
done
