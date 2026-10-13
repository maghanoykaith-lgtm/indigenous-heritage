<?php
require_once __DIR__ . '/includes/bootstrap.php';

$record = find_record(get_int('id'));
if (!$record || !can_view_record($record)) {
    not_found();
}

$media = record_media_list((int) $record['id']);
$images = array_values(array_filter($media, static fn($m) => $m['kind'] === 'image'));
$documents = array_values(array_filter($media, static fn($m) => $m['kind'] === 'document'));
$keywords = keyword_list($record['keywords']);
$show_location = $record['location'] && can_show_location($record);

if ($record['visibility'] !== 'public') {
    header('Cache-Control: private, no-store');
}

$page_title = $record['title'];
$page_description = str_limit($record['summary'] ?: $record['description'], 155);
$active_nav = 'records';
require __DIR__ . '/includes/partials/public_header.php';
?>
    <section class="page-hero page-hero-compact">
        <div class="hero-content">
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <a href="<?php echo e(url('records.php')); ?>">Heritage Records</a>
                <?php if ($record['category_slug']): ?>
                    <span>/</span><a href="<?php echo e(url('records.php', ['category' => $record['category_slug']])); ?>"><?php echo e($record['category_name']); ?></a>
                <?php endif; ?>
            </nav>
            <h1><?php echo e($record['title']); ?></h1>
            <?php if ($record['summary']): ?>
                <p><?php echo e($record['summary']); ?></p>
            <?php endif; ?>
        </div>
    </section>

    <section class="section">
        <div class="container record-detail">
            <?php if ($record['visibility'] !== 'public' || $record['status'] !== 'published'): ?>
                <div class="form-alert form-alert-warning">
                    <span>🔒</span>
                    <span>
                        <?php if ($record['visibility'] === 'restricted'): ?>
                            Restricted record: visible only to approved community members and archive staff. Please do not copy or redistribute it without community permission.
                        <?php else: ?>
                            Staff preview: this record is <?php echo e(strtolower(VISIBILITY_LEVELS[$record['visibility']])); ?> and <?php echo e(strtolower(RECORD_STATUSES[$record['status']])); ?>. It is not visible to the public.
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>

            <div class="record-layout">
                <article class="record-main">
                    <?php if ($images): ?>
                        <div class="record-gallery">
                            <?php foreach ($images as $index => $image): ?>
                                <figure class="gallery-card record-figure<?php echo $index === 0 ? ' is-primary' : ''; ?>">
                                    <img src="<?php echo e(media_url((int) $image['id'], $index !== 0)); ?>" data-full="<?php echo e(media_url((int) $image['id'])); ?>" alt="<?php echo e($image['caption'] ?: $record['title']); ?>" loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>">
                                    <?php if ($image['caption']): ?>
                                        <figcaption class="gallery-card-desc"><?php echo e($image['caption']); ?></figcaption>
                                    <?php endif; ?>
                                    <span class="gallery-card-title sr-only"><?php echo e($record['title']); ?></span>
                                </figure>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="record-section">
                        <h2>Description</h2>
                        <div class="record-text"><?php echo nl2br(e($record['description'])); ?></div>
                    </div>

                    <?php if ($record['cultural_significance']): ?>
                        <div class="record-section record-significance">
                            <h2>Cultural Significance</h2>
                            <div class="record-text"><?php echo nl2br(e($record['cultural_significance'])); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($documents): ?>
                        <div class="record-section">
                            <h2>Documents</h2>
                            <ul class="document-list">
                                <?php foreach ($documents as $document): ?>
                                    <li>
                                        <a href="<?php echo e(media_url((int) $document['id'])); ?>" target="_blank" rel="noopener">
                                            📄 <?php echo e($document['caption'] ?: $document['original_name']); ?>
                                        </a>
                                        <span><?php echo e(strtoupper(DOCUMENT_MIME_TYPES[$document['mime_type']] ?? 'file')); ?> &bull; <?php echo e(format_bytes((int) $document['file_size'])); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </article>

                <aside class="record-aside">
                    <div class="record-facts">
                        <h2>Record Details</h2>
                        <dl>
                            <dt>Category</dt>
                            <dd><?php echo e(($record['category_icon'] ? $record['category_icon'] . ' ' : '') . ($record['category_name'] ?? 'Uncategorized')); ?></dd>
                            <?php if ($record['community']): ?><dt>Community</dt><dd><?php echo e($record['community']); ?></dd><?php endif; ?>
                            <?php if ($record['language']): ?><dt>Language</dt><dd><?php echo e($record['language']); ?></dd><?php endif; ?>
                            <?php if ($record['period']): ?><dt>Period / Era</dt><dd><?php echo e($record['period']); ?></dd><?php endif; ?>
                            <?php if ($record['date_recorded']): ?><dt>Date Recorded</dt><dd><?php echo e(format_date($record['date_recorded'])); ?></dd><?php endif; ?>
                            <?php if ($record['source']): ?><dt>Knowledge Holder / Source</dt><dd><?php echo e($record['source']); ?></dd><?php endif; ?>
                            <?php if ($show_location): ?><dt>Location</dt><dd><?php echo e($record['location']); ?></dd><?php endif; ?>
                            <?php if ($record['consent_status'] === 'granted' && $record['consent_given_by']): ?>
                                <dt>Shared With Permission Of</dt><dd><?php echo e($record['consent_given_by']); ?></dd>
                            <?php endif; ?>
                            <dt>Access</dt><dd><?php echo visibility_badge($record['visibility']); ?></dd>
                            <dt>Published</dt><dd><?php echo e(format_date($record['published_at'] ?: $record['created_at'])); ?></dd>
                            <dt>Last Updated</dt><dd><?php echo e(format_date($record['updated_at'])); ?></dd>
                        </dl>
                        <?php if ($keywords): ?>
                            <div class="card-tags">
                                <?php foreach ($keywords as $keyword): ?>
                                    <a class="card-tag" href="<?php echo e(url('records.php', ['q' => $keyword])); ?>"><?php echo e($keyword); ?></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="record-notice">
                        <strong>Cultural respect notice</strong>
                        <p>This knowledge belongs to its community. Educational, non-commercial use requires attribution; any other use needs the community's permission.</p>
                    </div>

                    <?php if (is_staff()): ?>
                        <a class="btn btn-primary btn-block" href="<?php echo e(url('admin/records.php', ['action' => 'view', 'id' => $record['id']])); ?>">Manage This Record</a>
                    <?php endif; ?>
                    <a class="btn btn-secondary btn-block" href="<?php echo e(url('records.php', ['category' => $record['category_slug']])); ?>">&larr; More in <?php echo e($record['category_name'] ?? 'this category'); ?></a>
                </aside>
            </div>
        </div>
    </section>
<?php require __DIR__ . '/includes/partials/public_footer.php'; ?>
