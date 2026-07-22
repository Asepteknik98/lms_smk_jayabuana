<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helper.php';
require_once __DIR__ . '/../config/auth.php';

// Pastikan hanya Admin yang akses
check_access([1]);

$db = Database::getInstance();
$success = $_SESSION['flash_success'] ?? '';
$error   = $_SESSION['flash_error'] ?? '';
$import_summary = $_SESSION['import_summary'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['import_summary']);

function csv_safe_value($value): string {
    $value = (string)$value;
    return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
}

function normalize_csv_header(string $header): string {
    $header = preg_replace('/^\xEF\xBB\xBF/', '', trim($header));
    return strtolower(str_replace([' ', '-'], '_', $header));
}

// --- 1. PROSES EXPORT DATA SISWA (CSV) ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename=Data_Siswa_' . date('Y-m-d') . '.csv');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fwrite($output, "sep=;\r\n");
    fputcsv($output, ['No', 'NIS', 'NISN', 'Nama Lengkap', 'Kelas', 'Email', 'Username'], ';');

    $stmt_exp = $db->query("SELECT s.nis, s.nisn, s.nama_lengkap, k.nama_kelas, s.email, u.username 
                            FROM siswa s 
                            LEFT JOIN kelas k ON s.kelas_id = k.id
                            JOIN users u ON s.user_id = u.id 
                            ORDER BY k.nama_kelas ASC, s.nama_lengkap ASC");
    $no = 1;
    while ($row = $stmt_exp->fetch()) {
        fputcsv($output, array_map('csv_safe_value', [$no++, $row['nis'], $row['nisn'], $row['nama_lengkap'], $row['nama_kelas'], $row['email'], $row['username']]), ';');
    }
    fclose($output);
    exit;
}

// --- 2. DOWNLOAD TEMPLATE IMPORT ---
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename=Template_Import_Siswa.csv');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fwrite($output, "sep=;\r\n");
    fputcsv($output, ['nis', 'nisn', 'nama_lengkap', 'nama_kelas', 'email', 'username', 'password'], ';');
    fputcsv($output, ['20260001', '0012345678', 'Contoh Siswa', 'X TKJ 1', 'contoh.siswa@sekolah.sch.id', 'siswa001', 'Siswa@12345'], ';');
    fclose($output);
    exit;
}

// --- 3. TAMBAH SISWA (MANUAL) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah') {
    $nis = trim($_POST['nis'] ?? ''); $nisn = trim($_POST['nisn']); $nama = trim($_POST['nama_lengkap']); $kelas_id = (int)$_POST['kelas_id'];
    $email = trim($_POST['email']); $username = trim($_POST['username']); $password = $_POST['password'];

    try {
        $db->beginTransaction();
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt_u = $db->prepare("INSERT INTO users (username, password, email, role_id, is_active, created_at, updated_at) VALUES (?, ?, ?, 3, 1, NOW(), NOW())");
        $stmt_u->execute([$username, $hashed, $email]);
        $uid = $db->lastInsertId();

        $stmt_s = $db->prepare("INSERT INTO siswa (user_id, kelas_id, nis, nisn, nama_lengkap, email, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt_s->execute([$uid, $kelas_id, $nis, $nisn, $nama, $email]);
        $db->commit();
        $success = "Siswa berhasil ditambahkan!";
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Gagal: " . $e->getMessage();
    }
}

