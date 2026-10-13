<?php
require_once __DIR__ . '/includes/bootstrap.php';

$search = get_str('search', 100);

$sql = 'SELECT * FROM materials WHERE 1=1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (name LIKE ? OR description LIKE ? OR uses LIKE ? OR cultural_importance LIKE ?)';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like];
}
$sql .= ' ORDER BY created_at DESC';
$materials = db_all($sql, $params);

$page_title = 'Native Materials | Cultural Heritage Portal';
$page_description = 'Learn about bamboo, rattan, clay, fibers, and other traditional indigenous materials.';
$active_nav = 'materials';
require __DIR__ . '/includes/partials/public_header.php';
?>
    <section class="page-hero">
        <div class="hero-content">
            <div class="hero-badge">❖ Earth &amp; Forest Gifts ❖</div>
            <h1>Traditional Native Materials</h1>
            <p>Discover bamboo, rattan, wild clays, abaca fiber, and sustainable forest materials used in indigenous craftsmanship and daily life.</p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="search-toolbar">
                <form method="GET" action="<?php echo e(url('materials.php')); ?>" class="search-form">
                    <div class="search-input-group">
                        <span class="search-input-icon">🔍</span>
                        <input type="text" name="search" class="search-input" placeholder="Search materials by name, uses, or cultural importance..." value="<?php echo e($search); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Search Materials</button>
                    <?php if ($search): ?>
                        <a href="<?php echo e(url('materials.php')); ?>" class="btn btn-secondary">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="card-grid">
                <?php if ($materials): ?>
                    <?php foreach ($materials as $material): ?>
                        <div class="card">
                            <div class="card-image-wrap">
                                <?php
                                $img = !empty($material['image']) ? APP_ROOT . '/images/' . $material['image'] : '';
                                if ($img && is_file($img)):
                                ?>
                                    <img src="<?php echo e(asset('images/' . $material['image'])); ?>" alt="<?php echo e($material['name']); ?>" class="card-image">
                                <?php else: ?>
                                    <div class="card-fallback-art">
                                        <div class="card-fallback-icon">🌿</div>
                                        <div class="card-fallback-title"><?php echo e($material['name']); ?></div>
                                    </div>
                                <?php endif; ?>
                                <span class="card-badge-top">Native Material</span>
                            </div>
                            <div class="card-content">
                                <span class="card-category">Natural Resource</span>
                                <h3 class="card-title"><?php echo e($material['name']); ?></h3>
                                <p class="card-description"><?php echo e($material['description']); ?></p>
                                <?php if (!empty($material['uses'])): ?>
                                    <div class="card-tags">
                                        <span class="card-tag">🔧 Uses: <?php echo e(str_limit($material['uses'], 80)); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($material['cultural_importance'])): ?>
                                    <div class="card-significance">
                                        <strong>Cultural Importance:</strong><br>
                                        "<?php echo e($material['cultural_importance']); ?>"
                                    </div>
                                <?php endif; ?>
                                <div class="card-meta">
                                    <span>Added: <?php echo e(format_date($material['created_at'])); ?></span>
                                    <span style="font-weight: 600; color: var(--primary-600);">Sustainable Heritage</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 4rem 2rem; background: var(--bg-surface); border-radius: var(--radius-xl); border: 1px dashed var(--border-color);">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">🌿</div>
                        <h3 style="color: var(--primary-900); font-size: 1.6rem; margin-bottom: 0.5rem;">No Materials Found</h3>
                        <p style="color: var(--text-muted); margin-bottom: 1.5rem;">No materials match your search. Try another term or view all items.</p>
                        <a href="<?php echo e(url('materials.php')); ?>" class="btn btn-primary">View All Materials</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php require __DIR__ . '/includes/partials/public_footer.php'; ?>
