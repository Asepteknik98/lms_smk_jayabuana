<?php
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../config/auth.php';
require_once __DIR__.'/../config/database.php';
check_access([2,3]);$db=Database::getInstance();
$exam=(int)($_GET['ujian_id']??0);$sid=(int)($_GET['siswa_id']??0);
$q=$db->prepare('SELECT b.nama_file,g.user_id guru_user,s.user_id siswa_user FROM ujian_harian_berkas b JOIN ujian u ON u.id=b.ujian_id JOIN pengajaran p ON p.id=u.pengajaran_id JOIN guru g ON g.id=p.guru_id JOIN siswa s ON s.id=b.siswa_id WHERE b.ujian_id=? AND b.siswa_id=?');$q->execute([$exam,$sid]);$row=$q->fetch();
if(!$row||!in_array((int)$_SESSION['user_id'],[(int)$row['guru_user'],(int)$row['siswa_user']],true)){http_response_code(404);exit('Berkas tidak ditemukan.');}
$name=$row['nama_file'];$path=__DIR__.'/../storage/ujian/'.basename($name);
if(!preg_match('/^[a-f0-9]{40}\.(pdf|jpg|jpeg|png|docx)$/D',$name)||!is_file($path)){http_response_code(404);exit('Berkas tidak ditemukan.');}
header('Content-Type: application/octet-stream');header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');header('Content-Disposition: attachment; filename="jawaban-uh-'.$exam.'-'.$sid.'.'.pathinfo($name,PATHINFO_EXTENSION).'"');header('Content-Length: '.filesize($path));readfile($path);
