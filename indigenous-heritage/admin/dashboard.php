<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$staff = require_staff();

$stats = db_one(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(status = 'published'), 0) AS published,
            COALESCE(SUM(status = 'pending'), 0) AS pending,
            COALESCE(SUM(status = 'draft'), 0) AS drafts,
            COALESCE(SUM(visibility = 'restricted'), 0) AS restricted,
            COALESCE(SUM(visibility = 'private'), 0) AS private_count,
            COALESCE(SUM(status = 'published' AND consent_status NOT IN ('granted', 'not_required')), 0) AS consent_review
     FROM heritage_records"
);
$category_count = (int) db_value('SELECT COUNT(*) FROM categories');
$media_count = (int) db_value('SELECT COUNT(*) FROM record_media');
$unread_messages = (int) db_value('SELECT COUNT(*) FROM messages WHERE is_read = 0');
$pending_members = (int) db_value("SELECT COUNT(*) FROM users WHERE status = 'pending'");

$recent_columns = 'r.id, r.title, r.status, r.visibility, r.consent_status, r.created_at, r.updated_at, c.name AS category_name';
$recently_added = db_all("SELECT $recent_columns FROM heritage_records r LEFT JOIN categories c ON c.id = r.category_id ORDER BY r.created_at DESC, r.id DESC LIMIT 5");
$recently_updated = db_all("SELECT $recent_columns FROM heritage_records r LEFT JOIN categories c ON c.id = r.category_id ORDER BY r.updated_at DESC, r.id DESC LIMIT 5");
$pending_review = db_all("SELECT $recent_columns FROM heritage_records r LEFT JOIN categories c ON c.id = r.category_id WHERE r.status = 'pending' ORDER BY r.updated_at ASC LIMIT 5");
$per_category = db_all('SELECT c.id, c.name, c.icon, COUNT(r.id) AS total FROM categories c LEFT JOIN heritage_records r ON r.category_id = c.id GROUP BY c.id, c.name, c.icon, c.sort_order ORDER BY c.sort_order, c.name');
$max_in_category = max(1, ...array_map(static fn($c) => (int) $c['total'], $per_category ?: [['total' => 1]]));

$admin_title = 'Dashboard';
$admin_active = 'dashboard';
require dirname(__DIR__) . '/includes/partials/admin_header.php';

$record_table = static function (array $rows, string $empty): void {
    if (!$rows) {
        echo '<p class="admin-empty">' . e($empty) . '</p>';
        return;
    }
    echo '<div class="admin-table-wrap"><table class="admin-table admin-table-compact admin-table-responsive"><thead><tr><th>Title</th><th>Category</th><th>Status</th><th>Visibility</th><th>Updated</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        echo '<td data-label="Title"><a class="table-title-link" href="' . e(url('admin/records.php', ['action' => 'view', 'id' => $row['id']])) . '">' . e($row['title']) . '</a></td>';
        echo '<td data-label="Category">' . e($row['category_name'] ?? '—') . '</td>';
        echo '<td data-label="Status">' . status_badge($row['status']) . '</td>';
        echo '<td data-label="Visibility">' . visibility_badge($row['visibility']) . '</td>';
        echo '<td data-label="Updated">' . e(format_date($row['updated_at'])) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
};
?>
<div class="admin-header">
    <div>
        <h1>Welcome, <?php echo e($staff['full_name'] ?: $staff['username']); ?></h1>
        <p class="admin-subtitle">Summary of the heritage archive as of <?php echo e(date('F j, Y g:i A')); ?>.</p>
    </div>
    <div class="button-row">
        <a href="<?php echo e(url('admin/records.php', ['action' => 'add'])); ?>" class="btn btn-primary">+ Add Heritage Record</a>
        <a href="<?php echo e(url('admin/records.php', ['status' => 'pending'])); ?>" class="btn btn-secondary">Review Queue</a>
    </div>
</div>

<?php if ((int) $stats['consent_review'] > 0): ?>
    <div class="form-alert form-alert-warning">
        <span>⚠</span>
        <span><?php echo (int) $stats['consent_review']; ?> published record(s) have no documented community consent. <a href="<?php echo e(url('admin/records.php', ['status' => 'published', 'consent_status' => 'pending'])); ?>">Review them now</a>.</span>
    </div>
<?php endif; ?>

