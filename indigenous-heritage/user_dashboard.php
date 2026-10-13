<?php
require_once __DIR__ . '/includes/bootstrap.php';

$member = require_member();
$errors = [];

if (is_post() && post_str('action') === 'change_password') {
    $row = db_one('SELECT password FROM users WHERE id = ?', [(int) $member['id']]);
    $new = (string) ($_POST['new_password'] ?? '');
    if (!$row || !password_verify((string) ($_POST['current_password'] ?? ''), $row['password'])) {
        $errors[] = 'Your current password is incorrect.';
    } elseif ($problem = password_problem($new, (string) ($_POST['confirm_password'] ?? ''))) {
        $errors[] = $problem;
    } else {
        db_run('UPDATE users SET password = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), (int) $member['id']]);
        session_regenerate_id(true);
        audit('member_password_changed', 'user', (int) $member['id']);
        flash('success', 'Your password has been updated.');
        redirect('user_dashboard.php');
    }
}

$access = record_access_sql('r');
$visible_total = (int) db_value("SELECT COUNT(*) FROM heritage_records r WHERE $access");
$restricted_total = (int) db_value("SELECT COUNT(*) FROM heritage_records r WHERE $access AND r.visibility = 'restricted'");
$recent = search_records(['sort' => 'newest'], 1, 3)['rows'];

$page_title = 'My Account';
$active_nav = 'account';
require __DIR__ . '/includes/partials/public_header.php';
?>
    <section class="page-hero page-hero-compact">
        <div class="hero-content">
            <div class="hero-badge">❖ Community Member ❖</div>
            <h1>Welcome, <?php echo e($member['full_name'] ?: $member['username']); ?></h1>
            <p>Your hub for exploring heritage records shared with the community.</p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <?php render_flashes(); ?>
            <?php foreach ($errors as $error): ?>
                <div class="form-alert form-alert-danger" role="alert"><span>⚠</span><span><?php echo e($error); ?></span></div>
            <?php endforeach; ?>

            <?php if ($member['status'] === 'pending'): ?>
                <div class="form-alert form-alert-warning"><span>⏳</span><span>Your account is awaiting administrator approval. Until then, you can browse public records only.</span></div>
            <?php endif; ?>

            <div class="member-stats">
                <div class="admin-stat-card">
                    <div class="admin-stat-info"><h3>Records You Can View</h3><div class="admin-stat-value"><?php echo $visible_total; ?></div></div>
                    <div class="admin-stat-icon">📚</div>
                </div>
                <div class="admin-stat-card">
                    <div class="admin-stat-info"><h3>Restricted Records</h3><div class="admin-stat-value"><?php echo $restricted_total; ?></div></div>
                    <div class="admin-stat-icon">🔒</div>
                </div>
                <div class="admin-stat-card">
                    <div class="admin-stat-info"><h3>Account Status</h3><div class="member-status"><?php echo e(MEMBER_STATUSES[$member['status']] ?? $member['status']); ?></div></div>
                    <div class="admin-stat-icon">👤</div>
                </div>
            </div>

            <?php if ($recent): ?>
                <div class="section-title-wrap">
                    <h2 class="section-title">Recently Added For You</h2>
                    <div class="tribal-accent-bar"></div>
                </div>
                <div class="card-grid">
                    <?php foreach ($recent as $card_record): ?>
                        <?php require __DIR__ . '/includes/partials/record_card.php'; ?>
                    <?php endforeach; ?>
                </div>
                <div class="text-center section-cta"><a class="btn btn-primary btn-lg" href="<?php echo e(url('records.php')); ?>">Browse All Records</a></div>
            <?php endif; ?>

            <div class="member-panels">
                <div class="form-card">
                    <h3 class="form-card-title">Profile</h3>
                    <dl class="profile-list">
                        <dt>Username</dt><dd>@<?php echo e($member['username']); ?></dd>
                        <dt>Email</dt><dd><?php echo e($member['email']); ?></dd>
                        <dt>Community</dt><dd><?php echo e($member['community'] ?: '—'); ?></dd>
                        <dt>Member since</dt><dd><?php echo e(format_date($member['created_at'])); ?></dd>
                    </dl>
                    <form method="POST" action="<?php echo e(url('admin/logout.php')); ?>">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="btn btn-danger">Log Out 🚪</button>
                    </form>
                </div>

                <div class="form-card">
                    <h3 class="form-card-title">Change Password</h3>
                    <form method="POST" action="<?php echo e(url('user_dashboard.php')); ?>">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="change_password">
                        <?php
                        render_form_fields([
                            ['name' => 'current_password', 'type' => 'password', 'label' => 'Current Password', 'required' => true, 'autocomplete' => 'current-password'],
                            ['name' => 'new_password', 'type' => 'password', 'label' => 'New Password', 'required' => true, 'minlength' => 8, 'autocomplete' => 'new-password', 'help' => 'At least 8 characters, including letters and numbers.'],
                            ['name' => 'confirm_password', 'type' => 'password', 'label' => 'Confirm New Password', 'required' => true, 'minlength' => 8, 'autocomplete' => 'new-password'],
                        ]);
                        ?>
                        <button type="submit" class="btn btn-primary btn-block">Update Password</button>
                    </form>
                </div>
            </div>
        </div>
    </section>
<?php require __DIR__ . '/includes/partials/public_footer.php'; ?>
