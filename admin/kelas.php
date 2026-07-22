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

// Daftar 10 Jurusan Resmi SMK Jaya Buana
$daftar_jurusan = [
    'Teknik Komputer dan Jaringan',
    'Desain Teknik Furniture',
    'Teknik Sepeda Motor',
    'Teknik Pengelasan',
    'Teknik Pendingin Tata Udara',
    'Desain Produksi Busana',
    'Teknik Pemesinan',
    'Teknik Kimia Industri',
    'Teknik Bodi Kendaraan Ringan',
    'Teknik Instalasi Tenaga Listrik'
];

// --- PROSES 1: TAMBAH KELAS BARU ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah') {
    $nama_kelas = trim($_POST['nama_kelas'] ?? '');
    $tingkat    = trim($_POST['tingkat'] ?? '');
    $jurusan    = trim($_POST['jurusan'] ?? '');

    if (empty($nama_kelas) || empty($tingkat) || empty($jurusan)) {
        $error = "Semua field (Nama Kelas, Tingkat, dan Jurusan) wajib diisi!";
    } else {
        try {
            // Cek apakah nama kelas sudah ada
            $stmt_cek = $db->prepare("SELECT id FROM kelas WHERE nama_kelas = ?");
            $stmt_cek->execute([$nama_kelas]);
            if ($stmt_cek->fetch()) {
                $error = "Kelas '$nama_kelas' sudah terdaftar!";
            } else {
                $stmt_add = $db->prepare("INSERT INTO kelas (nama_kelas, tingkat, jurusan, created_at) VALUES (?, ?, ?, NOW())");
                $stmt_add->execute([$nama_kelas, $tingkat, $jurusan]);
                $success = "Data Kelas berhasil ditambahkan!";
            }
        } catch (Exception $e) {
            $error = "Gagal menambah data: " . $e->getMessage();
        }
    }
}

// --- PROSES 2: EDIT KELAS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $kelas_id   = (int)($_POST['kelas_id'] ?? 0);
    $nama_kelas = trim($_POST['nama_kelas'] ?? '');
    $tingkat    = trim($_POST['tingkat'] ?? '');
    $jurusan    = trim($_POST['jurusan'] ?? '');

    if (empty($kelas_id) || empty($nama_kelas) || empty($tingkat) || empty($jurusan)) {
        $error = "Data tidak valid atau field masih ada yang kosong!";
    } else {
        try {
            $stmt_edit = $db->prepare("UPDATE kelas SET nama_kelas = ?, tingkat = ?, jurusan = ? WHERE id = ?");
            $stmt_edit->execute([$nama_kelas, $tingkat, $jurusan, $kelas_id]);
            $success = "Data Kelas berhasil diperbarui!";
        } catch (Exception $e) {
            $error = "Gagal memperbarui data: " . $e->getMessage();
        }
    }
}

// --- PROSES 3: HAPUS KELAS ---
if (isset($_GET['hapus'])) {
    $kelas_id = (int)$_GET['hapus'];
    try {
        $stmt_del = $db->prepare("DELETE FROM kelas WHERE id = ?");
        $stmt_del->execute([$kelas_id]);
        $success = "Data Kelas berhasil dihapus!";
    } catch (Exception $e) {
        $error = "Gagal menghapus data (mungkin kelas sedang terikat dengan data siswa/pengajaran): " . $e->getMessage();
    }
}

// --- QUERY AMBIL DATA KELAS & HITUNG JUMLAH SISWA ---
$stmt_list = $db->query("
    SELECT k.*, 
           (SELECT COUNT(*) FROM siswa s WHERE s.kelas_id = k.id) as total_siswa 
    FROM kelas k 
    ORDER BY k.tingkat ASC, k.nama_kelas ASC
");
$daftar_kelas = $stmt_list->fetchAll();
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div id="page-content-wrapper">
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom px-4 py-3">
        <div class="d-flex align-items-center justify-content-between w-100">
            <h5 class="mb-0 fw-bold"><i class="fa-solid fa-school text-primary me-2"></i> Manajemen Data Kelas</h5>
            <button class="btn btn-primary rounded-3" data-bs-toggle="modal" data-bs-target="#modalTambahKelas">
                <i class="fa-solid fa-plus me-1"></i> Tambah Kelas
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

        <!-- Tabel Data Kelas -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="50">No</th>
                                <th>Nama Kelas</th>
                                <th>Tingkat</th>
                                <th>Jurusan</th>
                                <th>Total Siswa</th>
                                <th width="150">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($daftar_kelas)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">Belum ada data kelas yang terdaftar.</td>
                                </tr>
                            <?php else: ?>
                                <?php $no = 1; foreach ($daftar_kelas as $k): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><strong class="text-primary"><?= sanitize($k['nama_kelas']) ?></strong></td>
                                        <td><span class="badge bg-secondary"><?= sanitize($k['tingkat']) ?></span></td>
                                        <td><?= sanitize($k['jurusan']) ?></td>
                                        <td><span class="badge bg-info text-dark"><?= $k['total_siswa'] ?> Siswa</span></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning me-1 rounded-2" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalEditKelas<?= $k['id'] ?>">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                            <a href="kelas.php?hapus=<?= $k['id'] ?>" 
                                               class="btn btn-sm btn-danger rounded-2" 
                                               onclick="return confirm('Apakah Anda yakin ingin menghapus kelas ini?');">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>

                                    <!-- MODAL EDIT KELAS -->
                                    <div class="modal fade" id="modalEditKelas<?= $k['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form action="kelas.php" method="POST">
                                                    <input type="hidden" name="action" value="edit">
                                                    <input type="hidden" name="kelas_id" value="<?= $k['id'] ?>">
                                                    
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold">Edit Data Kelas</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Tingkat *</label>
                                                            <select name="tingkat" class="form-select" required>
                                                                <option value="X" <?= $k['tingkat'] === 'X' ? 'selected' : '' ?>>X (Sepuluh)</option>
                                                                <option value="XI" <?= $k['tingkat'] === 'XI' ? 'selected' : '' ?>>XI (Sebelas)</option>
                                                                <option value="XII" <?= $k['tingkat'] === 'XII' ? 'selected' : '' ?>>XII (Dua Belas)</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Jurusan *</label>
                                                            <select name="jurusan" class="form-select" required>
                                                                <?php foreach ($daftar_jurusan as $j): ?>
                                                                    <option value="<?= $j ?>" <?= $k['jurusan'] === $j ? 'selected' : '' ?>><?= $j ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Nama Kelas *</label>
                                                            <input type="text" name="nama_kelas" class="form-control" required value="<?= htmlspecialchars($k['nama_kelas']) ?>">
                                                        </div>
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

<!-- MODAL TAMBAH KELAS -->
<div class="modal fade" id="modalTambahKelas" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="kelas.php" method="POST">
                <input type="hidden" name="action" value="tambah">
                
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-plus text-primary me-2"></i> Tambah Kelas Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tingkat *</label>
                        <select name="tingkat" class="form-select" required>
                            <option value="">-- Pilih Tingkat --</option>
                            <option value="X">X (Sepuluh)</option>
                            <option value="XI">XI (Sebelas)</option>
                            <option value="XII">XII (Dua Belas)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Jurusan *</label>
                        <select name="jurusan" class="form-select" required>
                            <option value="">-- Pilih Jurusan --</option>
                            <?php foreach ($daftar_jurusan as $j): ?>
                                <option value="<?= $j ?>"><?= $j ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Kelas *</label>
                        <input type="text" name="nama_kelas" class="form-control" required placeholder="Contoh: 10 TKJ 1, 11 TSM 2, 12 TP 1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Kelas</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>