<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (current_staff()) {
    redirect('admin/dashboard.php');
}
if (current_member()) {
    redirect('user_dashboard.php');
}

$error = '';
if (is_post()) {
    $identifier = strtolower(post_str('username', 100));
    $password = (string) ($_POST['password'] ?? '');

    if ($identifier === '' || $password === '') {
        $error = 'Please enter both your username (or email) and password.';
    } elseif (login_is_throttled($identifier)) {
        $error = 'Too many failed sign-in attempts. Please wait ' . LOGIN_WINDOW_MINUTES . ' minutes and try again.';
    } else {
        $staff = db_one('SELECT id, username, password, is_active FROM admins WHERE LOWER(username) = ? OR LOWER(email) = ? LIMIT 1', [$identifier, $identifier]);
        $member = $staff ? null : db_one('SELECT id, username, password, status FROM users WHERE LOWER(username) = ? OR LOWER(email) = ? LIMIT 1', [$identifier, $identifier]);
        $account = $staff ?: $member;

        if ($account && password_verify($password, $account['password'])) {
            if ($staff && is_production() && $password === DEFAULT_LEGACY_PASSWORD) {
                $error = 'The default password is disabled on the live site. Ask the site owner to set ADMIN_INITIAL_PASSWORD on the server.';
            } elseif ($staff && !(int) $staff['is_active']) {
                $error = 'This staff account has been deactivated.';
            } elseif ($member && $member['status'] === 'suspended') {
                $error = 'This member account has been suspended. Please contact the archive team.';
            } else {
                clear_failed_logins($identifier);
                if (password_needs_rehash($account['password'], PASSWORD_DEFAULT)) {
                    db_run('UPDATE ' . ($staff ? 'admins' : 'users') . ' SET password = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $account['id']]);
                }
                $after = $_SESSION['_after_login'] ?? null;
                unset($_SESSION['_after_login']);
                if ($staff) {
                    login_staff($staff);
                    audit('staff_login', 'admin', (int) $staff['id']);
                    $base = app_base_path() . '/admin/';
                    if (is_string($after) && strncmp($after, $base, strlen($base)) === 0) {
                        header('Location: ' . $after, true, 303);
                        exit;
                    }
                    redirect('admin/dashboard.php');
                }
                login_member($member);
                audit('member_login', 'user', (int) $member['id']);
                redirect('user_dashboard.php');
            }
        } else {
            record_failed_login($identifier);
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Sign In | Indigenous Heritage Management System</title>
    <link rel="stylesheet" href="<?php echo e(asset('css/style.css')); ?>">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo-badge">🔐</div>
                <h2>Sign In</h2>
                <p>Staff are taken to the admin console; community members are taken to their account.</p>
            </div>

            <?php render_flashes(); ?>
            <?php if ($error): ?>
                <div class="form-alert form-alert-danger" role="alert"><span>⚠</span><span><?php echo e($error); ?></span></div>
            <?php endif; ?>

            <form method="POST" action="<?php echo e(url('admin/login.php')); ?>">
                <?php echo csrf_field(); ?>
                <?php
                render_form_fields([
                    ['name' => 'username', 'label' => 'Username or Email', 'placeholder' => 'e.g. admin', 'required' => true, 'autofocus' => true, 'maxlength' => 100, 'autocomplete' => 'username'],
                    ['name' => 'password', 'type' => 'password', 'label' => 'Password', 'placeholder' => '••••••••', 'required' => true, 'maxlength' => 128, 'autocomplete' => 'current-password'],
                ]);
                ?>
                <button type="submit" class="btn btn-primary btn-lg btn-block">Sign In &rarr;</button>
            </form>

            <div class="auth-footer">
                <p>Part of a community and need access to restricted records?</p>
                <a href="<?php echo e(url('register.php')); ?>" class="auth-footer-link">Request Community Access &rarr;</a>
                <div><a href="<?php echo e(url('index.php')); ?>" class="auth-back-link">&larr; Return to Homepage</a></div>
            </div>
        </div>
    </div>
</body>
</html>
