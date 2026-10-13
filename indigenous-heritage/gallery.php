<?php
require_once __DIR__ . '/includes/bootstrap.php';

$category_filter = get_str('category', 50);
$sql = 'SELECT * FROM gallery WHERE 1=1';
$params = [];
if ($category_filter !== '') {
    $sql .= ' AND category = ?';
    $params[] = $category_filter;
}
$sql .= ' ORDER BY created_at DESC';
$gallery_items = db_all($sql, $params);
$categories = array_column(db_all("SELECT DISTINCT category FROM gallery WHERE category IS NOT NULL AND category <> '' ORDER BY category"), 'category');

$page_title = 'Visual Cultural Archives & Gallery | Heritage Portal';
$page_description = 'Explore high-resolution visual documentation of indigenous artifacts, weaving patterns, rituals, and ceremonies.';
$active_nav = 'gallery';
require __DIR__ . '/includes/partials/public_header.php';
?>
    <section class="page-hero">
        <div class="hero-content">
            <div class="hero-badge">❖ Visual Heritage Collection ❖</div>
            <h1>Curated Cultural Gallery</h1>
            <p>Visual chronicles of indigenous artistry, tribal gatherings, traditional attire, and master artisan techniques.</p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="filter-chips">
                <a href="<?php echo e(url('gallery.php')); ?>" class="filter-chip<?php echo $category_filter === '' ? ' active' : ''; ?>">All</a>
                <?php foreach ($categories as $cat): ?>
                    <a href="<?php echo e(url('gallery.php', ['category' => $cat])); ?>" class="filter-chip<?php echo $category_filter === $cat ? ' active' : ''; ?>">❖ <?php echo e($cat); ?></a>
                <?php endforeach; ?>
            </div>

            <div class="gallery-grid">
                <?php if ($gallery_items): ?>
                    <?php foreach ($gallery_items as $item): ?>
                        <div class="gallery-card">
                            <?php
                            $img = !empty($item['image']) ? APP_ROOT . '/images/' . $item['image'] : '';
                            if ($img && is_file($img)):
                            ?>
                                <img src="<?php echo e(asset('images/' . $item['image'])); ?>" alt="<?php echo e($item['title']); ?>" data-full="<?php echo e(asset('images/' . $item['image'])); ?>">
                            <?php else: ?>
                                <div class="card-fallback-art" style="height: 100%;">
                                    <div class="card-fallback-icon">🖼️</div>
                                    <div class="card-fallback-title"><?php echo e($item['title']); ?></div>
                                </div>
                            <?php endif; ?>
                            <div class="gallery-overlay">
                                <span class="gallery-category-badge"><?php echo e($item['category'] ?? 'Heritage'); ?></span>
                                <h3 class="gallery-card-title"><?php echo e($item['title']); ?></h3>
                                <p class="gallery-card-desc"><?php echo e($item['description'] ?? ''); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; background: var(--bg-surface); border-radius: var(--radius-lg);">
                        <p>No gallery images uploaded yet. Upload images via the admin portal.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php require __DIR__ . '/includes/partials/public_footer.php'; ?>
