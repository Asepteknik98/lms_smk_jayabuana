<?php
require_once __DIR__.'/../config/auth.php';
check_access([1,2]);
require_once __DIR__.'/rapor_print_data.php';
require_once __DIR__.'/rapor_print_view.php';
require_once __DIR__.'/rapor_judul.php';
$db=Database::getInstance();$reports=[];$missing=[];$error='';$warnings=[];
$batch=($_GET['mode']??'')==='kelas';$back='rapor.php';$className='';
if(!rapor_ready($db))$error='Penyimpanan rapor belum tersedia. Hubungi administrator.';
elseif($batch){
    try {
        $kelas=is_string($_GET['kelas_id']??null)&&ctype_digit($_GET['kelas_id'])?(int)$_GET['kelas_id']:0;
        $tahun=is_string($_GET['tahun_ajaran']??null)?$_GET['tahun_ajaran']:'';
        $semester=is_string($_GET['semester']??null)?$_GET['semester']:'';
        $jenis=is_string($_GET['jenis']??null)?$_GET['jenis']:'';
        $result=rapor_print_batch($db,$kelas,$tahun,$semester,$jenis);$reports=$result['reports'];$missing=$result['missing'];
        $back.='?'.http_build_query(['kelas_id'=>$kelas,'tahun_ajaran'=>$tahun,'semester'=>$semester,'jenis'=>$jenis]);
        if(!$reports)$error='Belum ada rapor tersimpan untuk kelas dan periode ini.';
        else $className=$reports[0]['nama_kelas'];
    }catch(InvalidArgumentException $e){http_response_code(400);$error=$e->getMessage();}
    catch(Throwable $e){error_log('Cetak kelas rapor: '.$e->getMessage());$error='Rapor kelas belum dapat dimuat. Silakan coba kembali.';}
}elseif(!is_string($_GET['id']??null)||!ctype_digit($_GET['id'])){http_response_code(400);$error='Pilih rapor tersimpan untuk membuka pratinjau.';}
else{try{$r=rapor_print_single($db,(int)$_GET['id']);if(!$r){http_response_code(404);$error='Rapor tidak ditemukan.';}else{$reports[]=$r;$back.='?id='.(int)$r['id'];}}catch(Throwable $e){error_log('Cetak siswa rapor: '.$e->getMessage());$error='Rapor siswa belum dapat dimuat. Silakan coba kembali.';}}
$titleSettingsReady=rapor_judul_ready($db);$titleCache=[];
foreach($reports as &$titleReport){
    $context=[(int)$titleReport['kelas_id'],$titleReport['tahun_ajaran'],$titleReport['semester'],$titleReport['jenis']];$key=json_encode($context);
    if($titleSettingsReady&&!isset($titleCache[$key]))$titleCache[$key]=rapor_judul_load($db,...$context);
    $titleReport['judul_cetak']=$titleCache[$key]??[];
}unset($titleReport);
$incomplete=[];$noNumber=[];$numbers=[];$duplicates=[];
foreach($reports as $r){
    if(rapor_print_incomplete($r))$incomplete[]=$r['nama_siswa'];
    if($r['nomor_absen']===null)$noNumber[]=$r['nama_siswa'];
    elseif(isset($numbers[$r['nomor_absen']]))$duplicates[$r['nomor_absen']]=true;
    else $numbers[$r['nomor_absen']]=true;
}
if($incomplete)$warnings[]='Nilai/deskripsi/saran belum lengkap: '.implode(', ',$incomplete).'.';
if(array_filter($reports,static fn(array $report):bool=>!empty($report['peringkat']['sementara'])))$warnings[]='Peringkat belum final: masih ada nilai/rapor yang belum lengkap atau susunan mapel yang perlu diperiksa. Peringkat kosong tidak dianggap nol.';
if($batch&&$missing)$warnings[]='Belum memiliki rapor tersimpan dan tidak ikut dicetak: '.implode(', ',$missing).'. Daftar ini mengikuti anggota kelas saat ini.';
if($batch&&$noNumber)$warnings[]='Nomor absen belum diisi: '.implode(', ',$noNumber).'. Ditempatkan setelah siswa bernomor absen, menurut nama.';
if($batch&&$duplicates)$warnings[]='Nomor absen ganda: '.implode(', ',array_keys($duplicates)).'. Siswa dengan nomor sama diurutkan menurut nama.';
?>
<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Rapor<?= $reports?' - '.rapor_print_escape($batch?$className:$reports[0]['nama_siswa']):'' ?></title><link rel="stylesheet" href="../assets/css/rapor-print.css?v=7"></head>
<body>
<nav class="print-toolbar"><a href="<?= rapor_print_escape($back) ?>">Kembali ke rapor</a><div><strong><?= $batch?'Cetak satu kelas — '.rapor_print_escape($className):'Cetak per siswa' ?></strong><p><?= count($reports) ?> siswa · <?= count($reports)*2 ?> halaman F4. Pilih F4 210 × 330 mm, skala 100%, dan nonaktifkan header/footer browser.</p></div><button type="button" id="print-button" disabled>Cetak / Simpan PDF</button></nav>
<?php foreach($warnings as $warning): ?><p class="notice" role="status"><?= rapor_print_escape($warning) ?></p><?php endforeach ?>
<?php if($error): ?><p class="notice error" role="alert"><?= rapor_print_escape($error) ?></p><?php else: ?>
<p class="notice" id="layout-status" role="status">Menyiapkan pembagian dua halaman per siswa…</p>
<noscript><p class="notice error">Aktifkan JavaScript untuk menyesuaikan pembagian halaman dan mencetak.</p></noscript>
<?php foreach($reports as $r)rapor_print_render($r); ?>
<script src="../assets/js/rapor-print.js?v=5" defer></script>
<?php endif ?>
<p class="print-failure">Rapor belum siap dicetak. Buka pratinjau, aktifkan JavaScript, dan periksa pembagian halaman terlebih dahulu.</p>
</body></html>
