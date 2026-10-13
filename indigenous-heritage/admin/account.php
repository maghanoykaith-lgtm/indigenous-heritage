<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$staff = require_staff();

$errors = [];
if (is_post()) {
    $op = post_str('op', 20);
    $row = db_one('SELECT password FROM admins WHERE id = ?', [(int) $staff['id']]);
    $current_ok = $row && password_verify((string) ($_POST['current_password'] ?? ''), $row['password']);

    if ($op === 'password') {
        $new = (string) ($_POST['new_password'] ?? '');
        if (!$current_ok) {
            $errors[] = 'Your current password is incorrect.';
        } elseif ($problem = password_problem($new, (string) ($_POST['confirm_password'] ?? ''))) {
            $errors[] = $problem;
        } elseif (password_verify($new, $row['password'])) {
            $errors[] = 'Please choose a password different from your current one.';
        } else {
            db_run('UPDATE admins SET password = ?, must_change_password = 0 WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), (int) $staff['id']]);
            session_regenerate_id(true);
            audit('staff_password_changed', 'admin', (int) $staff['id']);
            flash('success', 'Your password has been changed.');
            redirect('admin/dashboard.php');
        }
    } elseif ($op === 'profile') {
        $email = strtolower(post_str('email', 100));
        $full_name = post_str('full_name', 100);
        if (!$current_ok) {
            $errors[] = 'Please confirm the change with your current password.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (db_value('SELECT COUNT(*) FROM admins WHERE email = ? AND id <> ?', [$email, (int) $staff['id']])) {
            $errors[] = 'That email address is used by another staff account.';
        } else {
            db_run('UPDATE admins SET email = ?, full_name = ? WHERE id = ?', [$email, $full_name ?: null, (int) $staff['id']]);
            audit('staff_profile_updated', 'admin', (int) $staff['id']);
            flash('success', 'Profile updated.');
            redirect('admin/account.php');
        }
    }
}

$admin_title = 'My Account';
$admin_active = 'account';
require dirname(__DIR__) . '/includes/partials/admin_header.php';
?>
<div class="admin-header">
    <div>
        <h1>🔑 My Account</h1>
        <p class="admin-subtitle">Signed in as @<?php echo e($staff['username']); ?> (<?php echo e(STAFF_ROLES[$staff['role']]); ?>).</p>
    </div>
</div>

<?php if ((int) $staff['must_change_password']): ?>
    <div class="form-alert form-alert-warning"><span>🔐</span><span>You are using a temporary or default password. Choose a new password to unlock the rest of the admin console.</span></div>
<?php endif; ?>
<?php foreach ($errors as $error): ?>
    <div class="form-alert form-alert-danger" role="alert"><span>⚠</span><span><?php echo e($error); ?></span></div>
<?php endforeach; ?>

<div class="admin-grid-2">
    <section class="admin-card">
        <div class="admin-card-header"><h2>Change Password</h2></div>
        <div class="admin-card-body">
            <form method="POST" action="<?php echo e(url('admin/account.php')); ?>">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="op" value="password">
                <?php
                render_form_fields([
                    ['name' => 'current_password', 'type' => 'password', 'label' => 'Current Password', 'required' => true, 'autocomplete' => 'current-password'],
                    ['name' => 'new_password', 'type' => 'password', 'label' => 'New Password', 'required' => true, 'minlength' => 8, 'maxlength' => 128, 'autocomplete' => 'new-password', 'help' => 'At least 8 characters with letters and numbers.'],
                    ['name' => 'confirm_password', 'type' => 'password', 'label' => 'Confirm New Password', 'required' => true, 'minlength' => 8, 'maxlength' => 128, 'autocomplete' => 'new-password'],
                ]);
                ?>
                <button type="submit" class="btn btn-primary btn-block">Change Password</button>
            </form>
        </div>
    </section>

    <?php if (!(int) $staff['must_change_password']): ?>
        <section class="admin-card">
            <div class="admin-card-header"><h2>Profile</h2></div>
            <div class="admin-card-body">
                <form method="POST" action="<?php echo e(url('admin/account.php')); ?>">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="op" value="profile">
                    <?php
                    render_form_fields([
                        ['name' => 'full_name', 'label' => 'Full Name', 'maxlength' => 100],
                        ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true, 'maxlength' => 100],
                        ['name' => 'current_password', 'id' => 'profile_current_password', 'type' => 'password', 'label' => 'Current Password (to confirm)', 'required' => true, 'autocomplete' => 'current-password'],
                    ], ['full_name' => $staff['full_name'], 'email' => $staff['email']]);
                    ?>
                    <button type="submit" class="btn btn-secondary btn-block">Save Profile</button>
                </form>
            </div>
        </section>
    <?php endif; ?>
</div>
<?php require dirname(__DIR__) . '/includes/partials/admin_footer.php'; ?>
