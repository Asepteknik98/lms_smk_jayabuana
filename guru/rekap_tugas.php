<?php
// Compatibility URL for saved links and CSV downloads. The recap lives on tugas.php.
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../config/auth.php';
require_once __DIR__.'/../config/helper.php';
require_once __DIR__.'/../includes/rekap_tugas_section.php';
check_access([2]);
$db=Database::getInstance();
$q=$db->prepare('SELECT id FROM guru WHERE user_id=?');$q->execute([$_SESSION['user_id']]);$guru=(int)$q->fetchColumn();
if(!$guru){http_response_code(403);exit('Data guru tidak ditemukan.');}
$query=$_GET;
if(!isset($query['rekap_pengajaran']) && isset($query['pengajaran_id'])){
    $query['rekap_pengajaran']=$query['pengajaran_id'];unset($query['pengajaran_id']);
}
if(($_GET['format']??'')==='csv')rt_inline_export(rt_inline_prepare($db,$guru,$query));
header('Location: '.rt_inline_url(rt_inline_context($query)));exit;
