<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    // Docker's upstream document root is sticky/world-writable by default;
    // the hardened image must permit Apache to follow the runtime config link.
    if (!is_readable(__DIR__ . '/wp-config.php')) throw new RuntimeException('Application configuration is not readable.');
    $secret = getenv('WORDPRESS_DB_PASSWORD_FILE');
    $password = $secret ? rtrim((string)file_get_contents($secret), "\r\n") : (getenv('WORDPRESS_DB_PASSWORD') ?: '');
    [$host, $port] = array_pad(explode(':', getenv('WORDPRESS_DB_HOST') ?: 'db:3306', 2), 2, '3306');
    $db = mysqli_init();
    $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
    $db->real_connect($host, getenv('WORDPRESS_DB_USER') ?: 'shop', $password, getenv('WORDPRESS_DB_NAME') ?: 'shop', (int)$port);
    $ready = $db->query("SELECT option_value FROM shop_options WHERE option_name = 'shop_template_bootstrapped'")->fetch_assoc();
    if (!$ready || $ready['option_value'] !== '0.1.0') throw new RuntimeException('Schema not ready.');
    echo json_encode(['status' => 'ok', 'version' => '0.1.0']);
} catch (Throwable $error) {
    http_response_code(503);
    echo json_encode(['status' => 'not_ready']);
}
