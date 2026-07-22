<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helper.php';
require_once __DIR__ . '/../config/auth.php';

// Pastikan hanya Admin (role_id = 1) yang dapat mengakses
check_access([1]);

$db = Database::getInstance();

// Ambil pesan notifikasi dari Session (Pola PRG)
$success = $_SESSION['flash_success'] ?? '';
$error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// --- PROSES 1: TAMBAH GURU BARU ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah') {
    $nip          = trim($_POST['nip'] ?? '');
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $username     = trim($_POST['username'] ?? '');
    $password     = $_POST['password'] ?? '';

    if (empty($nama_lengkap) || empty($username) || empty($password)) {
        $_SESSION['flash_error'] = "Nama Lengkap, Username, dan Password wajib diisi!";
        header("Location: guru.php");
        exit;
    } else {
        try {
            // Cek apakah username sudah dipakai di tabel users
            $stmt_cek = $db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt_cek->execute([$username]);
            
            if ($stmt_cek->fetch()) {
                $_SESSION['flash_error'] = "Username '$username' sudah digunakan oleh akun lain!";
                header("Location: guru.php");
                exit;
            } else {
                $db->beginTransaction();

                // 1. Hash Password & Insert ke tabel users (role_id = 2 untuk Guru)
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $stmt_user = $db->prepare("
                    INSERT INTO users (username, password, email, role_id, is_active, created_at, updated_at) 
                    VALUES (?, ?, ?, 2, 1, NOW(), NOW())
                ");
                $stmt_user->execute([$username, $hashed_password, $email]);
                $user_id = $db->lastInsertId();

                // 2. Insert ke tabel guru
                $stmt_guru = $db->prepare("
                    INSERT INTO guru (user_id, nip, nama_lengkap, email, created_at) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt_guru->execute([$user_id, $nip, $nama_lengkap, $email]);

                $db->commit();
                $_SESSION['flash_success'] = "Data Guru berhasil ditambahkan!";
                header("Location: guru.php");
                exit;
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['flash_error'] = "Gagal menambah data: " . $e->getMessage();
            header("Location: guru.php");
            exit;
        }
    }
}

// --- PROSES 2: EDIT DATA GURU ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $guru_id      = (int)($_POST['guru_id'] ?? 0);
    $user_id      = (int)($_POST['user_id'] ?? 0);
    $nip          = trim($_POST['nip'] ?? '');
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $password     = $_POST['password'] ?? '';

    if (empty($guru_id) || empty($nama_lengkap)) {
        $_SESSION['flash_error'] = "Data tidak valid atau nama lengkap kosong!";
        header("Location: guru.php");
        exit;
    } else {
        try {
            $db->beginTransaction();

            // Update tabel guru
            $stmt_edit_guru = $db->prepare("
                UPDATE guru SET nip = ?, nama_lengkap = ?, email = ? WHERE id = ?
            ");
            $stmt_edit_guru->execute([$nip, $nama_lengkap, $email, $guru_id]);

            // Update email di users
            $stmt_edit_user = $db->prepare("UPDATE users SET email = ?, updated_at = NOW() WHERE id = ?");
            $stmt_edit_user->execute([$email, $user_id]);

            // Jika password diisi, update password baru
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $stmt_pass = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt_pass->execute([$hashed_password, $user_id]);
            }

            $db->commit();
            $_SESSION['flash_success'] = "Data Guru berhasil diperbarui!";
            header("Location: guru.php");
            exit;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['flash_error'] = "Gagal memperbarui data: " . $e->getMessage();
            header("Location: guru.php");
            exit;
        }
    }
}

// --- PROSES 3: HAPUS GURU ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['hapus'])) {
    $guru_id = (int)$_GET['hapus'];
    try {
        // Ambil user_id terkait
        $stmt_g = $db->prepare("SELECT user_id FROM guru WHERE id = ?");
        $stmt_g->execute([$guru_id]);
        $data_guru = $stmt_g->fetch();

        if ($data_guru) {
            $db->beginTransaction();
            
            // Hapus dari tabel guru
            $stmt_del_guru = $db->prepare("DELETE FROM guru WHERE id = ?");
            $stmt_del_guru->execute([$guru_id]);

            // Hapus dari tabel users
            $stmt_del_user = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt_del_user->execute([$data_guru['user_id']]);

            $db->commit();
            $_SESSION['flash_success'] = "Data Guru berhasil dihapus!";
        } else {
            $_SESSION['flash_error'] = "Data guru tidak ditemukan!";
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['flash_error'] = "Gagal menghapus data: " . $e->getMessage();
    }
    header("Location: guru.php");
    exit;
}

// --- QUERY AMBIL SELURUH DATA GURU ---
$stmt_list = $db->query("
    SELECT g.*, u.username, u.is_active 
    FROM guru g 
    JOIN users u ON g.user_id = u.id 
    ORDER BY g.nama_lengkap ASC
");
$daftar_guru = $stmt_list->fetchAll();
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div id="page-content-wrapper">
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom px-4 py-3">
        <div class="d-flex align-items-center justify-content-between w-100">
            <h5 class="mb-0 fw-bold"><i class="fa-solid fa-chalkboard-user text-primary me-2"></i> Manajemen Data Guru</h5>
            <button class="btn btn-primary rounded-3" data-bs-toggle="modal" data-bs-target="#modalTambahGuru">
                <i class="fa-solid fa-plus me-1"></i> Tambah Guru
            </button>
        </div>
    </nav>

    <div class="container-fluid p-4">

        <!-- Script Alert Flash Message -->
        <?php if (!empty($success)): ?>
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    Swal.fire({ icon: 'success', title: 'Berhasil!', text: '<?= addslashes($success) ?>', timer: 2000, showConfirmButton: false });
                });
            </script>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    Swal.fire({ icon: 'error', title: 'Gagal!', text: '<?= addslashes($error) ?>' });
                });
            </script>
        <?php endif; ?>

        <!-- Tabel Data Guru -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="50">No</th>
                                <th>NIP</th>
                                <th>Nama Lengkap</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th width="150">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($daftar_guru)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">Belum ada data guru yang terdaftar.</td>
                                </tr>
                            <?php else: ?>
                                <?php $no = 1; foreach ($daftar_guru as $g): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($g['nip'] ?: '-') ?></span></td>
                                        <td><strong><?= htmlspecialchars($g['nama_lengkap']) ?></strong></td>
                                        <td><code><?= htmlspecialchars($g['username']) ?></code></td>
                                        <td><?= htmlspecialchars($g['email'] ?: '-') ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning me-1 rounded-2" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalEditGuru<?= $g['id'] ?>">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                            <a href="guru.php?hapus=<?= $g['id'] ?>" 
                                               class="btn btn-sm btn-danger rounded-2" 
                                               onclick="return confirm('Apakah Anda yakin ingin menghapus guru ini beserta akunnya?');">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>

                                    <!-- MODAL EDIT GURU -->
                                    <div class="modal fade" id="modalEditGuru<?= $g['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form action="guru.php" method="POST">
                                                    <input type="hidden" name="action" value="edit">
                                                    <input type="hidden" name="guru_id" value="<?= $g['id'] ?>">
                                                    <input type="hidden" name="user_id" value="<?= $g['user_id'] ?>">
                                                    
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold">Edit Data Guru</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">NIP</label>
                                                            <input type="text" name="nip" class="form-control" value="<?= htmlspecialchars($g['nip'] ?? '') ?>">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Nama Lengkap *</label>
                                                            <input type="text" name="nama_lengkap" class="form-control" required value="<?= htmlspecialchars($g['nama_lengkap']) ?>">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Email</label>
                                                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($g['email'] ?? '') ?>">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Password Baru (Opsional)</label>
                                                            <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak ingin diubah">
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

<!-- MODAL TAMBAH GURU -->
<div class="modal fade" id="modalTambahGuru" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="guru.php" method="POST">
                <input type="hidden" name="action" value="tambah">
                
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-plus text-primary me-2"></i> Tambah Guru Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">NIP / NUPTK</label>
                        <input type="text" name="nip" class="form-control" placeholder="Masukkan NIP (jika ada)">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Lengkap beserta Gelar *</label>
                        <input type="text" name="nama_lengkap" class="form-control" required placeholder="Contoh: Asep Setiadi, S.Kom.">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" name="email" class="form-control" placeholder="guru@smkjayabuana.sch.id">
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Username Login *</label>
                        <input type="text" name="username" class="form-control" required placeholder="Username untuk masuk sistem" autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password Login *</label>
                        <input type="password" name="password" class="form-control" required placeholder="Password login">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Guru</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>