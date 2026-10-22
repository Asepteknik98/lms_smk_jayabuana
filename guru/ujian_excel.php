<?php
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../config/auth.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/helper.php';
require_once __DIR__.'/../includes/ujian_excel.php';
require_once __DIR__.'/../includes/ujian_harian.php';
check_access([2]);$db=Database::getInstance();$action=$_GET['action']??'';
if($action==='template'){
    if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);header('Allow: GET');exit('Gunakan unduhan GET.');}
    $kind=$_GET['jenis']??'campuran';
    if(!is_string($kind)||!in_array($kind,['campuran','pg','esai'],true)){http_response_code(400);exit('Jenis template tidak valid.');}
    $file=ue_template($kind);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="Template_Soal_UH_'.$kind.'.xlsx"');
    header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Content-Length: '.strlen($file));echo $file;exit;
}
if($action!=='import'||$_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Gunakan formulir impor soal.');}
if(!is_string($_POST['csrf_token']??null)||!verify_csrf($_POST['csrf_token'])){http_response_code(403);exit('Permintaan tidak sah. Muat ulang halaman.');}
try{
    $upload=$_FILES['file_excel']??[];
    if(($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($upload['tmp_name']??''))throw new RuntimeException('File Excel gagal diunggah.');
    if(($upload['size']??0)>5*1024*1024||!in_array(strtolower(pathinfo($upload['name']??'',PATHINFO_EXTENSION)),['xlsx','xls'],true))throw new RuntimeException('Gunakan file .xlsx atau .xls maksimal 5 MB.');
    $book=ni_read_excel($upload['tmp_name']);$questions=ue_parse($book['rows']);$exam=(int)($_POST['ujian_id']??0);
    $db->beginTransaction();
    $q=$db->prepare('SELECT u.id,u.pengajaran_id,u.nama_ujian FROM ujian u JOIN pengajaran p ON p.id=u.pengajaran_id JOIN guru g ON g.id=p.guru_id WHERE u.id=? AND g.user_id=? FOR UPDATE');$q->execute([$exam,$_SESSION['user_id']]);$info=$q->fetch();
    if(!$info)throw new RuntimeException('Ulangan tidak ditemukan atau bukan milik Anda.');
    if(uh_settings($db,$exam,(int)$info['pengajaran_id'])['metode']!=='cbt')throw new RuntimeException('Impor soal hanya untuk metode CBT. Untuk berkas, isi soal/instruksi pada formulir ulangan.');
    $q=$db->prepare('SELECT id FROM sesi_ujian WHERE ujian_id=? LIMIT 1 FOR UPDATE');$q->execute([$exam]);if($q->fetchColumn())throw new RuntimeException('Soal dikunci karena ulangan sudah memiliki peserta.');
    $q=$db->prepare('SELECT tipe_soal,pertanyaan,opsi_a,opsi_b,opsi_c,opsi_d,opsi_e,kunci_jawaban,bobot FROM soal_ujian WHERE ujian_id=?');$q->execute([$exam]);$known=[];
    $fingerprint=static fn($row)=>hash('sha256',json_encode(array_map(static fn($v)=>$v===null?'':(string)$v,$row),JSON_UNESCAPED_UNICODE));
    foreach($q->fetchAll(PDO::FETCH_NUM) as $row)$known[$fingerprint($row)]=true;
    $insert=$db->prepare('INSERT INTO soal_ujian(ujian_id,tipe_soal,pertanyaan,opsi_a,opsi_b,opsi_c,opsi_d,opsi_e,kunci_jawaban,bobot) VALUES(?,?,?,?,?,?,?,?,?,?)');$count=0;$skipped=0;
    foreach($questions as $row){$key=$fingerprint($row);if(isset($known[$key])){$skipped++;continue;}$insert->execute([$exam,...$row]);$known[$key]=true;$count++;}
    catat_log($_SESSION['user_id'],'Impor soal UH #'.$exam.': '.$count.' ditambah, '.$skipped.' duplikat dilewati');$db->commit();
    $_SESSION['flash_success']=$count.' soal berhasil diimpor.'.($skipped?' '.$skipped.' soal identik dilewati agar tidak ganda.':'');
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('Impor soal UH: '.$e->getMessage());$_SESSION['flash_error']='Impor dibatalkan. '.($e instanceof PDOException?'Penyimpanan gagal. Silakan coba kembali.':$e->getMessage());}
redirect('ujian.php'.(isset($exam)&&$exam>0?'?soal='.$exam:''));
