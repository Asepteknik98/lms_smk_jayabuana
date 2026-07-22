<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helper.php';
require_once __DIR__ . '/../config/auth.php';

// Pastikan hanya Admin (role_id = 1) yang dapat mengakses
check_access([1]);

$db = Database::getInstance();
$success = '';
$error   = '';

// --- PROSES 1: TAMBAH MAPEL BARU ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah') {
    $kode_mapel = trim($_POST['kode_mapel'] ?? '');
    $nama_mapel = trim($_POST['nama_mapel'] ?? '');
    $kelompok   = trim($_POST['kelompok'] ?? '');
    $guru_id    = (int)($_POST['guru_id'] ?? 0);
    $kelas_id   = (int)($_POST['kelas_id'] ?? 0);
    $tahun_ajaran = trim($_POST['tahun_ajaran'] ?? '');
    $semester   = $_POST['semester'] ?? '';

    $tahun_valid = preg_match('/^(\d{4})\/(\d{4})$/', $tahun_ajaran, $tahun)
        && (int)$tahun[2] === (int)$tahun[1] + 1;

    if (empty($kode_mapel) || empty($nama_mapel) || $guru_id < 1 || $kelas_id < 1 || !$tahun_valid || !in_array($semester, ['Ganjil', 'Genap'], true)) {
        $error = "Kode, nama mapel, Guru, kelas, tahun ajaran, dan semester wajib diisi dengan benar!";
    } else {
        try {
            // Cek apakah kode mapel sudah ada
            $stmt_cek = $db->prepare("SELECT id FROM mapel WHERE kode_mapel = ?");
            $stmt_cek->execute([$kode_mapel]);
            if ($stmt_cek->fetch()) {
                $error = "Kode Mapel '$kode_mapel' sudah digunakan!";
            } else {
                $stmt_master = $db->prepare("SELECT EXISTS(SELECT 1 FROM guru WHERE id=?) AS guru_valid, EXISTS(SELECT 1 FROM kelas WHERE id=?) AS kelas_valid");
                $stmt_master->execute([$guru_id, $kelas_id]);
                $master = $stmt_master->fetch();
                if (!$master || !(int)$master['guru_valid'] || !(int)$master['kelas_valid']) {
                    throw new RuntimeException('Guru atau kelas tidak ditemukan.');
                }

                $db->beginTransaction();
                $stmt_add = $db->prepare("INSERT INTO mapel (kode_mapel, nama_mapel, kelompok, created_at) VALUES (?, ?, ?, NOW())");
                $stmt_add->execute([$kode_mapel, $nama_mapel, $kelompok]);
                $mapel_baru_id = (int)$db->lastInsertId();

                $stmt_pengajaran_baru = $db->prepare("INSERT INTO pengajaran (guru_id,mapel_id,kelas_id,tahun_ajaran,semester) VALUES (?,?,?,?,?)");
                $stmt_pengajaran_baru->execute([$guru_id, $mapel_baru_id, $kelas_id, $tahun_ajaran, $semester]);
                $db->commit();
                $success = "Mata Pelajaran dan Guru pengampu berhasil ditambahkan!";
            }
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('Gagal menambah mapel dan pengajaran: ' . $e->getMessage());
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Mata pelajaran gagal ditambahkan.';
        }
    }
}

