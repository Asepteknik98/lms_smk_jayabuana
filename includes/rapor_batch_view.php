<?php
// Template context supplied by rapor_page.php before this file is included.
/** @var PDO $db */
/** @var int $kelas */
/** @var string $tahun */
/** @var string $semester */
/** @var string $jenis */
/** @var array|null $posted */
/** @var array $students */
/** @var array $savedByStudent */
require_once __DIR__.'/rapor_ekstrakurikuler.php';
require_once __DIR__.'/rapor_tools.php';
$q=$db->prepare('SELECT p.id,m.nama_mapel,g.nama_lengkap nama_guru FROM pengajaran p JOIN mapel m ON m.id=p.mapel_id JOIN guru g ON g.id=p.guru_id WHERE p.kelas_id=? AND p.tahun_ajaran=? AND p.semester=? ORDER BY m.nama_mapel,g.nama_lengkap');$q->execute([$kelas,$tahun,$semester]);$batchSubjects=$q->fetchAll();
$batchContext=['csrf_token'=>$_SESSION['csrf_token'],'kelas_id'=>$kelas,'tahun_ajaran'=>$tahun,'semester'=>$semester,'jenis'=>$jenis];
$adminDefaults=rapor_admin_load($db,$kelas,$tahun,$semester,$jenis)??[];
$batchValue=static fn($key)=>is_string($posted[$key]??null)?$posted[$key]:(string)($adminDefaults[$key]??'');

$q=$db->prepare('SELECT DISTINCT e.nama_kegiatan FROM rapor_ekstrakurikuler e JOIN rapor r ON r.id=e.rapor_id WHERE r.kelas_id=? AND r.tahun_ajaran=? AND r.semester=? AND r.jenis=? ORDER BY e.nama_kegiatan');$q->execute([$kelas,$tahun,$semester,$jenis]);
$batchExtraNames=rapor_ekstra_names($q->fetchAll());
if(($posted['batch_action']??'')==='extras'&&is_string($posted['extras_payload']??null)){
    $retryRows=json_decode($posted['extras_payload'],true);
    if(is_array($retryRows))foreach($retryRows as $retryRow)if(is_array($retryRow)&&is_array($retryRow['grades']??null))foreach(array_keys($retryRow['grades']) as $retryName)if(is_string($retryName)&&mb_strlen($retryName)<=150&&!in_array($retryName,$batchExtraNames,true))$batchExtraNames[]=$retryName;
}
$batchExtraNames=rapor_ekstra_names(array_map(static fn($name)=>['nama_kegiatan'=>$name],$batchExtraNames));
?>

<section class="card mb-4 rapor-batch" id="rapor-prepare"><div class="card-body">
<div class="rapor-section-title"><span class="rapor-section-number"><i class="fa-solid fa-file-pen" aria-hidden="true"></i></span><div><h2>Siapkan rapor kelas</h2><p>Pilih mata pelajaran dan isi data yang berlaku untuk seluruh kelas.</p></div></div>
<p class="rapor-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Nilai diambil dari rekap setiap siswa. Rapor yang sudah ada tetap dipertahankan.</p>
<form method="post">
<?php foreach($batchContext as $key=>$value): ?><input type="hidden" name="<?= $key ?>" value="<?= sanitize((string)$value) ?>"><?php endforeach ?>
<input type="hidden" name="batch_action" value="prepare">
<fieldset class="mb-3"><legend class="h5">Mata pelajaran dan guru</legend>
<?php if(!$batchSubjects): ?><p class="text-warning">Belum ada pengajaran pada kelas dan periode ini.</p><?php endif ?>
<div class="row g-2 rapor-subject-grid"><?php foreach($batchSubjects as $subject): ?><div class="col-md-6 col-xl-4"><label class="rapor-subject-option"><input class="form-check-input m-0 flex-shrink-0" type="checkbox" name="pengajaran[]" value="<?= (int)$subject['id'] ?>" <?= in_array((string)$subject['id'],is_array($posted['pengajaran']??null)?$posted['pengajaran']:[],true)?'checked':'' ?>><span><strong><?= sanitize($subject['nama_mapel']) ?></strong><br><?= sanitize($subject['nama_guru']) ?></span></label></div><?php endforeach ?></div>
<p class="small text-muted mt-2">Jika satu mapel memiliki beberapa guru, pilih pengajaran yang memang perlu dicantumkan pada rapor.</p></fieldset>
<h3 class="rapor-subheading">Data bersama</h3><div class="row g-3">
<?php foreach(['nama_wali_kelas'=>'Nama wali kelas','nama_kepala_sekolah'=>'Nama kepala sekolah','tempat_terbit'=>'Tempat penerbitan','tanggal_terbit'=>'Tanggal penerbitan'] as $key=>$label): ?>
<div class="col-md-6 col-xl-3"><label class="form-label" for="batch-<?= $key ?>"><?= $label ?></label><input class="form-control form-control-lg" id="batch-<?= $key ?>" name="<?= $key ?>" type="<?= $key==='tanggal_terbit'?'date':'text' ?>" maxlength="100" value="<?= sanitize($batchValue($key)) ?>"></div><?php endforeach ?>
</div><p class="text-muted mt-3">Data bersama diterapkan ke draf baru. Absensi awal 0 hari; periksa pada tabel di bawah. Isian yang belum lengkap tetap bisa disimpan sebagai draf.</p>
<input type="hidden" name="batch_complete" value="1"><button class="btn btn-primary btn-lg" <?= !$students||!$batchSubjects?'disabled':'' ?>>Siapkan rapor seluruh kelas</button>
</form></div></section>
<?php $editableStudents=array_filter($students,static fn($s)=>isset($savedByStudent[(int)$s['id']])); if($editableStudents): ?>
<section class="card mb-4 rapor-batch" id="rapor-review"><div class="card-body"><div class="rapor-section-title"><span class="rapor-section-number"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span><div><h2>Periksa isian siswa</h2><p>Isi kehadiran per siswa dan tulis catatan wali kelas sekali untuk seluruh kelas.</p></div></div>
<p class="rapor-help">Isi jumlah hari pada tabel. Setelah selesai, gunakan tombol di bawah untuk menyimpan dan melanjutkan.</p>
<form method="post" class="rapor-batch-edit" data-rapor-filter>
<?php rapor_filter_toolbar('rapor-attendance-search','Cari siswa pada tabel kehadiran',false); ?>
<?php foreach($batchContext as $key=>$value): ?><input type="hidden" name="<?= $key ?>" value="<?= sanitize((string)$value) ?>"><?php endforeach ?>
<input type="hidden" name="batch_action" value="attendance">
<p class="rapor-table-hint">Geser tabel ke samping jika seluruh kolom belum terlihat. Absensi diisi dalam jumlah hari.</p><div class="table-responsive rapor-class-table"><table class="table align-middle"><thead><tr><th>Siswa</th><th>Sakit</th><th>Izin</th><th>Alfa</th></tr></thead><tbody>
<?php $attendanceReports=[];foreach($editableStudents as $student): $loaded=rapor_load($db,(int)$savedByStudent[(int)$student['id']]['id']);$r=$loaded;$attendanceReports[(int)$student['id']]=$loaded;$prefix='students['.(int)$student['id'].']';$postedRow=is_array($posted['students'][$student['id']]??null)?$posted['students'][$student['id']]:[]; ?>
<tr data-filter-row data-filter-search-value="<?= sanitize($student['nama_lengkap']) ?>"><th scope="row"><?= sanitize($student['nama_lengkap']) ?><input type="hidden" name="<?= $prefix ?>[id]" value="<?= (int)$r['id'] ?>"><input type="hidden" name="<?= $prefix ?>[version]" value="<?= sanitize(is_string($postedRow['version']??null)?$postedRow['version']:rapor_version($loaded)) ?>"></th>
<?php foreach(['sakit'=>'Sakit','izin'=>'Izin','alfa'=>'Alfa'] as $key=>$label): ?><td><input class="form-control"  type="number" min="0" max="65535" step="1" name="<?= $prefix ?>[<?= $key ?>]" aria-label="<?= sanitize($label.' '.$student['nama_lengkap']) ?>" value="<?= sanitize((is_string($postedRow[$key]??null)?$postedRow[$key]:(string)($r[$key]??''))) ?>" <?= $key!=='nomor_absen'?'required':'' ?>></td><?php endforeach ?>

