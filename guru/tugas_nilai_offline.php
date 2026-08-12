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
$tugas_id = (int)($_GET['tugas_id'] ?? $_POST['tugas_id'] ?? 0);

$stmt = $db->prepare("SELECT t.id,t.judul,t.deskripsi,t.deadline,t.pertemuan_ke,p.kelas_id,m.nama_mapel,k.nama_kelas FROM tugas t JOIN pengajaran p ON p.id=t.pengajaran_id JOIN mapel m ON m.id=p.mapel_id JOIN kelas k ON k.id=p.kelas_id WHERE t.id=? AND p.guru_id=? AND t.metode_pengumpulan='offline'");
$stmt->execute([$tugas_id, $guru_id]);
$tugas = $stmt->fetch();
if (!$tugas) {
    http_response_code(404);
    exit('Tugas offline tidak ditemukan atau bukan milik Anda.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['nilai_offline_error'] = 'Sesi keamanan berakhir. Silakan muat ulang halaman.';
    } else {
        try {
            $nilai_input = is_array($_POST['nilai'] ?? null) ? $_POST['nilai'] : [];
            $catatan_input = is_array($_POST['catatan'] ?? null) ? $_POST['catatan'] : [];
            $stmt_siswa = $db->prepare('SELECT id FROM siswa WHERE kelas_id=?');
            $stmt_siswa->execute([(int)$tugas['kelas_id']]);
            $siswa_valid = array_map('intval', $stmt_siswa->fetchAll(PDO::FETCH_COLUMN));
            $simpan = $db->prepare('INSERT INTO pengumpulan_tugas(tugas_id,siswa_id,file_tugas,catatan,nilai,catatan_guru) VALUES(?,?,NULL,NULL,?,?) ON DUPLICATE KEY UPDATE nilai=VALUES(nilai),catatan_guru=VALUES(catatan_guru)');
            $hapus_kosong = $db->prepare('DELETE FROM pengumpulan_tugas WHERE tugas_id=? AND siswa_id=? AND file_tugas IS NULL AND catatan IS NULL');
            $db->beginTransaction();
            $jumlah = 0;
            foreach ($siswa_valid as $siswa_id) {
                $nilai_raw = trim((string)($nilai_input[$siswa_id] ?? ''));
                if ($nilai_raw === '') {
                    $hapus_kosong->execute([$tugas_id, $siswa_id]);
                    continue;
                }
                $nilai = filter_var($nilai_raw, FILTER_VALIDATE_FLOAT);
                if ($nilai === false || $nilai < 0 || $nilai > 100) throw new RuntimeException('Seluruh nilai harus berada antara 0–100.');
                $catatan = trim((string)($catatan_input[$siswa_id] ?? ''));
                if (mb_strlen($catatan) > 1000) throw new RuntimeException('Catatan guru maksimal 1.000 karakter.');
                $simpan->execute([$tugas_id, $siswa_id, $nilai, $catatan ?: null]);
                $jumlah++;
            }
            $db->commit();
            try { catat_log($_SESSION['user_id'], 'Menyimpan nilai tugas offline: ' . $tugas['judul']); } catch (Throwable $log_error) { error_log('Log nilai tugas offline gagal: ' . $log_error->getMessage()); }
            $_SESSION['nilai_offline_success'] = "$jumlah nilai siswa berhasil disimpan.";
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('Input nilai tugas offline gagal: ' . $e->getMessage());
            $_SESSION['nilai_offline_error'] = $e instanceof RuntimeException ? $e->getMessage() : 'Nilai tugas offline gagal disimpan.';
        }
    }
    header('Location: tugas_nilai_offline.php?tugas_id=' . $tugas_id);
    exit;
}

