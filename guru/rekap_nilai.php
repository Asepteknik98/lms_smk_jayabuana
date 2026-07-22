<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

check_access([2]);

$db = Database::getInstance();

// Dapatkan ID Guru
$stmt_g = $db->prepare("SELECT id FROM guru WHERE user_id = ?");
$stmt_g->execute([$_SESSION['user_id']]);
$guru = $stmt_g->fetch();
$guru_id = $guru['id'] ?? 0;

// Daftar pengajaran
$stmt_p = $db->prepare("
    SELECT p.id as pengajaran_id, m.nama_mapel, k.nama_kelas 
    FROM pengajaran p
    JOIN mapel m ON p.mapel_id = m.id
    JOIN kelas k ON p.kelas_id = k.id
    WHERE p.guru_id = ?
");
$stmt_p->execute([$guru_id]);
$pengajaran_list = $stmt_p->fetchAll();

$selected_pengajaran = intval($_GET['pengajaran_id'] ?? ($pengajaran_list[0]['pengajaran_id'] ?? 0));

$rekap_data = [];
if ($selected_pengajaran > 0) {
    $stmt_r = $db->prepare("
        SELECT 
            s.nisn,
            s.nama_lengkap,
            COALESCE((SELECT AVG(pt.nilai)
                      FROM pengumpulan_tugas pt
                      JOIN tugas t ON t.id = pt.tugas_id
                      WHERE t.pengajaran_id = p.id AND pt.siswa_id = s.id), 0) AS avg_tugas,
            COALESCE((SELECT AVG(nu.nilai_total)
                      FROM nilai_ujian nu
                      JOIN ujian u ON u.id = nu.ujian_id
                      WHERE u.pengajaran_id = p.id AND nu.siswa_id = s.id), 0) AS avg_ujian,
            COALESCE((SELECT AVG(CASE da.status
                                    WHEN 'Hadir' THEN 100
                                    WHEN 'Terlambat' THEN 75
                                    WHEN 'Sakit' THEN 100
                                    WHEN 'Izin' THEN 100
                                    ELSE 0
                                END)
                      FROM detail_absensi da
                      JOIN sesi_absensi sa ON sa.id = da.sesi_absensi_id
                      WHERE sa.pengajaran_id = p.id
                        AND sa.status = 'Ditutup'
                        AND da.siswa_id = s.id), 0) AS avg_absensi
        FROM siswa s
        JOIN pengajaran p ON p.kelas_id = s.kelas_id
        WHERE p.id = ? AND p.guru_id = ?
        ORDER BY s.nama_lengkap ASC
    ");
    $stmt_r->execute([$selected_pengajaran, $guru_id]);
    $rekap_data = $stmt_r->fetchAll();

    foreach ($rekap_data as &$nilai) {
        $nilai['nilai_akhir'] = ((float)$nilai['avg_tugas'] * 0.20)
            + ((float)$nilai['avg_ujian'] * 0.15)
            + ((float)$nilai['avg_absensi'] * 0.40);
    }
    unset($nilai);
}
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div id="page-content-wrapper">
    <nav class="navbar navbar-expand-lg navbar-light top-navbar px-4 py-3">
        <h5 class="mb-0 fw-bold">Rekapitulasi Nilai Siswa</h5>
    </nav>

    <div class="container-fluid p-4">
        <div class="card border-0 shadow-sm p-4 mb-4">
            <form method="GET" action="rekap_nilai.php" class="row g-3 align-items-center">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Pilih Mata Pelajaran & Kelas</label>
                    <select name="pengajaran_id" class="form-select" onchange="this.form.submit()">
                        <?php foreach($pengajaran_list as $p): ?>
                            <option value="<?= $p['pengajaran_id'] ?>" <?= ($p['pengajaran_id'] == $selected_pengajaran) ? 'selected' : '' ?>>
                                <?= sanitize($p['nama_mapel']) ?> - <?= sanitize($p['nama_kelas']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <?php if ($selected_pengajaran > 0): ?>
        <div class="card border-0 shadow-sm p-4">
            <div class="alert alert-info small">
                Nilai LMS maksimal <strong>75</strong>: Tugas 20% + Ujian 15% + Absensi 40%.
                Sisa 25% disiapkan untuk nilai UTS/UAS di luar modul Guru ini.
            </div>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold m-0"><i class="fa-solid fa-calculator me-2 text-primary"></i> Data Nilai Terkalkulasi</h6>
                <div class="d-flex flex-wrap gap-2">
                    <a href="rekap_action.php?action=export_excel&pengajaran_id=<?= $selected_pengajaran ?>" class="btn btn-success btn-sm me-2">
                        <i class="fa-solid fa-file-excel me-1"></i> Unduh Excel
                    </a>
                    <a href="rekap_action.php?action=export_pdf&pengajaran_id=<?= $selected_pengajaran ?>" class="btn btn-danger btn-sm">
                        <i class="fa-solid fa-file-pdf me-1"></i> Unduh PDF
                    </a>
                </div>
            </div>

            <div class="table-responsive">
                <table id="tableRekap" class="table table-striped table-hover align-middle w-100">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>NISN</th>
                            <th>Nama Siswa</th>
                            <th>Rata-Rata Tugas (20%)</th>
                            <th>Rata-Rata Ujian (15%)</th>
                            <th>Absensi (40%)</th>
                            <th>Nilai LMS (Maks. 75)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($rekap_data as $idx => $r): ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td><?= sanitize($r['nisn']) ?></td>
                            <td class="fw-semibold"><?= sanitize($r['nama_lengkap']) ?></td>
                            <td><?= number_format($r['avg_tugas'], 2) ?></td>
                            <td><?= number_format($r['avg_ujian'], 2) ?></td>
                            <td><?= number_format($r['avg_absensi'], 2) ?></td>
                            <td>
                                <span class="badge bg-primary fs-6">
                                    <?= number_format($r['nilai_akhir'], 2) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#tableRekap').DataTable();
});
</script>
