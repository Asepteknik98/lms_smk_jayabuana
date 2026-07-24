<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
check_access([3]);

$db = Database::getInstance();
$stmt = $db->prepare('SELECT id,kelas_id FROM siswa WHERE user_id=?');
$stmt->execute([$_SESSION['user_id']]);
$siswa = $stmt->fetch();

$stmt = $db->prepare(
    "SELECT p.id AS pengajaran_id,m.nama_mapel,g.nama_lengkap AS nama_guru,
            p.semester,p.tahun_ajaran,ap.pertemuan_ke,ap.status,ap.dibuka_pada,
            EXISTS(SELECT 1 FROM materi mat
                   WHERE mat.pengajaran_id=p.id AND mat.pertemuan_ke=ap.pertemuan_ke) AS ada_materi,
            EXISTS(SELECT 1 FROM materi mat
                   WHERE mat.pengajaran_id=p.id AND mat.pertemuan_ke=ap.pertemuan_ke
                     AND mat.file_path IS NOT NULL) AS ada_file_materi,
            (SELECT COUNT(*) FROM tugas t
             WHERE t.pengajaran_id=p.id AND t.pertemuan_ke=ap.pertemuan_ke) AS jumlah_tugas,
            EXISTS(SELECT 1 FROM materi mat
                   JOIN materi_siswa_dibaca msb ON msb.materi_id=mat.id AND msb.siswa_id=?
                   WHERE mat.pengajaran_id=p.id AND mat.pertemuan_ke=ap.pertemuan_ke) AS sudah_review,
            EXISTS(SELECT 1 FROM materi mat
                   JOIN materi_siswa_diunduh msu ON msu.materi_id=mat.id AND msu.siswa_id=?
                   WHERE mat.pengajaran_id=p.id AND mat.pertemuan_ke=ap.pertemuan_ke) AS sudah_unduh
     FROM pengajaran p
     JOIN mapel m ON m.id=p.mapel_id
     JOIN guru g ON g.id=p.guru_id
     LEFT JOIN akses_pertemuan ap ON ap.pengajaran_id=p.id
     WHERE p.kelas_id=?
     ORDER BY p.tahun_ajaran DESC,p.semester,m.nama_mapel,ap.pertemuan_ke"
);
$stmt->execute([(int)($siswa['id'] ?? 0),(int)($siswa['id'] ?? 0),(int)($siswa['kelas_id'] ?? 0)]);
$baris = $stmt->fetchAll();