// --- PROSES 2: EDIT MAPEL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $mapel_id   = (int)($_POST['mapel_id'] ?? 0);
    $kode_mapel = trim($_POST['kode_mapel'] ?? '');
    $nama_mapel = trim($_POST['nama_mapel'] ?? '');
    $kelompok   = trim($_POST['kelompok'] ?? '');
    $guru_pengajaran = is_array($_POST['guru_pengajaran'] ?? null) ? $_POST['guru_pengajaran'] : [];

    if (empty($mapel_id) || empty($kode_mapel) || empty($nama_mapel)) {
        $error = "Data tidak valid atau field wajib masih kosong!";
    } else {
        try {
            $db->beginTransaction();
            $stmt_edit = $db->prepare("UPDATE mapel SET kode_mapel = ?, nama_mapel = ?, kelompok = ? WHERE id = ?");
            $stmt_edit->execute([$kode_mapel, $nama_mapel, $kelompok, $mapel_id]);

            $stmt_validasi = $db->prepare("SELECT COUNT(*) FROM pengajaran p JOIN guru g ON g.id = ? WHERE p.id = ? AND p.mapel_id = ?");
            $stmt_guru = $db->prepare("UPDATE pengajaran SET guru_id = ? WHERE id = ? AND mapel_id = ?");
            foreach ($guru_pengajaran as $pengajaran_id => $guru_id) {
                $pengajaran_id = (int)$pengajaran_id;
                $guru_id = (int)$guru_id;
                $stmt_validasi->execute([$guru_id, $pengajaran_id, $mapel_id]);
                if (!$stmt_validasi->fetchColumn()) {
                    throw new RuntimeException('Guru atau data pengajaran tidak valid.');
                }
                $stmt_guru->execute([$guru_id, $pengajaran_id, $mapel_id]);
            }

            $db->commit();
            $success = "Mata Pelajaran berhasil diperbarui!";
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('Gagal memperbarui mapel dan Guru pengampu: ' . $e->getMessage());
            $error = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Gagal memperbarui data. Pastikan penugasan Guru tidak duplikat.';
        }
    }
}

// --- PROSES 3: HAPUS MAPEL ---
if (isset($_GET['hapus'])) {
    $mapel_id = (int)$_GET['hapus'];
    try {
        $stmt_del = $db->prepare("DELETE FROM mapel WHERE id = ?");
        $stmt_del->execute([$mapel_id]);
        $success = "Mata Pelajaran berhasil dihapus!";
    } catch (Exception $e) {
        $error = "Gagal menghapus data (mapel mungkin sedang digunakan pada data pengajaran/tugas): " . $e->getMessage();
    }
}

