<?php
require_once __DIR__ . '/includes/bootstrap.php';

$crafts_count = (int) db_value('SELECT COUNT(*) FROM crafts');
$traditions_count = (int) db_value('SELECT COUNT(*) FROM traditions');
$materials_count = (int) db_value('SELECT COUNT(*) FROM materials');
$stories_count = (int) db_value('SELECT COUNT(*) FROM stories');

$featured_crafts = db_all('SELECT * FROM crafts ORDER BY created_at DESC LIMIT 3');
$featured_traditions = db_all('SELECT * FROM traditions ORDER BY created_at DESC LIMIT 3');
$featured_stories = db_all('SELECT * FROM stories ORDER BY created_at DESC LIMIT 3');
$gallery_items = db_all('SELECT * FROM gallery ORDER BY created_at DESC LIMIT 6');

$page_title = 'Indigenous Cultural Heritage Portal | Preserving Living Traditions';
$page_description = 'Discover, celebrate, and preserve indigenous crafts, traditions, sacred materials, and ancestral stories.';
$active_nav = 'home';
require __DIR__ . '/includes/partials/public_header.php';
?>
    <section class="hero">
        <div class="hero-content">
            <div class="hero-badge">❖ Living Ancestral Knowledge ❖</div>
            <h1>Preserving &amp; Celebrating Indigenous Cultural Heritage</h1>
            <p>Immerse yourself in thousands of years of sacred wisdom, sustainable craftsmanship, living rituals, and timeless folklore documented with indigenous community custodians.</p>

            <div class="hero-buttons">
                <a href="<?php echo e(url('crafts.php')); ?>" class="btn btn-primary btn-lg">Explore Master Crafts 🎨</a>
                <a href="<?php echo e(url('traditions.php')); ?>" class="btn btn-outline-light btn-lg">Discover Traditions 🎭</a>
                <a href="<?php echo e(url('about.php')); ?>" class="btn btn-secondary btn-lg">Our Mission 📜</a>
            </div>

            <div class="hero-stats-bar">
                <div class="hero-stat-item">
                    <div class="hero-stat-num"><?php echo $crafts_count; ?></div>
                    <div class="hero-stat-label">Master Crafts</div>
                </div>
                <div class="hero-stat-item">
                    <div class="hero-stat-num"><?php echo $traditions_count; ?></div>
                    <div class="hero-stat-label">Sacred Rituals</div>
                </div>
                <div class="hero-stat-item">
                    <div class="hero-stat-num"><?php echo $materials_count; ?></div>
                    <div class="hero-stat-label">Native Materials</div>
                </div>
                <div class="hero-stat-item">
                    <div class="hero-stat-num"><?php echo $stories_count; ?></div>
                    <div class="hero-stat-label">Ancestral Tales</div>
                </div>
            </div>
        </div>
    </section>

    <div class="container">
        <div class="pillars-grid">
            <div class="pillar-card">
                <div class="pillar-icon-box">🎨</div>
                <h3>Traditional Crafts</h3>
                <p>Intricate weaving, pottery, beadwork, and metalcraft handed down through master artisan lineages.</p>
                <a href="<?php echo e(url('crafts.php')); ?>" class="pillar-link">Browse Crafts &rarr;</a>
            </div>
            <div class="pillar-card">
                <div class="pillar-icon-box">🎭</div>
                <h3>Living Traditions</h3>
                <p>Harvest festivals, sacred dances, rites of passage, and communal spiritual ceremonies.</p>
                <a href="<?php echo e(url('traditions.php')); ?>" class="pillar-link">Explore Rituals &rarr;</a>
            </div>
            <div class="pillar-card">
                <div class="pillar-icon-box">🌿</div>
                <h3>Native Materials</h3>
                <p>Bamboo, rattan, wild clays, abaca fiber, and sustainable forest harvests in harmony with nature.</p>
                <a href="<?php echo e(url('materials.php')); ?>" class="pillar-link">Learn Materials &rarr;</a>
            </div>
            <div class="pillar-card">
                <div class="pillar-icon-box">📖</div>
                <h3>Oral Legends</h3>
                <p>Myths, origin epics, and ethical folklore recorded directly from tribal elders and storytellers.</p>
                <a href="<?php echo e(url('stories.php')); ?>" class="pillar-link">Read Stories &rarr;</a>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="container">
            <div class="section-title-wrap">
                <span class="section-badge">Handmade Heritage</span>
                <h2 class="section-title">Featured Indigenous Crafts</h2>
                <div class="tribal-accent-bar"></div>
                <p class="section-subtitle">Exemplary handcrafted artifacts showcasing intricate geometric patterns, natural pigments, and profound cultural symbolism.</p>
            </div>

            <div class="card-grid">
                <?php if ($featured_crafts): ?>
                    <?php foreach ($featured_crafts as $craft): ?>
                        <div class="card">
                            <div class="card-image-wrap">
                                <?php
                                $craft_image = !empty($craft['image']) ? APP_ROOT . '/images/' . $craft['image'] : '';
                                if ($craft_image && is_file($craft_image)):
                                ?>
                                    <img src="<?php echo e(asset('images/' . $craft['image'])); ?>" alt="<?php echo e($craft['name']); ?>" class="card-image">
                                <?php else: ?>
                                    <div class="card-fallback-art">
                                        <div class="card-fallback-icon">🏺</div>
                                        <div class="card-fallback-title"><?php echo e($craft['name']); ?></div>
                                    </div>
                                <?php endif; ?>
                                <span class="card-badge-top"><?php echo e($craft['category'] ?? 'Craft'); ?></span>
                            </div>
                            <div class="card-content">
                                <span class="card-category"><?php echo e($craft['category'] ?? 'Traditional Craft'); ?></span>
                                <h3 class="card-title"><?php echo e($craft['name']); ?></h3>
                                <p class="card-description"><?php echo e(str_limit($craft['description'], 140)); ?></p>
                                <?php if (!empty($craft['materials'])): ?>
                                    <div class="card-tags">
                                        <?php foreach (array_slice(explode(',', $craft['materials']), 0, 3) as $m): ?>
                                            <span class="card-tag">🌿 <?php echo e(trim($m)); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($craft['cultural_significance'])): ?>
                                    <div class="card-significance">"<?php echo e(str_limit($craft['cultural_significance'], 100)); ?>"</div>
                                <?php endif; ?>
                                <div class="card-meta">
                                    <span>Preserved Heritage</span>
                                    <a href="<?php echo e(url('crafts.php')); ?>" class="btn btn-secondary btn-sm">View Details &rarr;</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; background: var(--bg-surface); border-radius: var(--radius-lg);">
                        <p>No crafts found in database yet. Add crafts via the admin portal!</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="text-center" style="margin-top: 3rem;">
                <a href="<?php echo e(url('crafts.php')); ?>" class="btn btn-primary btn-lg">Explore Full Crafts Catalog 🏺</a>
            </div>
        </div>
    </section>

    <section class="section featured-spotlight">
        <div class="container">
            <div class="featured-item">
                <div class="featured-image-box">
                    <?php if (is_file(APP_ROOT . '/images/culture.jpg')): ?>
                        <img src="<?php echo e(asset('images/culture.jpg')); ?>" alt="Indigenous Cultural Heritage">
                    <?php else: ?>
                        <div class="card-fallback-art" style="height: 100%; min-height: 380px;">
                            <div class="card-fallback-icon" style="font-size: 4rem;">🌄</div>
                            <div class="card-fallback-title" style="font-size: 1.5rem; margin-top: 1rem;">Sacred Indigenous Sanctuaries</div>
                            <p style="color: rgba(255,255,255,0.8); max-width: 320px; margin-top: 0.5rem; font-size: 0.9rem;">Centuries of communal memory, ecological balance, and living artistry.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="featured-content">
                    <span class="featured-badge">Why It Matters</span>
                    <h3>Safeguarding Living Ancestral Wisdom</h3>
                    <p>Indigenous cultural heritage is not merely a collection of historical relics—it represents vibrant, living systems of knowledge, ecological balance, and profound spiritual relationships with the natural world.</p>
                    <p>By digitizing, archiving, and honoring traditional crafts, sacred rituals, and indigenous languages, we provide a bridge of respect and education between past ancestors and future generations.</p>
                    <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1.75rem;">
                        <a href="<?php echo e(url('about.php')); ?>" class="btn btn-primary">Our Preservation Mission</a>
                        <a href="<?php echo e(url('contact.php')); ?>" class="btn btn-secondary">Get Involved</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="section-title-wrap">
                <span class="section-badge">Ritual &amp; Communal Life</span>
                <h2 class="section-title">Living Traditions &amp; Ceremonies</h2>
                <div class="tribal-accent-bar"></div>
                <p class="section-subtitle">Sacred ceremonies, celebratory community dances, and spiritual traditions rooted in ancient tribal lore.</p>
            </div>

            <div class="card-grid">
                <?php if ($featured_traditions): ?>
                    <?php foreach ($featured_traditions as $tradition): ?>
                        <div class="card">
                            <div class="card-image-wrap">
                                <?php
                                $t_image = !empty($tradition['image']) ? APP_ROOT . '/images/' . $tradition['image'] : '';
                                if ($t_image && is_file($t_image)):
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
                                <p class="card-description"><?php echo e(str_limit($tradition['description'], 140)); ?></p>
                                <?php if (!empty($tradition['origin'])): ?>
                                    <div class="card-tags">
                                        <span class="card-tag">📍 Origin: <?php echo e($tradition['origin']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="card-meta">
                                    <span>Community Practice</span>
                                    <a href="<?php echo e(url('traditions.php')); ?>" class="btn btn-secondary btn-sm">Explore Tradition &rarr;</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; background: var(--bg-surface); border-radius: var(--radius-lg);">
                        <p>No traditions recorded yet. Document new traditions in the admin dashboard.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="text-center" style="margin-top: 3rem;">
                <a href="<?php echo e(url('traditions.php')); ?>" class="btn btn-primary btn-lg">View All Traditional Practices 🎭</a>
            </div>
        </div>
    </section>

    <section class="section" style="background: var(--bg-secondary); border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color);">
        <div class="container">
            <div class="section-title-wrap">
                <span class="section-badge">Visual Archives</span>
                <h2 class="section-title">Curated Cultural Gallery</h2>
                <div class="tribal-accent-bar"></div>
                <p class="section-subtitle">Visual chronicles of indigenous artistry, tribal gatherings, traditional attire, and master artisan techniques.</p>
            </div>

            <div class="gallery-grid">
                <?php if ($gallery_items): ?>
                    <?php foreach ($gallery_items as $item): ?>
                        <div class="gallery-card">
                            <?php
                            $g_image = !empty($item['image']) ? APP_ROOT . '/images/' . $item['image'] : '';
                            if ($g_image && is_file($g_image)):
                            ?>
                                <img src="<?php echo e(asset('images/' . $item['image'])); ?>" alt="<?php echo e($item['title']); ?>">
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

            <div class="text-center" style="margin-top: 3rem;">
                <a href="<?php echo e(url('gallery.php')); ?>" class="btn btn-primary btn-lg">Explore Complete Gallery 🖼️</a>
            </div>
        </div>
    </section>

    <?php if ($featured_stories): ?>
        <section class="section">
            <div class="container">
                <div class="section-title-wrap">
                    <span class="section-badge">Living Voices</span>
                    <h2 class="section-title">Ancestral Legends &amp; Oral Tales</h2>
                    <div class="tribal-accent-bar"></div>
                    <p class="section-subtitle">Timeless myths, origin accounts, and moral allegories preserved from elder storytellers.</p>
                </div>

                <div class="stories-grid">
                    <?php foreach ($featured_stories as $story): ?>
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
                                <p><?php echo e(str_limit($story['content'], 180)); ?></p>
                            </div>
                            <div class="story-footer">
                                <span class="story-author">Elder <?php echo e($story['author'] ?? 'Tradition Bearer'); ?></span>
                                <a href="<?php echo e(url('stories.php')); ?>" class="btn btn-secondary btn-sm">Read Full Tale &rarr;</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <div class="container" style="margin-bottom: -3rem; position: relative; z-index: 10;">
        <div style="background: linear-gradient(135deg, var(--primary-800), var(--primary-600)); color: #fff; padding: 3.5rem 2.5rem; border-radius: var(--radius-xl); box-shadow: var(--shadow-xl); border: 2px solid var(--secondary-color); text-align: center; position: relative; overflow: hidden;">
            <div style="max-width: 720px; margin: 0 auto; position: relative; z-index: 2;">
                <span class="hero-badge" style="background: rgba(255,255,255,0.15); color: #fff; border-color: rgba(255,255,255,0.3);">Community Custodianship</span>
                <h2 style="font-size: 2.3rem; color: #fff; margin-bottom: 1rem;">Are you an artisan, cultural educator, or researcher?</h2>
                <p style="color: rgba(255,255,255,0.9); font-size: 1.1rem; margin-bottom: 2rem;">Contribute knowledge, document local tribal traditions, or collaborate on digital cultural preservation initiatives.</p>
                <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                    <a href="<?php echo e(url('contact.php')); ?>" class="btn btn-accent btn-lg">Submit Cultural Record ✍️</a>
                    <a href="<?php echo e(url('register.php')); ?>" class="btn btn-outline-light btn-lg">Create Community Account</a>
                </div>
            </div>
        </div>
    </div>

<?php require __DIR__ . '/includes/partials/public_footer.php'; ?>
