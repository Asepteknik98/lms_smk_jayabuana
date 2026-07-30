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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf($_POST['csrf_token'] ?? '')) {
    $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
}

// --- PROSES 1: TAMBAH MAPEL BARU ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error) && ($_POST['action'] ?? '') === 'tambah') {
    $kode_mapel = trim($_POST['kode_mapel'] ?? '');
    $nama_mapel = trim($_POST['nama_mapel'] ?? '');
    $kelompok   = trim($_POST['kelompok'] ?? '');
    $guru_id    = (int)($_POST['guru_id'] ?? 0);
    $kelas_input = is_array($_POST['kelas_ids'] ?? null)
        ? $_POST['kelas_ids']
        : [$_POST['kelas_id'] ?? 0];
    $kelas_ids = array_values(array_unique(array_filter(
        array_map('intval', $kelas_input),
        static fn(int $id): bool => $id > 0
    )));
    $tahun_ajaran = trim($_POST['tahun_ajaran'] ?? '');
    $semester   = $_POST['semester'] ?? '';

    $tahun_valid = preg_match('/^(\d{4})\/(\d{4})$/', $tahun_ajaran, $tahun)
        && (int)$tahun[2] === (int)$tahun[1] + 1;

    if (empty($kode_mapel) || empty($nama_mapel) || $guru_id < 1 || !$kelas_ids || !$tahun_valid || !in_array($semester, ['Ganjil', 'Genap'], true)) {
        $error = "Kode, nama mapel, Guru, minimal satu kelas, tahun ajaran, dan semester wajib diisi dengan benar!";
    } else {
        try {
            // Kode mapel adalah master unik. Jika sudah ada, gunakan master
            // tersebut untuk membuat penugasan pada kelas yang lain.
            $stmt_cek = $db->prepare("SELECT id, nama_mapel FROM mapel WHERE kode_mapel = ?");
            $stmt_cek->execute([$kode_mapel]);
            $mapel_tersedia = $stmt_cek->fetch();

            $stmt_guru_valid = $db->prepare("SELECT EXISTS(SELECT 1 FROM guru WHERE id=?)");
            $stmt_guru_valid->execute([$guru_id]);
            $placeholder_kelas = implode(',', array_fill(0, count($kelas_ids), '?'));
            $stmt_kelas_valid = $db->prepare("SELECT COUNT(*) FROM kelas WHERE id IN ($placeholder_kelas)");
            $stmt_kelas_valid->execute($kelas_ids);
            if (!(int)$stmt_guru_valid->fetchColumn() || (int)$stmt_kelas_valid->fetchColumn() !== count($kelas_ids)) {
                throw new RuntimeException('Guru atau salah satu kelas tidak ditemukan.');
            }

            $db->beginTransaction();
            if ($mapel_tersedia) {
                $mapel_baru_id = (int)$mapel_tersedia['id'];
            } else {
                $stmt_add = $db->prepare("INSERT INTO mapel (kode_mapel, nama_mapel, kelompok, created_at) VALUES (?, ?, ?, NOW())");
                $stmt_add->execute([$kode_mapel, $nama_mapel, $kelompok]);
                $mapel_baru_id = (int)$db->lastInsertId();
            }

            $stmt_cek_pengajaran = $db->prepare(
                "SELECT EXISTS(
                    SELECT 1 FROM pengajaran
                    WHERE guru_id=? AND mapel_id=? AND kelas_id=? AND tahun_ajaran=? AND semester=?
                )"
            );
            $stmt_pengajaran_baru = $db->prepare(
                "INSERT INTO pengajaran (guru_id,mapel_id,kelas_id,tahun_ajaran,semester)
                 VALUES (?,?,?,?,?)"
            );
            $jumlah_ditambahkan = 0;
            $jumlah_dilewati = 0;
            foreach ($kelas_ids as $kelas_id) {
                $parameter = [$guru_id, $mapel_baru_id, $kelas_id, $tahun_ajaran, $semester];
                $stmt_cek_pengajaran->execute($parameter);
                if ($stmt_cek_pengajaran->fetchColumn()) {
                    $jumlah_dilewati++;
                    continue;
                }
                $stmt_pengajaran_baru->execute($parameter);
                $jumlah_ditambahkan++;
            }
            $db->commit();
            if ($jumlah_ditambahkan > 0) {
                $nama_mapel_hasil = $mapel_tersedia['nama_mapel'] ?? $nama_mapel;
                $success = "$jumlah_ditambahkan penugasan $nama_mapel_hasil berhasil ditambahkan.";
                if ($jumlah_dilewati > 0) {
                    $success .= " $jumlah_dilewati kelas dilewati karena penugasannya sudah tersedia.";
                }
            } else {
                $success = 'Tidak ada penugasan baru. Seluruh kelas yang dipilih sudah ditugaskan kepada Guru tersebut.';
            }
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('Gagal menambah mapel dan pengajaran: ' . $e->getMessage());
            $error = $e instanceof PDOException
                ? ($e->getCode() === '23000'
                    ? 'Salah satu penugasan kelas sudah tersedia atau data yang dikirim tidak valid.'
                    : 'Mata pelajaran atau penugasan gagal ditambahkan.')
                : ($e instanceof RuntimeException ? $e->getMessage() : 'Mata pelajaran atau penugasan gagal ditambahkan.');
        }
    }
}

