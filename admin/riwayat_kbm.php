<?php
require_once __DIR__.'/../config/auth.php';
check_access([1]);
$kbmHistoryAdmin=true;
require __DIR__.'/../includes/monitoring_kbm_history_page.php';
