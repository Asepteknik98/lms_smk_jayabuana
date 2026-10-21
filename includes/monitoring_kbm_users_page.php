<?php
if(!isset($_SESSION['user_id'])||(int)($_SESSION['role_id']??0)!==1){http_response_code(403);exit;}
$userClassNames=[];foreach($classes as $c)$userClassNames[(int)$c['id']]=$c['nama_kelas'];
?>
<form method="get" class="row g-3 mb-4"><input type="hidden" name="tab" value="ketua">
<div class="col-md-9"><label for="kelasKetua" class="form-label">Kelas</label><select id="kelasKetua" name="kelas" class="form-select">
<?php if($pklGroups): ?><optgroup label="Kelas PKL Gabungan"><?php foreach($pklGroups as $g): ?><option value="pkl:<?= kbm_simple_h($g['id']) ?>" <?= $pklId===$g['id']?'selected':'' ?>><?= kbm_simple_h($g['nama']) ?> (PKL)</option><?php endforeach ?></optgroup><?php endif ?>
<optgroup label="Kelas asal"><?php foreach($classes as $c): ?><option value="<?= (int)$c['id'] ?>" <?= !$selectedPkl&&$classId===(int)$c['id']?'selected':'' ?>><?= kbm_simple_h($c['nama_kelas']) ?></option><?php endforeach ?></optgroup>
</select></div><div class="col-md-3 d-flex align-items-end"><button class="btn btn-outline-primary w-100">Tampilkan Siswa</button></div></form>
<?php if($selectedPkl): ?><div class="alert alert-info"><strong><?= kbm_simple_h($selectedPkl['nama']) ?></strong><br>Kelas asal: <?= kbm_simple_h(implode(', ',array_map(fn($id)=>$userClassNames[$id]??'Kelas sudah dihapus',$selectedPkl['kelas_ids']))) ?>. Izin pelaporan siswa tetap mengikuti kelas asal.</div><?php endif ?>
<p class="text-muted"><?= $selectedPkl?'Maksimal 4 pengguna untuk seluruh kelompok PKL. Pilih satu atau beberapa siswa dari kelas gabungan.':'Maksimal 4 pengguna per kelas, tidak wajib empat. Tambahkan satu per satu sesuai kebutuhan.' ?> Pengguna sebelumnya tetap memiliki izin sampai dicabut.</p>
<div class="alert <?= $slotsFull?'alert-warning':'alert-light border' ?>">Pengguna ditunjuk: <strong><?= count($selectedUsers) ?>/4</strong>. <?= $slotsFull?'Batas sudah tercapai. Cabut satu pengguna melalui daftar di bawah untuk menggantinya.':'Boleh menunjuk 1, 2, 3, atau 4 pengguna.' ?></div>
<?php if(!$slotsFull): ?>
<?php if(!$students): ?><div class="alert alert-warning">Belum ada siswa aktif di kelas ini.</div>
<?php elseif(!$availableStudents): ?><div class="alert alert-info">Seluruh siswa aktif yang tersedia sudah ditunjuk.</div>
<?php else: ?>
<form method="post" class="row g-3">
<?php kbm_simple_token() ?>
<input type="hidden" name="action" value="<?= $selectedPkl?'pkl_ketua':'simple_ketua' ?>">
<?php if($selectedPkl): ?><input type="hidden" name="pkl_id" value="<?= kbm_simple_h($pklId) ?>"><?php else: ?><input type="hidden" name="kelas_id" value="<?= $classId ?>"><?php endif ?>
<div class="col-md-9"><label <?= $selectedPkl?'':'for="siswaKetua"' ?> class="form-label">Siswa aktif</label>
<?php if($selectedPkl): ?>
<p id="pklUserHint" class="small text-muted">Centang maksimal <?= 4-count($selectedUsers) ?> siswa lagi. Nama kelas asal tercantum di samping siswa.</p>
<div class="border rounded p-3 overflow-auto" style="max-height:300px" role="group" aria-label="Siswa aktif kelas gabungan" aria-describedby="pklUserHint">
<?php foreach($availableStudents as $s): ?><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="siswa_ids[]" value="<?= (int)$s['id'] ?>" id="pklSiswa<?= (int)$s['id'] ?>"><label class="form-check-label" for="pklSiswa<?= (int)$s['id'] ?>"><?= kbm_simple_h($s['nama_lengkap'].' · '.$s['nama_kelas'].' · NIS '.($s['nis']??'—')) ?></label></div><?php endforeach ?>
</div>
<?php else: ?><select id="siswaKetua" name="siswa_id" class="form-select" required><option value="">Pilih siswa</option><?php foreach($availableStudents as $s): ?><option value="<?= (int)$s['id'] ?>"><?= kbm_simple_h($s['nama_lengkap'].' · NIS '.($s['nis']??'—')) ?></option><?php endforeach ?></select><?php endif ?>
</div><div class="col-md-3 d-flex align-items-end"><button class="btn btn-primary w-100">Tambah Pengguna</button></div></form>
<?php endif ?>
<?php endif ?>
<?php if($selectedPkl&&$selectedUsers): ?>
<div class="mt-4"><h3 class="h6 fw-bold">Pengguna <?= kbm_simple_h($selectedPkl['nama']) ?></h3>
<?php foreach($selectedUsers as $chair): ?>
<div class="d-flex justify-content-between align-items-center gap-2 flex-wrap border rounded p-3 mb-2"><div><?= kbm_simple_h($chair['nama_lengkap']) ?><small class="text-muted d-block"><?= kbm_simple_h($userClassNames[(int)$chair['kelas_id']]??'Kelas sudah dihapus') ?></small>
<?php if(!$chair['is_active']||(int)$chair['role_id']!==3||(int)$chair['kelas_sekarang']!==(int)$chair['kelas_id']): ?><small class="text-danger d-block">Akun nonaktif atau siswa sudah pindah kelas. Cabut untuk mengosongkan slot.</small><?php endif ?></div>
<form method="post" data-confirm="Cabut izin pengguna ini? Riwayat laporan tetap tersimpan."><?php kbm_simple_token() ?><input type="hidden" name="action" value="cabut_ketua"><input type="hidden" name="id" value="<?= (int)$chair['id'] ?>"><button class="btn btn-outline-danger btn-sm">Cabut Penugasan</button></form></div>
<?php endforeach ?></div>
<?php endif ?>
