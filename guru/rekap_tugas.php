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
if(in_array($_GET['format']??'',['csv','template'],true)){
    $view=rt_inline_prepare($db,$guru,$query);
    if($_GET['format']==='csv')rt_inline_export($view);
    try{$xml=rt_import_template($view);}catch(RuntimeException $e){http_response_code(400);exit($e->getMessage());}
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="Template_Nilai_Tugas_'.$view['pid'].'.xls"');
    header('Cache-Control: no-store');echo $xml;exit;
}
header('Location: '.rt_inline_url(rt_inline_context($query)));exit;