<div class="admin-stats-grid">
    <a class="admin-stat-card" href="<?php echo e(url('admin/records.php')); ?>">
        <div class="admin-stat-info"><h3>Total Heritage Records</h3><div class="admin-stat-value"><?php echo (int) $stats['total']; ?></div></div>
        <div class="admin-stat-icon">📚</div>
    </a>
    <a class="admin-stat-card" href="<?php echo e(url('admin/records.php', ['status' => 'published'])); ?>">
        <div class="admin-stat-info"><h3>Published</h3><div class="admin-stat-value"><?php echo (int) $stats['published']; ?></div></div>
        <div class="admin-stat-icon">✅</div>
    </a>
    <a class="admin-stat-card" href="<?php echo e(url('admin/records.php', ['status' => 'pending'])); ?>">
        <div class="admin-stat-info"><h3>Pending Review</h3><div class="admin-stat-value"><?php echo (int) $stats['pending']; ?></div></div>
        <div class="admin-stat-icon">⏳</div>
    </a>
    <a class="admin-stat-card" href="<?php echo e(url('admin/records.php', ['status' => 'draft'])); ?>">
        <div class="admin-stat-info"><h3>Drafts</h3><div class="admin-stat-value"><?php echo (int) $stats['drafts']; ?></div></div>
        <div class="admin-stat-icon">📝</div>
    </a>
    <a class="admin-stat-card" href="<?php echo e(url($staff['role'] === 'administrator' ? 'admin/categories.php' : 'records.php')); ?>">
        <div class="admin-stat-info"><h3>Categories</h3><div class="admin-stat-value"><?php echo $category_count; ?></div></div>
        <div class="admin-stat-icon">🗂️</div>
    </a>
    <a class="admin-stat-card" href="<?php echo e(url('admin/records.php', ['visibility' => 'restricted'])); ?>">
        <div class="admin-stat-info"><h3>Restricted / Private</h3><div class="admin-stat-value"><?php echo (int) $stats['restricted'] + (int) $stats['private_count']; ?></div></div>
        <div class="admin-stat-icon">🔒</div>
    </a>
    <div class="admin-stat-card">
        <div class="admin-stat-info"><h3>Images &amp; Documents</h3><div class="admin-stat-value"><?php echo $media_count; ?></div></div>
        <div class="admin-stat-icon">🖼️</div>
    </div>
    <a class="admin-stat-card" href="<?php echo e(url('admin/messages.php')); ?>">
        <div class="admin-stat-info"><h3>Unread Messages</h3><div class="admin-stat-value"><?php echo $unread_messages; ?></div></div>
        <div class="admin-stat-icon">✉️</div>
    </a>
    <?php if ($staff['role'] === 'administrator'): ?>
        <a class="admin-stat-card" href="<?php echo e(url('admin/users.php', ['status' => 'pending'])); ?>">
            <div class="admin-stat-info"><h3>Members Awaiting Approval</h3><div class="admin-stat-value"><?php echo $pending_members; ?></div></div>
            <div class="admin-stat-icon">👥</div>
        </a>
    <?php endif; ?>
</div>

<div class="admin-grid-2">
    <section class="admin-card">
        <div class="admin-card-header"><h2>⏳ Awaiting Review</h2><a href="<?php echo e(url('admin/records.php', ['status' => 'pending'])); ?>" class="btn btn-secondary btn-sm">Open queue</a></div>
        <?php $record_table($pending_review, 'No records are waiting for review.'); ?>
    </section>
    <section class="admin-card">
        <div class="admin-card-header"><h2>🗂️ Records per Category</h2></div>
        <ul class="category-bars">
            <?php foreach ($per_category as $category): ?>
                <li>
                    <span class="category-bars-label"><?php echo e(($category['icon'] ?: '') . ' ' . $category['name']); ?></span>
                    <span class="category-bars-track"><span class="category-bars-fill" style="width: <?php echo round(((int) $category['total'] / $max_in_category) * 100); ?>%"></span></span>
                    <span class="category-bars-value"><?php echo (int) $category['total']; ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>

<div class="admin-grid-2">
    <section class="admin-card">
        <div class="admin-card-header"><h2>🆕 Recently Added</h2></div>
        <?php $record_table($recently_added, 'No records yet.'); ?>
    </section>
    <section class="admin-card">
        <div class="admin-card-header"><h2>✏️ Recently Updated</h2></div>
        <?php $record_table($recently_updated, 'No records yet.'); ?>
    </section>
</div>
<?php require dirname(__DIR__) . '/includes/partials/admin_footer.php'; ?>
