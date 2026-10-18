<?php
require_once __DIR__.'/../config/auth.php';
check_akademik_access();
$kbmHistoryAdmin=false;
// Tautan detail/audit lama tetap tersedia, seluruh akses akademik hanya baca.
if(isset($_GET['detail_jadwal'])||isset($_GET['detail_tanggal']))require __DIR__.'/../includes/monitoring_kbm_history_page.php';
else require __DIR__.'/../includes/monitoring_kbm_matrix_page.php';
