<?php
/** @var array $staff */
$admin_title = $admin_title ?? 'Admin Console';
$admin_active = $admin_active ?? '';
$sidebar_counts = [
    'records' => (int) db_value('SELECT COUNT(*) FROM heritage_records'),
    'pending' => (int) db_value("SELECT COUNT(*) FROM heritage_records WHERE status = 'pending'"),
    'messages' => (int) db_value('SELECT COUNT(*) FROM messages WHERE is_read = 0'),
    'members' => (int) db_value("SELECT COUNT(*) FROM users WHERE status = 'pending'"),
];
$admin_nav = [
    'dashboard' => ['admin/dashboard.php', '📊', 'Dashboard', null, false],
    'records' => ['admin/records.php', '📚', 'Heritage Records', $sidebar_counts['pending'] ? $sidebar_counts['pending'] . ' pending' : $sidebar_counts['records'], false],
    'categories' => ['admin/categories.php', '🗂️', 'Categories', null, true],
    'messages' => ['admin/messages.php', '✉️', 'Messages', $sidebar_counts['messages'] ? $sidebar_counts['messages'] . ' unread' : null, false],
    'users' => ['admin/users.php', '👥', 'Members & Staff', $sidebar_counts['members'] ? $sidebar_counts['members'] . ' pending' : null, true],
    'account' => ['admin/account.php', '🔑', 'My Account', null, false],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo e($admin_title); ?> | Admin Console | Indigenous Heritage Management System</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🌿</text></svg>">
    <link rel="stylesheet" href="<?php echo e(asset('css/style.css')); ?>">
</head>
<body class="admin-body">
    <div class="admin-layout">
        <aside class="admin-sidebar" id="admin-sidebar">
            <div class="admin-brand">
                <h2><span>🌿</span><span>Heritage Admin</span></h2>
                <div class="admin-brand-sub">Indigenous Heritage Management System</div>
                <button type="button" class="admin-menu-toggle" aria-expanded="false" aria-controls="admin-nav">☰ Menu</button>
            </div>
            <ul class="admin-nav" id="admin-nav">
                <?php foreach ($admin_nav as $key => [$href, $icon, $label, $badge, $adminOnly]): ?>
                    <?php if ($adminOnly && $staff['role'] !== 'administrator') { continue; } ?>
                    <li>
                        <a href="<?php echo e(url($href)); ?>"<?php echo $admin_active === $key ? ' class="active" aria-current="page"' : ''; ?>>
                            <span class="admin-nav-item-content"><span><?php echo $icon; ?></span><span><?php echo e($label); ?></span></span>
                            <?php if ($badge !== null): ?>
                                <span class="<?php echo is_string($badge) ? 'admin-badge-count' : 'badge badge-primary'; ?>"><?php echo e((string) $badge); ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                <li class="admin-nav-divider">
                    <a href="<?php echo e(url('index.php')); ?>" target="_blank" rel="noopener">
                        <span class="admin-nav-item-content"><span>🌐</span><span>View Public Site</span></span><span>↗</span>
                    </a>
                </li>
                <li>
                    <form method="POST" action="<?php echo e(url('admin/logout.php')); ?>" class="admin-logout-form">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="admin-logout-btn"><span class="admin-nav-item-content"><span>🚪</span><span>Log Out</span></span></button>
                    </form>
                </li>
            </ul>
        </aside>

        <div class="admin-main">
            <header class="admin-topbar">
                <div class="admin-breadcrumb">
                    <span>Admin Console</span> / <strong><?php echo e($admin_title); ?></strong>
                </div>
                <div class="admin-user-profile">
                    <div class="admin-user-pill">
                        <div class="admin-avatar"><?php echo e(strtoupper(mb_substr($staff['username'], 0, 1))); ?></div>
                        <span><?php echo e($staff['full_name'] ?: $staff['username']); ?></span>
                        <span class="badge <?php echo $staff['role'] === 'administrator' ? 'badge-success' : 'badge-primary'; ?>"><?php echo e(STAFF_ROLES[$staff['role']] ?? $staff['role']); ?></span>
                    </div>
                </div>
            </header>
            <main class="admin-content-area" id="main-content">
                <?php render_flashes(); ?>
