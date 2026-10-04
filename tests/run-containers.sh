#!/bin/sh
# Integration suite. Requires a Docker daemon, Compose plugin and Python 3.
# Uses an isolated demo project; never run this against a live production shop.
set -eu
cd "$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
project=${SHOP_TEST_PROJECT:-shopverify}
envfile=${SHOP_TEST_ENV_FILE:-.env}
docker_cli=${SHOP_DOCKER_CLI:-docker}
dc() { "$docker_cli" compose --project-name "$project" --env-file "$envfile" -f compose.yaml -f compose.dev.yaml "$@"; }
case "$project" in shopverify|shopverify-[a-z0-9-]*) ;; *) printf '%s\n' 'Use an isolated project named shopverify or shopverify-*.' >&2; exit 2 ;; esac
mode=$(dc config --format json | python3 -c 'import json,sys; print(json.load(sys.stdin)["services"]["app"]["environment"]["SHOP_MODE"])')
[ "$mode" = demo ] || { printf '%s\n' 'Tests require SHOP_MODE=demo.' >&2; exit 2; }
dc up --build --wait --wait-timeout 240
dc exec -T nginx nginx -t -c /tmp/nginx.conf
DOCKER_COMMAND="$docker_cli" python3 tests/test_image_layout.py
dc exec -T app sh -c 'cat > /tmp/launch-guard.php' < app/plugin/tests/launch-guard.php
dc exec -T app wp --allow-root eval-file /tmp/launch-guard.php
dc exec -T app sh -c 'cat > /tmp/product-permissions.php' < app/plugin/tests/product-permissions.php
dc exec -T app wp --allow-root eval-file /tmp/product-permissions.php
dc exec -T app sh -c 'cat > /tmp/example-products.csv' < examples/products.csv
dc exec -T app sh -c 'cat > /tmp/standard-csv.php' < tests/standard-csv.php
dc exec -T app wp --allow-root eval-file /tmp/standard-csv.php /tmp/example-products.csv
SHOP_TEST_PRODUCT_ID=$(dc exec -T app wp --allow-root eval 'echo wc_get_product_id_by_sku("DEMO-TSHIRT-M");')
export SHOP_TEST_PRODUCT_ID
SHOP_TEST_URL=${SHOP_TEST_URL:-http://localhost:18080}
export SHOP_TEST_URL
python3 tests/test_http.py
DOCKER_COMMAND="$docker_cli" python3 tests/test_logs.py
dc exec -T app sh -c 'cat > /tmp/persistence.php' < tests/persistence.php
dc exec -T app wp --allow-root eval-file /tmp/persistence.php create
dc up -d --force-recreate --wait --wait-timeout 240
DOCKER_COMMAND="$docker_cli" python3 tests/test_image_layout.py
dc exec -T app sh -c 'cat > /tmp/persistence.php' < tests/persistence.php
dc exec -T app wp --allow-root eval-file /tmp/persistence.php verify
python3 tests/test_http.py
printf '%s\n' 'Dynamic NGINX/WordPress/WooCommerce integration and container recreation checks passed.'
printf '%s\n' 'The test project is still available. Remove it deliberately with docker compose --project-name ... down.'
