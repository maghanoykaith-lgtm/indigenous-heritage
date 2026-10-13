<?php
require_once __DIR__ . '/includes/bootstrap.php';

$search = get_str('search', 100);
$category_filter = get_str('category', 50);

$sql = 'SELECT * FROM stories WHERE 1=1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (title LIKE ? OR content LIKE ? OR author LIKE ? OR origin LIKE ? OR category LIKE ?)';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like, $like];
}
if ($category_filter !== '') {
    $sql .= ' AND category = ?';
    $params[] = $category_filter;
}
$sql .= ' ORDER BY created_at DESC';
$stories = db_all($sql, $params);
$categories = array_column(db_all("SELECT DISTINCT category FROM stories WHERE category IS NOT NULL AND category <> '' ORDER BY category"), 'category');

$page_title = 'Ancestral Stories & Folklore | Cultural Heritage Portal';
$page_description = 'Read traditional stories, legends, and oral histories from indigenous communities.';
$active_nav = 'stories';
require __DIR__ . '/includes/partials/public_header.php';
?>
    <section class="page-hero">
        <div class="hero-content">
            <div class="hero-badge">❖ Living Voices ❖</div>
            <h1>Ancestral Legends &amp; Oral Tales</h1>
            <p>Timeless myths, origin accounts, and moral allegories preserved from elder storytellers and community tradition bearers.</p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="search-toolbar">
                <form method="GET" action="<?php echo e(url('stories.php')); ?>" class="search-form">
                    <div class="search-input-group">
                        <span class="search-input-icon">🔍</span>
                        <input type="text" name="search" class="search-input" placeholder="Search stories, authors, or origins..." value="<?php echo e($search); ?>">
                    </div>
                    <select name="category" class="filter-select" data-autosubmit>
                        <option value="">All Story Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo e($cat); ?>"<?php echo selected($category_filter, $cat); ?>><?php echo e($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">Filter Stories</button>
                    <?php if ($search || $category_filter): ?>
                        <a href="<?php echo e(url('stories.php')); ?>" class="btn btn-secondary">Clear</a>
                    <?php endif; ?>
                </form>

                <div class="filter-chips">
                    <a href="<?php echo e(url('stories.php')); ?>" class="filter-chip<?php echo $category_filter === '' ? ' active' : ''; ?>">All Stories</a>
                    <?php foreach ($categories as $cat): ?>
                        <a href="<?php echo e(url('stories.php', ['category' => $cat])); ?>" class="filter-chip<?php echo $category_filter === $cat ? ' active' : ''; ?>">❖ <?php echo e($cat); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="stories-grid">
                <?php if ($stories): ?>
                    <?php foreach ($stories as $story): ?>
                        <div class="story-card">
                            <div class="story-header">
                                <span class="story-badge"><?php echo e($story['category'] ?? 'Legend'); ?></span>
                                <h3 class="story-title"><?php echo e($story['title']); ?></h3>
                                <div class="story-meta-top">
                                    <span>📍 <?php echo e($story['origin'] ?? 'Highland Region'); ?></span>
                                    <span>⏱ ~4 min read</span>
                                </div>
                            </div>
                            <div class="story-body">
                                <p><?php echo e($story['content']); ?></p>
                            </div>
                            <div class="story-footer">
                                <span class="story-author">Elder <?php echo e($story['author'] ?? 'Tradition Bearer'); ?></span>
                                <span style="font-size: 0.85rem; color: var(--text-muted);"><?php echo e(format_date($story['created_at'])); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 4rem 2rem; background: var(--bg-surface); border-radius: var(--radius-xl); border: 1px dashed var(--border-color);">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📖</div>
                        <h3 style="color: var(--primary-900); font-size: 1.6rem; margin-bottom: 0.5rem;">No Stories Found</h3>
                        <p style="color: var(--text-muted); margin-bottom: 1.5rem;">No stories match your search. Try another term or view all tales.</p>
                        <a href="<?php echo e(url('stories.php')); ?>" class="btn btn-primary">View All Stories</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php require __DIR__ . '/includes/partials/public_footer.php'; ?>
