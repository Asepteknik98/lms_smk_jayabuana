<?php
require_once __DIR__.'/../config/auth.php';
check_access([1]);
$db=Database::getInstance();$role=akademik_role_id($db);
$q=$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='akademik_profil'");
$ready=$role>0&&(bool)$q->fetchColumn();
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','POST'],true)){http_response_code(405);exit;}
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!is_string($_POST['csrf_token']??null)||!verify_csrf($_POST['csrf_token']))throw new RuntimeException('Sesi keamanan berakhir. Muat ulang halaman.');
        if(!$ready)throw new RuntimeException('Penyimpanan akun Akademik belum tersedia.');
        if(!is_string($_POST['id']??null)||!preg_match('/^\d+$/D',$_POST['id']))throw new RuntimeException('ID akun tidak valid.');
        akademik_save($db,(int)$_SESSION['user_id'],(int)$_POST['id'],$_POST['nama_lengkap']??null,$_POST['username']??null,$_POST['password']??null,$_POST['is_active']??null);
        $_SESSION['akademik_success']='Akun Akademik berhasil disimpan.';
    }catch(PDOException $e){error_log('Akun Akademik: '.$e->getMessage());$_SESSION['akademik_error']=$e->getCode()==='23000'?'Username sudah digunakan atau data akun tidak valid.':'Akun gagal disimpan. Tidak ada perubahan diterapkan.';}
    catch(RuntimeException $e){$_SESSION['akademik_error']=$e->getMessage();}
    redirect('akun_akademik.php');
}
$edit=null;$accounts=[];
if($ready){
    $q=$db->prepare('SELECT u.id,u.username,u.is_active,a.nama_lengkap FROM users u LEFT JOIN akademik_profil a ON a.user_id=u.id WHERE u.role_id=? ORDER BY u.id DESC');$q->execute([$role]);$accounts=$q->fetchAll();
    if(isset($_GET['edit'])){foreach($accounts as $account)if((int)$account['id']===(int)$_GET['edit'])$edit=$account;if(!$edit){http_response_code(404);exit('Akun Akademik tidak ditemukan.');}}
}
$success=$_SESSION['akademik_success']??'';$error=$_SESSION['akademik_error']??'';unset($_SESSION['akademik_success'],$_SESSION['akademik_error']);
require_once __DIR__.'/../includes/header.php';require_once __DIR__.'/../includes/sidebar.php';
?>
<div id="page-content-wrapper">
<nav class="navbar top-navbar px-4 py-3"><div><h1 class="h5 fw-bold mb-1"><i class="fa-solid fa-user-tie text-primary me-2" aria-hidden="true"></i>Akun Wakasek Bidang Akademik</h1><small class="text-muted">Kelola identitas, status akun, dan reset password.</small></div><a href="users.php" class="btn btn-outline-secondary btn-sm">Manajemen User</a></nav>
<main class="container-fluid p-3 p-md-4">
<?php if($success): ?><div class="alert alert-success" role="status"><?= sanitize($success) ?></div><?php endif ?>
<?php if($error): ?><div class="alert alert-danger" role="alert"><?= sanitize($error) ?></div><?php endif ?>
<?php if(!$ready): ?><div class="alert alert-warning">Fitur akun Akademik belum siap. Pengelola sistem perlu menerapkan migrasi tahap akun Akademik terlebih dahulu.</div><?php else: ?>
<div class="row g-4"><div class="col-xl-5"><section class="card border-0 shadow-sm"><div class="card-body p-4">
<h2 class="h6 fw-bold mb-3"><?= $edit?'Edit Akun / Reset Password':'Buat Akun Akademik' ?></h2>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="id" value="<?= (int)($edit['id']??0) ?>">
<div class="mb-3"><label for="akademikNama" class="form-label">Nama lengkap</label><input id="akademikNama" name="nama_lengkap" class="form-control" maxlength="100" required value="<?= sanitize($edit['nama_lengkap']??'') ?>"></div>
<div class="mb-3"><label for="akademikUsername" class="form-label">Username</label><input id="akademikUsername" name="username" class="form-control" minlength="3" maxlength="50" pattern="[A-Za-z0-9][A-Za-z0-9._-]{2,49}" autocomplete="off" required value="<?= sanitize($edit['username']??'') ?>"><small class="text-muted">Unik untuk seluruh akun LMS.</small></div>
<div class="mb-3"><label for="akademikPassword" class="form-label"><?= $edit?'Password baru (opsional)':'Password' ?></label><input id="akademikPassword" type="password" name="password" class="form-control" minlength="8" maxlength="72" autocomplete="new-password" <?= $edit?'':'required' ?>><small class="text-muted">8–72 byte. <?= $edit?'Kosongkan untuk mempertahankan password. Isi untuk mereset; sesi login lama akan dihentikan.':'Tentukan password sendiri; tidak ada password bawaan.' ?></small></div>
<div class="mb-3"><label for="akademikAktif" class="form-label">Status akun</label><select id="akademikAktif" name="is_active" class="form-select"><option value="1" <?= (int)($edit['is_active']??1)===1?'selected':'' ?>>Aktif</option><option value="0" <?= (int)($edit['is_active']??1)===0?'selected':'' ?>>Nonaktif</option></select></div>
<button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>Simpan Akun</button><?php if($edit): ?> <a href="akun_akademik.php" class="btn btn-outline-secondary">Batal</a><?php endif ?>
</form></div></section></div>
<div class="col-xl-7"><section class="card border-0 shadow-sm"><div class="card-body p-4"><h2 class="h6 fw-bold">Daftar Akun Akademik</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Nama</th><th>Username</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
<?php foreach($accounts as $account): ?><tr><td><?= sanitize($account['nama_lengkap']??'Profil belum dilengkapi') ?></td><td><?= sanitize($account['username']) ?></td><td><span class="badge <?= $account['is_active']?'bg-success':'bg-secondary' ?>"><?= $account['is_active']?'Aktif':'Nonaktif' ?></span></td><td><a class="btn btn-outline-primary btn-sm text-nowrap" href="?edit=<?= (int)$account['id'] ?>"><i class="fa-solid fa-pen-to-square me-1" aria-hidden="true"></i>Edit / Reset Password</a></td></tr><?php endforeach ?>
<?php if(!$accounts): ?><tr><td colspan="4" class="text-center text-muted py-4">Belum ada akun Akademik.</td></tr><?php endif ?>
</tbody></table></div><p class="small text-muted mb-0">Nonaktifkan akun melalui Edit. Penonaktifan berlaku pada request berikutnya.</p></div></section></div></div>
<?php endif ?>
</main></div><?php require_once __DIR__.'/../includes/footer.php'; ?>
