<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$staff = require_administrator();

$errors = [];
$editing = null;

if (is_post()) {
    $op = post_str('op', 20);
    $category_id = post_int('category_id');

    if ($op === 'save') {
        $name = post_str('name', 100);
        $description = post_str('description', 1000);
        $icon = post_str('icon', 8);
        $sort_order = (int) post_str('sort_order', 6);
        $slug = slugify($name);

        if (mb_strlen($name) < 3) {
            $errors[] = 'Category name must be at least 3 characters.';
        }
        $duplicate = db_value('SELECT COUNT(*) FROM categories WHERE (name = ? OR slug = ?) AND id <> ?', [$name, $slug, $category_id]);
        if ($duplicate) {
            $errors[] = 'A category with that name already exists.';
        }
        if (!$errors) {
            if ($category_id) {
                db_run('UPDATE categories SET name = ?, slug = ?, description = ?, icon = ?, sort_order = ? WHERE id = ?', [$name, $slug, $description ?: null, $icon ?: null, $sort_order, $category_id]);
                audit('category_updated', 'category', $category_id, $name);
                flash('success', 'Category updated.');
            } else {
                db_run('INSERT INTO categories (name, slug, description, icon, sort_order) VALUES (?, ?, ?, ?, ?)', [$name, $slug, $description ?: null, $icon ?: null, $sort_order]);
                audit('category_created', 'category', (int) db()->lastInsertId(), $name);
                flash('success', 'Category created.');
            }
            redirect('admin/categories.php');
        }
        $editing = ['id' => $category_id, 'name' => $name, 'description' => $description, 'icon' => $icon, 'sort_order' => $sort_order];
    } elseif ($op === 'delete') {
        $in_use = (int) db_value('SELECT COUNT(*) FROM heritage_records WHERE category_id = ?', [$category_id]);
        if ($in_use > 0) {
            flash('error', "This category still has $in_use record(s). Move them to another category first.");
        } else {
            $name = db_value('SELECT name FROM categories WHERE id = ?', [$category_id]);
            db_run('DELETE FROM categories WHERE id = ?', [$category_id]);
            audit('category_deleted', 'category', $category_id, (string) $name);
            flash('success', 'Category deleted.');
        }
        redirect('admin/categories.php');
    }
}

if (!$editing && ($edit_id = get_int('edit'))) {
    $editing = db_one('SELECT * FROM categories WHERE id = ?', [$edit_id]);
}
$categories = db_all('SELECT c.*, COUNT(r.id) AS record_count FROM categories c LEFT JOIN heritage_records r ON r.category_id = c.id GROUP BY c.id, c.name, c.slug, c.description, c.icon, c.sort_order, c.created_at, c.updated_at ORDER BY c.sort_order, c.name');

$admin_title = 'Categories';
$admin_active = 'categories';
require dirname(__DIR__) . '/includes/partials/admin_header.php';
?>
<div class="admin-header">
    <div>
        <h1>🗂️ Heritage Categories</h1>
        <p class="admin-subtitle">Organize records into categories. A category can only be deleted when it has no records.</p>
    </div>
</div>

<?php foreach ($errors as $error): ?>
    <div class="form-alert form-alert-danger" role="alert"><span>⚠</span><span><?php echo e($error); ?></span></div>
<?php endforeach; ?>

<div class="admin-grid-2 admin-grid-wide-left">
    <section class="admin-card">
        <div class="admin-card-header"><h2>All Categories (<?php echo count($categories); ?>)</h2></div>
        <div class="admin-table-wrap">
            <table class="admin-table admin-table-responsive">
                <thead><tr><th>Order</th><th>Category</th><th>Records</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td data-label="Order"><?php echo (int) $category['sort_order']; ?></td>
                            <td data-label="Category">
                                <strong><?php echo e(($category['icon'] ? $category['icon'] . ' ' : '') . $category['name']); ?></strong>
                                <div class="table-subtext"><?php echo e(str_limit($category['description'], 90)); ?></div>
                            </td>
                            <td data-label="Records"><a href="<?php echo e(url('admin/records.php', ['category_id' => $category['id']])); ?>"><?php echo (int) $category['record_count']; ?></a></td>
                            <td data-label="Actions">
                                <div class="table-actions">
                                    <a href="<?php echo e(url('admin/categories.php', ['edit' => $category['id']])); ?>" class="btn btn-secondary btn-sm">Edit</a>
                                    <?php if ((int) $category['record_count'] === 0): ?>
                                        <form method="POST" action="<?php echo e(url('admin/categories.php')); ?>">
                                            <?php echo csrf_field(); ?><input type="hidden" name="op" value="delete"><input type="hidden" name="category_id" value="<?php echo (int) $category['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete the category “<?php echo e($category['name']); ?>”?">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="admin-card">
        <div class="admin-card-header"><h2><?php echo !empty($editing['id']) ? 'Edit Category' : 'Add Category'; ?></h2></div>
        <div class="admin-card-body">
            <form method="POST" action="<?php echo e(url('admin/categories.php')); ?>">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="op" value="save">
                <input type="hidden" name="category_id" value="<?php echo (int) ($editing['id'] ?? 0); ?>">
                <div class="form-fields">
                    <div class="form-group">
                        <label class="form-label" for="name">Name *</label>
                        <input class="form-input" id="name" name="name" maxlength="100" required value="<?php echo e($editing['name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="icon">Icon (emoji)</label>
                        <input class="form-input" id="icon" name="icon" maxlength="8" value="<?php echo e($editing['icon'] ?? ''); ?>" placeholder="e.g. 🌾">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="sort_order">Display Order</label>
                        <input class="form-input" id="sort_order" name="sort_order" inputmode="numeric" pattern="-?[0-9]*" value="<?php echo e((string) ($editing['sort_order'] ?? '100')); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-textarea" id="description" name="description" maxlength="1000"><?php echo e($editing['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?php echo !empty($editing['id']) ? 'Save Category' : 'Add Category'; ?></button>
                    <?php if (!empty($editing['id'])): ?><a href="<?php echo e(url('admin/categories.php')); ?>" class="btn btn-secondary">Cancel</a><?php endif; ?>
                </div>
            </form>
        </div>
    </section>
</div>
<?php require dirname(__DIR__) . '/includes/partials/admin_footer.php'; ?>
