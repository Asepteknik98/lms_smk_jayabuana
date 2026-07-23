<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

check_access([3]);
$db = Database::getInstance();

$stmt_siswa = $db->prepare(
    'SELECT s.id, s.nama_lengkap, s.kelas_id, k.nama_kelas
     FROM siswa s
     LEFT JOIN kelas k ON k.id = s.kelas_id
     WHERE s.user_id = ?'
);
$stmt_siswa->execute([$_SESSION['user_id']]);
$siswa = $stmt_siswa->fetch();

$kelas_id = (int)($siswa['kelas_id'] ?? 0);
$pengajaran_id = (int)($_GET['pengajaran_id'] ?? 0);

$stmt_pengajaran = $db->prepare(
    'SELECT p.id, p.tahun_ajaran, p.semester, m.nama_mapel, g.nama_lengkap AS nama_guru
     FROM pengajaran p
     JOIN mapel m ON m.id = p.mapel_id
     JOIN guru g ON g.id = p.guru_id
     WHERE p.kelas_id = ?
     ORDER BY p.tahun_ajaran DESC, p.semester, m.nama_mapel'
);
$stmt_pengajaran->execute([$kelas_id]);
$pengajaran_list = $stmt_pengajaran->fetchAll();

$parameter = [$kelas_id];
$filter_pengajaran = '';
if ($pengajaran_id > 0) {
    $filter_pengajaran = ' AND p.id = ?';
    $parameter[] = $pengajaran_id;
}

