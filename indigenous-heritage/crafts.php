<?php
require_once __DIR__ . '/includes/bootstrap.php';

$search = get_str('search', 100);
$category_filter = get_str('category', 50);

$sql = 'SELECT * FROM crafts WHERE 1=1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (name LIKE ? OR description LIKE ? OR materials LIKE ? OR category LIKE ?)';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like];
}
if ($category_filter !== '') {
    $sql .= ' AND category = ?';
    $params[] = $category_filter;
}
$sql .= ' ORDER BY created_at DESC';
$crafts = db_all($sql, $params);
$categories = array_column(db_all("SELECT DISTINCT category FROM crafts WHERE category IS NOT NULL AND category <> '' ORDER BY category"), 'category');

$page_title = 'Indigenous Master Crafts | Cultural Heritage Portal';
$page_description = 'Discover traditional handwoven textiles, beadwork, pottery, and indigenous handicrafts.';
$active_nav = 'crafts';
require __DIR__ . '/includes/partials/public_header.php';
?>
    <section class="page-hero">
        <div class="hero-content">
            <div class="hero-badge">❖ Living Artistry &amp; Artifacts ❖</div>
            <h1>Indigenous Crafts &amp; Artistry</h1>
            <p>Explore traditional handcrafted items created by master indigenous artisans using sustainable ancestral techniques passed down through generations.</p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="search-toolbar">
                <form method="GET" action="<?php echo e(url('crafts.php')); ?>" class="search-form">
                    <div class="search-input-group">
                        <span class="search-input-icon">🔍</span>
                        <input type="text" name="search" class="search-input" placeholder="Search crafts by name, materials, or description..." value="<?php echo e($search); ?>">
                    </div>
                    <select name="category" class="filter-select" data-autosubmit>
                        <option value="">All Craft Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo e($cat); ?>"<?php echo selected($category_filter, $cat); ?>><?php echo e($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">Filter Crafts</button>
                    <?php if ($search || $category_filter): ?>
                        <a href="<?php echo e(url('crafts.php')); ?>" class="btn btn-secondary">Clear</a>
                    <?php endif; ?>
                </form>

                <div class="filter-chips">
                    <a href="<?php echo e(url('crafts.php')); ?>" class="filter-chip<?php echo $category_filter === '' ? ' active' : ''; ?>">All Crafts</a>
                    <?php foreach ($categories as $cat): ?>
                        <a href="<?php echo e(url('crafts.php', ['category' => $cat])); ?>" class="filter-chip<?php echo $category_filter === $cat ? ' active' : ''; ?>">❖ <?php echo e($cat); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card-grid">
                <?php if ($crafts): ?>
                    <?php foreach ($crafts as $craft): ?>
                        <div class="card" data-category="<?php echo e($craft['category'] ?? ''); ?>">
                            <div class="card-image-wrap">
                                <?php
                                $img = !empty($craft['image']) ? APP_ROOT . '/images/' . $craft['image'] : '';
                                if ($img && is_file($img)):
                                ?>
                                    <img src="<?php echo e(asset('images/' . $craft['image'])); ?>" alt="<?php echo e($craft['name']); ?>" class="card-image">
                                <?php else: ?>
                                    <div class="card-fallback-art">
                                        <div class="card-fallback-icon">🏺</div>
                                        <div class="card-fallback-title"><?php echo e($craft['name']); ?></div>
                                    </div>
                                <?php endif; ?>
                                <span class="card-badge-top"><?php echo e($craft['category'] ?? 'Artisan Craft'); ?></span>
                            </div>
                            <div class="card-content">
                                <span class="card-category"><?php echo e($craft['category'] ?? 'Indigenous Craft'); ?></span>
                                <h3 class="card-title"><?php echo e($craft['name']); ?></h3>
                                <p class="card-description"><?php echo e($craft['description']); ?></p>
                                <?php if (!empty($craft['materials'])): ?>
                                    <div class="card-tags">
                                        <?php foreach (explode(',', $craft['materials']) as $m): ?>
                                            <span class="card-tag">🌿 <?php echo e(trim($m)); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($craft['cultural_significance'])): ?>
                                    <div class="card-significance">
                                        <strong>Cultural Significance:</strong><br>
                                        "<?php echo e($craft['cultural_significance']); ?>"
                                    </div>
                                <?php endif; ?>
                                <div class="card-meta">
                                    <span>Added: <?php echo e(format_date($craft['created_at'])); ?></span>
                                    <span style="font-weight: 600; color: var(--primary-600);">Master Artisan Work</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 4rem 2rem; background: var(--bg-surface); border-radius: var(--radius-xl); border: 1px dashed var(--border-color);">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">🏺</div>
                        <h3 style="color: var(--primary-900); font-size: 1.6rem; margin-bottom: 0.5rem;">No Crafts Found</h3>
                        <p style="color: var(--text-muted); margin-bottom: 1.5rem;">No indigenous crafts match your search criteria. Try a different search term or view all items.</p>
                        <a href="<?php echo e(url('crafts.php')); ?>" class="btn btn-primary">View All Crafts</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php require __DIR__ . '/includes/partials/public_footer.php'; ?>
