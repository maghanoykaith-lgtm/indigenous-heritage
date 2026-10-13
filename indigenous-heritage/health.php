<?php
// Health check for the hosting platform. Loads only config and the database
// layer so it never redirects, starts a session, or exposes details.

define('APP_ROOT', __DIR__);
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/db.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

try {
    db()->query('SELECT 1')->fetchColumn();
    echo 'ok';
} catch (Throwable $e) {
    error_log('[IHMS] Health check failed: ' . $e->getMessage());
    http_response_code(503);
    echo 'database unavailable';
}
