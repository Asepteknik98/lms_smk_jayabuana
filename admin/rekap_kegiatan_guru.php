<?php
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../config/auth.php';
require_once __DIR__.'/../includes/kegiatan_sekolah.php';
check_access([1]);
$db = Database::getInstance();
$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$error = '';
try { $date = ks_date($_GET['minggu'] ?? date('Y-m-d')); }
catch (InvalidArgumentException $e) { $date = new DateTimeImmutable('today'); $error = $e->getMessage(); }
$start = $date->modify('-'.((int)$date->format('N')-1).' days');
$end = $start->modify('+6 days');
$week = $start->format('Y-m-d');
$url = 'rekap_kegiatan_guru.php?minggu='.$week;
$selected = max(0, (int)($_GET['kegiatan'] ?? 0));
$ready = true;
try { $db->query('SELECT id FROM kegiatan_sekolah LIMIT 1'); $db->query('SELECT peserta FROM kegiatan_kehadiran LIMIT 1'); $db->query('SELECT id FROM kegiatan_staf LIMIT 1'); }
catch (PDOException $e) { $ready = false; $error = 'Tabel kegiatan belum tersedia. Jalankan migrasi database/2026_09_10_kegiatan_sekolah.sql.'; }
if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!is_string($_POST['csrf_token'] ?? null) || !verify_csrf($_POST['csrf_token'])) throw new InvalidArgumentException('Token keamanan tidak valid. Silakan muat ulang halaman.');
        $action = $_POST['action'] ?? '';
        if ($action === 'save') {
            $selected = (int)($_POST['kegiatan_id'] ?? 0);
            if (!isset($_POST['form_complete']) || !is_array($_POST['hadir'] ?? [])) throw new InvalidArgumentException('Form tidak lengkap. Kehadiran belum disimpan.');
            ks_save($db,$selected,(int)($_POST['versi'] ?? -1),$_POST['hadir'] ?? [],(int)$_SESSION['user_id']);
            $_SESSION['ks_success'] = 'Kehadiran berhasil disimpan.';
        } elseif ($action === 'meeting') {
            $meetingDate = ks_date($_POST['tanggal'] ?? '');
            if ($meetingDate < $start || $meetingDate > $end) throw new InvalidArgumentException('Pilih tanggal dalam minggu yang ditampilkan.');
            $title = is_string($_POST['nama'] ?? null) ? trim($_POST['nama']) : '';
            if ($title === '' || mb_strlen($title) > 140) throw new InvalidArgumentException('Nama rapat wajib diisi, maksimal 140 karakter.');
            $stmt = $db->prepare('INSERT INTO kegiatan_sekolah(tanggal,kode,nama) VALUES(?,?,?)');
            $stmt->execute([$meetingDate->format('Y-m-d'),'rapat_'.bin2hex(random_bytes(12)),'Rapat: '.$title]);
            $selected = (int)$db->lastInsertId();
            $_SESSION['ks_success'] = 'Rapat ditambahkan. Silakan isi kehadirannya.';
        } elseif ($action === 'staff') {
            $name = is_string($_POST['nama'] ?? null) ? trim($_POST['nama']) : '';
            $nip = is_string($_POST['nip'] ?? null) ? trim($_POST['nip']) : '';
            if ($name === '' || mb_strlen($name)>100 || mb_strlen($nip)>30) throw new InvalidArgumentException('Nama staf wajib diisi (maksimal 100 karakter), NIP maksimal 30 karakter.');
            $db->prepare('INSERT INTO kegiatan_staf(nama_lengkap,nip) VALUES(?,?)')->execute([$name,$nip]);
            $_SESSION['ks_success'] = 'Staf ditambahkan ke daftar peserta seluruh kegiatan.';
        } else { throw new InvalidArgumentException('Aksi tidak valid.'); }
        redirect($url.'&kegiatan='.$selected);
    } catch (InvalidArgumentException $e) { $error = $e->getMessage(); }
    catch (PDOException $e) { error_log($e->getMessage()); $error = 'Data gagal disimpan. Periksa duplikasi identitas staf atau koneksi database.'; }
}
$days = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];
$events = []; $byDate = []; $people = []; $attendance = []; $current = null; $summary = [];
if ($ready) {
    // Jadwal tanggal lain tidak dihapus ketika admin berpindah minggu.
    $seed = $db->prepare('INSERT INTO kegiatan_sekolah(tanggal,kode,nama) VALUES(?,?,?) ON DUPLICATE KEY UPDATE id=id');
    for ($i=0;$i<7;$i++) foreach (ks_schedule($i+1) as $code=>$name) $seed->execute([$start->modify("+$i days")->format('Y-m-d'),$code,$name]);
    $stmt = $db->prepare('SELECT k.*,COUNT(h.peserta) jumlah,SUM(h.hadir=1) hadir FROM kegiatan_sekolah k LEFT JOIN kegiatan_kehadiran h ON h.kegiatan_id=k.id WHERE k.tanggal BETWEEN ? AND ? GROUP BY k.id ORDER BY k.tanggal,k.id');
    $stmt->execute([$week,$end->format('Y-m-d')]); $events = $stmt->fetchAll();
    foreach ($events as $event) { $byDate[$event['tanggal']][]=$event; if ((int)$event['id']===$selected) $current=$event; }
    $people = ks_people($db);
    $stmt = $db->prepare('SELECT h.*,k.tanggal,k.nama kegiatan FROM kegiatan_kehadiran h JOIN kegiatan_sekolah k ON k.id=h.kegiatan_id WHERE k.tanggal BETWEEN ? AND ? ORDER BY h.nama_lengkap,k.tanggal,k.id');
    $stmt->execute([$week,$end->format('Y-m-d')]); $records = $stmt->fetchAll();
    foreach ($people as $key=>$person) $summary[$key]=$person+['hadir'=>0,'tidak'=>0,'tercatat'=>0];
    foreach ($records as $r) {
        $key=$r['peserta'];
        if (!isset($summary[$key])) $summary[$key]=$r+['tidak'=>0,'tercatat'=>0];
        if ($summary[$key]['tercatat']===0) $summary[$key]['hadir']=0;
        $summary[$key]['hadir']+=(int)$r['hadir']; $summary[$key]['tidak']+=1-(int)$r['hadir']; $summary[$key]['tercatat']++;
        if ((int)$r['kegiatan_id']===$selected) { $attendance[$key]=$r; $people[$key] ??= $r; }
    }
    if (($_GET['format'] ?? '') === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="Rekap_Kegiatan_'.$week.'.xls"');
        header('Cache-Control: no-store');
        echo "\xEF\xBB\xBF".'<html><meta charset="UTF-8"><h2>Rekap Kegiatan Guru/Staf</h2><p>'.$esc($week.' s.d. '.$end->format('Y-m-d')).'</p><table border="1"><tr><th>Tanggal</th><th>Kegiatan</th><th>Nama</th><th>Jenis</th><th>Kehadiran</th></tr>';
        foreach ($records as $r) { echo '<tr>'; foreach ([$r['tanggal'],$r['kegiatan'],$r['nama_lengkap'],$r['jenis'],$r['hadir']?'Hadir':'Tidak hadir'] as $cell) echo '<td style="mso-number-format:\@">'.$esc($cell).'</td>'; echo '</tr>'; }
        echo '</table><p>Kegiatan yang belum disimpan tidak dihitung sebagai ketidakhadiran.</p></html>'; exit;
    }
}
$success = $_SESSION['ks_success'] ?? ''; unset($_SESSION['ks_success']);
require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/sidebar.php';
?>
<div id="page-content-wrapper" class="bg-light">
<nav class="navbar top-navbar px-4 py-3"><h5 class="fw-bold mb-0">Rekap Kegiatan Guru di Sekolah</h5></nav>
<main class="container-fluid p-3 p-md-4">
<style>.ks-week{display:grid;grid-template-columns:repeat(7,minmax(155px,1fr));gap:10px}.ks-day{background:white;border:1px solid #dee2e6;border-radius:12px;padding:12px}.ks-event{display:block;padding:9px;margin-top:8px;border:1px solid #dee2e6;border-radius:8px;text-decoration:none;font-size:.85rem}.ks-event.active{background:#e7f1ff;border-color:#0d6efd}.ks-scroll{overflow-x:auto}.ks-day.today{border:2px solid #0d6efd}@media print{#sidebar-wrapper,.top-navbar,.ks-controls,.ks-editor{display:none!important}#page-content-wrapper{margin:0!important;width:100%!important}.ks-week{grid-template-columns:repeat(7,1fr)}.ks-scroll{overflow:visible}.ks-event{font-size:9px}body{font-size:11px}}</style>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= $esc($error) ?></div><?php endif ?>
<?php if ($success): ?><div class="alert alert-success" role="status"><?= $esc($success) ?></div><?php endif ?>
<h1 class="h4">Kehadiran Kegiatan Guru &amp; Staf</h1>
<p class="text-muted">Senin–Minggu · <?= $start->format('d/m/Y') ?> – <?= $end->format('d/m/Y') ?>. Pilih kegiatan untuk mengisi ceklis kehadiran.</p>
<div class="ks-controls d-flex flex-wrap gap-2 mb-3 align-items-center">
<a class="btn btn-outline-primary" href="?minggu=<?= $start->modify('-7 days')->format('Y-m-d') ?>">&larr; Minggu sebelumnya</a>
<a class="btn btn-outline-primary" href="?minggu=<?= date('Y-m-d') ?>">Minggu ini</a>
<a class="btn btn-outline-primary" href="?minggu=<?= $start->modify('+7 days')->format('Y-m-d') ?>">Minggu berikutnya &rarr;</a>
<form method="get" class="d-flex gap-2"><label class="visually-hidden" for="minggu">Tanggal dalam minggu</label><input id="minggu" type="date" name="minggu" value="<?= $week ?>" class="form-control" required><button class="btn btn-primary">Tampilkan</button></form>
<?php if ($ready): ?><a class="btn btn-success" href="<?= $esc($url) ?>&amp;format=excel">Unduh Excel</a><button class="btn btn-outline-secondary" onclick="window.print()">Cetak / PDF</button><?php endif ?>
</div>
<?php if ($ready): ?>
<div class="ks-scroll mb-4"><div class="ks-week">
<?php for ($i=0;$i<7;$i++): $day=$start->modify("+$i days"); $key=$day->format('Y-m-d'); ?>
<section class="ks-day <?= $key===date('Y-m-d')?'today':'' ?>"><h2 class="h6 fw-bold"><?= $days[$i] ?></h2><small class="text-muted"><?= $day->format('d/m/Y') ?></small>
<?php foreach ($byDate[$key] ?? [] as $event): ?><a class="ks-event <?= (int)$event['id']===$selected?'active':'' ?>" href="<?= $esc($url) ?>&amp;kegiatan=<?= (int)$event['id'] ?>#kehadiran"><?= $esc($event['nama']) ?><small class="d-block mt-1 <?= $event['disimpan_pada']?'text-success':'text-muted' ?>"><?= $event['disimpan_pada']?(int)$event['hadir'].' / '.(int)$event['jumlah'].' hadir':'Belum diisi' ?></small></a><?php endforeach ?>
<?php if (empty($byDate[$key])): ?><p class="small text-muted mt-3">Tidak ada kegiatan rutin. Rapat dapat ditambahkan.</p><?php endif ?></section>
<?php endfor ?></div></div>
<div class="ks-editor row g-3 mb-4">
<div class="col-md-6"><details class="card p-3"><summary class="fw-bold">Tambah rapat (Senin–Minggu)</summary><form method="post" class="mt-3"><input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="meeting"><label class="form-label" for="rapat-tanggal">Tanggal rapat</label><input id="rapat-tanggal" class="form-control mb-2" type="date" name="tanggal" min="<?= $week ?>" max="<?= $end->format('Y-m-d') ?>" value="<?= $week ?>" required><label class="form-label" for="rapat-nama">Nama / agenda rapat</label><input id="rapat-nama" class="form-control mb-2" name="nama" maxlength="140" required><button class="btn btn-primary">Tambah rapat</button></form></details></div>
<div class="col-md-6"><details class="card p-3"><summary class="fw-bold">Tambah staf ke daftar peserta</summary><p class="small text-muted mt-2">Seluruh guru dari Data Guru otomatis tersedia. Tambahkan staf yang belum terdaftar sebagai guru.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="staff"><label class="form-label" for="staf-nama">Nama lengkap staf</label><input id="staf-nama" class="form-control mb-2" name="nama" maxlength="100" required><label class="form-label" for="staf-nip">NIP / identitas (opsional)</label><input id="staf-nip" class="form-control mb-2" name="nip" maxlength="30"><button class="btn btn-primary">Tambah staf</button></form></details></div>
</div>
<?php if ($current): ?>
<section id="kehadiran" class="card p-3 p-md-4 mb-4 ks-editor"><h2 class="h5"><?= $esc($current['nama']) ?> · <?= $esc($current['tanggal']) ?></h2><p class="text-muted small">Dicentang = hadir. Setelah disimpan, tidak dicentang = tidak hadir. Peserta baru belum tercatat sampai kehadiran disimpan kembali.</p>
<form method="post" id="ks-form"><input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="kegiatan_id" value="<?= $selected ?>"><input type="hidden" name="versi" value="<?= (int)$current['versi'] ?>">
<label class="d-block mb-3"><input type="checkbox" id="ks-all" class="form-check-input me-2">Centang semua guru/staf</label>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Hadir</th><th>Nama guru/staf</th><th>Jenis</th><th>Catatan tersimpan</th></tr></thead><tbody>
<?php foreach ($people as $key=>$person): ?><tr><td><input aria-label="Hadir: <?= $esc($person['nama_lengkap']) ?>" class="form-check-input ks-check" type="checkbox" name="hadir[]" value="<?= $esc($key) ?>" <?= !empty($attendance[$key]['hadir'])?'checked':'' ?>></td><td><?= $esc($person['nama_lengkap']) ?></td><td><?= $esc($person['jenis']) ?></td><td><?= isset($attendance[$key])?($attendance[$key]['hadir']?'Hadir':'Tidak hadir'):'Belum tercatat' ?></td></tr><?php endforeach ?>
<?php if (!$people): ?><tr><td colspan="4">Belum ada guru/staf. Tambahkan peserta terlebih dahulu.</td></tr><?php endif ?>
</tbody></table></div><input type="hidden" name="form_complete" value="1"><button class="btn btn-primary" <?= !$people?'disabled':'' ?>>Simpan kehadiran</button></form></section>
<?php endif ?>
<section class="card p-3 p-md-4"><h2 class="h5">Rekap mingguan seluruh guru/staf</h2><p class="small text-muted">Hanya kegiatan yang sudah diisi yang dihitung. Kegiatan belum diisi tidak dianggap tidak hadir.</p><div class="table-responsive"><table class="table table-striped"><thead><tr><th>Nama</th><th>Jenis</th><th>Hadir</th><th>Tidak hadir</th><th>Belum tercatat</th></tr></thead><tbody>
<?php foreach ($summary as $r): ?><tr><td><?= $esc($r['nama_lengkap']) ?></td><td><?= $esc($r['jenis']) ?></td><td><?= (int)$r['hadir'] ?></td><td><?= (int)$r['tidak'] ?></td><td><?= count($events)-(int)$r['tercatat'] ?></td></tr><?php endforeach ?>
</tbody></table></div></section>
<?php endif ?>
</main></div>
<script>
const ksForm=document.getElementById('ks-form');
if(ksForm){let dirty=false;const checks=[...ksForm.querySelectorAll('.ks-check')],all=document.getElementById('ks-all');const sync=()=>{all.checked=checks.length>0&&checks.every(c=>c.checked);all.indeterminate=checks.some(c=>c.checked)&&!all.checked;};all.addEventListener('change',()=>{checks.forEach(c=>c.checked=all.checked);dirty=true;sync();});checks.forEach(c=>c.addEventListener('change',()=>{dirty=true;sync();}));sync();ksForm.addEventListener('submit',()=>dirty=false);window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});}
</script>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
