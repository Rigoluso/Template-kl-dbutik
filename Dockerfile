# All application dependencies are shipped and locked in dependencies.lock.json.
FROM wordpress:7.1.2-php8.3-apache@sha256:4abf7a450ee477dde967584f8174d7e03221d224c4971a0c38d84e7254426e64
COPY vendor/ /opt/shop/vendor/
RUN printf '%s\n' \
 '9de9350a1cf5671b9960afb3151f40f7980e223217a441bf2ea5921b5fce8e9e  /opt/shop/vendor/woocommerce.zip' \
 'ce34ddd838f7351d6759068d09793f26755463b4a4610a5a5c0a97b68220d85c  /opt/shop/vendor/wp-cli.phar' \
 '44f324160a9f8bf9b16b800aa370941463143388c588928b32b8cba9a7a1bf20  /opt/shop/vendor/wordpress_sv_SE.zip' \
 'f4d1f0cc98928d0aa36f9c7bebb23e4a5d1c682738b68364072d515ab41a75f4  /opt/shop/vendor/woocommerce_sv_SE.zip' \
 | sha256sum -c - \
 && mkdir -p /var/www/shop \
 && cp -r /usr/src/wordpress/. /var/www/shop/ \
 && php -r '$z = new ZipArchive(); if ($z->open("/opt/shop/vendor/woocommerce.zip") !== true) exit(1); if (!$z->extractTo("/var/www/shop/wp-content/plugins")) exit(1); $z->close();' \
 && mkdir -p /var/www/shop/wp-content/languages/plugins \
 && php -r 'foreach (["wordpress_sv_SE.zip" => "/var/www/shop/wp-content/languages", "woocommerce_sv_SE.zip" => "/var/www/shop/wp-content/languages/plugins"] as $file => $directory) { $z = new ZipArchive(); if ($z->open("/opt/shop/vendor/" . $file) !== true || !$z->extractTo($directory)) exit(1); $z->close(); }' \
 && cp /opt/shop/vendor/wp-cli.phar /usr/local/bin/wp \
 && chmod 0755 /usr/local/bin/wp \
 && rm -rf /opt/shop/vendor /var/www/shop/wp-content/plugins/akismet /var/www/shop/wp-content/plugins/hello.php \
 && mkdir -p /var/www/shop/wp-content/mu-plugins /var/www/shop/wp-content/uploads /var/shop-private \
 && ln -s /tmp/shop-wp-config.php /var/www/shop/wp-config.php
COPY app/theme/ /var/www/shop/wp-content/themes/kladbutik/
COPY app/plugin/ /var/www/shop/wp-content/mu-plugins/
COPY app/bootstrap.php /opt/shop/bootstrap.php
COPY docker/ /opt/shop/
COPY docker/health.php /var/www/shop/health.php
COPY docker/htaccess /var/www/shop/.htaccess
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
RUN chmod 0755 /opt/shop/*.sh \
 && rm -rf /var/www/shop/wp-content/mu-plugins/tests \
 && chmod 0755 /var/www/shop \
 && chown root:root /var/www/shop \
 && chown www-data:www-data /var/www/shop/wp-content/uploads /var/shop-private
ENV WP_CLI_ALLOW_ROOT=1
# The upstream image declares /var/www/html as a VOLUME. Keep application code
# outside it so image updates cannot retain stale anonymous-volume contents.
WORKDIR /var/www/shop
ENTRYPOINT ["/opt/shop/entrypoint.sh"]
CMD ["apache2-foreground"]
