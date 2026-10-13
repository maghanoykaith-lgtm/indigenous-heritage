<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$staff = require_staff();

if (is_post()) {
    $message_id = post_int('id');
    $op = post_str('op', 20);
    if ($op === 'mark_read' || $op === 'mark_unread') {
        db_run('UPDATE messages SET is_read = ? WHERE id = ?', [$op === 'mark_read' ? 1 : 0, $message_id]);
        flash('success', 'Message updated.');
    } elseif ($op === 'delete') {
        db_run('DELETE FROM messages WHERE id = ?', [$message_id]);
        audit('message_deleted', 'message', $message_id);
        flash('success', 'Message deleted.');
    }
    redirect('admin/messages.php');
}

$view = null;
if ($view_id = get_int('id')) {
    $view = db_one('SELECT * FROM messages WHERE id = ?', [$view_id]);
    if ($view && !(int) $view['is_read']) {
        db_run('UPDATE messages SET is_read = 1 WHERE id = ?', [$view_id]);
        $view['is_read'] = 1;
    }
}

$filter = get_str('filter', 10) === 'unread' ? 'unread' : 'all';
$where = $filter === 'unread' ? 'WHERE is_read = 0' : '';
$total = (int) db_value("SELECT COUNT(*) FROM messages $where");
$pager = paginate($total, 20, get_int('page', 1));
$messages = db_all("SELECT id, name, email, subject, message, is_read, created_at FROM messages $where ORDER BY created_at DESC, id DESC LIMIT " . (int) $pager['per_page'] . ' OFFSET ' . (int) $pager['offset']);

$admin_title = 'Messages';
$admin_active = 'messages';
require dirname(__DIR__) . '/includes/partials/admin_header.php';
?>
<div class="admin-header">
    <div>
        <h1>✉️ Messages &amp; Contributions</h1>
        <p class="admin-subtitle">Inquiries and record proposals submitted through the contact form.</p>
    </div>
    <div class="filter-chips">
        <a class="filter-chip<?php echo $filter === 'all' ? ' active' : ''; ?>" href="<?php echo e(url('admin/messages.php')); ?>">All</a>
        <a class="filter-chip<?php echo $filter === 'unread' ? ' active' : ''; ?>" href="<?php echo e(url('admin/messages.php', ['filter' => 'unread'])); ?>">Unread</a>
    </div>
</div>

<?php if ($view): ?>
    <section class="admin-card">
        <div class="admin-card-header">
            <h2><?php echo e($view['subject']); ?></h2>
            <a href="<?php echo e(url('admin/messages.php')); ?>" class="btn btn-secondary btn-sm">Close</a>
        </div>
        <div class="admin-card-body">
            <dl class="detail-list detail-list-inline">
                <dt>From</dt><dd><?php echo e($view['name']); ?></dd>
                <dt>Email</dt><dd><a href="mailto:<?php echo e($view['email']); ?>"><?php echo e($view['email']); ?></a></dd>
                <dt>Received</dt><dd><?php echo e(format_date($view['created_at'], 'F j, Y g:i A')); ?></dd>
            </dl>
            <div class="message-body"><?php echo nl2br(e($view['message'])); ?></div>
            <div class="button-row">
                <a href="mailto:<?php echo e($view['email']); ?>?subject=<?php echo rawurlencode('Re: ' . $view['subject']); ?>" class="btn btn-primary">Reply by Email</a>
                <a href="<?php echo e(url('admin/records.php', ['action' => 'add'])); ?>" class="btn btn-secondary">Create Record from This</a>
                <form method="POST" action="<?php echo e(url('admin/messages.php')); ?>">
                    <?php echo csrf_field(); ?><input type="hidden" name="op" value="mark_unread"><input type="hidden" name="id" value="<?php echo (int) $view['id']; ?>">
                    <button type="submit" class="btn btn-secondary">Mark Unread</button>
                </form>
                <form method="POST" action="<?php echo e(url('admin/messages.php')); ?>">
                    <?php echo csrf_field(); ?><input type="hidden" name="op" value="delete"><input type="hidden" name="id" value="<?php echo (int) $view['id']; ?>">
                    <button type="submit" class="btn btn-danger" data-confirm="Delete this message permanently?">Delete</button>
                </form>
            </div>
        </div>
    </section>
<?php endif; ?>

<section class="admin-card">
    <div class="admin-card-header"><h2><?php echo $total; ?> message<?php echo $total === 1 ? '' : 's'; ?></h2></div>
    <div class="admin-table-wrap">
        <table class="admin-table admin-table-responsive">
            <thead><tr><th>Status</th><th>From</th><th>Subject</th><th>Received</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($messages as $item): ?>
                    <tr class="<?php echo (int) $item['is_read'] ? '' : 'row-unread'; ?>">
                        <td data-label="Status"><?php echo (int) $item['is_read'] ? '<span class="badge badge-success">Read</span>' : '<span class="badge badge-danger">Unread</span>'; ?></td>
                        <td data-label="From"><strong><?php echo e($item['name']); ?></strong><div class="table-subtext"><?php echo e($item['email']); ?></div></td>
                        <td data-label="Subject"><a class="table-title-link" href="<?php echo e(url('admin/messages.php', ['id' => $item['id']])); ?>"><?php echo e($item['subject']); ?></a><div class="table-subtext"><?php echo e(str_limit($item['message'], 80)); ?></div></td>
                        <td data-label="Received"><?php echo e(format_date($item['created_at'])); ?></td>
                        <td data-label="Actions">
                            <div class="table-actions">
                                <a href="<?php echo e(url('admin/messages.php', ['id' => $item['id']])); ?>" class="btn btn-secondary btn-sm">Open</a>
                                <form method="POST" action="<?php echo e(url('admin/messages.php')); ?>">
                                    <?php echo csrf_field(); ?><input type="hidden" name="op" value="delete"><input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete this message permanently?">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$messages): ?>
                    <tr><td colspan="5" class="admin-empty">No messages yet. Submissions from the contact form will appear here.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="admin-card-body"><?php render_pagination($pager, 'admin/messages.php', ['filter' => $filter === 'unread' ? 'unread' : null]); ?></div>
</section>
<?php require dirname(__DIR__) . '/includes/partials/admin_footer.php'; ?>
