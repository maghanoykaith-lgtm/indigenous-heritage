<?php
// Usage: php database/migrate.php
// Brings the configured database up to date. Safe to run repeatedly.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/migrations.php';

$cfg = app_config('db');
echo '[migrate] Environment: ' . app_config('env') . PHP_EOL;
echo '[migrate] Database: ' . ($cfg ? $cfg['host'] . ':' . $cfg['port'] . '/' . $cfg['name'] : 'not configured') . PHP_EOL;

run_migrations(db(), static function (string $message): void {
    echo '[migrate] ' . $message . PHP_EOL;
});
