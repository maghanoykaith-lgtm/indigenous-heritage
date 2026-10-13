<?php

define('APP_ROOT', dirname(__DIR__));

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/heritage.php';

date_default_timezone_set(app_config('timezone') ?: 'UTC');
error_reporting(E_ALL);
ini_set('display_errors', app_config('debug') ? '1' : '0');
ini_set('log_errors', '1');

set_exception_handler(static function (Throwable $e): void {
    error_log('[IHMS] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        exit(1);
    }
    if ($e instanceof DatabaseUnavailable) {
        render_error_page(503, 'Service Temporarily Unavailable', 'The heritage database is not reachable right now. Please try again in a minute.');
    }
    $detail = app_config('debug') ? $e->getMessage() : 'An unexpected error occurred. Please try again later.';
    render_error_page(500, 'Something Went Wrong', $detail);
});

if (PHP_SAPI === 'cli') {
    return;
}

if (app_config('force_https') && !is_https()) {
    $target = app_config('url') ?: 'https://' . preg_replace('/[^A-Za-z0-9.:-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
    $path = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: ' . rtrim(preg_replace('#^http://#i', 'https://', $target), '/') . $path, true, 301);
    exit;
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; img-src 'self' data: blob:; object-src 'none'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
if (is_https()) {
    header('Strict-Transport-Security: max-age=31536000');
}

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
session_name('IHMS_SESSION');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => app_base_path() !== '' ? app_base_path() . '/' : '/',
    'secure' => is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$isAuthenticated = !empty($_SESSION['staff']) || !empty($_SESSION['member']);
if ($isAuthenticated) {
    $timeout = (int) app_config('session_timeout_minutes') * 60;
    if (time() - (int) ($_SESSION['_last_activity'] ?? 0) > $timeout) {
        logout_everyone();
        session_start();
        flash('warning', 'Your session expired after a period of inactivity. Please sign in again.');
    } else {
        $_SESSION['_last_activity'] = time();
        if (time() - (int) ($_SESSION['_regenerated_at'] ?? 0) > 900) {
            session_regenerate_id(true);
            $_SESSION['_regenerated_at'] = time();
        }
    }
}
unset($isAuthenticated);

if (is_post()) {
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if (empty($_POST) && empty($_FILES) && $contentLength > 0) {
        flash('error', 'The submitted files were too large. Each file must be under ' . format_bytes((int) app_config('upload_max_bytes')) . '.');
        header('Location: ' . ($_SERVER['REQUEST_URI'] ?? url('index.php')), true, 303);
        exit;
    }
    if (!csrf_is_valid($_POST['_csrf'] ?? null)) {
        render_error_page(403, 'Form Expired', 'Your form session expired or was invalid. Please go back, refresh the page, and try again.');
    }
}
