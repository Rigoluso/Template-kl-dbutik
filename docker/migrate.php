<?php
declare(strict_types=1);
// Advisory lock remains on this connection across every child migration command.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
function install_env(string $key, string $fallback = ''): string {
    $file = getenv($key . '_FILE');
    if ($file) {
        $data = file_get_contents($file);
        if ($data === false) throw new RuntimeException('Missing required secret: ' . $key);
        return rtrim($data, "\r\n");
    }
    return getenv($key) ?: $fallback;
}
function cli(array $arguments, ?string $stdin = null, bool $mustSucceed = true): array {
    $process = proc_open(array_merge(['/usr/local/bin/php', '/usr/local/bin/wp', '--allow-root', '--path=/var/www/shop'], $arguments), [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Cannot start migration command.');
    if ($stdin !== null) fwrite($pipes[0], $stdin);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $errors = stream_get_contents($pipes[2]); fclose($pipes[2]);
    $status = proc_close($process);
    // WP CLI's non-interactive password prompt may echo input. Never log it.
    if ($mustSucceed && $status !== 0) throw new RuntimeException($stdin !== null ? 'Administrator installation failed (sensitive output suppressed).' : 'Migration command failed: ' . implode(' ', $arguments) . "\n" . $errors);
    if ($mustSucceed && $output !== '' && $stdin === null) fwrite(STDOUT, $output);
    if ($errors !== '' && $status === 0 && $stdin === null) fwrite(STDERR, $errors);
    return [$status, $output];
}
try {
    [$host, $port] = array_pad(explode(':', install_env('WORDPRESS_DB_HOST', 'db:3306'), 2), 2, '3306');
    $db = null;
    for ($attempt = 0; $attempt < 60; $attempt++) {
        try { $db = new mysqli($host, install_env('WORDPRESS_DB_USER', 'shop'), install_env('WORDPRESS_DB_PASSWORD'), install_env('WORDPRESS_DB_NAME', 'shop'), (int)$port); break; }
        catch (mysqli_sql_exception $e) { if ($attempt === 59) throw new RuntimeException('Database did not become available.'); sleep(2); }
    }
    if ((int)$db->query("SELECT GET_LOCK('shop-template-migrations', 120) AS acquired")->fetch_assoc()['acquired'] !== 1) throw new RuntimeException('Migration lock unavailable.');
    try {
        [$installed] = cli(['core', 'is-installed'], null, false);
        if ($installed !== 0) {
            $password = install_env('SHOP_ADMIN_PASSWORD');
            $user = install_env('SHOP_ADMIN_USER');
            $email = install_env('SHOP_ADMIN_EMAIL');
            if (strlen($password) < 20 || !preg_match('/^[A-Za-z0-9_.-]{3,60}$/D', $user) || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Set a valid administrator username/email and password file (at least 20 characters).');
            cli(['core', 'install', '--url=' . install_env('SHOP_URL'), '--title=Klädbutik', '--admin_user=' . $user, '--admin_email=' . $email, '--skip-email', '--prompt=admin_password'], $password . "\n");
            unset($password);
        }
        cli(['core', 'update-db']);
        cli(['plugin', 'activate', 'woocommerce']);
        cli(['wc', 'update']);
        cli(['eval-file', '/opt/shop/bootstrap.php']);
        fwrite(STDOUT, "Installation and migrations completed.\n");
    } finally { $db->query("SELECT RELEASE_LOCK('shop-template-migrations')"); }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
