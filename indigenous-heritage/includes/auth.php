<?php

const STAFF_ROLES = [
    'administrator' => 'Administrator',
    'editor' => 'Editor',
];

const MEMBER_STATUSES = [
    'pending' => 'Pending approval',
    'active' => 'Active',
    'suspended' => 'Suspended',
];

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_WINDOW_MINUTES = 15;
const DEFAULT_LEGACY_PASSWORD = 'admin123';

function current_staff(): ?array
{
    static $loaded = false;
    static $staff = null;
    if ($loaded) {
        return $staff;
    }
    $loaded = true;
    if (empty($_SESSION['staff']['id'])) {
        return null;
    }
    $row = db_one(
        'SELECT id, username, email, full_name, role, is_active, must_change_password FROM admins WHERE id = ?',
        [(int) $_SESSION['staff']['id']]
    );
    if (!$row || !(int) $row['is_active']) {
        unset($_SESSION['staff']);
        return null;
    }
    $staff = $row;
    return $staff;
}

function is_staff(): bool
{
    return current_staff() !== null;
}

function is_administrator(): bool
{
    $staff = current_staff();
    return $staff !== null && $staff['role'] === 'administrator';
}

function current_member(): ?array
{
    static $loaded = false;
    static $member = null;
    if ($loaded) {
        return $member;
    }
    $loaded = true;
    if (empty($_SESSION['member']['id'])) {
        return null;
    }
    $row = db_one('SELECT id, username, email, full_name, community, status, created_at FROM users WHERE id = ?', [(int) $_SESSION['member']['id']]);
    if (!$row || $row['status'] === 'suspended') {
        unset($_SESSION['member']);
        return null;
    }
    $member = $row;
    return $member;
}

function is_active_member(): bool
{
    $member = current_member();
    return $member !== null && $member['status'] === 'active';
}

function require_staff(): array
{
    $staff = current_staff();
    if (!$staff) {
        $_SESSION['_after_login'] = $_SERVER['REQUEST_URI'] ?? null;
        flash('error', 'Please sign in with a staff account to continue.');
        redirect('admin/login.php');
    }
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ((int) $staff['must_change_password'] && !in_array($script, ['account.php', 'logout.php'], true)) {
        redirect('admin/account.php');
    }
    return $staff;
}

function require_administrator(): array
{
    $staff = require_staff();
    if ($staff['role'] !== 'administrator') {
        render_error_page(403, 'Access Denied', 'This area is limited to administrators.');
    }
    return $staff;
}

function require_member(): array
{
    $member = current_member();
    if (!$member) {
        flash('error', 'Please sign in to view your member dashboard.');
        redirect('admin/login.php');
    }
    return $member;
}

function start_authenticated_session(): void
{
    session_regenerate_id(true);
    $_SESSION['_last_activity'] = time();
    $_SESSION['_regenerated_at'] = time();
    unset($_SESSION['_csrf']);
}

function login_staff(array $row): void
{
    start_authenticated_session();
    unset($_SESSION['member']);
    $_SESSION['staff'] = ['id' => (int) $row['id'], 'username' => $row['username']];
    db_run('UPDATE admins SET last_login_at = NOW() WHERE id = ?', [(int) $row['id']]);
}

function login_member(array $row): void
{
    start_authenticated_session();
    unset($_SESSION['staff']);
    $_SESSION['member'] = ['id' => (int) $row['id'], 'username' => $row['username']];
    db_run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [(int) $row['id']]);
}

function logout_everyone(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}

function login_is_throttled(string $identifier): bool
{
    $since = date('Y-m-d H:i:s', time() - LOGIN_WINDOW_MINUTES * 60);
    $byAccount = (int) db_value('SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND ip_address = ? AND attempted_at > ?', [$identifier, client_ip(), $since]);
    $byIp = (int) db_value('SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at > ?', [client_ip(), $since]);
    return $byAccount >= LOGIN_MAX_ATTEMPTS || $byIp >= LOGIN_MAX_ATTEMPTS * 4;
}

function record_failed_login(string $identifier): void
{
    db_run('INSERT INTO login_attempts (identifier, ip_address) VALUES (?, ?)', [$identifier, client_ip()]);
    if (random_int(1, 20) === 1) {
        db_run('DELETE FROM login_attempts WHERE attempted_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    }
}

function clear_failed_logins(string $identifier): void
{
    db_run('DELETE FROM login_attempts WHERE identifier = ? AND ip_address = ?', [$identifier, client_ip()]);
}

function password_problem(string $password, string $confirm): ?string
{
    if (mb_strlen($password) < 8) {
        return 'Password must be at least 8 characters long.';
    }
    if (mb_strlen($password) > 128) {
        return 'Password must be 128 characters or fewer.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Password must contain at least one letter and one number.';
    }
    if (strtolower($password) === DEFAULT_LEGACY_PASSWORD) {
        return 'Please choose a password other than the old default.';
    }
    if ($password !== $confirm) {
        return 'Passwords do not match.';
    }
    return null;
}

function valid_username(string $username): bool
{
    return (bool) preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username);
}
