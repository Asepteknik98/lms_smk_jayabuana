<?php
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../config/auth.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/helper.php';
require_once __DIR__.'/../includes/nilai_import.php';
check_access([2]);$db=Database::getInstance();
$stmt=$db->prepare('SELECT id FROM guru WHERE user_id=?');$stmt->execute([$_SESSION['user_id']]);$guru=(int)$stmt->fetchColumn();
$pid=(int)($_POST['pengajaran_id']??$_GET['pengajaran_id']??0);
$stmt=$db->prepare('SELECT p.id,p.kelas_id,p.semester,p.tahun_ajaran,k.nama_kelas,m.nama_mapel FROM pengajaran p JOIN kelas k ON k.id=p.kelas_id JOIN mapel m ON m.id=p.mapel_id WHERE p.id=? AND p.guru_id=?');$stmt->execute([$pid,$guru]);$info=$stmt->fetch();
if(!$info){http_response_code(403);exit('Pengajaran tidak dapat diakses.');}
$mode=($_POST['mode']??$_GET['mode']??'import')==='capaian'?'capaian':'import';
$stmt=$db->prepare('SELECT id,nama_komponen FROM komponen_penilaian WHERE pengajaran_id=? ORDER BY urutan,id');$stmt->execute([$pid]);
$components=array_values(array_filter($stmt->fetchAll(),static fn($c)=>in_array($c['nama_komponen'],$mode==='capaian'?['UTS','UAS']:ni_allowed(),true)));
$error='';$preview=null;$token='';$description='';$advice='';$chosen=(int)($_POST['komponen_id']??($components[0]['id']??0));
$replace=($_POST['narrative_mode']??'fill')==='replace';
if(!isset($_SESSION['nilai_import_previews'])||!is_array($_SESSION['nilai_import_previews']))$_SESSION['nilai_import_previews']=[];
foreach($_SESSION['nilai_import_previews'] as $key=>$item)if(($item['expires']??0)<time())unset($_SESSION['nilai_import_previews'][$key]);
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!is_string($_POST['csrf_token']??null)||!verify_csrf($_POST['csrf_token'])){http_response_code(403);exit('Permintaan tidak sah. Muat ulang halaman.');}
    try{
        $action=$_POST['action']??'';
        if($action==='template'){
            $context=ni_context($db,$guru,$pid,$chosen);
            require_once __DIR__.'/../includes/nilai_template.php';
            $content=ni_template_xlsx($context);
            $filename='Template_'.preg_replace('/[^A-Za-z0-9_-]/','_',$context['component']['nama_komponen'].'_'.$context['nama_kelas']).'.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="'.$filename.'"');
            header('Cache-Control: no-store');header('Content-Length: '.strlen($content));echo $content;exit;
        }elseif($action==='confirm'){
            $key=is_string($_POST['preview_token']??null)?$_POST['preview_token']:'';
            $item=$_SESSION['nilai_import_previews'][$key]??null;
            if(!$item||$item['user']!==(int)$_SESSION['user_id']||$item['guru']!==$guru||$item['pid']!==$pid||$item['mode']!==$mode||$item['expires']<time())throw new RuntimeException('Pratinjau sudah kedaluwarsa atau tidak sesuai. Buat pratinjau kembali.');
            $context=ni_context($db,$guru,$pid,$item['component']);
            if($context['component']['nama_komponen']!==$item['component_name']||$context['kelas_id']!=$item['kelas_id']||$context['semester']!==$item['semester']||$context['tahun_ajaran']!==$item['tahun_ajaran'])throw new RuntimeException('Pengajaran berubah sejak pratinjau. Buat pratinjau ulang.');
            if($mode==='import'){
                $result=ni_commit($db,$guru,$pid,$item['component'],$item['candidates'],$item);
                $_SESSION['flash_success']='Impor selesai: '.$result['inserted'].' nilai baru dan '.$result['fields'].' kolom capaian kosong disimpan. '.$result['skipped'].' kandidat dilewati karena data sudah terisi atau berubah. Nilai dan teks lama tetap dipertahankan.';
            }else{
                $result=ni_narrative_commit($db,$guru,$pid,$item['component'],$item['description'],$item['advice'],$item['candidates'],$item,!empty($item['replace']));
                $_SESSION['flash_success']='Capaian kelas: '.$result['fields'].' kolom '.(!empty($item['replace'])?'diperbarui':'kosong dilengkapi').' pada '.$result['students'].' siswa. Angka nilai tidak berubah.';
            }
            unset($_SESSION['nilai_import_previews'][$key]);redirect('rekap_nilai.php?pengajaran_id='.$pid);
        }elseif($action==='preview'){
            $context=ni_context($db,$guru,$pid,$chosen);
            $preview=['user'=>(int)$_SESSION['user_id'],'guru'=>$guru,'pid'=>$pid,'mode'=>$mode,'component'=>$chosen,'component_name'=>$context['component']['nama_komponen'],'kelas_id'=>$context['kelas_id'],'semester'=>$context['semester'],'tahun_ajaran'=>$context['tahun_ajaran'],'expires'=>time()+1800];
            if($mode==='import'){
                $upload=$_FILES['excel']??null;
                if(!is_array($upload)||($upload['error']??null)!==UPLOAD_ERR_OK||!is_string($upload['tmp_name']??null)||!is_string($upload['name']??null)||!is_uploaded_file($upload['tmp_name']))throw new RuntimeException('Unggah file Excel yang valid, maksimal 5 MB (juga mengikuti batas upload hosting).');
                $ext=strtolower(pathinfo($upload['name'],PATHINFO_EXTENSION));
                if(!in_array($ext,['xlsx','xls'],true))throw new RuntimeException('Gunakan file .xlsx atau .xls.');
                $sheet=filter_var($_POST['sheet']??1,FILTER_VALIDATE_INT);
                if($sheet===false||$sheet<1||$sheet>30)throw new RuntimeException('Nomor sheet harus 1 sampai 30.');
                require_once __DIR__.'/../includes/nilai_excel_reader.php';
                $book=ni_read_excel($upload['tmp_name'],$sheet-1);
                $preview['rows']=ni_import_preview($db,$context,ni_excel_records($book['rows']));
                $preview['sheet']=$book['sheet'];$preview['file']=mb_substr(basename($upload['name']),0,200);
                $preview['candidates']=array_values(array_filter($preview['rows'],static fn($r)=>$r['ready']));
            }else{
                $description=ni_narrative_text($_POST['deskripsi']??'');$advice=ni_narrative_text($_POST['saran']??'');
                if($description===''&&$advice==='')throw new RuntimeException('Isi deskripsi atau saran terlebih dahulu.');
                $preview['replace']=$replace;
                $preview['rows']=ni_narrative_preview($db,$context,$description,$advice,$replace);
                $preview['description']=$description;$preview['advice']=$advice;
                $preview['candidates']=array_values(array_filter($preview['rows'],static fn($r)=>$r['description']||$r['advice']));
            }
            $token=bin2hex(random_bytes(24));
            while(count($_SESSION['nilai_import_previews'])>=3)array_shift($_SESSION['nilai_import_previews']);
            // Keep only the confirmed candidates server-side, never accept grades from hidden POST fields.
            $stored=$preview;unset($stored['rows']);$_SESSION['nilai_import_previews'][$token]=$stored;
        }else throw new RuntimeException('Aksi tidak valid.');
    }catch(Throwable $e){
        $preview=null;
        if($e instanceof RuntimeException && !$e instanceof PDOException)$error=$e->getMessage();
        else{error_log('Rekap import: '.$e->getMessage());$error='Pemrosesan gagal. Tidak ada perubahan yang disimpan. Periksa file atau hubungi administrator.';}
    }
}
require_once __DIR__.'/../includes/header.php';require_once __DIR__.'/../includes/sidebar.php';
?>
<div id="page-content-wrapper" class="bg-light"><nav class="navbar top-navbar px-3 px-md-4 py-3"><h5 class="mb-0"><?= $mode==='import'?'Import Nilai Excel':'Capaian Satu Kelas' ?></h5></nav>
<main class="container-fluid p-3 p-md-4" style="max-width:1400px">
<a class="btn btn-outline-secondary mb-3" href="rekap_nilai.php?pengajaran_id=<?= $pid ?>">Kembali ke Rekap Nilai</a>
<div class="card mb-3"><div class="card-body"><h6><?= sanitize($info['nama_mapel'].' - '.$info['nama_kelas']) ?></h6><p class="text-muted mb-2"><?= sanitize($info['semester'].' / '.$info['tahun_ajaran']) ?></p>
<div class="d-flex flex-wrap gap-2"><a class="btn btn-sm <?= $mode==='import'?'btn-primary':'btn-outline-primary' ?>" href="?pengajaran_id=<?= $pid ?>&amp;mode=import">Import Excel</a><a class="btn btn-sm <?= $mode==='capaian'?'btn-primary':'btn-outline-primary' ?>" href="?pengajaran_id=<?= $pid ?>&amp;mode=capaian">Capaian Satu Kelas</a></div></div></div>
<?php if($error): ?><div class="alert alert-danger" role="alert"><?= sanitize($error) ?></div><?php endif ?>
<?php if($preview): ?>
<section class="card"><div class="card-body"><h6>Pratinjau <?= sanitize($preview['component_name']) ?></h6>
<?php if($mode==='import'): ?><p class="text-muted"><?= sanitize($preview['file'].' - '.$preview['sheet']) ?></p><?php endif ?>
<div class="alert <?= !empty($preview['replace'])?'alert-warning':'alert-info' ?>"><strong><?= count($preview['candidates']) ?> siswa</strong> memiliki isian yang akan disimpan. <?= !empty($preview['replace'])?'Mode Perbarui satu kelas: teks yang Anda isi akan mengganti teks lama, termasuk teks khusus per siswa, sesuai pratinjau. Angka nilai tetap sama.':'Hanya bagian kosong yang dilengkapi. Nilai lama termasuk 0 dan teks yang sudah ada tetap dipertahankan.' ?></div>
<?php if($mode==='capaian'): ?><div class="row g-3 mb-3"><div class="col-md-6"><strong>Deskripsi yang diterapkan</strong><p style="white-space:pre-wrap"><?= sanitize($description?:'(Tidak diisi)') ?></p></div><div class="col-md-6"><strong>Saran yang diterapkan</strong><p style="white-space:pre-wrap"><?= sanitize($advice?:'(Tidak diisi)') ?></p></div></div><?php endif ?>
<div class="table-responsive" style="max-height:60vh"><table class="table table-bordered table-sm align-middle"><thead class="table-light" style="position:sticky;top:0"><tr>
<?php if($mode==='import'): ?><th>Baris Excel</th><th>Nama di Excel</th><th>Kelas di Excel</th><th>Sumber</th><th>Nilai</th><th>Deskripsi</th><th>Saran</th><th>Hasil pencocokan</th><?php else: ?><th>Nama Siswa</th><th>Deskripsi: lama → baru</th><th>Saran: lama → baru</th><?php endif ?></tr></thead><tbody>
<?php foreach($preview['rows'] as $row): ?><tr>
<?php if($mode==='import'): ?><td><?= (int)$row['row'] ?></td><td><?= sanitize($row['name']) ?></td><td><?= sanitize($row['class']) ?></td><td><?= sanitize($row['source']) ?></td><td><?= sanitize($row['raw']) ?></td>
<?php foreach(['description','advice'] as $field): ?><td style="min-width:180px;white-space:pre-wrap"><?= !empty($row[$field])?sanitize($row[$field.'_text']):'Dipertahankan / tidak diisi' ?></td><?php endforeach ?>
<td class="<?= $row['ready']?'text-success':'text-muted' ?>"><?= sanitize($row['status']) ?></td>
<?php else: ?><td><?= sanitize($row['name']) ?></td><?php foreach(['description'=>$description,'advice'=>$advice] as $field=>$newText): ?><td style="min-width:240px;white-space:pre-wrap"><div class="text-muted"><?= sanitize($row['old_'.$field]!==''?$row['old_'.$field]:'(Kosong)') ?></div><?php if($row[$field]): ?><div class="text-success">→ <?= sanitize($newText) ?></div><?php else: ?><small>Dipertahankan</small><?php endif ?></td><?php endforeach ?><?php endif ?></tr><?php endforeach ?>
</tbody></table></div>
<form method="post" class="mt-3"><input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="pengajaran_id" value="<?= $pid ?>"><input type="hidden" name="mode" value="<?= $mode ?>"><input type="hidden" name="action" value="confirm"><input type="hidden" name="preview_token" value="<?= sanitize($token) ?>">
<button class="btn btn-primary" <?= !$preview['candidates']?'disabled':'' ?>><?= !empty($preview['replace'])?'Konfirmasi Pembaruan Capaian':'Konfirmasi dan Simpan yang Masih Kosong' ?></button><a class="btn btn-light" href="?pengajaran_id=<?= $pid ?>&amp;mode=<?= $mode ?>">Batal / Ulangi</a></form>
</div></section>
<?php else: ?>
<section class="card"><div class="card-body">
<?php if(!$components): ?><div class="alert alert-warning mb-0">Tambahkan komponen penilaian yang sesuai di Rekap Nilai terlebih dahulu.</div><?php else: ?>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="pengajaran_id" value="<?= $pid ?>"><input type="hidden" name="mode" value="<?= $mode ?>">
<div class="mb-3"><label for="importComponent" class="form-label fw-semibold">Komponen tujuan</label><select id="importComponent" name="komponen_id" class="form-select" required><?php foreach($components as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $chosen===(int)$c['id']?'selected':'' ?>><?= sanitize($c['nama_komponen']) ?></option><?php endforeach ?></select></div>
<?php if($mode==='import'): ?>
<button type="submit" name="action" value="template" formnovalidate class="btn btn-outline-success mb-3">Unduh Template Excel</button>
<p>Template mengikuti komponen tujuan yang dipilih, dengan <strong>Nama Siswa dan Kelas</strong> otomatis terisi. Isi kolom <strong>Nilai</strong>. Khusus UTS/UAS, tersedia kolom <strong>Deskripsi Capaian Pembelajaran</strong> dan <strong>Saran Capaian Pembelajaran</strong> per siswa; teks lama tetap dipertahankan saat impor.</p>
<p class="small text-muted">Format lama dengan kolom Asli dan opsional Penyesuaian tetap diterima. Penyesuaian dipakai jika terisi; jika kosong, Asli dipakai.</p>
<p class="text-muted">Pencocokan memakai nama dan kelas, mengabaikan kapital dan spasi berlebih. Nama ganda/tidak cocok dilewati. Nilai yang sudah tersimpan, termasuk 0, dipertahankan. Kehadiran tidak diimpor. Tugas/Ulangan Harian yang diimpor menjadi nilai manual pengganti; data kegiatan asal tetap utuh.</p>
<div class="row g-3 mb-3"><div class="col-md-9"><label for="importFile" class="form-label">File Excel (.xlsx / .xls, maksimal 5 MB)</label><input id="importFile" class="form-control" type="file" name="excel" accept=".xlsx,.xls" required></div><div class="col-md-3"><label for="importSheet" class="form-label">Sheet ke</label><input id="importSheet" class="form-control" type="number" name="sheet" min="1" max="30" value="1" required><small class="text-muted">1 = sheet paling kiri.</small></div></div>
<?php else: ?>
<div class="mb-3"><label for="narrativeMode" class="form-label fw-semibold">Cara menyimpan capaian</label><select id="narrativeMode" name="narrative_mode" class="form-select"><option value="fill" <?= !$replace?'selected':'' ?>>Isi yang kosong — pertahankan teks lama</option><option value="replace" <?= $replace?'selected':'' ?>>Perbarui satu kelas — ganti teks lama</option></select></div>
<p>Isi sekali untuk kelas, mapel, dan periode yang dipilih. <strong>Perbarui satu kelas</strong> mengganti teks lama, termasuk teks khusus per siswa. Deskripsi atau saran yang Anda biarkan kosong tidak akan menghapus teks lama. Angka nilai siswa tetap sama. Periksa perubahan pada pratinjau sebelum menyimpan.</p>
<div class="row g-3 mb-3"><div class="col-md-6"><label class="form-label" for="classDescription">Deskripsi Capaian Pembelajaran</label><textarea class="form-control" id="classDescription" name="deskripsi" rows="5" maxlength="2000"><?= sanitize($description) ?></textarea></div><div class="col-md-6"><label class="form-label" for="classAdvice">Saran Capaian Pembelajaran</label><textarea class="form-control" id="classAdvice" name="saran" rows="5" maxlength="2000"><?= sanitize($advice) ?></textarea></div></div>
<?php endif ?>
<button type="submit" name="action" value="preview" class="btn btn-primary">Periksa Pratinjau</button><small class="text-muted ms-2">Belum menyimpan perubahan.</small>
</form><?php endif ?></div></section><?php endif ?>
</main></div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