// --- 4. IMPORT SISWA (CSV) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
        redirect('siswa.php');
    }

    $file = $_FILES['file_csv'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        $_SESSION['flash_error'] = 'File CSV gagal diunggah.';
        redirect('siswa.php');
    }
    if ($file['size'] > 5 * 1024 * 1024 || strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $_SESSION['flash_error'] = 'File harus berformat CSV dengan ukuran maksimal 5 MB.';
        redirect('siswa.php');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime, ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'], true)) {
        $_SESSION['flash_error'] = 'Isi file tidak dikenali sebagai CSV.';
        redirect('siswa.php');
    }

    $handle = fopen($file['tmp_name'], 'rb');
    $first_line = fgets($handle);
    if ($first_line === false) {
        fclose($handle);
        $_SESSION['flash_error'] = 'File CSV kosong.';
        redirect('siswa.php');
    }

    $first_line = preg_replace('/^\xEF\xBB\xBF/', '', $first_line);
    if (stripos(trim($first_line), 'sep=') === 0) {
        $delimiter = substr(trim($first_line), 4, 1) ?: ';';
        $header_line = fgets($handle);
    } else {
        $header_line = $first_line;
        $delimiter_counts = [';' => substr_count($header_line, ';'), ',' => substr_count($header_line, ','), "\t" => substr_count($header_line, "\t")];
        $delimiter = array_search(max($delimiter_counts), $delimiter_counts, true);
    }

    $headers = array_map('normalize_csv_header', str_getcsv((string)$header_line, $delimiter));
    $required_headers = ['nis', 'nisn', 'nama_lengkap', 'nama_kelas', 'email', 'username', 'password'];
    if (array_diff($required_headers, $headers)) {
        fclose($handle);
        $_SESSION['flash_error'] = 'Header CSV tidak sesuai. Gunakan template resmi tanpa mengubah nama kolom.';
        redirect('siswa.php');
    }

    $kelas_map = [];
    foreach ($db->query('SELECT id, nama_kelas FROM kelas')->fetchAll() as $kelas) {
        $kelas_map[mb_strtolower(trim($kelas['nama_kelas']), 'UTF-8')] = (int)$kelas['id'];
    }

    $stmt_username = $db->prepare('SELECT 1 FROM users WHERE username = ?');
    $stmt_email = $db->prepare('SELECT 1 FROM users WHERE email = ?');
    $stmt_nis = $db->prepare("SELECT 1 FROM siswa WHERE nis = ? AND nis <> ''");
    $stmt_nisn = $db->prepare("SELECT 1 FROM siswa WHERE nisn = ? AND nisn <> ''");
    $stmt_user = $db->prepare('INSERT INTO users (username, password, email, role_id, is_active) VALUES (?, ?, ?, 3, 1)');
    $stmt_siswa = $db->prepare('INSERT INTO siswa (user_id, kelas_id, nis, nisn, nama_lengkap, email) VALUES (?, ?, ?, ?, ?, ?)');

    $berhasil = 0;
    $gagal = [];
    $baris = stripos(trim($first_line), 'sep=') === 0 ? 3 : 2;
    $maksimal_baris = 5000;
    $diproses = 0;

    while (($columns = fgetcsv($handle, 0, $delimiter)) !== false) {
        if (++$diproses > $maksimal_baris) {
            $gagal[] = 'Import dihentikan: maksimal 5.000 baris per file.';
            break;
        }
        if (count(array_filter($columns, static fn($value) => trim((string)$value) !== '')) === 0) {
            $baris++;
            continue;
        }

        $columns = array_pad($columns, count($headers), '');
        $data = array_combine($headers, array_slice($columns, 0, count($headers)));
        $nis = ltrim(trim($data['nis']), "'\t ");
        $nisn = ltrim(trim($data['nisn']), "'\t ");
        $nama = trim($data['nama_lengkap']);
        $nama_kelas = trim($data['nama_kelas']);
        $email = trim($data['email']);
        $username = trim($data['username']);
        $password = (string)$data['password'];

        try {
            if ($nis === '' || $nisn === '' || $nama === '' || $nama_kelas === '' || $username === '' || $password === '') {
                throw new InvalidArgumentException('NIS, NISN, nama, kelas, username, dan password wajib diisi.');
            }
            if (!preg_match('/^[A-Za-z0-9.-]{3,20}$/', $nis)) {
                throw new InvalidArgumentException('NIS harus terdiri dari 3–20 huruf, angka, titik, atau strip.');
            }
            if (!preg_match('/^[0-9]{5,20}$/', $nisn)) {
                throw new InvalidArgumentException('NISN harus terdiri dari 5–20 angka.');
            }
            if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
                throw new InvalidArgumentException('Username hanya boleh berisi huruf, angka, titik, garis bawah, atau strip.');
            }
            if (strlen($password) < 8) {
                throw new InvalidArgumentException('Password minimal 8 karakter.');
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Format email tidak valid.');
            }

            $kelas_key = mb_strtolower($nama_kelas, 'UTF-8');
            if (!isset($kelas_map[$kelas_key])) {
                throw new InvalidArgumentException("Kelas '$nama_kelas' tidak ditemukan.");
            }

            $stmt_username->execute([$username]);
            if ($stmt_username->fetchColumn()) throw new InvalidArgumentException("Username '$username' sudah digunakan.");
            $stmt_nis->execute([$nis]);
            if ($stmt_nis->fetchColumn()) throw new InvalidArgumentException("NIS '$nis' sudah terdaftar.");
            $stmt_nisn->execute([$nisn]);
            if ($stmt_nisn->fetchColumn()) throw new InvalidArgumentException("NISN '$nisn' sudah terdaftar.");
            if ($email !== '') {
                $stmt_email->execute([$email]);
                if ($stmt_email->fetchColumn()) throw new InvalidArgumentException("Email '$email' sudah digunakan.");
            }

            $db->beginTransaction();
            $email_db = $email !== '' ? $email : null;
            $stmt_user->execute([$username, password_hash($password, PASSWORD_DEFAULT), $email_db]);
            $stmt_siswa->execute([$db->lastInsertId(), $kelas_map[$kelas_key], $nis, $nisn, $nama, $email_db]);
            $db->commit();
            $berhasil++;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $gagal[] = "Baris $baris: " . $e->getMessage();
        }
        $baris++;
    }
    fclose($handle);

    catat_log($_SESSION['user_id'], "Import siswa: $berhasil berhasil, " . count($gagal) . ' gagal');
    $_SESSION['flash_success'] = "Import selesai: $berhasil siswa berhasil ditambahkan.";
    $_SESSION['import_summary'] = ['berhasil' => $berhasil, 'gagal' => $gagal];
    redirect('siswa.php');
}

