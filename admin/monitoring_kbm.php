<?php
require_once __DIR__.'/../config/auth.php';
check_access([1]);
require_once __DIR__.'/../includes/monitoring_kbm_admin.php';
require __DIR__.'/../includes/monitoring_kbm_simple_page.php';
