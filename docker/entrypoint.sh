#!/bin/sh
set -eu
umask 027
mkdir -p /tmp/shop-runtime-secrets
chmod 0700 /tmp/shop-runtime-secrets
if [ -n "${WORDPRESS_DB_PASSWORD_FILE:-}" ]; then
    cp "$WORDPRESS_DB_PASSWORD_FILE" /tmp/shop-runtime-secrets/db_password
    export WORDPRESS_DB_PASSWORD_FILE=/tmp/shop-runtime-secrets/db_password
fi
if [ -n "${SHOP_SECRET_FILE:-}" ]; then
    cp "$SHOP_SECRET_FILE" /tmp/shop-runtime-secrets/shop_secret
    export SHOP_SECRET_FILE=/tmp/shop-runtime-secrets/shop_secret
fi
chown -R www-data:www-data /tmp/shop-runtime-secrets
chmod 0400 /tmp/shop-runtime-secrets/*
cp /opt/shop/wp-config-template.php /tmp/shop-wp-config.php
chown root:www-data /tmp/shop-wp-config.php
chmod 0640 /tmp/shop-wp-config.php
if [ "$(id -u)" = 0 ]; then
    mkdir -p /var/www/shop/wp-content/uploads /var/shop-private /var/run/apache2 /var/lock/apache2
    chown www-data:www-data /var/www/shop/wp-content/uploads /var/shop-private /var/run/apache2 /var/lock/apache2
fi
exec "$@"