$stmt = $db->prepare('SELECT s.id,s.nis,s.nisn,s.nama_lengkap,pt.nilai,pt.catatan_guru FROM siswa s LEFT JOIN pengumpulan_tugas pt ON pt.siswa_id=s.id AND pt.tugas_id=? WHERE s.kelas_id=? ORDER BY s.nama_lengkap');
$stmt->execute([$tugas_id, $tugas['kelas_id']]);
$siswa = $stmt->fetchAll();
$sudah_dinilai = count(array_filter($siswa, static fn(array $item): bool => $item['nilai'] !== null));
$success = $_SESSION['nilai_offline_success'] ?? '';
$error = $_SESSION['nilai_offline_error'] ?? '';
unset($_SESSION['nilai_offline_success'], $_SESSION['nilai_offline_error']);
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
<style>
.offline-grade-page{background:#f5f7fb;min-width:0}.offline-grade-content{max-width:1100px;margin:auto}.offline-grade-card{border:0;border-radius:17px;box-shadow:0 6px 22px rgba(15,23,42,.06)}.offline-score{width:100px;min-width:90px}.offline-note{min-width:220px}.offline-table td{vertical-align:middle}.save-bar{position:sticky;bottom:0;z-index:10;background:rgba(255,255,255,.96);backdrop-filter:blur(8px);border-top:1px solid #e8edf4}
@media(max-width:767.98px){.offline-grade-content{padding:13px!important}.offline-grade-card{border-radius:14px}.offline-grade-card .card-body{padding:15px!important}.offline-table-wrap{overflow:visible}.offline-table{display:block}.offline-table thead{display:none}.offline-table tbody{display:grid;gap:12px}.offline-table tr{display:block;padding:14px;border:1px solid #e6ebf2;border-radius:13px;background:#fff}.offline-table td{display:block;padding:0 0 11px!important;border:0!important}.offline-table td:last-child{padding-bottom:0!important}.offline-table td::before{content:attr(data-label);display:block;margin-bottom:4px;font-size:.68rem;font-weight:700;text-transform:uppercase;color:#94a3b8}.offline-score,.offline-note{width:100%;min-width:0;font-size:16px}.save-bar{margin:0 -15px -15px;padding:12px 15px!important}.save-bar .btn{width:100%}}
</style>
<div id="page-content-wrapper" class="offline-grade-page"><nav class="navbar top-navbar px-3 px-md-4 py-3"><div><h5 class="fw-bold mb-0"><i class="fa-solid fa-pen-to-square text-warning me-2"></i>Nilai Tugas Offline</h5><small class="text-muted">Masukkan nilai pekerjaan tertulis atau kegiatan tanpa perangkat</small></div></nav><main class="offline-grade-content p-3 p-md-4">
<?php if($success): ?><div class="alert alert-success alert-dismissible fade show"><?= sanitize($success) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?><?php if($error): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif; ?>
<section class="card offline-grade-card mb-3"><div class="card-body p-3 p-md-4"><div class="d-flex flex-wrap justify-content-between gap-2"><div><span class="badge bg-warning-subtle text-warning mb-2">Tugas Offline</span><h6 class="fw-bold mb-1"><?= sanitize($tugas['judul']) ?></h6><div class="small text-muted"><?= sanitize($tugas['nama_mapel']) ?> · <?= sanitize($tugas['nama_kelas']) ?> · Pertemuan <?= (int)$tugas['pertemuan_ke'] ?></div></div><a href="tugas.php" class="btn btn-sm btn-outline-secondary align-self-start"><i class="fa-solid fa-arrow-left me-1"></i>Daftar Tugas</a></div><div class="mt-3"><strong><?= $sudah_dinilai ?></strong> dari <strong><?= count($siswa) ?></strong> siswa sudah dinilai. Nilai kosong berarti belum dinilai; masukkan angka <strong>0</strong> jika nilainya memang nol.</div></div></section>
<section class="card offline-grade-card"><form method="post"><input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="tugas_id" value="<?= $tugas_id ?>"><div class="table-responsive offline-table-wrap"><table class="table table-hover offline-table mb-0"><thead class="table-light"><tr><th>No.</th><th>Siswa</th><th>Nilai</th><th>Catatan Guru</th></tr></thead><tbody><?php foreach($siswa as $i=>$item): ?><tr><td data-label="Nomor"><?= $i+1 ?></td><td data-label="Siswa"><strong><?= sanitize($item['nama_lengkap']) ?></strong><small class="d-block text-muted">NIS <?= sanitize($item['nis']?:'-') ?> · NISN <?= sanitize($item['nisn']?:'-') ?></small></td><td data-label="Nilai"><input class="form-control offline-score" type="number" name="nilai[<?= (int)$item['id'] ?>]" min="0" max="100" step="0.01" value="<?= $item['nilai']!==null?number_format((float)$item['nilai'],2,'.',''):'' ?>" placeholder="0–100"></td><td data-label="Catatan"><input class="form-control offline-note" name="catatan[<?= (int)$item['id'] ?>]" maxlength="1000" value="<?= sanitize($item['catatan_guru']??'') ?>" placeholder="Opsional"></td></tr><?php endforeach; ?><?php if(!$siswa): ?><tr><td colspan="4" class="text-center text-muted py-5">Belum ada siswa pada kelas ini.</td></tr><?php endif; ?></tbody></table></div><div class="save-bar d-flex justify-content-end p-3"><button class="btn btn-primary" <?= !$siswa?'disabled':'' ?>><i class="fa-solid fa-floppy-disk me-1"></i>Simpan Semua Nilai</button></div></form></section>
</main></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
