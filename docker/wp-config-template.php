<?php
// Runtime configuration: code is immutable; secrets never enter the image.
function shop_env(string $key, string $default = ''): string {
    $file = getenv($key . '_FILE');
    $value = getenv($key);
    if ($file !== false && $file !== '') {
        if ($value !== false && $value !== '') throw new RuntimeException('Ambiguous secret configuration: ' . $key);
        $contents = file_get_contents($file);
        if ($contents === false) throw new RuntimeException('Required secret file cannot be read: ' . $key);
        return rtrim($contents, "\r\n");
    }
    return ($value !== false && $value !== '') ? $value : $default;
}
define('DB_NAME', shop_env('WORDPRESS_DB_NAME', 'shop'));
define('DB_USER', shop_env('WORDPRESS_DB_USER', 'shop'));
define('DB_PASSWORD', shop_env('WORDPRESS_DB_PASSWORD'));
define('DB_HOST', shop_env('WORDPRESS_DB_HOST', 'db:3306'));
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
$shop_secret = shop_env('SHOP_SECRET');
if (strlen($shop_secret) < 32) throw new RuntimeException('SHOP_SECRET_FILE must contain at least 32 characters.');
foreach (['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'] as $key) {
    define($key, hash_hmac('sha512', $key, $shop_secret));
}
unset($shop_secret);
$shop_mode = shop_env('SHOP_MODE', 'production');
if (!in_array($shop_mode, ['demo', 'production'], true)) throw new RuntimeException('SHOP_MODE must be demo or production.');
$shop_url = rtrim(shop_env('SHOP_URL'), '/');
if (!filter_var($shop_url, FILTER_VALIDATE_URL) || parse_url($shop_url, PHP_URL_USER) !== null || parse_url($shop_url, PHP_URL_QUERY) !== null || parse_url($shop_url, PHP_URL_FRAGMENT) !== null) {
    throw new RuntimeException('SHOP_URL must be an absolute canonical URL without credentials/query/fragment.');
}
if ($shop_mode === 'production' && parse_url($shop_url, PHP_URL_SCHEME) !== 'https') throw new RuntimeException('Production requires an HTTPS SHOP_URL.');
define('WP_HOME', $shop_url);
define('WP_SITEURL', $shop_url);
define('WP_DEBUG', false);
define('WP_DEBUG_DISPLAY', false);
define('DISALLOW_FILE_EDIT', true);
define('DISALLOW_FILE_MODS', true);
define('AUTOMATIC_UPDATER_DISABLED', true);
define('DISABLE_WP_CRON', true);
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');
define('FORCE_SSL_ADMIN', $shop_mode === 'production');
define('WP_ENVIRONMENT_TYPE', $shop_mode === 'demo' ? 'local' : 'production');
// Trust forwarded data only from the configured private NGINX address.
if (($_SERVER['REMOTE_ADDR'] ?? '') === shop_env('SHOP_TRUSTED_PROXY', '172.30.72.2')) {
    if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') $_SERVER['HTTPS'] = 'on';
    if (filter_var($_SERVER['HTTP_X_REAL_IP'] ?? '', FILTER_VALIDATE_IP)) $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_REAL_IP'];
}
$table_prefix = 'shop_';
if (!defined('ABSPATH')) define('ABSPATH', '/var/www/shop/');
require_once ABSPATH . 'wp-settings.php';
