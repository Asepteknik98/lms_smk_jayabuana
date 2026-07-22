<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helper.php';
require_once __DIR__ . '/../config/database.php';
check_access([2]);
$db = Database::getInstance();
$stmt = $db->prepare('SELECT id FROM guru WHERE user_id=?');
$stmt->execute([$_SESSION['user_id']]);
$guru_id = (int)($stmt->fetchColumn() ?: 0);
$tugas_id = (int)($_GET['tugas_id'] ?? 0);
$fokus_id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['nilai_error'] = 'Sesi keamanan berakhir. Silakan muat ulang halaman.';
    } else {
        $pengumpulan_id = (int)($_POST['pengumpulan_id'] ?? 0);
        $nilai = filter_var($_POST['nilai'] ?? null, FILTER_VALIDATE_FLOAT);
        $catatan_guru = trim($_POST['catatan_guru'] ?? '');
        $tugas_id = (int)($_POST['tugas_id'] ?? 0);
        $cek = $db->prepare('SELECT pt.id,s.nama_lengkap,t.judul FROM pengumpulan_tugas pt JOIN tugas t ON t.id=pt.tugas_id JOIN pengajaran p ON p.id=t.pengajaran_id JOIN siswa s ON s.id=pt.siswa_id WHERE pt.id=? AND p.guru_id=?');
        $cek->execute([$pengumpulan_id, $guru_id]);
        $data = $cek->fetch();
        if (!$data || $nilai === false || $nilai < 0 || $nilai > 100) {
            $_SESSION['nilai_error'] = 'Data tidak valid atau nilai harus berada antara 0–100.';
        } else {
            $db->prepare('UPDATE pengumpulan_tugas SET nilai=?,catatan_guru=? WHERE id=?')->execute([$nilai, $catatan_guru ?: null, $pengumpulan_id]);
            catat_log($_SESSION['user_id'], 'Menilai tugas ' . $data['nama_lengkap'] . ': ' . $data['judul']);
            $_SESSION['nilai_success'] = 'Nilai dan catatan guru berhasil disimpan.';
        }
    }
    header('Location: tugas_penilaian.php?tugas_id=' . $tugas_id);
    exit;
}

$stmt = $db->prepare('SELECT t.id,t.judul,m.nama_mapel,k.nama_kelas,t.deadline FROM tugas t JOIN pengajaran p ON p.id=t.pengajaran_id JOIN mapel m ON m.id=p.mapel_id JOIN kelas k ON k.id=p.kelas_id WHERE p.guru_id=? ORDER BY t.deadline DESC');
$stmt->execute([$guru_id]);
$pilihan_tugas = $stmt->fetchAll();
if (!$tugas_id && $fokus_id) {
    $stmt = $db->prepare('SELECT pt.tugas_id FROM pengumpulan_tugas pt JOIN tugas t ON t.id=pt.tugas_id JOIN pengajaran p ON p.id=t.pengajaran_id WHERE pt.id=? AND p.guru_id=?');
    $stmt->execute([$fokus_id, $guru_id]);
    $tugas_id = (int)($stmt->fetchColumn() ?: 0);
}
if (!$tugas_id && $pilihan_tugas) $tugas_id = (int)$pilihan_tugas[0]['id'];

