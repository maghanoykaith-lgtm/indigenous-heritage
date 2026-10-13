<?php
/** @var array $card_record */
$card_url = url('record.php', ['id' => $card_record['id']]);
$card_keywords = array_slice(keyword_list($card_record['keywords'] ?? ''), 0, 3);
?>
<article class="card record-card">
    <a href="<?php echo e($card_url); ?>" class="card-image-wrap" tabindex="-1" aria-hidden="true">
        <?php if (!empty($card_record['cover_media_id'])): ?>
            <img src="<?php echo e(media_url((int) $card_record['cover_media_id'], true)); ?>" alt="" class="card-image" loading="lazy" decoding="async">
        <?php else: ?>
            <div class="card-fallback-art">
                <div class="card-fallback-icon"><?php echo e($card_record['category_icon'] ?: '🌿'); ?></div>
                <div class="card-fallback-title"><?php echo e($card_record['title']); ?></div>
            </div>
        <?php endif; ?>
        <span class="card-badge-top"><?php echo e($card_record['category_name'] ?? 'Uncategorized'); ?></span>
    </a>
    <div class="card-content">
        <div class="card-category-row">
            <span class="card-category"><?php echo e($card_record['category_name'] ?? 'Uncategorized'); ?></span>
            <?php if ($card_record['visibility'] !== 'public'): ?>
                <?php echo visibility_badge($card_record['visibility']); ?>
            <?php endif; ?>
        </div>
        <h3 class="card-title"><a href="<?php echo e($card_url); ?>"><?php echo e($card_record['title']); ?></a></h3>
        <p class="card-description"><?php echo e(str_limit($card_record['summary'] ?: $card_record['description'], 150)); ?></p>
        <?php if ($card_keywords): ?>
            <div class="card-tags">
                <?php foreach ($card_keywords as $keyword): ?>
                    <span class="card-tag"><?php echo e($keyword); ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="card-meta">
            <span><?php echo e($card_record['community'] ?: 'Indigenous community'); ?></span>
            <a href="<?php echo e($card_url); ?>" class="btn btn-secondary btn-sm">View Record &rarr;</a>
        </div>
    </div>
</article>
