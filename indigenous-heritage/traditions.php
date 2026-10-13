<?php
require_once __DIR__ . '/includes/bootstrap.php';

$search = get_str('search', 100);
$type_filter = get_str('type', 50);

$sql = 'SELECT * FROM traditions WHERE 1=1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (name LIKE ? OR description LIKE ? OR type LIKE ? OR origin LIKE ?)';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like];
}
if ($type_filter !== '') {
    $sql .= ' AND type = ?';
    $params[] = $type_filter;
}
$sql .= ' ORDER BY created_at DESC';
$traditions = db_all($sql, $params);
$types = array_column(db_all("SELECT DISTINCT type FROM traditions WHERE type IS NOT NULL AND type <> '' ORDER BY type"), 'type');

$page_title = 'Living Traditions & Ceremonies | Cultural Heritage Portal';
$page_description = 'Explore sacred ceremonies, harvest festivals, dances, and indigenous community traditions.';
$active_nav = 'traditions';
require __DIR__ . '/includes/partials/public_header.php';
?>
    <section class="page-hero">
        <div class="hero-content">
            <div class="hero-badge">❖ Ritual &amp; Communal Life ❖</div>
            <h1>Living Traditions &amp; Ceremonies</h1>
            <p>Discover sacred ceremonies, celebratory dances, rites of passage, and spiritual practices rooted in ancestral tribal lore.</p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="search-toolbar">
                <form method="GET" action="<?php echo e(url('traditions.php')); ?>" class="search-form">
                    <div class="search-input-group">
                        <span class="search-input-icon">🔍</span>
                        <input type="text" name="search" class="search-input" placeholder="Search traditions, ceremonies, or origins..." value="<?php echo e($search); ?>">
                    </div>
                    <select name="type" class="filter-select" data-autosubmit>
                        <option value="">All Tradition Types</option>
                        <?php foreach ($types as $type): ?>
                            <option value="<?php echo e($type); ?>"<?php echo selected($type_filter, $type); ?>><?php echo e($type); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">Filter Traditions</button>
                    <?php if ($search || $type_filter): ?>
                        <a href="<?php echo e(url('traditions.php')); ?>" class="btn btn-secondary">Clear</a>
                    <?php endif; ?>
                </form>

                <div class="filter-chips">
                    <a href="<?php echo e(url('traditions.php')); ?>" class="filter-chip<?php echo $type_filter === '' ? ' active' : ''; ?>">All Traditions</a>
                    <?php foreach ($types as $type): ?>
                        <a href="<?php echo e(url('traditions.php', ['type' => $type])); ?>" class="filter-chip<?php echo $type_filter === $type ? ' active' : ''; ?>">❖ <?php echo e($type); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card-grid">
                <?php if ($traditions): ?>
                    <?php foreach ($traditions as $tradition): ?>
                        <div class="card">
                            <div class="card-image-wrap">
                                <?php
                                $img = !empty($tradition['image']) ? APP_ROOT . '/images/' . $tradition['image'] : '';
                                if ($img && is_file($img)):
                                ?>
                                    <img src="<?php echo e(asset('images/' . $tradition['image'])); ?>" alt="<?php echo e($tradition['name']); ?>" class="card-image">
                                <?php else: ?>
                                    <div class="card-fallback-art">
                                        <div class="card-fallback-icon">🎭</div>
                                        <div class="card-fallback-title"><?php echo e($tradition['name']); ?></div>
                                    </div>
                                <?php endif; ?>
                                <span class="card-badge-top"><?php echo e($tradition['type'] ?? 'Ceremony'); ?></span>
                            </div>
                            <div class="card-content">
                                <span class="card-category"><?php echo e($tradition['type'] ?? 'Tradition'); ?></span>
                                <h3 class="card-title"><?php echo e($tradition['name']); ?></h3>
                                <p class="card-description"><?php echo e($tradition['description']); ?></p>
                                <?php if (!empty($tradition['origin'])): ?>
                                    <div class="card-tags">
                                        <span class="card-tag">📍 Origin: <?php echo e($tradition['origin']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="card-meta">
                                    <span>Added: <?php echo e(format_date($tradition['created_at'])); ?></span>
                                    <span style="font-weight: 600; color: var(--primary-600);">Community Practice</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 4rem 2rem; background: var(--bg-surface); border-radius: var(--radius-xl); border: 1px dashed var(--border-color);">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">🎭</div>
                        <h3 style="color: var(--primary-900); font-size: 1.6rem; margin-bottom: 0.5rem;">No Traditions Found</h3>
                        <p style="color: var(--text-muted); margin-bottom: 1.5rem;">No traditions match your search. Try another term or view all items.</p>
                        <a href="<?php echo e(url('traditions.php')); ?>" class="btn btn-primary">View All Traditions</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php require __DIR__ . '/includes/partials/public_footer.php'; ?>
