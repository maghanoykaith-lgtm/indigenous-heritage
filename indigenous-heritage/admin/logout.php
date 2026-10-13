<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

// Logging out requires a POST with a valid CSRF token (checked in bootstrap),
// so other sites cannot sign users out with a simple link.
if (is_post()) {
    $wasStaff = current_staff();
    if ($wasStaff) {
        audit('staff_logout', 'admin', (int) $wasStaff['id']);
    }
    logout_everyone();
    session_start();
    flash('success', 'You have been signed out.');
    redirect('admin/login.php');
}

if (!current_staff() && !current_member()) {
    redirect('admin/login.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Out | Indigenous Heritage Management System</title>
    <link rel="stylesheet" href="<?php echo e(asset('css/style.css')); ?>">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo-badge">🚪</div>
                <h2>Sign Out?</h2>
                <p>Confirm that you want to end your session.</p>
            </div>
            <form method="POST" action="<?php echo e(url('admin/logout.php')); ?>">
                <?php echo csrf_field(); ?>
                <button type="submit" class="btn btn-danger btn-lg btn-block">Sign Out</button>
            </form>
            <div class="auth-footer"><a href="<?php echo e(url('index.php')); ?>" class="auth-back-link">&larr; Cancel</a></div>
        </div>
    </div>
</body>
</html>
