<?php
// The separate traditions manager was replaced by the unified heritage records manager.
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_staff();
$category = find_category_by_slug('traditional-practices');
redirect('admin/records.php', $category ? ['category_id' => $category['id']] : []);
