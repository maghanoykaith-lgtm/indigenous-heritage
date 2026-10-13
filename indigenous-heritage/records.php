<?php
require_once __DIR__ . '/includes/bootstrap.php';

$categories = fetch_categories(true);
$category_slug = get_str('category', 120);
$current_category = null;
foreach ($categories as $category) {
    if ($category['slug'] === $category_slug) {
        $current_category = $category;
    }
}

$q = get_str('q', 100);
$sort = in_array(get_str('sort', 10), ['newest', 'updated', 'title', 'oldest'], true) ? get_str('sort', 10) : 'newest';
$result = search_records([
    'q' => $q,
    'category_id' => $current_category['id'] ?? null,
    'sort' => $sort,
], get_int('page', 1), 12);
$records = $result['rows'];
$pager = $result['pager'];
$query_params = ['q' => $q, 'category' => $current_category['slug'] ?? null, 'sort' => $sort !== 'newest' ? $sort : null];

$page_title = $current_category ? $current_category['name'] : 'Heritage Records';
$page_description = $current_category['description'] ?? 'Search and browse approved Indigenous heritage records.';
$active_nav = 'records';
require __DIR__ . '/includes/partials/public_header.php';
?>
    <section class="page-hero">
        <div class="hero-content">
            <div class="hero-badge">❖ <?php echo $current_category ? e($current_category['icon'] . ' Category') : 'Heritage Archive'; ?> ❖</div>
            <h1><?php echo e($current_category['name'] ?? 'Heritage Records'); ?></h1>
            <p><?php echo e($current_category['description'] ?? 'Search approved records of traditions, oral histories, languages, arts, artifacts, and traditional knowledge.'); ?></p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="search-toolbar">
                <form method="GET" action="<?php echo e(url('records.php')); ?>" class="search-form" role="search">
                    <div class="search-input-group">
                        <span class="search-input-icon" aria-hidden="true">🔍</span>
                        <label for="records-q" class="sr-only">Search records</label>
                        <input type="search" id="records-q" name="q" class="search-input" placeholder="Search by title, community, language, keyword…" value="<?php echo e($q); ?>" maxlength="100">
                    </div>
                    <label for="records-category" class="sr-only">Category</label>
                    <select id="records-category" name="category" class="filter-select" data-autosubmit>
                        <option value="">All categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo e($category['slug']); ?>"<?php echo selected($category['slug'], $current_category['slug'] ?? ''); ?>><?php echo e($category['name']); ?> (<?php echo (int) $category['record_count']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <label for="records-sort" class="sr-only">Sort</label>
                    <select id="records-sort" name="sort" class="filter-select" data-autosubmit>
                        <option value="newest"<?php echo selected($sort, 'newest'); ?>>Newest first</option>
                        <option value="updated"<?php echo selected($sort, 'updated'); ?>>Recently updated</option>
                        <option value="title"<?php echo selected($sort, 'title'); ?>>Title A–Z</option>
                        <option value="oldest"<?php echo selected($sort, 'oldest'); ?>>Oldest first</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if ($q !== '' || $current_category || $sort !== 'newest'): ?>
                        <a href="<?php echo e(url('records.php')); ?>" class="btn btn-secondary">Clear</a>
                    <?php endif; ?>
                </form>

                <div class="filter-chips">
                    <a href="<?php echo e(url('records.php', ['q' => $q])); ?>" class="filter-chip<?php echo $current_category ? '' : ' active'; ?>">All</a>
                    <?php foreach ($categories as $category): ?>
                        <a href="<?php echo e(url('records.php', ['category' => $category['slug'], 'q' => $q])); ?>" class="filter-chip<?php echo ($current_category['slug'] ?? '') === $category['slug'] ? ' active' : ''; ?>">
                            <?php echo e($category['icon'] . ' ' . $category['name']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <p class="results-summary">
                <?php echo $pager['total']; ?> record<?php echo $pager['total'] === 1 ? '' : 's'; ?> found<?php echo $q !== '' ? ' for “' . e($q) . '”' : ''; ?>.
                <?php if (!is_staff() && !is_active_member()): ?>
                    <span>Some records are restricted to approved community members.</span>
                <?php endif; ?>
            </p>

            <div class="card-grid">
                <?php if ($records): ?>
                    <?php foreach ($records as $card_record): ?>
                        <?php require __DIR__ . '/includes/partials/record_card.php'; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">🔍</div>
                        <h3>No matching records</h3>
                        <p>Try a different search term or category.</p>
                        <a href="<?php echo e(url('records.php')); ?>" class="btn btn-primary">View all records</a>
                    </div>
                <?php endif; ?>
            </div>

            <?php render_pagination($pager, 'records.php', $query_params); ?>
        </div>
    </section>
<?php require __DIR__ . '/includes/partials/public_footer.php'; ?>