$info_tugas = null; $daftar_siswa = [];
if ($tugas_id) {
    $stmt = $db->prepare('SELECT t.*,p.kelas_id,m.nama_mapel,k.nama_kelas FROM tugas t JOIN pengajaran p ON p.id=t.pengajaran_id JOIN mapel m ON m.id=p.mapel_id JOIN kelas k ON k.id=p.kelas_id WHERE t.id=? AND p.guru_id=?');
    $stmt->execute([$tugas_id, $guru_id]);
    $info_tugas = $stmt->fetch();
    if ($info_tugas) {
        $stmt = $db->prepare('SELECT s.id,s.nis,s.nisn,s.nama_lengkap,pt.id pengumpulan_id,pt.file_tugas,pt.catatan,pt.nilai,pt.catatan_guru,pt.dikumpulkan_pada FROM siswa s LEFT JOIN pengumpulan_tugas pt ON pt.siswa_id=s.id AND pt.tugas_id=? WHERE s.kelas_id=? ORDER BY s.nama_lengkap');
        $stmt->execute([$tugas_id, $info_tugas['kelas_id']]);
        $daftar_siswa = $stmt->fetchAll();
    }
}
$terkumpul = count(array_filter($daftar_siswa, fn($s) => !empty($s['pengumpulan_id'])));
$dinilai = count(array_filter($daftar_siswa, fn($s) => $s['nilai'] !== null));
$pesan_sukses = $_SESSION['nilai_success'] ?? ''; $pesan_error = $_SESSION['nilai_error'] ?? '';
unset($_SESSION['nilai_success'], $_SESSION['nilai_error']);
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
<div id="page-content-wrapper"><nav class="navbar top-navbar px-3 px-md-4 py-3"><div><h5 class="fw-bold mb-0"><i class="fa-solid fa-pen-to-square text-warning me-2"></i>Penilaian Tugas</h5><small class="text-muted">Pantau pengumpulan dan berikan umpan balik</small></div></nav>
<div class="container-fluid p-3 p-md-4">
<?php if($pesan_sukses): ?><div class="alert alert-success alert-dismissible"><?= sanitize($pesan_sukses) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?><?php if($pesan_error): ?><div class="alert alert-danger"><?= sanitize($pesan_error) ?></div><?php endif; ?>
<div class="card border-0 shadow-sm p-3 mb-3"><form method="get" class="row g-2 align-items-end"><div class="col-md-9"><label class="form-label fw-semibold">Pilih Tugas</label><select name="tugas_id" class="form-select" onchange="this.form.submit()"><option value="">Pilih tugas</option><?php foreach($pilihan_tugas as $t): ?><option value="<?= (int)$t['id'] ?>" <?= $tugas_id===(int)$t['id']?'selected':'' ?>><?= sanitize($t['nama_mapel']) ?> — <?= sanitize($t['nama_kelas']) ?> · <?= sanitize($t['judul']) ?></option><?php endforeach; ?></select></div><div class="col-md-3"><a href="tugas.php" class="btn btn-outline-secondary w-100"><i class="fa-solid fa-arrow-left me-1"></i> Daftar Tugas</a></div></form></div>
<?php if(!$info_tugas): ?><div class="card border-0 shadow-sm text-center p-5"><p class="text-muted mb-0">Belum ada tugas yang dapat dinilai.</p></div><?php else: ?>
<div class="row g-3 mb-3"><div class="col-4"><div class="card border-0 shadow-sm p-3 text-center"><h4 class="fw-bold mb-0"><?= count($daftar_siswa) ?></h4><small class="text-muted">Siswa</small></div></div><div class="col-4"><div class="card border-0 shadow-sm p-3 text-center"><h4 class="fw-bold text-success mb-0"><?= $terkumpul ?></h4><small class="text-muted">Terkumpul</small></div></div><div class="col-4"><div class="card border-0 shadow-sm p-3 text-center"><h4 class="fw-bold text-primary mb-0"><?= $dinilai ?></h4><small class="text-muted">Dinilai</small></div></div></div>
<div class="card border-0 shadow-sm"><div class="card-header bg-white border-0 p-3 p-md-4"><h6 class="fw-bold mb-1"><?= sanitize($info_tugas['judul']) ?></h6><small class="text-muted"><?= sanitize($info_tugas['nama_mapel']) ?> · <?= sanitize($info_tugas['nama_kelas']) ?> · Pertemuan <?= (int)$info_tugas['pertemuan_ke'] ?></small></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Siswa</th><th>Status</th><th>Waktu</th><th>Jawaban</th><th>Nilai</th><th>Aksi</th></tr></thead><tbody>
<?php foreach($daftar_siswa as $s): ?><tr <?= $fokus_id===(int)$s['pengumpulan_id']?'class="table-warning"':'' ?>><td><strong><?= sanitize($s['nama_lengkap']) ?></strong><br><small class="text-muted">NIS <?= sanitize($s['nis'] ?: '-') ?> · NISN <?= sanitize($s['nisn'] ?: '-') ?></small></td><td><?php if($s['pengumpulan_id']): ?><span class="badge bg-success">Terkumpul</span><?php else: ?><span class="badge bg-secondary">Belum</span><?php endif; ?></td><td class="small"><?= $s['dikumpulkan_pada']?date('d/m/Y H:i',strtotime($s['dikumpulkan_pada'])):'—' ?></td><td><?php if($s['file_tugas']): ?><a class="btn btn-sm btn-outline-primary" target="_blank" href="../assets/upload/jawaban/<?= rawurlencode(basename($s['file_tugas'])) ?>"><i class="fa-solid fa-download me-1"></i> Unduh</a><?php else: ?>—<?php endif; ?></td><td><?= $s['nilai']!==null?'<strong>'.number_format((float)$s['nilai'],2,',','.').'</strong>':'<span class="text-muted">Belum</span>' ?></td><td><?php if($s['pengumpulan_id']): ?><button class="btn btn-sm btn-warning text-white" data-bs-toggle="modal" data-bs-target="#modalNilai" onclick='isiNilai(<?= json_encode(["id"=>(int)$s["pengumpulan_id"],"nama"=>$s["nama_lengkap"],"nilai"=>$s["nilai"],"catatan"=>$s["catatan_guru"],"catatan_siswa"=>$s["catatan"]],JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP) ?>)'><i class="fa-solid fa-pen me-1"></i> Nilai</button><?php else: ?><button class="btn btn-sm btn-light" disabled>Belum mengirim</button><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div><?php endif; ?></div></div>
<div class="modal fade" id="modalNilai" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post"><div class="modal-header"><h5 class="modal-title fw-bold">Nilai Tugas Siswa</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="pengumpulan_id" id="pengumpulanId"><input type="hidden" name="tugas_id" value="<?= $tugas_id ?>"><div class="alert alert-light"><strong id="namaSiswa"></strong><div class="small text-muted mt-1" id="catatanSiswa"></div></div><label class="form-label fw-semibold">Nilai (0–100)</label><input type="number" name="nilai" id="nilaiSiswa" class="form-control mb-3" min="0" max="100" step="0.01" required><label class="form-label fw-semibold">Catatan Guru</label><textarea name="catatan_guru" id="catatanGuru" class="form-control" rows="4" placeholder="Berikan koreksi atau apresiasi untuk siswa"></textarea></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan Nilai</button></div></form></div></div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script>function isiNilai(d){document.getElementById('pengumpulanId').value=d.id;document.getElementById('namaSiswa').textContent=d.nama;document.getElementById('nilaiSiswa').value=d.nilai??'';document.getElementById('catatanGuru').value=d.catatan??'';document.getElementById('catatanSiswa').textContent=d.catatan_siswa?'Catatan siswa: '+d.catatan_siswa:'Tidak ada catatan siswa.'}</script>
