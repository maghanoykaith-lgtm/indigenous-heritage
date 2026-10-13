<?php
$page_title = $page_title ?? 'Indigenous Cultural Heritage Portal';
$page_description = $page_description ?? 'Discover, celebrate, and preserve indigenous crafts, traditions, sacred materials, and ancestral stories.';
$active_nav = $active_nav ?? '';
$nav_items = [
    'home' => ['index.php', 'Home'],
    'crafts' => ['crafts.php', 'Crafts'],
    'traditions' => ['traditions.php', 'Traditions'],
    'materials' => ['materials.php', 'Materials'],
    'stories' => ['stories.php', 'Stories'],
    'gallery' => ['gallery.php', 'Gallery'],
    'about' => ['about.php', 'About'],
    'contact' => ['contact.php', 'Contact'],
];
$viewer_staff = current_staff();
$viewer_member = $viewer_staff ? null : current_member();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($page_title); ?></title>
    <meta name="description" content="<?php echo e($page_description); ?>">
    <meta name="theme-color" content="#3E1E0C">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🌿</text></svg>">
    <link rel="stylesheet" href="<?php echo e(asset('css/style.css')); ?>">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to content</a>
    <nav class="navbar" aria-label="Main navigation">
        <div class="nav-container">
            <a href="<?php echo e(url('index.php')); ?>" class="nav-logo">
                <span class="nav-logo-icon">🌿</span>
                <span>Indigenous Heritage</span>
            </a>
            <button type="button" class="nav-toggle" aria-label="Toggle navigation" aria-expanded="false" aria-controls="site-menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <ul class="nav-menu" id="site-menu">
                <?php foreach ($nav_items as $key => [$href, $label]): ?>
                    <li><a href="<?php echo e(url($href)); ?>"<?php echo $active_nav === $key ? ' class="active" aria-current="page"' : ''; ?>><?php echo e($label); ?></a></li>
                <?php endforeach; ?>
                <?php if ($viewer_staff): ?>
                    <li><a href="<?php echo e(url('admin/dashboard.php')); ?>" class="nav-cta-btn">Portal Access 🔐</a></li>
                <?php elseif ($viewer_member): ?>
                    <li><a href="<?php echo e(url('user_dashboard.php')); ?>" class="nav-cta-btn<?php echo $active_nav === 'account' ? ' active' : ''; ?>">My Account</a></li>
                <?php else: ?>
                    <li><a href="<?php echo e(url('admin/login.php')); ?>" class="nav-cta-btn">Portal Access 🔐</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
    <main id="main-content">