// --- 5. EDIT DATA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $sid = (int)$_POST['siswa_id']; $uid = (int)$_POST['user_id'];
    $nis = trim($_POST['nis'] ?? ''); $nisn = $_POST['nisn']; $nama = $_POST['nama_lengkap']; $kelas_id = (int)$_POST['kelas_id']; $email = $_POST['email'];

    $db->prepare("UPDATE siswa SET nis=?, nisn=?, nama_lengkap=?, kelas_id=?, email=? WHERE id=?")
       ->execute([$nis, $nisn, $nama, $kelas_id, $email, $sid]);
    $db->prepare("UPDATE users SET email=? WHERE id=?")->execute([$email, $uid]);
    if (!empty($_POST['password'])) {
        $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($_POST['password'], PASSWORD_BCRYPT), $uid]);
    }
    $success = "Data berhasil diupdate!";
}

// --- 6. HAPUS ---
if (isset($_GET['hapus'])) {
    $stmt = $db->prepare("SELECT user_id FROM siswa WHERE id=?");
    $stmt->execute([$_GET['hapus']]);
    $data = $stmt->fetch();
    if ($data) {
        $db->prepare("DELETE FROM siswa WHERE id=?")->execute([$_GET['hapus']]);
        $db->prepare("DELETE FROM users WHERE id=?")->execute([$data['user_id']]);
        $success = "Data berhasil dihapus!";
    }
}

$daftar_kelas = $db->query("SELECT * FROM kelas ORDER BY nama_kelas ASC")->fetchAll();
$pencarian = trim($_GET['q'] ?? '');
$filter_kelas = (int)($_GET['kelas_id'] ?? 0);
$per_halaman_options = [25, 50, 100];
$per_halaman = (int)($_GET['per_page'] ?? 25);
if (!in_array($per_halaman, $per_halaman_options, true)) $per_halaman = 25;
$halaman = max(1, (int)($_GET['page'] ?? 1));

$where = [];
$params = [];
if ($pencarian !== '') {
    $where[] = '(s.nis LIKE ? OR s.nisn LIKE ? OR s.nama_lengkap LIKE ? OR u.username LIKE ?)';
    $keyword = '%' . $pencarian . '%';
    array_push($params, $keyword, $keyword, $keyword, $keyword);
}
if ($filter_kelas > 0) {
    $where[] = 's.kelas_id = ?';
    $params[] = $filter_kelas;
}
$where_sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$stmt_total = $db->prepare("SELECT COUNT(*) FROM siswa s JOIN users u ON u.id = s.user_id" . $where_sql);
$stmt_total->execute($params);
$total_filtered = (int)$stmt_total->fetchColumn();
$total_halaman = max(1, (int)ceil($total_filtered / $per_halaman));
$halaman = min($halaman, $total_halaman);
$offset = ($halaman - 1) * $per_halaman;

