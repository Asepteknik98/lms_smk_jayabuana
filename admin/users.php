<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();
$users = $db->query("SELECT u.*, r.nama_role FROM users u JOIN roles r ON u.role_id = r.id ORDER BY u.id DESC")->fetchAll();
$roles = $db->query("SELECT * FROM roles")->fetchAll();
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div id="page-content-wrapper">
    <nav class="navbar navbar-expand-lg navbar-light top-navbar px-4 py-3">
        <h5 class="mb-0 fw-bold">Manajemen User System</h5>
    </nav>

    <div class="container-fluid p-4">
        <div class="card border-0 shadow-sm p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold m-0"><i class="fa-solid fa-users me-2 text-primary"></i> Daftar Pengguna</h6>
                <button class="btn btn-primary btn-sm rounded-2" onclick="openModalCreate()">
                    <i class="fa-solid fa-plus me-1"></i> Tambah User
                </button>
            </div>

            <div class="table-responsive">
                <table id="tableUsers" class="table table-striped table-hover align-middle w-100">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $index => $u): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td class="fw-semibold"><?= sanitize($u['username']) ?></td>
                            <td><?= sanitize($u['email']) ?></td>
                            <td><span class="badge bg-secondary"><?= sanitize($u['nama_role']) ?></span></td>
                            <td>
                                <?php if($u['is_active']): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Non-Aktif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-warning btn-sm text-white me-1" onclick="openModalEdit(<?= $u['id'] ?>)">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <?php if($u['id'] !== $_SESSION['user_id']): ?>
                                <button class="btn btn-danger btn-sm" onclick="deleteUser(<?= $u['id'] ?>, '<?= sanitize($u['username']) ?>')">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Form User -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalTitle">Tambah User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="userForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="id" id="userId">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Username</label>
                        <input type="text" name="username" id="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" name="email" id="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password <small class="text-muted" id="passHelp">(Kosongkan jika tidak diubah)</small></label>
                        <input type="password" name="password" id="password" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Role</label>
                        <select name="role_id" id="role_id" class="form-select" required>
                            <?php foreach($roles as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= $r['nama_role'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3" id="statusGroup">
                        <label class="form-label fw-semibold">Status Akun</label>
                        <select name="is_active" id="is_active" class="form-select">
                            <option value="1">Aktif</option>
                            <option value="0">Non-Aktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
let modal;
let currentAction = 'create';

$(document).ready(function() {
    $('#tableUsers').DataTable();
    modal = new bootstrap.Modal(document.getElementById('userModal'));
});

function openModalCreate() {
    currentAction = 'create';
    $('#modalTitle').text('Tambah User Baru');
    $('#userForm')[0].reset();
    $('#userId').val('');
    $('#username').prop('readonly', false);
    $('#password').prop('required', true);
    $('#passHelp').hide();
    $('#statusGroup').hide();
    modal.show();
}

function openModalEdit(id) {
    currentAction = 'update';
    $('#modalTitle').text('Edit User');
    $('#passHelp').show();
    $('#password').prop('required', false);
    $('#statusGroup').show();
    
    $.get('user_action.php?action=get&id=' + id, function(res) {
        if(res.status === 'success') {
            $('#userId').val(res.data.id);
            $('#username').val(res.data.username).prop('readonly', true);
            $('#email').val(res.data.email);
            $('#role_id').val(res.data.role_id);
            $('#is_active').val(res.data.is_active);
            modal.show();
        }
    });
}

$('#userForm').on('submit', function(e) {
    e.preventDefault();
    $.post('user_action.php?action=' + currentAction, $(this).serialize(), function(res) {
        if(res.status === 'success') {
            modal.hide();
            Swal.fire('Berhasil!', res.message, 'success').then(() => location.reload());
        } else {
            Swal.fire('Gagal!', res.message, 'error');
        }
    }, 'json');
});

function deleteUser(id, username) {
    Swal.fire({
        title: 'Hapus User?',
        text: `Apakah Anda yakin ingin menghapus user ${username}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('user_action.php?action=delete', {
                id: id,
                csrf_token: '<?= $_SESSION['csrf_token'] ?>'
            }, function(res) {
                if(res.status === 'success') {
                    Swal.fire('Terhapus!', res.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Gagal!', res.message, 'error');
                }
            }, 'json');
        }
    });
}
</script>