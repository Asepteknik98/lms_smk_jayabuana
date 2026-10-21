<?php
// Hanya dirender dari halaman pengaturan admin setelah pemeriksaan akses.
if(!isset($_SESSION['user_id'])||(int)($_SESSION['role_id']??0)!==1){http_response_code(403);exit;}
$pklNames=[];foreach($classes as $c)$pklNames[(int)$c['id']]=$c['nama_kelas'];
$pklEdit=null;$editId=$_GET['pkl_edit']??null;
if(is_string($editId))foreach($pklGroups as $g)if($g['id']===$editId)$pklEdit=$g;
?>
<p class="text-muted">Gabungkan seluruh siswa dari beberapa kelas asal ke dalam kelompok PKL. Siswa, pengguna pelapor, dan laporan KBM tetap mengikuti kelas asal.</p>
<div class="alert alert-info">Simpan kelompok terlebih dahulu, lalu pilih sesi bersama pada daftar kelompok. Perubahan anggota tidak otomatis mengubah sesi. Pengaturan sesi kelas asal tetap dapat diubah secara terpisah.</div>
<?php if($pklAvailable): ?>
<form method="post" class="row g-3 mb-4">
<?php kbm_simple_token() ?>
<input type="hidden" name="action" value="pkl_save">
<input type="hidden" name="pkl_id" value="<?= kbm_simple_h($pklEdit['id']??'') ?>">
<div class="col-12"><label for="pklNama" class="form-label">Nama kelompok PKL</label><input id="pklNama" name="pkl_nama" class="form-control" required maxlength="80" placeholder="Contoh: PKL Gelombang 1" value="<?= kbm_simple_h($pklEdit['nama']??'') ?>"></div>
<fieldset class="col-12"><legend class="form-label fs-6">Kelas asal (minimal dua)</legend><div class="border rounded p-3 overflow-auto" style="max-height:280px">
<?php foreach($classes as $c): $cid=(int)$c['id'];$owner=null;foreach($pklGroups as $g)if($g['id']!==($pklEdit['id']??'')&&in_array($cid,$g['kelas_ids'],true))$owner=$g['nama']; ?>
<div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="pkl_kelas[]" value="<?= $cid ?>" id="pklKelas<?= $cid ?>" <?= in_array($cid,$pklEdit['kelas_ids']??[],true)?'checked':'' ?> <?= $owner!==null?'disabled':'' ?>><label class="form-check-label" for="pklKelas<?= $cid ?>"><?= kbm_simple_h($c['nama_kelas']) ?><?php if($owner!==null): ?> <small class="text-muted">(<?= kbm_simple_h($owner) ?>)</small><?php endif ?></label></div>
<?php endforeach ?>
</div></fieldset>
<div class="col-12"><button class="btn btn-primary"><?= $pklEdit?'Simpan Perubahan':'Buat Kelompok PKL' ?></button><?php if($pklEdit): ?> <a class="btn btn-outline-secondary" href="?tab=pkl">Batal</a><?php endif ?></div>
</form>
<?php endif ?>
<?php if(!$pklGroups): ?><p class="text-muted mb-0">Belum ada kelompok PKL.</p><?php endif ?>
<?php foreach($pklGroups as $g): $codes=[];foreach($g['kelas_ids'] as $cid)$codes[]=$sessionByClass[$cid]['sesi_kode']??'';$unique=array_unique($codes);$same=count($unique)===1&&$codes[0]!==''?$codes[0]:''; ?>
<article class="border rounded p-3 mb-3"><h3 class="h6 fw-bold"><?= kbm_simple_h($g['nama']) ?></h3>
<p class="mb-2"><?php $labels=[];foreach($g['kelas_ids'] as $cid)$labels[]=$pklNames[$cid]??'Kelas sudah dihapus (#'.$cid.')';echo kbm_simple_h(implode(', ',$labels)); ?></p>
<p class="text-muted small">Sesi saat ini: <?= $same?($same==='pagi'?'Pagi':'Siang'):'Belum seragam atau belum diatur' ?>.</p>
<form method="post" class="row g-2 mb-3" data-confirm="Terapkan sesi ini ke seluruh kelas asal dalam kelompok PKL?">
<?php kbm_simple_token() ?><input type="hidden" name="action" value="pkl_sesi"><input type="hidden" name="pkl_id" value="<?= kbm_simple_h($g['id']) ?>">
<div class="col-md-7"><label class="form-label" for="pklSesi<?= kbm_simple_h($g['id']) ?>">Sesi bersama</label><select class="form-select" name="sesi_kode" id="pklSesi<?= kbm_simple_h($g['id']) ?>" required><option value="">Pilih sesi</option><option value="pagi" <?= $same==='pagi'?'selected':'' ?>>Pagi</option><option value="siang" <?= $same==='siang'?'selected':'' ?>>Siang</option></select></div><div class="col-md-5 d-flex align-items-end"><button class="btn btn-primary w-100">Simpan Sesi Kelompok</button></div>
</form>
<div class="d-flex gap-2 flex-wrap"><a class="btn btn-outline-primary" href="?tab=pkl&amp;pkl_edit=<?= kbm_simple_h($g['id']) ?>">Ubah Kelompok</a><form method="post" data-confirm="Hapus kelompok PKL ini? Sesi kelas asal yang sudah disimpan tidak akan diubah."><?php kbm_simple_token() ?><input type="hidden" name="action" value="pkl_delete"><input type="hidden" name="pkl_id" value="<?= kbm_simple_h($g['id']) ?>"><button class="btn btn-outline-danger">Hapus Kelompok</button></form></div>
</article>
<?php endforeach ?>
