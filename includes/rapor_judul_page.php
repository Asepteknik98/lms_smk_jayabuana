<?php
require_once __DIR__.'/../config/auth.php';check_access([1,2]);
require_once __DIR__.'/rapor_judul.php';
$db=Database::getInstance();$ready=rapor_judul_ready($db);$error='';$input=$_SERVER['REQUEST_METHOD']==='POST'?$_POST:$_GET;
$classes=$db->query('SELECT id,nama_kelas,jurusan FROM kelas ORDER BY nama_kelas')->fetchAll();
$periods=$db->query('SELECT DISTINCT tahun_ajaran FROM pengajaran ORDER BY tahun_ajaran DESC')->fetchAll(PDO::FETCH_COLUMN);
$kelas=is_scalar($input['kelas_id']??null)?(int)$input['kelas_id']:0;
$tahun=is_string($input['tahun_ajaran']??null)?$input['tahun_ajaran']:($periods[0]??'');
$semester=($input['semester']??'')==='Genap'?'Genap':'Ganjil';$jenis=($input['jenis']??'')==='UAS'?'UAS':'UTS';$selected=null;
foreach($classes as $c)if((int)$c['id']===$kelas)$selected=$c;
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!is_string($_POST['csrf_token']??null)||!verify_csrf($_POST['csrf_token'])){http_response_code(403);$error='Sesi formulir tidak valid. Muat ulang halaman.';}
    elseif(!$ready)$error='Penyimpanan pengaturan belum tersedia.';
    else try{rapor_judul_save($db,$_POST,(int)$_SESSION['user_id']);$_SESSION['rapor_judul_success']='Pengaturan judul berhasil disimpan.';header('Location: rapor_pengaturan.php?'.http_build_query(['kelas_id'=>$kelas,'tahun_ajaran'=>$tahun,'semester'=>$semester,'jenis'=>$jenis]));exit;}
    catch(RuntimeException $e){$error=$e instanceof PDOException?'Pengaturan belum dapat disimpan. Silakan coba kembali.':$e->getMessage();if($e instanceof PDOException)error_log($e->getMessage());}
}
$values=$ready&&$selected?rapor_judul_load($db,$kelas,$tahun,$semester,$jenis):[];
if($error)foreach(['judul','penilaian','konsentrasi'] as $k)if(is_string($input[$k]??null))$values[$k]=$input[$k];
$defaults=['judul'=>'LAPORAN PENCAPAIAN KOMPETENSI PESERTA DIDIK','penilaian'=>'PENILAIAN SUMATIF '.($jenis==='UAS'?'AKHIR':'TENGAH').' SEMESTER','konsentrasi'=>$selected['jurusan']??''];
$back='rapor.php?'.http_build_query(['kelas_id'=>$kelas,'tahun_ajaran'=>$tahun,'semester'=>$semester,'jenis'=>$jenis]);
$success=$_SESSION['rapor_judul_success']??'';unset($_SESSION['rapor_judul_success']);
require __DIR__.'/header.php';require __DIR__.'/sidebar.php';
?>
<div id="page-content-wrapper"><nav class="navbar top-navbar px-4 py-3"><h5 class="mb-0">Pengaturan judul rapor</h5></nav><main class="container-fluid p-3 p-md-4" style="max-width:1000px">
<a class="btn btn-outline-secondary btn-sm mb-3" href="<?= sanitize($back) ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Kembali ke rapor</a>
<?php if($error): ?><div class="alert alert-danger" role="alert"><?= sanitize($error) ?></div><?php endif ?>
<?php if($success): ?><div class="alert alert-success" role="status"><?= sanitize($success) ?></div><?php endif ?>
<section class="card border-0 shadow-sm mb-3"><div class="card-body p-4"><h6>Pilih kelas dan periode</h6><p class="small text-muted">Pengaturan berlaku untuk seluruh siswa pada kelas dan periode yang dipilih, termasuk rapor yang sudah tersimpan.</p><form method="get" class="row g-3">
<div class="col-md-4"><label for="kelas" class="form-label">Kelas</label><select class="form-select" id="kelas" name="kelas_id" required><option value="">Pilih kelas</option><?php foreach($classes as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$c['id']===$kelas?'selected':'' ?>><?= sanitize($c['nama_kelas']) ?></option><?php endforeach ?></select></div>
<div class="col-md-3"><label class="form-label" for="tahun">Tahun ajaran</label><input class="form-control" name="tahun_ajaran" id="tahun" value="<?= sanitize($tahun) ?>" pattern="[0-9]{4}/[0-9]{4}" maxlength="10" placeholder="2026/2027" required></div>
<?php foreach(['semester'=>['Ganjil','Genap'],'jenis'=>['UTS','UAS']] as $k=>$opts): ?><div class="col-md-2"><label class="form-label" for="<?= $k ?>"><?= $k==='jenis'?'Jenis rapor':'Semester' ?></label><select class="form-select" id="<?= $k ?>" name="<?= $k ?>"><?php foreach($opts as $v): ?><option <?= ($k==='jenis'?$jenis:$semester)===$v?'selected':'' ?>><?= $v ?></option><?php endforeach ?></select></div><?php endforeach ?>
<div class="col-12"><button class="btn btn-outline-primary" type="submit">Buka pengaturan</button></div></form></div></section>
<?php if(!$ready): ?><p class="alert alert-warning">Penyimpanan pengaturan belum tersedia. Hubungi administrator.</p><?php elseif($selected): ?>
<section class="card border-0 shadow-sm"><div class="card-body p-4"><h6><i class="fa-solid fa-heading" aria-hidden="true"></i> Teks pada hasil cetak</h6><p class="small text-muted">Kosongkan isian untuk memakai teks bawaan. Perubahan hanya memengaruhi judul cetak, bukan nilai atau jurusan pada data kelas.</p><form method="post">
<?php foreach(['kelas_id'=>$kelas,'tahun_ajaran'=>$tahun,'semester'=>$semester,'jenis'=>$jenis,'csrf_token'=>$_SESSION['csrf_token']] as $k=>$v): ?><input type="hidden" name="<?= $k ?>" value="<?= sanitize((string)$v) ?>"><?php endforeach ?>
<?php foreach(['judul'=>['Judul laporan',160],'penilaian'=>['Judul penilaian',160],'konsentrasi'=>['Konsentrasi keahlian',120]] as $k=>[$label,$limit]): ?><div class="mb-3"><label class="form-label" for="setting-<?= $k ?>"><?= $label ?></label><input class="form-control" id="setting-<?= $k ?>" name="<?= $k ?>" maxlength="<?= $limit ?>" value="<?= sanitize($values[$k]??'') ?>" placeholder="<?= sanitize($defaults[$k]) ?>" data-preview="<?= $k ?>" data-default="<?= sanitize($defaults[$k]) ?>"></div><?php endforeach ?>
<div class="border rounded p-3 mb-3 text-center" style="font-size:.85rem;overflow-wrap:anywhere"><strong id="preview-judul"><?= sanitize(($values['judul']??'')?:$defaults['judul']) ?></strong><br><strong id="preview-penilaian"><?= sanitize(($values['penilaian']??'')?:$defaults['penilaian']) ?></strong><p class="mt-2 mb-0 fw-bold">KONSENTRASI KEAHLIAN: <span id="preview-konsentrasi"><?= sanitize(($values['konsentrasi']??'')?:$defaults['konsentrasi']) ?></span></p></div>
<button class="btn btn-primary" type="submit"><i class="fa-regular fa-floppy-disk" aria-hidden="true"></i> Simpan pengaturan</button></form></div></section>
<?php endif ?></main></div>
<script>document.querySelectorAll('[data-preview]').forEach(input=>input.addEventListener('input',()=>{document.getElementById('preview-'+input.dataset.preview).textContent=input.value.trim()||input.dataset.default;}));</script>
<?php require __DIR__.'/footer.php'; ?>
