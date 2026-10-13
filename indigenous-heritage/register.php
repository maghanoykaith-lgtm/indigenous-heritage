<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (current_staff()) {
    redirect('admin/dashboard.php');
}
if (current_member()) {
    redirect('user_dashboard.php');
}

$errors = [];
$registered = isset($_GET['registered']);

if (is_post()) {
    $full_name = post_str('full_name', 100);
    $community = post_str('community', 150);
    $username = post_str('username', 50);
    $email = strtolower(post_str('email', 100));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if (post_str('website') !== '') {
        redirect('register.php', ['registered' => 1]);
    }
    if ($full_name === '' || $username === '' || $email === '') {
        $errors[] = 'Please complete all required fields.';
    }
    if ($username !== '' && !valid_username($username)) {
        $errors[] = 'Username must be 3–50 characters: letters, numbers, dots, dashes, or underscores.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($problem = password_problem($password, $confirm)) {
        $errors[] = $problem;
    }
    if (!$errors) {
        $taken = db_value('SELECT COUNT(*) FROM users WHERE username = ? OR email = ?', [$username, $email])
            + db_value('SELECT COUNT(*) FROM admins WHERE username = ?', [$username]);
        if ($taken > 0) {
            $errors[] = 'That username or email address is already registered.';
        }
    }
    if (!$errors) {
        db_run(
            "INSERT INTO users (username, password, email, full_name, community, status) VALUES (?, ?, ?, ?, ?, 'pending')",
            [$username, password_hash($password, PASSWORD_DEFAULT), $email, $full_name, $community !== '' ? $community : null]
        );
        audit('member_registered', 'user', (int) db()->lastInsertId(), $username);
        redirect('register.php', ['registered' => 1]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Member Registration | Indigenous Heritage Management System</title>
    <link rel="stylesheet" href="<?php echo e(asset('css/style.css')); ?>">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo-badge">🌿</div>
                <h2>Request Community Access</h2>
                <p>Register as a community member, custodian, or researcher. An administrator reviews every request before restricted records become visible to you.</p>
            </div>

            <?php if ($registered): ?>
                <div class="form-alert form-alert-success" role="status">
                    <span>✓</span>
                    <div>
                        <strong>Registration received.</strong> You can sign in now; restricted records unlock once an administrator approves your account.
                        <div class="alert-actions"><a href="<?php echo e(url('admin/login.php')); ?>" class="btn btn-primary btn-sm">Proceed to Sign In &rarr;</a></div>
                    </div>
                </div>
            <?php endif; ?>
            <?php foreach ($errors as $error): ?>
                <div class="form-alert form-alert-danger" role="alert"><span>⚠</span><span><?php echo e($error); ?></span></div>
            <?php endforeach; ?>

            <form method="POST" action="<?php echo e(url('register.php')); ?>">
                <?php echo csrf_field(); ?>
                <div class="hp-field" aria-hidden="true">
                    <label for="website">Leave this field empty</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>
                <?php
                render_form_fields([
                    ['name' => 'full_name', 'label' => 'Full Name', 'placeholder' => 'e.g. Maria Clara Santos', 'required' => true, 'maxlength' => 100, 'autocomplete' => 'name'],
                    ['name' => 'community', 'label' => 'Community / Organization', 'placeholder' => 'e.g. Ifugao community, University research team', 'maxlength' => 150, 'help' => 'Helps administrators verify your access request.'],
                    ['name' => 'username', 'label' => 'Username', 'placeholder' => 'e.g. msantos', 'required' => true, 'maxlength' => 50, 'autocomplete' => 'username'],
                    ['name' => 'email', 'type' => 'email', 'label' => 'Email Address', 'placeholder' => 'e.g. maria@example.org', 'required' => true, 'maxlength' => 100, 'autocomplete' => 'email'],
                    ['name' => 'password', 'type' => 'password', 'label' => 'Password', 'placeholder' => 'At least 8 characters, with letters and numbers', 'required' => true, 'minlength' => 8, 'maxlength' => 128, 'autocomplete' => 'new-password'],
                    ['name' => 'confirm_password', 'type' => 'password', 'label' => 'Confirm Password', 'placeholder' => 'Repeat your password', 'required' => true, 'minlength' => 8, 'maxlength' => 128, 'autocomplete' => 'new-password'],
                ], $registered ? [] : null);
                ?>
                <button type="submit" class="btn btn-primary btn-lg btn-block">Create Account ✍️</button>
            </form>

            <div class="auth-footer">
                <p>Already have an account?</p>
                <a href="<?php echo e(url('admin/login.php')); ?>" class="auth-footer-link">Sign In &rarr;</a>
                <div><a href="<?php echo e(url('index.php')); ?>" class="auth-back-link">&larr; Return to Homepage</a></div>
            </div>
        </div>
    </div>
</body>
</html>