</tr>
<?php endforeach ?></tbody></table></div>
<?php
$classNotes=[];foreach($editableStudents as $noteStudent)$classNotes[]=(string)($attendanceReports[(int)$noteStudent['id']]['catatan_wali_kelas']??'');
$classNotes=array_values(array_unique($classNotes));$mixedNotes=count($classNotes)>1;
$sharedNote=is_string($posted['catatan_wali_kelas']??null)?$posted['catatan_wali_kelas']:($mixedNotes?'':($classNotes[0]??''));
?>
<div class="rapor-class-note"><label class="form-label" for="class-teacher-note">Catatan wali kelas untuk seluruh kelas</label><p id="class-note-help">Tulis satu kali. Catatan ini akan digunakan pada rapor semua siswa dalam tabel.</p>
<?php if($mixedNotes): ?><input type="hidden" name="preserve_note_if_empty" value="1"><p class="small text-muted">Catatan lama siswa berbeda-beda. Isi catatan untuk menyamakannya; biarkan kosong untuk mempertahankan catatan lama.</p><?php endif ?>
<textarea id="class-teacher-note" name="catatan_wali_kelas" class="form-control" rows="4" maxlength="4000" aria-describedby="class-note-help" placeholder="Tulis catatan wali kelas di sini..."><?= sanitize($sharedNote) ?></textarea></div>
<input type="hidden" name="batch_complete" value="1"><button class="btn btn-primary btn-lg">Simpan tabel kelas</button>
</form></div></section>
<?php require __DIR__.'/rapor_extra_matrix.php'; ?>
<section class="card mb-4 rapor-print-card" id="rapor-print-class"><div class="card-body"><div class="rapor-section-title"><span class="rapor-section-number"><i class="fa-solid fa-print" aria-hidden="true"></i></span><div><h2>Pratinjau dan cetak</h2><p>Periksa tampilan rapor sebelum mencetak satu kelas.</p></div></div><p>Simpan tabel kelas, lalu periksa kelengkapan rapor dan tampilan cetak.</p><a class="btn btn-outline-primary btn-lg" href="rapor_cetak.php?<?= sanitize(http_build_query(['mode'=>'kelas','kelas_id'=>$kelas,'tahun_ajaran'=>$tahun,'semester'=>$semester,'jenis'=>$jenis])) ?>"><i class="fa-solid fa-print" aria-hidden="true"></i> Pratinjau & cetak satu kelas</a><a class="btn btn-outline-secondary mt-2" href="rapor_peringkat.php?<?= sanitize(http_build_query(['kelas_id'=>$kelas,'tahun_ajaran'=>$tahun,'semester'=>$semester,'jenis'=>$jenis])) ?>"><i class="fa-solid fa-ranking-star" aria-hidden="true"></i> Laporan peringkat 1?10</a></div></section>
<?php endif ?>
