<?php
require_once __DIR__.'/../config/auth.php';
require_once __DIR__.'/monitoring_kbm_dashboard.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
function kbm_feed_error(int $code,string $message): never {
    http_response_code($code);echo json_encode(['ok'=>false,'message'=>$message],JSON_UNESCAPED_UNICODE);exit;
}
try {
    if(!isset($kbmFeedArea) || !in_array($kbmFeedArea,['admin','akademik'],true)) kbm_feed_error(404,'Endpoint tidak tersedia.');
    if(!auth_refresh_session()) kbm_feed_error(401,'Sesi login berakhir. Silakan login kembali.');
    $role=(int)$_SESSION['role_id'];
    if($role!==1 && !(($_SESSION['role_nama']??'')==='Akademik' && $role===akademik_role_id(Database::getInstance()))) kbm_feed_error(403,'Anda tidak memiliki akses monitoring KBM.');
    if(($kbmFeedArea==='admin' && $role!==1) || ($kbmFeedArea==='akademik' && $role===1)) kbm_feed_error(403,'Gunakan endpoint monitoring sesuai area akun Anda.');
    if($_SERVER['REQUEST_METHOD']!=='GET') {header('Allow: GET');kbm_feed_error(405,'Endpoint monitoring hanya menerima pembacaan GET.');}
    session_write_close(); // Polling tidak menahan kunci session ketika tab lain dipakai.
    $db=Database::getInstance();
    if(!kbm_admin_ready($db)) kbm_feed_error(503,'Monitoring KBM belum dikonfigurasi. Lengkapi migrasi dan pengaturan sekolah.');
    echo json_encode(kbm_dashboard_payload($db,$_GET,$kbmFeedArea==='akademik'),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
} catch(InvalidArgumentException $e) {kbm_feed_error(400,$e->getMessage());}
catch(Throwable $e) {error_log('Feed monitoring KBM: '.$e->getMessage());kbm_feed_error(503,'Data monitoring belum dapat diperbarui. Silakan coba lagi.');}