$stmt_materi = $db->prepare(
    'SELECT mat.id, mat.pertemuan_ke, mat.judul, mat.deskripsi, mat.file_path, mat.created_at,
            CASE WHEN md.materi_id IS NULL THEN 1 ELSE 0 END AS is_baru,
            p.id AS pengajaran_id, p.tahun_ajaran, p.semester,
            m.nama_mapel, g.nama_lengkap AS nama_guru
     FROM materi mat
     JOIN pengajaran p ON p.id = mat.pengajaran_id
     JOIN mapel m ON m.id = p.mapel_id
     JOIN guru g ON g.id = p.guru_id
     LEFT JOIN materi_siswa_dibaca md ON md.materi_id=mat.id AND md.siswa_id='.(int)($siswa['id']??0).'
     WHERE p.kelas_id = ?' . $filter_pengajaran . '
     ORDER BY mat.created_at DESC, mat.id DESC'
);
$stmt_materi->execute($parameter);
$materi_list = $stmt_materi->fetchAll();
$materi_groups=[];foreach($materi_list as $materi){$key=(int)$materi['pengajaran_id'];if(!isset($materi_groups[$key]))$materi_groups[$key]=['nama_mapel'=>$materi['nama_mapel'],'nama_guru'=>$materi['nama_guru'],'semester'=>$materi['semester'],'tahun_ajaran'=>$materi['tahun_ajaran'],'items'=>[],'baru'=>0,'terbaru'=>$materi['created_at']];$materi_groups[$key]['items'][]=$materi;$materi_groups[$key]['baru']+=(int)$materi['is_baru'];}
$materi_baru_ids=array_column(array_filter($materi_list,static fn($m)=>(int)$m['is_baru']===1),'id');
if($materi_baru_ids&&$siswa){$tandai=$db->prepare('INSERT IGNORE INTO materi_siswa_dibaca(materi_id,siswa_id) VALUES(?,?)');$db->beginTransaction();foreach($materi_baru_ids as $mid)$tandai->execute([(int)$mid,(int)$siswa['id']]);$db->commit();}
?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
<style>.material-accordion .accordion-item{border:0;border-radius:17px!important;box-shadow:0 6px 20px rgba(15,23,42,.06);overflow:hidden}.material-accordion .accordion-button{padding:18px 20px;background:#fff}.material-accordion .accordion-button:not(.collapsed){color:#175fc0;background:#f5f9ff;box-shadow:none}.subject-icon{width:43px;height:43px;display:grid;place-items:center;border-radius:13px;background:#eaf3ff;color:#1769e0;flex:0 0 43px}.material-row{padding:16px 0;border-bottom:1px solid #edf1f5}.material-row:last-child{border-bottom:0}.material-title{overflow-wrap:anywhere}.material-description{max-height:100px;overflow:auto}@media(max-width:575.98px){.material-accordion .accordion-button{padding:14px}.material-accordion .accordion-body{padding:4px 14px 14px}.material-row .btn{min-height:44px}.subject-icon{width:39px;height:39px;flex-basis:39px}.subject-copy{min-width:0}.subject-copy strong,.subject-copy small{overflow-wrap:anywhere}}</style>

<div id="page-content-wrapper">
    <nav class="navbar navbar-expand-lg navbar-light top-navbar px-3 px-md-4 py-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 w-100">
            <h5 class="mb-0 fw-bold"><i class="fa-solid fa-book-open-reader text-primary me-2"></i> Materi Pembelajaran</h5>
            <span class="badge bg-primary px-3 py-2">Kelas: <?= sanitize($siswa['nama_kelas'] ?? 'Belum ditentukan') ?></span>
        </div>
    </nav>

    <div class="container-fluid p-3 p-md-4">
        <?php if (!$siswa): ?>
            <div class="alert alert-danger">Profil Siswa tidak ditemukan. Hubungi Administrator.</div>
        <?php elseif (!$kelas_id): ?>
            <div class="alert alert-warning">Anda belum ditempatkan pada kelas. Hubungi Administrator.</div>
        <?php else: ?>
            <div class="card border-0 shadow-sm p-3 mb-4">
                <form method="get" action="materi.php" class="row g-2 align-items-end">
                    <div class="col-md-9">
                        <label class="form-label fw-semibold">Filter Mata Pelajaran</label>
                        <select name="pengajaran_id" class="form-select">
                            <option value="0">Semua Mata Pelajaran</option>
                            <?php foreach ($pengajaran_list as $pengajaran): ?>
                                <option value="<?= (int)$pengajaran['id'] ?>" <?= $pengajaran_id === (int)$pengajaran['id'] ? 'selected' : '' ?>>
                                    <?= sanitize($pengajaran['nama_mapel']) ?> — <?= sanitize($pengajaran['nama_guru']) ?>
                                    (<?= sanitize($pengajaran['semester']) ?> <?= sanitize($pengajaran['tahun_ajaran']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-grid">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter me-1"></i> Tampilkan</button>
                    </div>
                </form>
            </div>

            <?php if (!$pengajaran_list): ?>
                <div class="alert alert-info">Belum ada mata pelajaran yang ditugaskan untuk kelas Anda.</div>
            <?php elseif (!$materi_list): ?>
                <div class="card border-0 shadow-sm p-5 text-center">
                    <i class="fa-solid fa-folder-open fa-3x text-secondary mb-3"></i>
                    <h6 class="fw-bold">Belum ada materi</h6>
                    <p class="text-muted mb-0">Guru belum mengunggah materi untuk pilihan ini.</p>
                </div>
            <?php else: ?>
                <p class="small text-muted mb-3"><i class="fa-solid fa-arrow-down-wide-short me-1"></i>Mata pelajaran dengan pembaruan terbaru ditampilkan paling atas.</p>
                <div class="accordion material-accordion d-grid gap-3" id="materialGroups">
                    <?php foreach($materi_groups as $groupIndex=>$group): $collapseId='materialGroup'.(int)$groupIndex;$open=$groupIndex===array_key_first($materi_groups); ?>
                    <section class="accordion-item"><h2 class="accordion-header"><button class="accordion-button <?= $open?'':'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>" aria-expanded="<?= $open?'true':'false' ?>"><span class="subject-icon me-3"><i class="fa-solid fa-book-open"></i></span><span class="subject-copy flex-grow-1"><strong class="d-block"><?= sanitize($group['nama_mapel']) ?></strong><small class="text-muted d-block"><i class="fa-solid fa-user-tie me-1"></i><?= sanitize($group['nama_guru']) ?> · <?= count($group['items']) ?> materi</small></span><?php if($group['baru']): ?><span class="badge bg-danger me-2"><?= $group['baru'] ?> baru</span><?php endif ?></button></h2>
                    <div id="<?= $collapseId ?>" class="accordion-collapse collapse <?= $open?'show':'' ?>" data-bs-parent="#materialGroups"><div class="accordion-body">
                        <?php foreach($group['items'] as $materi): ?><article class="material-row"><div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2"><span><span class="badge bg-primary-subtle text-primary">Pertemuan <?= (int)$materi['pertemuan_ke'] ?></span><?php if($materi['is_baru']): ?> <span class="badge bg-danger">Baru</span><?php endif ?></span><small class="text-muted"><i class="fa-regular fa-clock me-1"></i><?= date('d/m/Y H:i',strtotime($materi['created_at'])) ?></small></div><h3 class="h6 fw-bold material-title mb-2"><?= sanitize($materi['judul']) ?></h3><?php if($materi['deskripsi']): ?><div class="material-description text-secondary small mb-3"><?= nl2br(sanitize($materi['deskripsi'])) ?></div><?php endif ?><?php if($materi['file_path']): ?><a href="file_pembelajaran.php?jenis=materi&amp;id=<?= (int)$materi['id'] ?>&amp;mode=preview" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-eye me-1"></i>Buka Materi</a><?php else: ?><span class="badge bg-light text-muted border">Tanpa lampiran</span><?php endif ?></article><?php endforeach ?>
                    </div></div></section>
                    <?php endforeach ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
