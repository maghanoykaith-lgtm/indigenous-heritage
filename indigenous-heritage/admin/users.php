<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$staff = require_administrator();

$errors = [];

$active_admins = static fn(): int => (int) db_value("SELECT COUNT(*) FROM admins WHERE role = 'administrator' AND is_active = 1");

if (is_post()) {
    $op = post_str('op', 30);
    $target_id = post_int('id');

    if ($op === 'member_status') {
        $status = post_str('status', 20);
        if (isset(MEMBER_STATUSES[$status])) {
            db_run('UPDATE users SET status = ? WHERE id = ?', [$status, $target_id]);
            audit('member_' . $status, 'user', $target_id);
            flash('success', 'Member status changed to ' . MEMBER_STATUSES[$status] . '.');
        }
        redirect('admin/users.php');
    }

    if ($op === 'member_delete') {
        db_run('DELETE FROM users WHERE id = ?', [$target_id]);
        audit('member_deleted', 'user', $target_id);
        flash('success', 'Member account deleted.');
        redirect('admin/users.php');
    }

    if ($op === 'staff_add') {
        $username = post_str('username', 50);
        $email = strtolower(post_str('email', 100));
        $full_name = post_str('full_name', 100);
        $role = post_str('role', 20);
        $password = (string) ($_POST['password'] ?? '');
        if (!valid_username($username)) {
            $errors[] = 'Username must be 3–50 characters: letters, numbers, dots, dashes, or underscores.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (!isset(STAFF_ROLES[$role])) {
            $errors[] = 'Please choose a valid role.';
        }
        if ($problem = password_problem($password, $password)) {
            $errors[] = 'Temporary password: ' . $problem;
        }
        if (!$errors && (db_value('SELECT COUNT(*) FROM admins WHERE username = ? OR email = ?', [$username, $email]) || db_value('SELECT COUNT(*) FROM users WHERE username = ?', [$username]))) {
            $errors[] = 'That username or email is already in use.';
        }
        if (!$errors) {
            db_run(
                'INSERT INTO admins (username, password, email, full_name, role, is_active, must_change_password) VALUES (?, ?, ?, ?, ?, 1, 1)',
                [$username, password_hash($password, PASSWORD_DEFAULT), $email, $full_name ?: null, $role]
            );
            audit('staff_created', 'admin', (int) db()->lastInsertId(), "$username ($role)");
            flash('success', "Staff account '$username' created. They must change the temporary password at first sign-in.");
            redirect('admin/users.php');
        }
    }

    if ($op === 'staff_update') {
        $role = post_str('role', 20);
        $is_active = post_str('is_active', 1) === '1' ? 1 : 0;
        $target = db_one('SELECT id, role, is_active FROM admins WHERE id = ?', [$target_id]);
        if (!$target || !isset(STAFF_ROLES[$role])) {
            not_found();
        }
        $removes_admin = $target['role'] === 'administrator' && (int) $target['is_active'] && ($role !== 'administrator' || !$is_active);
        if ($target_id === (int) $staff['id'] && ($role !== 'administrator' || !$is_active)) {
            flash('error', 'You cannot remove your own administrator access.');
        } elseif ($removes_admin && $active_admins() <= 1) {
            flash('error', 'At least one active administrator is required.');
        } else {
            db_run('UPDATE admins SET role = ?, is_active = ? WHERE id = ?', [$role, $is_active, $target_id]);
            audit('staff_updated', 'admin', $target_id, "$role, " . ($is_active ? 'active' : 'inactive'));
            flash('success', 'Staff account updated.');
        }
        redirect('admin/users.php');
    }

    if ($op === 'staff_reset') {
        $password = (string) ($_POST['password'] ?? '');
        if ($problem = password_problem($password, $password)) {
            flash('error', 'Temporary password: ' . $problem);
        } else {
            db_run('UPDATE admins SET password = ?, must_change_password = 1 WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $target_id]);
            audit('staff_password_reset', 'admin', $target_id);
            flash('success', 'Temporary password set. The user must change it at next sign-in.');
        }
        redirect('admin/users.php');
    }
}

$member_filter = get_str('status', 20);
$member_where = isset(MEMBER_STATUSES[$member_filter]) ? 'WHERE status = ?' : '';
$members = db_all("SELECT id, username, email, full_name, community, status, created_at, last_login_at FROM users $member_where ORDER BY FIELD(status, 'pending', 'active', 'suspended'), created_at DESC", $member_where ? [$member_filter] : []);
$staff_accounts = db_all('SELECT id, username, email, full_name, role, is_active, must_change_password, last_login_at, created_at FROM admins ORDER BY role, username');

$admin_title = 'Members & Staff';
$admin_active = 'users';
require dirname(__DIR__) . '/includes/partials/admin_header.php';
?>
<div class="admin-header">
    <div>
        <h1>👥 Members &amp; Staff</h1>
        <p class="admin-subtitle">Approve community members for restricted records, and manage staff roles.</p>
    </div>
</div>

<?php foreach ($errors as $error): ?>
    <div class="form-alert form-alert-danger" role="alert"><span>⚠</span><span><?php echo e($error); ?></span></div>
<?php endforeach; ?>

<section class="admin-card">
    <div class="admin-card-header">
        <h2>Community Members (<?php echo count($members); ?>)</h2>
        <div class="filter-chips">
            <a class="filter-chip<?php echo $member_where ? '' : ' active'; ?>" href="<?php echo e(url('admin/users.php')); ?>">All</a>
            <?php foreach (MEMBER_STATUSES as $value => $label): ?>
                <a class="filter-chip<?php echo $member_filter === $value ? ' active' : ''; ?>" href="<?php echo e(url('admin/users.php', ['status' => $value])); ?>"><?php echo e($label); ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table admin-table-responsive">
            <thead><tr><th>Member</th><th>Community</th><th>Status</th><th>Registered</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($members as $member): ?>
                    <tr>
                        <td data-label="Member"><strong><?php echo e($member['full_name'] ?: $member['username']); ?></strong><div class="table-subtext">@<?php echo e($member['username']); ?> &bull; <?php echo e($member['email']); ?></div></td>
                        <td data-label="Community"><?php echo e($member['community'] ?: '—'); ?></td>
                        <td data-label="Status"><span class="badge <?php echo ['active' => 'badge-success', 'pending' => 'badge-warning', 'suspended' => 'badge-danger'][$member['status']]; ?>"><?php echo e(MEMBER_STATUSES[$member['status']]); ?></span></td>
                        <td data-label="Registered"><?php echo e(format_date($member['created_at'])); ?></td>
                        <td data-label="Actions">
                            <div class="table-actions">
                                <?php foreach (['active' => 'Approve', 'suspended' => 'Suspend', 'pending' => 'Set pending'] as $status => $label): ?>
                                    <?php if ($member['status'] !== $status): ?>
                                        <form method="POST" action="<?php echo e(url('admin/users.php')); ?>">
                                            <?php echo csrf_field(); ?><input type="hidden" name="op" value="member_status"><input type="hidden" name="id" value="<?php echo (int) $member['id']; ?>"><input type="hidden" name="status" value="<?php echo $status; ?>">
                                            <button type="submit" class="btn <?php echo $status === 'active' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm"><?php echo $member['status'] === 'suspended' && $status === 'active' ? 'Reactivate' : $label; ?></button>
                                        </form>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <form method="POST" action="<?php echo e(url('admin/users.php')); ?>">
                                    <?php echo csrf_field(); ?><input type="hidden" name="op" value="member_delete"><input type="hidden" name="id" value="<?php echo (int) $member['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete the member account @<?php echo e($member['username']); ?>?">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$members): ?>
                    <tr><td colspan="5" class="admin-empty">No member accounts found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="admin-grid-2 admin-grid-wide-left">
    <section class="admin-card">
        <div class="admin-card-header"><h2>Staff Accounts (<?php echo count($staff_accounts); ?>)</h2></div>
        <div class="admin-table-wrap">
            <table class="admin-table admin-table-responsive">
                <thead><tr><th>Staff</th><th>Role &amp; Access</th><th>Last Sign-in</th><th>Reset Password</th></tr></thead>
                <tbody>
                    <?php foreach ($staff_accounts as $account): ?>
                        <tr>
                            <td data-label="Staff">
                                <strong><?php echo e($account['full_name'] ?: $account['username']); ?></strong>
                                <?php echo (int) $account['id'] === (int) $staff['id'] ? '<span class="badge badge-primary">You</span>' : ''; ?>
                                <div class="table-subtext">@<?php echo e($account['username']); ?> &bull; <?php echo e($account['email']); ?></div>
                                <?php if ((int) $account['must_change_password']): ?><span class="badge badge-warning">Password change pending</span><?php endif; ?>
                            </td>
                            <td data-label="Role">
                                <form method="POST" action="<?php echo e(url('admin/users.php')); ?>" class="inline-form">
                                    <?php echo csrf_field(); ?><input type="hidden" name="op" value="staff_update"><input type="hidden" name="id" value="<?php echo (int) $account['id']; ?>">
                                    <select name="role" class="form-select form-select-sm" aria-label="Role">
                                        <?php foreach (STAFF_ROLES as $value => $label): ?>
                                            <option value="<?php echo $value; ?>"<?php echo selected($value, $account['role']); ?>><?php echo e($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select name="is_active" class="form-select form-select-sm" aria-label="Access">
                                        <option value="1"<?php echo selected(1, $account['is_active']); ?>>Active</option>
                                        <option value="0"<?php echo selected(0, $account['is_active']); ?>>Deactivated</option>
                                    </select>
                                    <button type="submit" class="btn btn-secondary btn-sm">Save</button>
                                </form>
                            </td>
                            <td data-label="Last sign-in"><?php echo e(format_date($account['last_login_at'], 'M j, Y g:i A')); ?></td>
                            <td data-label="Reset">
                                <form method="POST" action="<?php echo e(url('admin/users.php')); ?>" class="inline-form">
                                    <?php echo csrf_field(); ?><input type="hidden" name="op" value="staff_reset"><input type="hidden" name="id" value="<?php echo (int) $account['id']; ?>">
                                    <input type="password" name="password" class="form-input form-input-sm" placeholder="Temporary password" minlength="8" required autocomplete="new-password" aria-label="Temporary password">
                                    <button type="submit" class="btn btn-secondary btn-sm">Set</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="admin-card">
        <div class="admin-card-header"><h2>Add Staff Account</h2></div>
        <div class="admin-card-body">
            <form method="POST" action="<?php echo e(url('admin/users.php')); ?>">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="op" value="staff_add">
                <?php
                render_form_fields([
                    ['name' => 'full_name', 'label' => 'Full Name', 'maxlength' => 100],
                    ['name' => 'username', 'label' => 'Username', 'required' => true, 'maxlength' => 50, 'autocomplete' => 'off'],
                    ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true, 'maxlength' => 100, 'autocomplete' => 'off'],
                    ['name' => 'password', 'type' => 'password', 'label' => 'Temporary Password', 'required' => true, 'minlength' => 8, 'autocomplete' => 'new-password', 'help' => 'They will be asked to choose their own password at first sign-in.'],
                ]);
                ?>
                <div class="form-group">
                    <label class="form-label" for="role">Role *</label>
                    <select class="form-select" id="role" name="role">
                        <option value="editor">Editor (create &amp; edit, submit for review)</option>
                        <option value="administrator">Administrator (full access, publishing)</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Create Staff Account</button>
            </form>
        </div>
    </section>
</div>
<?php require dirname(__DIR__) . '/includes/partials/admin_footer.php'; ?>
