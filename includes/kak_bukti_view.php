<?php
require_once __DIR__.'/../config/auth.php';check_access([1,2,3]);
require_once __DIR__.'/kak_klaim.php';
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:0;
$db=Database::getInstance();
require_once __DIR__.'/kredit_akses.php';
if((int)$_SESSION['role_id']===2)kredit_access_require($db,(int)$_SESSION['user_id'],'aktivitas');
if(!kak_claim_ready($db)){http_response_code(404);exit('Bukti tidak ditemukan.');}
$q=$db->prepare('SELECT c.bukti_file,c.bukti_mime,a.dibuat_oleh,s.user_id FROM kak_klaim c JOIN kak_acara a ON a.id=c.acara_id JOIN siswa s ON s.id=c.siswa_id WHERE c.id=?');
$q->execute([$id]);$bukti=$q->fetch();$role=(int)$_SESSION['role_id'];$user=(int)$_SESSION['user_id'];
$allowed=$bukti&&($role===1||($role===2&&(int)$bukti['dibuat_oleh']===$user)||($role===3&&(int)$bukti['user_id']===$user));
if(!$allowed||!preg_match('/\A[a-f0-9]{48}\.(jpg|png|webp)\z/',$bukti['bukti_file'])){http_response_code(404);exit('Bukti tidak ditemukan.');}
$path=__DIR__.'/../storage/kak-klaim/'.$bukti['bukti_file'];
if(!is_file($path)){http_response_code(404);exit('Bukti tidak ditemukan.');}
header('Content-Type: '.$bukti['bukti_mime']);header('Content-Length: '.filesize($path));
header('Content-Disposition: inline; filename="bukti-aktivitas.'.pathinfo($path,PATHINFO_EXTENSION).'"');
header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');readfile($path);
