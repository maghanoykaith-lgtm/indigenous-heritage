<?php

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $configured = app_config('base_path');
    if ($configured !== null) {
        return $base = rtrim('/' . trim((string) $configured, '/'), '/');
    }
    $docRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $appRoot = realpath(APP_ROOT);
    if ($docRoot && $appRoot && stripos($appRoot, $docRoot) === 0) {
        $relative = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));
        return $base = rtrim('/' . ltrim($relative, '/'), '/');
    }
    return $base = '';
}

function url(string $path = '', array $params = []): string
{
    $params = array_filter($params, static fn($v) => $v !== null && $v !== '');
    $query = $params ? '?' . http_build_query($params) : '';
    return app_base_path() . '/' . ltrim($path, '/') . $query;
}

function asset(string $path): string
{
    $file = APP_ROOT . '/' . ltrim($path, '/');
    $version = is_file($file) ? substr((string) filemtime($file), -6) : '1';
    return url($path) . '?v=' . $version;
}

function redirect(string $path, array $params = []): void
{
    header('Location: ' . url($path, $params), true, 303);
    exit;
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function client_ip(): string
{
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $hops = (int) app_config('trusted_proxy_hops');
    if ($hops > 0 && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $chain = array_values(array_filter(array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']))));
        $index = count($chain) - $hops;
        if ($index >= 0 && filter_var($chain[$index], FILTER_VALIDATE_IP)) {
            return $chain[$index];
        }
    }
    return $remote;
}

// ----- Flash messages -----

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}

function render_flashes(): void
{
    foreach (take_flashes() as $flash) {
        $class = $flash['type'] === 'success' ? 'form-alert-success' : ($flash['type'] === 'warning' ? 'form-alert-warning' : 'form-alert-danger');
        $icon = $flash['type'] === 'success' ? '✓' : '⚠';
        echo '<div class="form-alert ' . $class . '" role="status"><span>' . $icon . '</span><span>' . e($flash['message']) . '</span></div>';
    }
}

// ----- CSRF -----

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_is_valid(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
}

// ----- Request input -----

function input_str(array $source, string $key, int $maxLength = 255): string
{
    $value = $source[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    $value = trim(str_replace("\0", '', $value));
    return mb_substr($value, 0, $maxLength);
}

function post_str(string $key, int $maxLength = 255): string
{
    return input_str($_POST, $key, $maxLength);
}

function get_str(string $key, int $maxLength = 100): string
{
    return input_str($_GET, $key, $maxLength);
}

function get_int(string $key, int $default = 0): int
{
    $value = $_GET[$key] ?? null;
    return is_scalar($value) && ctype_digit((string) $value) ? (int) $value : $default;
}

function post_int(string $key, int $default = 0): int
{
    $value = $_POST[$key] ?? null;
    return is_scalar($value) && ctype_digit((string) $value) ? (int) $value : $default;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function valid_date(string $value): bool
{
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

// ----- Formatting -----

function format_date(?string $value, string $format = 'M j, Y'): string
{
    if (!$value) {
        return '—';
    }
    $time = strtotime($value);
    return $time ? date($format, $time) : '—';
}

function str_limit(?string $value, int $length): string
{
    $value = trim(preg_replace('/\s+/u', ' ', (string) $value));
    return mb_strimwidth($value, 0, $length, '…', 'UTF-8');
}

function slugify(string $value): string
{
    $value = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $value), '-'));
    return $value !== '' ? $value : 'item';
}

function format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }
    return max(1, round($bytes / 1024)) . ' KB';
}

// ----- Pagination -----

function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, $page), $pages);
    return ['total' => $total, 'per_page' => $perPage, 'page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage];
}

function render_pagination(array $pager, string $path, array $params = []): void
{
    if ($pager['pages'] <= 1) {
        return;
    }
    $current = $pager['page'];
    echo '<nav class="pagination" aria-label="Pagination">';
    if ($current > 1) {
        echo '<a class="pagination-link" href="' . e(url($path, $params + ['page' => $current - 1])) . '">&larr; Prev</a>';
    }
    $start = max(1, $current - 2);
    $end = min($pager['pages'], $current + 2);
    for ($i = $start; $i <= $end; $i++) {
        if ($i === $current) {
            echo '<span class="pagination-link is-current" aria-current="page">' . $i . '</span>';
        } else {
            echo '<a class="pagination-link" href="' . e(url($path, $params + ['page' => $i])) . '">' . $i . '</a>';
        }
    }
    if ($current < $pager['pages']) {
        echo '<a class="pagination-link" href="' . e(url($path, $params + ['page' => $current + 1])) . '">Next &rarr;</a>';
    }
    echo '<span class="pagination-summary">Page ' . $current . ' of ' . $pager['pages'] . ' &bull; ' . $pager['total'] . ' results</span>';
    echo '</nav>';
}

