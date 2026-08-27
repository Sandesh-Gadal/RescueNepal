<?php
function app_config(): array {
    static $config;
    if (!$config) {
        $path = __DIR__ . '/../config.php';
        if (!file_exists($path)) {
            if (PHP_SAPI !== 'cli') {
                $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
                $scheme = $https ? 'https' : 'http';
                $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
                header('Location: ' . $scheme . '://' . $host . '/setup', true, 302);
                exit;
            }
            throw new RuntimeException('Missing config.php. Run the web setup first.');
        }
        $config = require $path;
        date_default_timezone_set($config['timezone'] ?? 'Asia/Kathmandu');
    }
    return $config;
}

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $c = app_config()['db'];
        $dsn = "mysql:host={$c['host']};dbname={$c['name']};charset={$c['charset']}";
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}