$mapel = [];
foreach ($baris as $item) {
    $id = (int)$item['pengajaran_id'];
    if (!isset($mapel[$id])) {
        $mapel[$id] = [
            'nama_mapel' => $item['nama_mapel'],
            'nama_guru' => $item['nama_guru'],
            'semester' => $item['semester'],
            'tahun_ajaran' => $item['tahun_ajaran'],
            'pertemuan' => [],
        ];
    }
    if ($item['pertemuan_ke'] !== null) {
        $mapel[$id]['pertemuan'][(int)$item['pertemuan_ke']] = $item;
    }
}
require_once __DIR__ . '/../includes/sidebar.php';
?>
<style>
.student-access-shell{max-width:1100px;margin:0 auto}.subject-access-card{border:0;border-radius:18px;box-shadow:0 6px 22px rgba(15,23,42,.06);overflow:hidden}
.meeting-status-card{border:1px solid #e5eaf1;border-radius:14px;padding:14px;background:#f8fafc;height:100%}.meeting-status-card.is-open{border-color:#bbf7d0;background:#f7fff9}
.meeting-status-icon{width:40px;height:40px;display:grid;place-items:center;border-radius:11px;background:#e2e8f0;color:#64748b;flex:0 0 40px}.is-open .meeting-status-icon{background:#dcfce7;color:#15803d}
.meeting-status-card .btn{min-height:42px}.student-access-shell .form-select{min-height:46px;border-radius:11px}.meeting-status-panel:not([hidden]){animation:meetingIn .18s ease}
@keyframes meetingIn{from{opacity:0;transform:translateY(5px)}to{opacity:1;transform:none}}
@media(max-width:575.98px){.student-access-shell{padding:12px!important}.subject-access-card{border-radius:15px}.meeting-status-card{padding:13px}.meeting-actions{display:grid!important;grid-template-columns:1fr}.meeting-actions .btn{width:100%}}
</style>
<div id="page-content-wrapper">
<nav class="navbar navbar-light top-navbar px-3 px-md-4 py-3"><div><h5 class="mb-0 fw-bold"><i class="fa-solid fa-door-open text-primary me-2"></i>Status Pertemuan</h5><small class="text-muted">Lihat pertemuan yang telah dibuka oleh guru</small></div></nav>
<main class="container-fluid student-access-shell p-3 p-md-4">
<?php if (!$mapel): ?>
<div class="card border-0 shadow-sm text-center p-5"><i class="fa-solid fa-book fa-3x text-muted opacity-50 mb-3"></i><h6 class="fw-bold">Belum ada mata pelajaran</h6><p class="text-muted mb-0">Mata pelajaran kelas Anda belum tersedia.</p></div>
<?php else: ?>
<section class="card subject-access-card mb-3"><div class="card-body p-3 p-md-4">
<label for="pilihStatusMapel" class="form-label fw-semibold"><i class="fa-solid fa-chalkboard-user text-primary me-1"></i>Pilih Guru & Mata Pelajaran</label>
<select class="form-select" id="pilihStatusMapel">
<?php foreach ($mapel as $pengajaran_id => $pelajaran): ?>
<option value="<?= $pengajaran_id ?>"><?= sanitize($pelajaran['nama_mapel']) ?> &mdash; <?= sanitize($pelajaran['nama_guru']) ?> (<?= sanitize($pelajaran['semester']) ?>)</option>
<?php endforeach ?>
</select>
<small class="text-muted d-block mt-2">Status pertemuan dari pilihan Anda akan ditampilkan di bawah.</small>
</div></section>
<?php foreach ($mapel as $pengajaran_id => $pelajaran):
    $nomor_terbuka=array_keys(array_filter($pelajaran['pertemuan'],static fn($p)=>$p['status']==='Dibuka'));
    $pertemuan_awal=$nomor_terbuka ? max($nomor_terbuka) : 1;
?>
<section class="card subject-access-card mb-3 subject-access-panel" data-pengajaran-id="<?= $pengajaran_id ?>" <?= $pengajaran_id !== array_key_first($mapel) ? 'hidden' : '' ?>>
<div class="card-header bg-white border-0 p-3 p-md-4"><div class="d-flex flex-wrap justify-content-between gap-2"><div><h2 class="h6 fw-bold mb-1"><?= sanitize($pelajaran['nama_mapel']) ?></h2><small class="text-muted"><i class="fa-solid fa-user-tie me-1"></i><?= sanitize($pelajaran['nama_guru']) ?> &middot; <?= sanitize($pelajaran['semester'].' '.$pelajaran['tahun_ajaran']) ?></small></div><span class="badge bg-primary align-self-start"><?= count($nomor_terbuka) ?> dari 20 terbuka</span></div></div>
<div class="card-body p-3 p-md-4 pt-0">
<label class="form-label fw-semibold" for="pilihPertemuan<?= $pengajaran_id ?>"><i class="fa-solid fa-layer-group text-primary me-1"></i>Pilih Pertemuan</label>
<select class="form-select meeting-selector mb-3" id="pilihPertemuan<?= $pengajaran_id ?>" data-pengajaran-id="<?= $pengajaran_id ?>" aria-controls="statusPertemuan<?= $pengajaran_id ?>">
<?php for($pilihan=1;$pilihan<=20;$pilihan++):$pilihanItem=$pelajaran['pertemuan'][$pilihan]??null;$pilihanTerbuka=($pilihanItem['status']??'Dikunci')==='Dibuka'; ?>
<option value="<?= $pilihan ?>" <?= $pilihan===$pertemuan_awal?'selected':'' ?>>Pertemuan <?= $pilihan ?> &mdash; <?= $pilihanTerbuka?'Terbuka':'Terkunci' ?></option>
<?php endfor ?>
</select>
<div class="row g-2" id="statusPertemuan<?= $pengajaran_id ?>" aria-live="polite">
<?php for ($nomor=1;$nomor<=20;$nomor++): $item=$pelajaran['pertemuan'][$nomor]??null;$terbuka=($item['status']??'Dikunci')==='Dibuka';$tugas_terkunci=$terbuka&&!empty($item['ada_materi'])&&(empty($item['sudah_review'])||(!empty($item['ada_file_materi'])&&empty($item['sudah_unduh']))); ?>
<div class="col-12 meeting-status-panel" data-pengajaran-id="<?= $pengajaran_id ?>" data-pertemuan="<?= $nomor ?>" <?= $nomor!==$pertemuan_awal?'hidden':'' ?>><article class="meeting-status-card <?= $terbuka?'is-open':'' ?>">
<div class="d-flex align-items-start gap-2"><span class="meeting-status-icon"><i class="fa-solid <?= $terbuka?'fa-lock-open':'fa-lock' ?>"></i></span><div class="flex-grow-1"><div class="d-flex justify-content-between gap-2"><strong>Pertemuan <?= $nomor ?></strong><span class="badge <?= $terbuka?'bg-success':'bg-secondary' ?>"><?= $terbuka?'Terbuka':'Terkunci' ?></span></div>
<?php if($terbuka): ?><small class="text-muted d-block mt-1"><?= !empty($item['ada_materi'])?'Materi tersedia':'Belum ada materi' ?><?= !$tugas_terkunci?' &middot; '.(int)($item['jumlah_tugas']??0).' tugas':'' ?></small><?php if($tugas_terkunci):?><small class="text-warning-emphasis d-block mt-1"><i class="fa-solid fa-circle-info me-1"></i><?=empty($item['sudah_review'])?'Review materi':'Unduh materi'?> untuk membuka tugas</small><?php elseif(!empty($item['ada_materi'])):?><small class="text-success d-block mt-1"><i class="fa-solid fa-circle-check me-1"></i>Persyaratan materi selesai</small><?php endif;else: ?><small class="text-muted d-block mt-1">Menunggu dibuka oleh guru</small><?php endif ?>
</div></div>
<?php if($terbuka): ?><div class="d-flex gap-2 mt-3 meeting-actions"><a class="btn btn-sm btn-primary flex-fill" href="materi.php?pengajaran_id=<?= $pengajaran_id ?>&amp;pertemuan=<?= $nomor ?>"><i class="fa-solid fa-book-open me-1"></i><?= $tugas_terkunci?(empty($item['sudah_review'])?'Review Materi':'Unduh Materi'):'Buka Materi' ?></a><?php if($tugas_terkunci):?><button class="btn btn-sm btn-light border text-muted flex-fill" disabled><i class="fa-solid fa-lock me-1"></i>Tugas Terkunci</button><?php else:?><a class="btn btn-sm btn-outline-warning text-dark flex-fill" href="tugas.php?pengajaran_id=<?= $pengajaran_id ?>&amp;pertemuan=<?= $nomor ?>"><i class="fa-solid fa-list-check me-1"></i>Lihat Tugas</a><?php endif?></div><?php else: ?><button class="btn btn-sm btn-light border text-muted w-100 mt-3" disabled><i class="fa-solid fa-lock me-1"></i>Belum dapat diakses</button><?php endif ?>
</article></div>
<?php endfor ?>
</div></div></section>
<?php endforeach; endif ?>
</main></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php if ($mapel): ?>
<script>
const mapelSelector = document.getElementById('pilihStatusMapel');
function tampilkanMapel(id) {
    document.querySelectorAll('.subject-access-panel').forEach(function (panel) {
        panel.hidden = panel.dataset.pengajaranId !== id;
    });
}
mapelSelector?.addEventListener('change', function () {
    tampilkanMapel(this.value);
    try { localStorage.setItem('lms_status_mapel', this.value); } catch (error) {}
});
document.querySelectorAll('.meeting-selector').forEach(function (selector) {
    selector.addEventListener('change', function () {
        document.querySelectorAll('.meeting-status-panel[data-pengajaran-id="' + this.dataset.pengajaranId + '"]').forEach(function (panel) {
            panel.hidden = panel.dataset.pertemuan !== this.value;
        }, this);
        try { localStorage.setItem('lms_status_pertemuan_' + this.dataset.pengajaranId, this.value); } catch (error) {}
    });
});
try {
    const mapelTersimpan = localStorage.getItem('lms_status_mapel');
    if (mapelTersimpan && mapelSelector?.querySelector('option[value="' + mapelTersimpan + '"]')) {
        mapelSelector.value = mapelTersimpan;
    }
    tampilkanMapel(mapelSelector?.value || '');
    document.querySelectorAll('.meeting-selector').forEach(function (selector) {
        const tersimpan = localStorage.getItem('lms_status_pertemuan_' + selector.dataset.pengajaranId);
        if (tersimpan && selector.querySelector('option[value="' + tersimpan + '"]')) selector.value = tersimpan;
        selector.dispatchEvent(new Event('change'));
    });
} catch (error) {
    tampilkanMapel(mapelSelector?.value || '');
}
</script>
<?php endif; ?>
