<?php
// Template context supplied by rapor_admin_page.php before this file is included.
/** @var PDO $db */
/** @var array|null $configuration */
/** @var string $error */
/** @var array|null $posted */
/** @var array $context */
/** @var array $selected */
/** @var string $tahun */
/** @var string $semester */
/** @var string $jenis */
/** @var bool $adminReady */
$locked=!empty($configuration['terkunci']);$values=$configuration??[];
if($error&&($posted['admin_action']??'')==='settings')foreach(['wali_guru_id','nama_kepala_sekolah','tempat_terbit','tanggal_terbit'] as $key)if(is_scalar($posted[$key]??null))$values[$key]=$posted[$key];
$teachers=$db->query('SELECT id,nama_lengkap FROM guru ORDER BY nama_lengkap')->fetchAll();
$hidden=$context+['csrf_token'=>$_SESSION['csrf_token'],'settings_version'=>rapor_admin_version($configuration)];
?>
<section class="card mb-4" id="admin-class-detail"><div class="card-body"><div class="rapor-section-title"><span class="rapor-section-number"><i class="fa-solid fa-sliders" aria-hidden="true"></i></span><div><h2>Pengaturan <?= sanitize($selected['nama_kelas']) ?></h2><p><?= sanitize($tahun.' / '.$semester.' / '.$jenis) ?></p></div></div>
<div class="rapor-admin-actions mb-3"><a class="btn btn-outline-secondary" href="rapor_pengaturan.php?<?= sanitize(http_build_query($context)) ?>"><i class="fa-solid fa-heading" aria-hidden="true"></i> Atur judul cetak</a><a class="btn btn-outline-secondary" href="rapor_peringkat.php?<?= sanitize(http_build_query($context)) ?>"><i class="fa-solid fa-ranking-star" aria-hidden="true"></i> Laporan peringkat 1?10</a></div>
<p class="rapor-note">Wali kelas dan data penerbitan disiapkan sekali. Formulir rapor baru milik guru akan menggunakan data ini sebagai isian awal.</p>
<form method="post"><?php foreach($hidden as $key=>$value): ?><input type="hidden" name="<?= $key ?>" value="<?= sanitize((string)$value) ?>"><?php endforeach ?><input type="hidden" name="admin_action" value="settings">
<fieldset <?= !$adminReady||$locked?'disabled':'' ?>><div class="row g-3"><div class="col-md-6"><label class="form-label" for="admin-wali">Wali kelas</label><select class="form-select" id="admin-wali" name="wali_guru_id"><option value="0">Belum ditugaskan</option><?php foreach($teachers as $teacher): ?><option value="<?= (int)$teacher['id'] ?>" <?= (int)($values['wali_guru_id']??0)===(int)$teacher['id']?'selected':'' ?>><?= sanitize($teacher['nama_lengkap']) ?></option><?php endforeach ?></select></div>
<?php foreach(['nama_kepala_sekolah'=>'Nama kepala sekolah','tempat_terbit'=>'Tempat penerbitan','tanggal_terbit'=>'Tanggal penerbitan'] as $key=>$label): ?><div class="col-md-6"><label class="form-label" for="admin-<?= $key ?>"><?= $label ?></label><input id="admin-<?= $key ?>" name="<?= $key ?>" class="form-control" type="<?= $key==='tanggal_terbit'?'date':'text' ?>" maxlength="100" value="<?= sanitize((string)($values[$key]??'')) ?>"></div><?php endforeach ?></div>
<label class="d-flex align-items-start gap-3 my-3"><input class="form-check-input flex-shrink-0" type="checkbox" name="apply_existing" value="1" <?= ($posted['apply_existing']??'')==='1'?'checked':'' ?>><span>Terapkan juga data penanda tangan dan penerbitan ke seluruh rapor tersimpan pada kelas dan periode ini.<small class="d-block text-muted">Nilai, kehadiran, catatan wali kelas, dan eskul tetap dipertahankan.</small></span></label>
<button class="btn btn-primary"><i class="fa-regular fa-floppy-disk" aria-hidden="true"></i> Simpan pengaturan kelas</button></fieldset></form>
<div class="rapor-admin-lock"><div><strong><i class="fa-solid <?= $locked?'fa-lock':'fa-lock-open' ?>" aria-hidden="true"></i> <?= $locked?'Rapor dikunci':'Rapor masih terbuka' ?></strong><p><?= $locked?'Buka kembali jika wali kelas perlu melakukan koreksi.':'Kunci setelah seluruh isian diperiksa. Penguncian menghentikan perubahan rapor dan judul cetak pada kelas serta periode ini.' ?></p></div>
<form method="post"><?php foreach($hidden as $key=>$value): ?><input type="hidden" name="<?= $key ?>" value="<?= sanitize((string)$value) ?>"><?php endforeach ?><input type="hidden" name="admin_action" value="<?= $locked?'unlock':'lock' ?>"><button class="btn btn-outline-secondary" <?= !$adminReady?'disabled':'' ?>><i class="fa-solid <?= $locked?'fa-lock-open':'fa-lock' ?>" aria-hidden="true"></i> <?= $locked?'Buka kunci rapor':'Selesai & kunci rapor' ?></button></form></div>
</div></section>