$stmt_siswa = $db->prepare(
    "SELECT s.*, k.nama_kelas, u.username, u.is_active
     FROM siswa s
     LEFT JOIN kelas k ON s.kelas_id = k.id
     JOIN users u ON s.user_id = u.id" . $where_sql .
    " ORDER BY k.nama_kelas ASC, s.nama_lengkap ASC LIMIT ? OFFSET ?"
);
$bind_index = 1;
foreach ($params as $param) $stmt_siswa->bindValue($bind_index++, $param, PDO::PARAM_STR);
$stmt_siswa->bindValue($bind_index++, $per_halaman, PDO::PARAM_INT);
$stmt_siswa->bindValue($bind_index, $offset, PDO::PARAM_INT);
$stmt_siswa->execute();
$daftar_siswa = $stmt_siswa->fetchAll();

$total_siswa = (int)$db->query('SELECT COUNT(*) FROM siswa')->fetchColumn();
$siswa_tanpa_kelas = (int)$db->query('SELECT COUNT(*) FROM siswa WHERE kelas_id IS NULL')->fetchColumn();
$akun_siswa_aktif = (int)$db->query('SELECT COUNT(*) FROM siswa s JOIN users u ON u.id=s.user_id WHERE u.is_active=1')->fetchColumn();

$query_pagination = ['q' => $pencarian, 'kelas_id' => $filter_kelas, 'per_page' => $per_halaman];
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div id="page-content-wrapper" class="student-admin-page">
    <style>
        .student-admin-page { background: #f7f9fc; min-height: 100vh; }
        .student-hero { background: linear-gradient(135deg,#172554,#1d4ed8); color:#fff; border-radius:22px; padding:26px; box-shadow:0 16px 40px rgba(29,78,216,.16); }
        .summary-card,.student-table-card { border:1px solid #e8edf5 !important; border-radius:18px !important; box-shadow:0 8px 26px rgba(15,23,42,.045) !important; }
        .summary-icon { width:46px; height:46px; display:grid; place-items:center; border-radius:14px; }
        .filter-panel { background:#fff; border:1px solid #e8edf5; border-radius:16px; padding:16px; }
        .student-table-card thead th { background:#f8fafc; color:#64748b; font-size:.75rem; text-transform:uppercase; letter-spacing:.04em; white-space:nowrap; }
        .student-table-card .table>:not(caption)>*>* { padding:.9rem .75rem; border-bottom-color:#edf1f6; vertical-align:middle; }
        .student-avatar { width:40px; height:40px; display:grid; place-items:center; border-radius:12px; background:#e0e7ff; color:#4338ca; font-weight:700; flex:0 0 40px; }
        .pagination .page-link { border:0; border-radius:9px !important; margin:0 2px; color:#475569; }
        .pagination .page-item.active .page-link { background:#1d4ed8; box-shadow:0 5px 12px rgba(29,78,216,.2); }
        @media(max-width:767.98px){.student-content{padding:18px!important}.student-hero{border-radius:18px;padding:20px}.student-actions{width:100%}.student-actions .btn{flex:1}}
    </style>
    <nav class="navbar navbar-light bg-white border-bottom px-4 py-3">
        <div><h5 class="fw-bold m-0"><i class="fa-solid fa-user-graduate text-primary me-2"></i> Data Siswa</h5><small class="text-muted">Kelola data Siswa dalam jumlah besar</small></div>
    </nav>

    <div class="container-fluid p-4 student-content">
        <section class="student-hero mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div><small class="text-uppercase opacity-75 fw-semibold">Manajemen Siswa</small><h3 class="fw-bold mb-1 mt-1"><?= number_format($total_siswa,0,',','.') ?> Siswa</h3><p class="mb-0 opacity-75">Gunakan pencarian, filter kelas, atau import massal untuk pengelolaan yang efisien.</p></div>
                <div class="d-flex flex-wrap gap-2 student-actions">
                    <a href="siswa.php?export=csv" class="btn btn-light text-success fw-semibold"><i class="fa-solid fa-file-excel me-1"></i> Export</a>
                    <button class="btn btn-outline-light fw-semibold" data-bs-toggle="modal" data-bs-target="#modalImport"><i class="fa-solid fa-upload me-1"></i> Import</button>
                    <button class="btn btn-warning fw-semibold" data-bs-toggle="modal" data-bs-target="#modalTambah"><i class="fa-solid fa-plus me-1"></i> Tambah</button>
                </div>
            </div>
        </section>

        <?php if ($success): ?><script>Swal.fire({icon:'success', title:'Berhasil', text:<?= json_encode($success) ?>});</script><?php endif; ?>
        <?php if ($error): ?><script>Swal.fire({icon:'error', title:'Gagal', text:<?= json_encode($error) ?>});</script><?php endif; ?>

        <?php if ($import_summary): ?>
            <div class="alert <?= empty($import_summary['gagal']) ? 'alert-success' : 'alert-warning' ?> mb-4">
                <h6 class="fw-bold"><i class="fa-solid fa-file-import me-2"></i>Hasil Import</h6>
                <p class="mb-2"><strong><?= (int)$import_summary['berhasil'] ?></strong> siswa berhasil, <strong><?= count($import_summary['gagal']) ?></strong> baris gagal.</p>
                <?php if ($import_summary['gagal']): ?>
                    <details><summary class="fw-semibold" style="cursor:pointer">Lihat kesalahan</summary><ul class="small mt-2 mb-0"><?php foreach (array_slice($import_summary['gagal'], 0, 100) as $pesan): ?><li><?= sanitize($pesan) ?></li><?php endforeach; ?></ul><?php if (count($import_summary['gagal']) > 100): ?><small>Hanya 100 kesalahan pertama yang ditampilkan.</small><?php endif; ?></details>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3"><div class="card summary-card p-3 h-100"><div class="d-flex gap-3 align-items-center"><span class="summary-icon bg-primary-subtle text-primary"><i class="fa-solid fa-users"></i></span><div><small class="text-muted">Total Siswa</small><h4 class="fw-bold mb-0"><?= number_format($total_siswa,0,',','.') ?></h4></div></div></div></div>
            <div class="col-6 col-lg-3"><div class="card summary-card p-3 h-100"><div class="d-flex gap-3 align-items-center"><span class="summary-icon bg-success-subtle text-success"><i class="fa-solid fa-user-check"></i></span><div><small class="text-muted">Akun Aktif</small><h4 class="fw-bold mb-0"><?= number_format($akun_siswa_aktif,0,',','.') ?></h4></div></div></div></div>
            <div class="col-6 col-lg-3"><div class="card summary-card p-3 h-100"><div class="d-flex gap-3 align-items-center"><span class="summary-icon bg-warning-subtle text-warning"><i class="fa-solid fa-school"></i></span><div><small class="text-muted">Total Kelas</small><h4 class="fw-bold mb-0"><?= count($daftar_kelas) ?></h4></div></div></div></div>
            <div class="col-6 col-lg-3"><div class="card summary-card p-3 h-100"><div class="d-flex gap-3 align-items-center"><span class="summary-icon bg-danger-subtle text-danger"><i class="fa-solid fa-user-clock"></i></span><div><small class="text-muted">Tanpa Kelas</small><h4 class="fw-bold mb-0"><?= $siswa_tanpa_kelas ?></h4></div></div></div></div>
        </div>

        <form method="get" action="siswa.php" class="filter-panel mb-4" id="formFilterSiswa">
            <div class="row g-2 align-items-end">
                <div class="col-lg-5"><label class="form-label small fw-semibold">Cari Siswa <span class="text-muted fw-normal">(otomatis)</span></label><div class="input-group"><span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span><input type="search" name="q" id="pencarianSiswa" value="<?= sanitize($pencarian) ?>" class="form-control" placeholder="Nama, NIS, NISN, atau username" autocomplete="off"></div></div>
                <div class="col-md-5 col-lg-3"><label class="form-label small fw-semibold">Kelas <span class="text-muted fw-normal">(otomatis)</span></label><select name="kelas_id" id="filterKelasSiswa" class="form-select"><option value="0">Semua Kelas</option><?php foreach($daftar_kelas as $k): ?><option value="<?= (int)$k['id'] ?>" <?= $filter_kelas===(int)$k['id']?'selected':'' ?>><?= sanitize($k['nama_kelas']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3 col-lg-2"><label class="form-label small fw-semibold">Per Halaman</label><select name="per_page" class="form-select"><?php foreach($per_halaman_options as $opsi): ?><option value="<?= $opsi ?>" <?= $per_halaman===$opsi?'selected':'' ?>><?= $opsi ?> baris</option><?php endforeach; ?></select></div>
                <div class="col-md-4 col-lg-2 d-grid"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-list-ol me-1"></i> Terapkan Jumlah</button></div>
            </div>
        </form>

        <div class="card student-table-card">
            <div class="card-body p-0">
                <div class="d-flex justify-content-between align-items-center px-4 py-3 border-bottom"><div><h6 class="fw-bold mb-0">Daftar Siswa</h6><small class="text-muted">Menampilkan <?= $total_filtered ? $offset+1 : 0 ?>–<?= min($offset+$per_halaman,$total_filtered) ?> dari <?= number_format($total_filtered,0,',','.') ?> data</small></div><?php if($pencarian!==''||$filter_kelas): ?><a href="siswa.php" class="btn btn-sm btn-light">Reset Filter</a><?php endif; ?></div>
                <div class="table-responsive"><table class="table table-hover mb-0">
                    <thead><tr><th class="ps-4">Siswa</th><th>NIS</th><th>NISN</th><th>Kelas</th><th>Username</th><th>Status</th><th class="text-end pe-4">Aksi</th></tr></thead>
                    <tbody>
                        <?php foreach($daftar_siswa as $s): ?>
                        <tr>
                            <td class="ps-4"><div class="d-flex align-items-center gap-3"><span class="student-avatar"><?= sanitize(strtoupper(substr($s['nama_lengkap'],0,1))) ?></span><div><strong class="d-block"><?= sanitize($s['nama_lengkap']) ?></strong><small class="text-muted"><?= sanitize($s['email'] ?: 'Email belum diisi') ?></small></div></div></td>
                            <td><?= sanitize($s['nis'] ?: '-') ?></td>
                            <td><?= sanitize($s['nisn']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= sanitize($s['nama_kelas'] ?: 'Belum ada kelas') ?></span></td>
                            <td><code><?= sanitize($s['username']) ?></code></td>
                            <td><span class="badge bg-<?= $s['is_active']?'success':'secondary' ?>-subtle text-<?= $s['is_active']?'success':'secondary' ?>"><?= $s['is_active']?'Aktif':'Nonaktif' ?></span></td>
                            <td class="text-end pe-4">
                                <button type="button" class="btn btn-outline-primary btn-sm btn-edit-siswa" data-bs-toggle="modal" data-bs-target="#modalEdit" data-siswa-id="<?= (int)$s['id'] ?>" data-user-id="<?= (int)$s['user_id'] ?>" data-nis="<?= sanitize($s['nis']) ?>" data-nisn="<?= sanitize($s['nisn']) ?>" data-nama="<?= sanitize($s['nama_lengkap']) ?>" data-kelas-id="<?= (int)$s['kelas_id'] ?>" data-email="<?= sanitize($s['email']) ?>"><i class="fa-solid fa-pen"></i></button>
                                <a href="?hapus=<?= (int)$s['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Hapus Siswa <?= sanitize($s['nama_lengkap']) ?>?')"><i class="fa-solid fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(!$daftar_siswa): ?><tr><td colspan="7" class="text-center py-5"><i class="fa-solid fa-user-slash text-muted fa-2x mb-2"></i><p class="text-muted mb-0">Data Siswa tidak ditemukan.</p></td></tr><?php endif; ?>
                    </tbody>
                </table></div>
                <?php if($total_halaman>1): ?><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-4 py-3 border-top"><small class="text-muted">Halaman <?= $halaman ?> dari <?= $total_halaman ?></small><nav><ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $halaman<=1?'disabled':'' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($query_pagination,['page'=>$halaman-1])) ?>"><i class="fa-solid fa-chevron-left"></i></a></li>
                    <?php $awal=max(1,$halaman-2);$akhir=min($total_halaman,$halaman+2);for($i=$awal;$i<=$akhir;$i++): ?><li class="page-item <?= $i===$halaman?'active':'' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($query_pagination,['page'=>$i])) ?>"><?= $i ?></a></li><?php endfor; ?>
                    <li class="page-item <?= $halaman>=$total_halaman?'disabled':'' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($query_pagination,['page'=>$halaman+1])) ?>"><i class="fa-solid fa-chevron-right"></i></a></li>
                </ul></nav></div><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Satu modal edit dipakai bersama untuk seluruh halaman -->
<div class="modal fade" id="modalEdit" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST"><div class="modal-header"><h5 class="modal-title fw-bold">Edit Data Siswa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
    <input type="hidden" name="action" value="edit"><input type="hidden" name="siswa_id" id="editSiswaId"><input type="hidden" name="user_id" id="editUserId">
    <div class="row g-3"><div class="col-md-6"><label class="form-label">NIS</label><input type="text" name="nis" id="editNis" class="form-control" required></div><div class="col-md-6"><label class="form-label">NISN</label><input type="text" name="nisn" id="editNisn" class="form-control" required></div><div class="col-12"><label class="form-label">Nama Lengkap</label><input type="text" name="nama_lengkap" id="editNama" class="form-control" required></div><div class="col-12"><label class="form-label">Kelas</label><select name="kelas_id" id="editKelas" class="form-select" required><?php foreach($daftar_kelas as $k): ?><option value="<?= (int)$k['id'] ?>"><?= sanitize($k['nama_kelas']) ?></option><?php endforeach; ?></select></div><div class="col-12"><label class="form-label">Email</label><input type="email" name="email" id="editEmail" class="form-control"></div><div class="col-12"><label class="form-label">Password Baru</label><input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diubah"></div></div>
</div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan Perubahan</button></div></form></div></div></div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah"><div class="modal-dialog"><div class="modal-content p-3">
    <form method="POST"><input type="hidden" name="action" value="tambah">
        <h5>Tambah Siswa</h5>
        <input type="text" name="nis" placeholder="NIS" class="form-control mb-2" required>
        <input type="text" name="nisn" placeholder="NISN" class="form-control mb-2" required>
        <input type="text" name="nama_lengkap" placeholder="Nama Lengkap" class="form-control mb-2" required>
        <select name="kelas_id" class="form-select mb-2" required>
            <option value="">Pilih Kelas</option>
            <?php foreach($daftar_kelas as $k): ?><option value="<?= $k['id'] ?>"><?= $k['nama_kelas'] ?></option><?php endforeach; ?>
        </select>
        <input type="email" name="email" placeholder="Email" class="form-control mb-2">
        <input type="text" name="username" placeholder="Username" class="form-control mb-2" required>
        <input type="password" name="password" placeholder="Password" class="form-control" required>
        <button class="btn btn-primary mt-3 w-100">Simpan</button>
    </form>
</div></div></div>

<!-- Modal Import -->
<div class="modal fade" id="modalImport"><div class="modal-dialog"><div class="modal-content p-3">
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="import">
        <input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>">
        <h5 class="fw-bold">Import Banyak Siswa</h5>
        <p class="text-muted small">Unduh template, buka di Excel, lalu copy-paste data Siswa mulai baris kedua. Jangan mengubah nama kolom.</p>
        <a href="?download_template=1" class="btn btn-info mb-3"><i class="fa-solid fa-download me-1"></i> Download Template CSV</a>
        <div class="alert alert-light border small">
            <strong>Kolom wajib:</strong> NIS, NISN, Nama Lengkap, Nama Kelas, Username, Password.<br>
            <strong>Email:</strong> boleh dikosongkan.<br>
            <strong>Nama kelas harus sama persis:</strong><br>
            <?= implode(', ', array_map(static fn($kelas) => sanitize($kelas['nama_kelas']), $daftar_kelas)) ?>
        </div>
        <label class="form-label fw-semibold">Pilih file CSV</label>
        <input type="file" name="file_csv" class="form-control" accept=".csv,text/csv" required>
        <small class="text-muted">Maksimal 5 MB atau 5.000 baris per import.</small>
        <button class="btn btn-primary mt-3 w-100"><i class="fa-solid fa-upload me-1"></i> Proses Import</button>
    </form>
</div></div></div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script>
document.querySelectorAll('.btn-edit-siswa').forEach(function(button){
    button.addEventListener('click',function(){
        document.getElementById('editSiswaId').value=this.dataset.siswaId;
        document.getElementById('editUserId').value=this.dataset.userId;
        document.getElementById('editNis').value=this.dataset.nis;
        document.getElementById('editNisn').value=this.dataset.nisn;
        document.getElementById('editNama').value=this.dataset.nama;
        document.getElementById('editKelas').value=this.dataset.kelasId;
        document.getElementById('editEmail').value=this.dataset.email;
    });
});

(function(){
    const form = document.getElementById('formFilterSiswa');
    const search = document.getElementById('pencarianSiswa');
    const kelas = document.getElementById('filterKelasSiswa');
    let searchTimer;

    function submitFilter() {
        const oldPage = form.querySelector('input[name="page"]');
        if (oldPage) oldPage.remove();
        form.requestSubmit();
    }

    search.addEventListener('input', function(){
        clearTimeout(searchTimer);
        searchTimer = setTimeout(submitFilter, 500);
    });
    kelas.addEventListener('change', submitFilter);
})();
</script>
