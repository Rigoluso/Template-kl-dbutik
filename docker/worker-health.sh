#!/bin/sh
set -eu
test -f /tmp/shop-worker-heartbeat
now=$(date +%s)
last=$(stat -c %Y /tmp/shop-worker-heartbeat)
test "$((now - last))" -lt 150