// --- PROSES 2: EDIT MAPEL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error) && ($_POST['action'] ?? '') === 'edit') {
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error) && ($_POST['action'] ?? '') === 'hapus') {
    $mapel_id = (int)($_POST['mapel_id'] ?? 0);
    try {
        $stmt_del = $db->prepare("DELETE FROM mapel WHERE id = ?");
        $stmt_del->execute([$mapel_id]);
        $success = "Mata Pelajaran berhasil dihapus!";
    } catch (Exception $e) {
        $error = "Gagal menghapus data (mapel mungkin sedang digunakan pada data pengajaran/tugas): " . $e->getMessage();
    }
}

// --- PROSES 4: HAPUS BEBERAPA MAPEL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error) && ($_POST['action'] ?? '') === 'hapus_massal') {
    $mapel_ids = is_array($_POST['mapel_ids'] ?? null) ? $_POST['mapel_ids'] : [];
    $mapel_ids = array_values(array_unique(array_filter(
        array_map('intval', $mapel_ids),
        static fn($id) => $id > 0
    )));

    if (!$mapel_ids) {
        $error = 'Pilih minimal satu mata pelajaran yang akan dihapus.';
    } else {
        try {
            $db->beginTransaction();
            $placeholder = implode(',', array_fill(0, count($mapel_ids), '?'));
            $stmt_del_massal = $db->prepare("DELETE FROM mapel WHERE id IN ($placeholder)");
            $stmt_del_massal->execute($mapel_ids);
            $jumlah_dihapus = $stmt_del_massal->rowCount();
            $db->commit();
            $success = "$jumlah_dihapus mata pelajaran berhasil dihapus!";
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('Gagal menghapus mapel secara massal: ' . $e->getMessage());
            $error = 'Penghapusan dibatalkan. Salah satu mapel masih digunakan pada pengajaran, materi, tugas, atau data akademik lainnya.';
        }
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
                <div class="row g-3 align-items-end mb-4">
                    <div class="col-lg-4">
                        <label for="cariMapel" class="form-label fw-semibold">Cari Mata Pelajaran</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                            <input type="search" id="cariMapel" class="form-control" placeholder="Kode, nama mapel, atau Guru">
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label for="filterKelompokMapel" class="form-label fw-semibold">Kelompok</label>
                        <select id="filterKelompokMapel" class="form-select">
                            <option value="">Semua Kelompok</option>
                            <option value="Muatan Nasional">Muatan Nasional</option>
                            <option value="Muatan Kewilayahan">Muatan Kewilayahan</option>
                            <option value="Muatan Kejuruan">Muatan Kejuruan</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label for="filterGuruMapel" class="form-label fw-semibold">Guru</label>
                        <select id="filterGuruMapel" class="form-select">
                            <option value="">Semua Guru</option>
                            <?php foreach ($daftar_guru as $guru): ?>
                                <option value="<?= (int)$guru['id'] ?>"><?= sanitize($guru['nama_lengkap']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label for="filterSemesterMapel" class="form-label fw-semibold">Semester</label>
                        <select id="filterSemesterMapel" class="form-select">
                            <option value="">Semua Semester</option>
                            <option value="Ganjil">Ganjil</option>
                            <option value="Genap">Genap</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <button type="button" id="resetFilterMapel" class="btn btn-outline-secondary w-100">
                            <i class="fa-solid fa-rotate-left me-1"></i> Reset
                        </button>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <small class="text-muted">Filter dijalankan langsung tanpa memuat ulang halaman.</small>
                    <span id="jumlahHasilMapel" class="badge bg-primary-subtle text-primary"></span>
                </div>
                <form id="formHapusMassalMapel" action="mapel.php" method="POST" onsubmit="return confirm('Hapus semua mata pelajaran yang dipilih? Tindakan ini tidak dapat dibatalkan.');">
                    <input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="hapus_massal">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <span id="jumlahPilihanMapel" class="small text-muted">Belum ada mapel dipilih.</span>
                        <button type="submit" id="hapusMassalMapel" class="btn btn-danger btn-sm" disabled>
                            <i class="fa-solid fa-trash-can me-1"></i> Hapus yang Dipilih
                        </button>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="45">
                                    <input type="checkbox" id="pilihSemuaMapel" class="form-check-input" aria-label="Pilih semua mapel yang terlihat">
                                </th>
                                <th width="50">No</th>
                                <th>Kode Mapel</th>
                                <th>Guru Pengampu</th>
                                <th>Semester</th>
                                <th>Nama Mata Pelajaran</th>
                                <th>Kelas</th>
                                <th>Kelompok</th>
                                <th width="150">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($daftar_mapel)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">Belum ada data mata pelajaran yang terdaftar.</td>
                                </tr>
                            <?php else: ?>
                                <?php $no = 1; foreach ($daftar_mapel as $m): ?>
                                    <?php
                                    $penugasan_mapel = $pengajaran_per_mapel[$m['id']] ?? [];
                                    $guru_ids_mapel = array_values(array_unique(array_map(
                                        static fn($penugasan) => (string)(int)$penugasan['guru_id'],
                                        $penugasan_mapel
                                    )));
                                    $semester_mapel = array_values(array_unique(array_column($penugasan_mapel, 'semester')));
                                    $kelas_mapel = array_values(array_unique(array_column($penugasan_mapel, 'nama_kelas')));
                                    sort($kelas_mapel, SORT_NATURAL);
                                    $teks_pencarian = implode(' ', [
                                        $m['kode_mapel'],
                                        $m['nama_mapel'],
                                        $m['kelompok'] ?? '',
                                        str_replace('||', ' ', $m['nama_guru'] ?? ''),
                                        implode(' ', $kelas_mapel),
                                    ]);
                                    ?>
                                    <tr class="mapel-data-row"
                                        data-search="<?= sanitize(mb_strtolower($teks_pencarian, 'UTF-8')) ?>"
                                        data-kelompok="<?= sanitize($m['kelompok'] ?? '') ?>"
                                        data-guru="<?= sanitize(implode(',', $guru_ids_mapel)) ?>"
                                        data-semester="<?= sanitize(implode(',', $semester_mapel)) ?>">
                                        <td>
                                            <input type="checkbox" name="mapel_ids[]" value="<?= (int)$m['id'] ?>" form="formHapusMassalMapel" class="form-check-input pilih-mapel" aria-label="Pilih <?= sanitize($m['nama_mapel']) ?>">
                                        </td>
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
                                        <td>
                                            <?php if ($kelas_mapel): ?>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <?php foreach ($kelas_mapel as $nama_kelas): ?>
                                                        <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">
                                                            <i class="fa-solid fa-school me-1"></i><?= sanitize($nama_kelas) ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small"><i class="fa-solid fa-school-circle-xmark me-1"></i>Belum ditentukan</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge bg-secondary"><?= sanitize($m['kelompok'] ?: 'Umum') ?></span></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning me-1 rounded-2" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalEditMapel<?= $m['id'] ?>">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                            <form action="mapel.php" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus mata pelajaran ini?');">
                                                <input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="action" value="hapus">
                                                <input type="hidden" name="mapel_id" value="<?= (int)$m['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger rounded-2"><i class="fa-solid fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>

                                    <!-- MODAL EDIT MAPEL -->
                                    <div class="modal fade" id="modalEditMapel<?= $m['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form action="mapel.php" method="POST">
                                                    <input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>">
                                                    <input type="hidden" name="action" value="edit">
                                                    <input type="hidden" name="mapel_id" value="<?= $m['id'] ?>">
                                                    
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold">Edit Mata Pelajaran</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Kode Mapel *</label>
                                                            <input type="text" name="kode_mapel" class="form-control" maxlength="20" required value="<?= htmlspecialchars($m['kode_mapel']) ?>">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Nama Mata Pelajaran *</label>
                                                            <input type="text" name="nama_mapel" class="form-control" maxlength="100" required value="<?= htmlspecialchars($m['nama_mapel']) ?>">
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
                                <tr id="mapelTidakDitemukan" class="d-none">
                                    <td colspan="9" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-magnifying-glass d-block fs-3 mb-2"></i>
                                        Tidak ada mata pelajaran yang sesuai dengan filter.
                                    </td>
                                </tr>
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
            <form action="mapel.php" method="POST" id="formTambahMapel">
                <input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="tambah">
                
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-plus text-primary me-2"></i> Tambah Mapel / Penugasan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kode Mapel *</label>
                        <input type="text" name="kode_mapel" class="form-control" maxlength="20" required placeholder="Gunakan kode yang sama untuk mapel yang sudah ada">
                        <div class="form-text">Kode yang sudah ada akan dipakai untuk menambahkan Guru ke kelas lain.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Mata Pelajaran *</label>
                        <input type="text" name="nama_mapel" class="form-control" maxlength="100" required placeholder="Contoh: Administrasi Infrastruktur Jaringan">
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
                        <div class="border rounded-3 p-2" style="max-height:220px;overflow-y:auto">
                            <?php foreach ($daftar_kelas as $kelas): ?>
                                <?php $id_pilihan_kelas = 'kelasTambah' . (int)$kelas['id']; ?>
                                <div class="form-check px-2 py-2 border-bottom">
                                    <input class="form-check-input ms-0 me-2 kelas-penugasan" type="checkbox" name="kelas_ids[]" value="<?= (int)$kelas['id'] ?>" id="<?= $id_pilihan_kelas ?>">
                                    <label class="form-check-label d-block" for="<?= $id_pilihan_kelas ?>">
                                        <strong><?= sanitize($kelas['nama_kelas']) ?></strong>
                                        <?php if ($kelas['jurusan']): ?><small class="text-muted"> — <?= sanitize($kelas['jurusan']) ?></small><?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-text">Pilih satu atau beberapa kelas. Setiap kelas akan dibuat sebagai penugasan terpisah.</div>
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
                    <button type="submit" class="btn btn-primary" id="simpanPenugasanMapel">Simpan Mapel / Penugasan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const pencarian = document.getElementById('cariMapel');
    const kelompok = document.getElementById('filterKelompokMapel');
    const guru = document.getElementById('filterGuruMapel');
    const semester = document.getElementById('filterSemesterMapel');
    const reset = document.getElementById('resetFilterMapel');
    const jumlahHasil = document.getElementById('jumlahHasilMapel');
    const tidakDitemukan = document.getElementById('mapelTidakDitemukan');
    const baris = Array.from(document.querySelectorAll('.mapel-data-row'));
    const pilihSemua = document.getElementById('pilihSemuaMapel');
    const pilihan = Array.from(document.querySelectorAll('.pilih-mapel'));
    const hapusMassal = document.getElementById('hapusMassalMapel');
    const jumlahPilihan = document.getElementById('jumlahPilihanMapel');
    const formTambahMapel = document.getElementById('formTambahMapel');
    const kelasPenugasan = Array.from(document.querySelectorAll('.kelas-penugasan'));

    if (!pencarian || !kelompok || !guru || !semester || !reset) return;

    if (formTambahMapel) {
        formTambahMapel.addEventListener('submit', function (event) {
            if (!kelasPenugasan.some(function (checkbox) { return checkbox.checked; })) {
                event.preventDefault();
                Swal.fire({ icon: 'warning', title: 'Pilih Kelas', text: 'Pilih minimal satu kelas yang diajar.' });
            }
        });
    }

    function terapkanFilter() {
        const kata = pencarian.value.trim().toLocaleLowerCase('id-ID');
        let terlihat = 0;

        baris.forEach(function (row) {
            const guruIds = (row.dataset.guru || '').split(',').filter(Boolean);
            const semesterList = (row.dataset.semester || '').split(',').filter(Boolean);
            const cocok = (!kata || (row.dataset.search || '').includes(kata))
                && (!kelompok.value || row.dataset.kelompok === kelompok.value)
                && (!guru.value || guruIds.includes(guru.value))
                && (!semester.value || semesterList.includes(semester.value));

            row.classList.toggle('d-none', !cocok);
            if (cocok) terlihat++;
        });

        if (jumlahHasil) jumlahHasil.textContent = terlihat + ' dari ' + baris.length + ' mapel';
        if (tidakDitemukan) tidakDitemukan.classList.toggle('d-none', terlihat !== 0);
        perbaruiPilihan();
    }

    function perbaruiPilihan() {
        const terpilih = pilihan.filter(function (checkbox) { return checkbox.checked; });
        const terlihat = pilihan.filter(function (checkbox) {
            return !checkbox.closest('.mapel-data-row').classList.contains('d-none');
        });
        const terlihatTerpilih = terlihat.filter(function (checkbox) { return checkbox.checked; });

        if (hapusMassal) hapusMassal.disabled = terpilih.length === 0;
        if (jumlahPilihan) {
            jumlahPilihan.textContent = terpilih.length
                ? terpilih.length + ' mapel dipilih.'
                : 'Belum ada mapel dipilih.';
        }
        if (pilihSemua) {
            pilihSemua.checked = terlihat.length > 0 && terlihatTerpilih.length === terlihat.length;
            pilihSemua.indeterminate = terlihatTerpilih.length > 0 && terlihatTerpilih.length < terlihat.length;
        }
    }

    pencarian.addEventListener('input', terapkanFilter);
    kelompok.addEventListener('change', terapkanFilter);
    guru.addEventListener('change', terapkanFilter);
    semester.addEventListener('change', terapkanFilter);
    pilihan.forEach(function (checkbox) {
        checkbox.addEventListener('change', perbaruiPilihan);
    });
    if (pilihSemua) {
        pilihSemua.addEventListener('change', function () {
            pilihan.forEach(function (checkbox) {
                if (!checkbox.closest('.mapel-data-row').classList.contains('d-none')) {
                    checkbox.checked = pilihSemua.checked;
                }
            });
            perbaruiPilihan();
        });
    }
    reset.addEventListener('click', function () {
        pencarian.value = '';
        kelompok.value = '';
        guru.value = '';
        semester.value = '';
        terapkanFilter();
        pencarian.focus();
    });

    terapkanFilter();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