// --- QUERY MAPEL BESERTA GURU PENGAMPU DARI TABEL PENGAJARAN ---
$stmt_list = $db->query("
    SELECT m.*,
           GROUP_CONCAT(DISTINCT g.nama_lengkap ORDER BY g.nama_lengkap SEPARATOR '||') AS nama_guru,
           GROUP_CONCAT(DISTINCT CONCAT(p.semester, '||', p.tahun_ajaran) ORDER BY p.tahun_ajaran DESC, p.semester SEPARATOR '##') AS periode_pengajaran
    FROM mapel m
    LEFT JOIN pengajaran p ON p.mapel_id = m.id
    LEFT JOIN guru g ON g.id = p.guru_id
    GROUP BY m.id
    ORDER BY m.nama_mapel ASC
");
$daftar_mapel = $stmt_list->fetchAll();
$daftar_guru = $db->query("SELECT id, nip, nama_lengkap FROM guru ORDER BY nama_lengkap")->fetchAll();
$daftar_kelas = $db->query("SELECT id, nama_kelas, jurusan FROM kelas ORDER BY nama_kelas")->fetchAll();
$tahun_sekarang = (int)date('Y');
$tahun_ajaran_default = $tahun_sekarang . '/' . ($tahun_sekarang + 1);
$pengajaran_per_mapel = [];
$stmt_pengajaran = $db->query("
    SELECT p.id, p.mapel_id, p.guru_id, p.tahun_ajaran, p.semester, k.nama_kelas
    FROM pengajaran p
    JOIN kelas k ON k.id = p.kelas_id
    ORDER BY k.nama_kelas, p.tahun_ajaran DESC, p.semester
");
foreach ($stmt_pengajaran->fetchAll() as $pengajaran) {
    $pengajaran_per_mapel[$pengajaran['mapel_id']][] = $pengajaran;
}
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div id="page-content-wrapper">
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom px-4 py-3">
        <div class="d-flex align-items-center justify-content-between w-100">
            <h5 class="mb-0 fw-bold"><i class="fa-solid fa-book text-primary me-2"></i> Manajemen Mata Pelajaran</h5>
            <button class="btn btn-primary rounded-3" data-bs-toggle="modal" data-bs-target="#modalTambahMapel">
                <i class="fa-solid fa-plus me-1"></i> Tambah Mapel
            </button>
        </div>
    </nav>

    <div class="container-fluid p-4">

        <?php if (!empty($success)): ?>
            <script>
                Swal.fire({ icon: 'success', title: 'Berhasil!', text: '<?= addslashes($success) ?>', timer: 2000, showConfirmButton: false });
            </script>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <script>
                Swal.fire({ icon: 'error', title: 'Gagal!', text: '<?= addslashes($error) ?>' });
            </script>
        <?php endif; ?>

        <!-- Tabel Data Mapel -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="50">No</th>
                                <th>Kode Mapel</th>
                                <th>Guru Pengampu</th>
                                <th>Semester</th>
                                <th>Nama Mata Pelajaran</th>
                                <th>Kelompok</th>
                                <th width="150">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($daftar_mapel)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">Belum ada data mata pelajaran yang terdaftar.</td>
                                </tr>
                            <?php else: ?>
                                <?php $no = 1; foreach ($daftar_mapel as $m): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><code><?= sanitize($m['kode_mapel']) ?></code></td>
                                        <td>
                                            <?php if (!empty($m['nama_guru'])): ?>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <?php foreach (explode('||', $m['nama_guru']) as $nama_guru): ?>
                                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                                            <i class="fa-solid fa-chalkboard-user me-1"></i><?= sanitize($nama_guru) ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small"><i class="fa-solid fa-user-slash me-1"></i>Belum ditentukan</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($m['periode_pengajaran'])): ?>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <?php foreach (explode('##', $m['periode_pengajaran']) as $periode): ?>
                                                        <?php [$semester, $tahun_ajaran] = array_pad(explode('||', $periode, 2), 2, ''); ?>
                                                        <span class="badge <?= $semester === 'Ganjil' ? 'bg-warning-subtle text-warning-emphasis' : 'bg-info-subtle text-info-emphasis' ?> border">
                                                            <?= sanitize($semester) ?> · <?= sanitize($tahun_ajaran) ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small">Belum ditentukan</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?= sanitize($m['nama_mapel']) ?></strong></td>
                                        <td><span class="badge bg-secondary"><?= sanitize($m['kelompok'] ?: 'Umum') ?></span></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning me-1 rounded-2" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalEditMapel<?= $m['id'] ?>">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                            <a href="mapel.php?hapus=<?= $m['id'] ?>" 
                                               class="btn btn-sm btn-danger rounded-2" 
                                               onclick="return confirm('Apakah Anda yakin ingin menghapus mata pelajaran ini?');">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>

                                    <!-- MODAL EDIT MAPEL -->
                                    <div class="modal fade" id="modalEditMapel<?= $m['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form action="mapel.php" method="POST">
                                                    <input type="hidden" name="action" value="edit">
                                                    <input type="hidden" name="mapel_id" value="<?= $m['id'] ?>">
                                                    
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold">Edit Mata Pelajaran</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Kode Mapel *</label>
                                                            <input type="text" name="kode_mapel" class="form-control" required value="<?= htmlspecialchars($m['kode_mapel']) ?>">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Nama Mata Pelajaran *</label>
                                                            <input type="text" name="nama_mapel" class="form-control" required value="<?= htmlspecialchars($m['nama_mapel']) ?>">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Kelompok Mapel</label>
                                                            <select name="kelompok" class="form-select">
                                                                <option value="Muatan Nasional" <?= $m['kelompok'] === 'Muatan Nasional' ? 'selected' : '' ?>>Muatan Nasional</option>
                                                                <option value="Muatan Kewilayahan" <?= $m['kelompok'] === 'Muatan Kewilayahan' ? 'selected' : '' ?>>Muatan Kewilayahan</option>
                                                                <option value="Muatan Kejuruan" <?= $m['kelompok'] === 'Muatan Kejuruan' ? 'selected' : '' ?>>Muatan Kejuruan</option>
                                                            </select>
                                                        </div>
                                                        <hr>
                                                        <label class="form-label fw-semibold">Guru Pengampu</label>
                                                        <?php $penugasan_mapel = $pengajaran_per_mapel[$m['id']] ?? []; ?>
                                                        <?php if ($penugasan_mapel): ?>
                                                            <p class="text-muted small">Guru diatur per kelas, semester, dan tahun ajaran.</p>
                                                            <?php foreach ($penugasan_mapel as $penugasan): ?>
                                                                <div class="border rounded-3 p-3 mb-2 bg-light">
                                                                    <div class="small fw-semibold mb-2">
                                                                        <?= sanitize($penugasan['nama_kelas']) ?> · <?= sanitize($penugasan['semester']) ?> · <?= sanitize($penugasan['tahun_ajaran']) ?>
                                                                    </div>
                                                                    <select name="guru_pengajaran[<?= (int)$penugasan['id'] ?>]" class="form-select" required>
                                                                        <?php foreach ($daftar_guru as $guru): ?>
                                                                            <option value="<?= (int)$guru['id'] ?>" <?= (int)$penugasan['guru_id'] === (int)$guru['id'] ? 'selected' : '' ?>>
                                                                                <?= sanitize($guru['nama_lengkap']) ?><?= $guru['nip'] ? ' — ' . sanitize($guru['nip']) : '' ?>
                                                                            </option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <div class="alert alert-warning small mb-0">
                                                                Mapel ini belum memiliki kelas pengajaran. Tambahkan melalui menu <a href="pengajaran.php" class="alert-link">Pengajaran</a> agar Guru dapat dipilih.
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- MODAL TAMBAH MAPEL -->
<div class="modal fade" id="modalTambahMapel" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="mapel.php" method="POST">
                <input type="hidden" name="action" value="tambah">
                
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-plus text-primary me-2"></i> Tambah Mapel Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kode Mapel *</label>
                        <input type="text" name="kode_mapel" class="form-control" required placeholder="Contoh: TKJ-01, MTK-01, BING-01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Mata Pelajaran *</label>
                        <input type="text" name="nama_mapel" class="form-control" required placeholder="Contoh: Administrasi Infrastruktur Jaringan">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kelompok Mapel</label>
                        <select name="kelompok" class="form-select">
                            <option value="Muatan Kejuruan">Muatan Kejuruan</option>
                            <option value="Muatan Nasional">Muatan Nasional</option>
                            <option value="Muatan Kewilayahan">Muatan Kewilayahan</option>
                        </select>
                    </div>
                    <hr>
                    <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-chalkboard-user me-1"></i> Penugasan Guru</h6>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Guru Pengampu *</label>
                        <select name="guru_id" class="form-select" required>
                            <option value="">-- Pilih Guru Pengampu --</option>
                            <?php foreach ($daftar_guru as $guru): ?>
                                <option value="<?= (int)$guru['id'] ?>"><?= sanitize($guru['nama_lengkap']) ?><?= $guru['nip'] ? ' — ' . sanitize($guru['nip']) : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kelas yang Diajar *</label>
                        <select name="kelas_id" class="form-select" required>
                            <option value="">-- Pilih Kelas --</option>
                            <?php foreach ($daftar_kelas as $kelas): ?>
                                <option value="<?= (int)$kelas['id'] ?>"><?= sanitize($kelas['nama_kelas']) ?> — <?= sanitize($kelas['jurusan']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label fw-semibold">Tahun Ajaran *</label>
                            <input type="text" name="tahun_ajaran" class="form-control" value="<?= sanitize($tahun_ajaran_default) ?>" pattern="\d{4}/\d{4}" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Semester *</label>
                            <select name="semester" class="form-select" required><option value="Ganjil">Ganjil</option><option value="Genap">Genap</option></select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Mapel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
