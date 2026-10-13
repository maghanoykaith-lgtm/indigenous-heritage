<?php

class DatabaseUnavailable extends RuntimeException
{
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = app_config('db');
    if (!$cfg || $cfg['name'] === '') {
        throw new DatabaseUnavailable('Database connection settings are missing. Set DATABASE_URL or DB_HOST/DB_NAME/DB_USER/DB_PASSWORD.');
    }

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $cfg['host'], $cfg['port'], $cfg['name']);
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 10,
    ];

    if ($cfg['ssl']) {
        $caFile = $cfg['ssl_ca'] ?: null;
        if (!$caFile && $cfg['ssl_ca_pem']) {
            $pem = str_replace('\n', "\n", $cfg['ssl_ca_pem']);
            $caFile = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'ihms-db-ca-' . substr(hash('sha256', $pem), 0, 16) . '.pem';
            if (!is_file($caFile)) {
                file_put_contents($caFile, $pem, LOCK_EX);
            }
        }
        if ($caFile) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $caFile;
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
        } else {
            // Encrypted but unverified: used only when no CA certificate is supplied.
            $bundle = '/etc/ssl/certs/ca-certificates.crt';
            $options[PDO::MYSQL_ATTR_SSL_CA] = is_file($bundle) ? $bundle : '';
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }
    }

    try {
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], $options);
    } catch (PDOException $e) {
        error_log('[IHMS] Database connection failed: ' . $e->getMessage());
        throw new DatabaseUnavailable('Unable to connect to the database.', 0, $e);
    }

    // Managed hosts (e.g. Aiven) default to ANSI mode; pin the standard MySQL 8 mode for consistent behaviour.
    $pdo->exec("SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    $pdo->exec("SET time_zone = '" . (new DateTime('now'))->format('P') . "'");
    return $pdo;
}

function db_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function db_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_value(string $sql, array $params = [])
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $value = $stmt->fetchColumn();
    return $value === false ? null : $value;
}

function db_run(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
