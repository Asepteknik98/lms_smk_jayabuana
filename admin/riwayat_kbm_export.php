<?php
require_once __DIR__.'/../config/auth.php';
check_access([1]);
if($_SERVER['REQUEST_METHOD']!=='GET'){header('Allow: GET');http_response_code(405);exit('Unduhan hanya menerima GET.');}
require_once __DIR__.'/../includes/monitoring_kbm_export.php';
$db=Database::getInstance();$filter=kbm_export_filter_input($_GET);
try {
    if(!kbm_admin_ready($db))throw new KbmExportNotice('Monitoring KBM belum dikonfigurasi.');
    session_write_close();set_time_limit(120);
    $export=kbm_history_export_file($db,$filter);
}catch(InvalidArgumentException $e){session_start();$_SESSION['kbm_history_error']=$e->getMessage();redirect('riwayat_kbm.php');}
catch(KbmExportNotice $e){if(session_status()!==PHP_SESSION_ACTIVE)session_start();$_SESSION['kbm_history_error']=$e->getMessage();redirect('riwayat_kbm.php?'.http_build_query($filter));}
catch(Throwable $e){error_log('Ekspor riwayat KBM: '.$e->getMessage());if(session_status()!==PHP_SESSION_ACTIVE)session_start();$_SESSION['kbm_history_error']='Unduhan belum dapat disiapkan. Persempit filter atau coba kembali.';redirect('riwayat_kbm.php?'.http_build_query($filter));}
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$export['filename'].'"');
header('Content-Length: '.$export['size']);header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');
try{fpassthru($export['file']);}finally{fclose($export['file']);}
exit;
