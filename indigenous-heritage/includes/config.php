<?php
// Settings come from real environment variables first (production hosts),
// then from a git-ignored .env file in the project root (local development).

function load_env_file(string $path): void
{
    if (!is_readable($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $quote = $value[0] ?? '';
        if (strlen($value) >= 2 && ($quote === '"' || $quote === "'") && substr($value, -1) === $quote) {
            $value = substr($value, 1, -1);
            if ($quote === '"') {
                $value = str_replace('\n', "\n", $value);
            }
        }
        if (getenv($key) === false && !array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
        }
    }
}

function env(string $key, $default = null)
{
    $value = getenv($key);
    if ($value === false) {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
    }
    if ($value === null || $value === '') {
        return $default;
    }
    return $value;
}

function env_bool(string $key, bool $default = false): bool
{
    $value = env($key);
    if ($value === null) {
        return $default;
    }
    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on', 'required'], true);
}

function database_config(string $environment): ?array
{
    $url = env('DATABASE_URL');
    if ($url) {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return null;
        }
        parse_str($parts['query'] ?? '', $query);
        $sslMode = strtoupper((string) ($query['ssl-mode'] ?? $query['sslmode'] ?? ''));
        return [
            'host' => $parts['host'],
            'port' => (int) ($parts['port'] ?? 3306),
            'name' => ltrim($parts['path'] ?? '', '/') ?: (string) env('DB_NAME', ''),
            'user' => urldecode($parts['user'] ?? ''),
            'pass' => urldecode($parts['pass'] ?? ''),
            'ssl' => env_bool('DB_SSL', in_array($sslMode, ['REQUIRED', 'REQUIRE', 'VERIFY_CA', 'VERIFY_IDENTITY'], true)),
            'ssl_ca' => env('DB_SSL_CA'),
            'ssl_ca_pem' => env('DB_SSL_CA_PEM'),
        ];
    }

    $isProduction = $environment === 'production';
    $host = env('DB_HOST', $isProduction ? null : 'localhost');
    if (!$host) {
        return null;
    }
    return [
        'host' => $host,
        'port' => (int) env('DB_PORT', 3306),
        'name' => (string) env('DB_NAME', $isProduction ? '' : 'indigenous_heritage'),
        'user' => (string) env('DB_USER', $isProduction ? '' : 'root'),
        'pass' => (string) env('DB_PASSWORD', ''),
        'ssl' => env_bool('DB_SSL', false),
        'ssl_ca' => env('DB_SSL_CA'),
        'ssl_ca_pem' => env('DB_SSL_CA_PEM'),
    ];
}

function app_config(?string $key = null)
{
    static $config = null;
    if ($config === null) {
        load_env_file(APP_ROOT . '/.env');
        $environment = strtolower((string) env('APP_ENV', 'local'));
        $config = [
            'name' => 'Indigenous Heritage Management System',
            'short_name' => 'Indigenous Heritage',
            'env' => $environment,
            'debug' => env_bool('APP_DEBUG', $environment !== 'production'),
            'url' => rtrim((string) env('APP_URL', ''), '/'),
            'base_path' => env('APP_BASE_PATH'),
            'timezone' => (string) env('APP_TIMEZONE', 'Asia/Manila'),
            'force_https' => env_bool('FORCE_HTTPS', $environment === 'production'),
            'trusted_proxy_hops' => (int) env('TRUSTED_PROXY_HOPS', $environment === 'production' ? 1 : 0),
            'session_timeout_minutes' => max(5, (int) env('SESSION_TIMEOUT_MINUTES', 30)),
            'upload_max_bytes' => (int) round((float) env('UPLOAD_MAX_MB', 5) * 1048576),
            'contact_email' => (string) env('CONTACT_EMAIL', 'archive@indigenousheritage.org'),
            'db' => database_config($environment),
        ];
    }
    return $key === null ? $config : ($config[$key] ?? null);
}

function is_production(): bool
{
    return app_config('env') === 'production';
}
