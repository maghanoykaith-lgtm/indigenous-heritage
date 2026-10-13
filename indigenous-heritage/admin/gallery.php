<?php
// The separate gallery manager was replaced by the unified heritage records manager.
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_staff();
redirect('admin/records.php');