// ----- Audit trail -----

function audit(string $action, string $entity = '', ?int $entityId = null, string $details = ''): void
{
    try {
        $staff = $_SESSION['staff'] ?? null;
        $member = $_SESSION['member'] ?? null;
        db_run(
            'INSERT INTO audit_log (actor_type, actor_id, action, entity, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $staff ? 'staff' : ($member ? 'member' : 'guest'),
                $staff['id'] ?? ($member['id'] ?? null),
                $action,
                $entity,
                $entityId,
                mb_substr($details, 0, 500),
                client_ip(),
            ]
        );
    } catch (Throwable $e) {
        error_log('[IHMS] Audit log failed: ' . $e->getMessage());
    }
}

// ----- Error pages -----

function render_error_page(int $status, string $title, string $message): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');
    }
    $home = url('index.php');
    $css = url('css/style.css');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . e($title) . ' | Indigenous Heritage Management System</title><link rel="stylesheet" href="' . e($css) . '"></head><body>';
    echo '<div class="auth-wrapper error-wrapper"><div class="auth-card"><div class="auth-header"><div class="auth-logo-badge">🌿</div>';
    echo '<h2>' . e($title) . '</h2><p>' . e($message) . '</p></div>';
    echo '<a class="btn btn-primary btn-lg btn-block" href="' . e($home) . '">Return to Homepage</a></div></div></body></html>';
    exit;
}

function not_found(): void
{
    render_error_page(404, 'Record Not Found', 'The page or record you requested does not exist or is not available to you.');
}

// ----- Form rendering -----

// Renders simple stacked fields; values come from $record first, then from POST.
function render_form_fields(array $fields, $record = null): void
{
    echo '<div class="form-fields">';
    foreach ($fields as $field) {
        $name = $field['name'];
        $type = $field['type'] ?? 'text';
        $id = $field['id'] ?? $name;
        $label = $field['label'] ?? '';
        $required = !empty($field['required']);
        $value = '';
        if ($type !== 'password') {
            if (is_array($record) && array_key_exists($name, $record) && $record[$name] !== null) {
                $value = (string) $record[$name];
            } elseif (isset($_POST[$name]) && is_string($_POST[$name])) {
                $value = (string) $_POST[$name];
            }
        }
        $attrs = $required ? ' required' : '';
        $attrs .= !empty($field['autofocus']) ? ' autofocus' : '';
        $attrs .= isset($field['maxlength']) ? ' maxlength="' . (int) $field['maxlength'] . '"' : '';
        $attrs .= isset($field['minlength']) ? ' minlength="' . (int) $field['minlength'] . '"' : '';
        $attrs .= isset($field['autocomplete']) ? ' autocomplete="' . e($field['autocomplete']) . '"' : '';

        echo '<div class="form-group">';
        echo '<label for="' . e($id) . '" class="form-label">' . e($label) . ($required ? ' *' : '') . '</label>';
        if ($type === 'textarea') {
            echo '<textarea id="' . e($id) . '" name="' . e($name) . '" class="form-textarea" placeholder="' . e($field['placeholder'] ?? '') . '"' . $attrs . '>' . e($value) . '</textarea>';
        } else {
            $inputType = in_array($type, ['text', 'email', 'password', 'date'], true) ? $type : 'text';
            echo '<input type="' . $inputType . '" id="' . e($id) . '" name="' . e($name) . '" class="form-input" placeholder="' . e($field['placeholder'] ?? '') . '" value="' . e($value) . '"' . $attrs . '>';
        }
        if (!empty($field['help'])) {
            echo '<div class="form-help">' . e($field['help']) . '</div>';
        }
        echo '</div>';
    }
    echo '</div>';
}

function selected($a, $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked($value): string
{
    return $value ? ' checked' : '';
}
