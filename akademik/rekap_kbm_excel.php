<?php
require_once __DIR__.'/../config/auth.php';
check_akademik_access();
if($_SERVER['REQUEST_METHOD']!=='GET'){header('Allow: GET');http_response_code(405);exit('Unduhan hanya menerima GET.');}
require_once __DIR__.'/../includes/monitoring_kbm_matrix_export.php';
$filter=array_intersect_key($_GET,array_flip(['tanggal','sesi']));
try{
    $db=Database::getInstance();if(!kbm_admin_ready($db))throw new KbmExportNotice('Monitoring KBM belum dikonfigurasi.');
    session_write_close();set_time_limit(120);$export=kbm_matrix_export_file($db,$filter);
}catch(InvalidArgumentException|KbmExportNotice $e){
    if(session_status()!==PHP_SESSION_ACTIVE)session_start();$_SESSION['kbm_matrix_error']=$e->getMessage();redirect('riwayat_kbm.php');
}catch(Throwable $e){
    error_log('Excel rekap Waka: '.$e->getMessage());if(session_status()!==PHP_SESSION_ACTIVE)session_start();$_SESSION['kbm_matrix_error']='Unduhan belum dapat disiapkan. Silakan coba lagi.';redirect('riwayat_kbm.php');
}
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$export['filename'].'"');
header('Content-Length: '.$export['size']);header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');
try{fpassthru($export['file']);}finally{fclose($export['file']);}
exit;
