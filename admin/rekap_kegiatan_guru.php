<?php
require_once __DIR__.'/../config/session.php';
require_once __DIR__.'/../config/auth.php';
require_once __DIR__.'/../includes/kegiatan_sekolah.php';
check_access([1]);
$db = Database::getInstance();
$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$error = '';
try { $date = ks_date($_GET['tanggal'] ?? $_GET['minggu'] ?? date('Y-m-d')); }
catch (InvalidArgumentException $e) { $date = new DateTimeImmutable('today'); $error = $e->getMessage(); }
$start = $date->modify('-'.((int)$date->format('N')-1).' days');
$end = $start->modify('+6 days');
$week = $start->format('Y-m-d');
$reportMode = ($_GET['rekap'] ?? '') === 'bulanan' ? 'bulanan' : 'mingguan';
$reportMonth = $date->modify('first day of this month');
try {
    $monthInput = $_GET['bulan'] ?? $date->format('Y-m');
    if (!is_string($monthInput) || !preg_match('/^[1-9][0-9]{3}-[0-9]{2}$/', $monthInput)) throw new InvalidArgumentException('Bulan rekap tidak valid.');
    $reportMonth = ks_date($monthInput.'-01');
} catch (InvalidArgumentException $e) { $error = $e->getMessage(); }
$reportStart = $reportMode === 'bulanan' ? $reportMonth : $start;
$reportEnd = $reportMode === 'bulanan' ? $reportMonth->modify('last day of this month') : $end;
$reportQuery = '&rekap='.$reportMode.'&bulan='.$reportMonth->format('Y-m');

$url = 'rekap_kegiatan_guru.php?tanggal='.$date->format('Y-m-d');
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
            $url = 'rekap_kegiatan_guru.php?tanggal='.$meetingDate->format('Y-m-d');
            $_SESSION['ks_success'] = 'Rapat ditambahkan. Silakan isi kehadirannya.';
        } elseif ($action === 'staff') {
            $name = is_string($_POST['nama'] ?? null) ? trim($_POST['nama']) : '';
            $nip = is_string($_POST['nip'] ?? null) ? trim($_POST['nip']) : '';
            if ($name === '' || mb_strlen($name)>100 || mb_strlen($nip)>30) throw new InvalidArgumentException('Nama staf wajib diisi (maksimal 100 karakter), NIP maksimal 30 karakter.');
            $db->prepare('INSERT INTO kegiatan_staf(nama_lengkap,nip) VALUES(?,?)')->execute([$name,$nip]);
            $_SESSION['ks_success'] = 'Staf ditambahkan ke daftar peserta seluruh kegiatan.';
        } else { throw new InvalidArgumentException('Aksi tidak valid.'); }
        redirect($url.'&kegiatan='.$selected.$reportQuery);
    } catch (InvalidArgumentException $e) { $error = $e->getMessage(); }
    catch (PDOException $e) { error_log($e->getMessage()); $error = 'Data gagal disimpan. Periksa duplikasi identitas staf atau koneksi database.'; }
}
$days = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];
$events = []; $byDate = []; $people = []; $attendance = []; $current = null; $summary = [];
if ($ready) {
    // Jadwal tanggal lain tidak dihapus ketika admin berpindah minggu.
    $seed = $db->prepare('INSERT INTO kegiatan_sekolah(tanggal,kode,nama) VALUES(?,?,?) ON DUPLICATE KEY UPDATE id=id');
    foreach ([[$start, $end], [$reportStart, $reportEnd]] as [$seedStart, $seedEnd]) {
        for ($day = $seedStart; $day <= $seedEnd; $day = $day->modify('+1 day')) {
            foreach (ks_schedule((int)$day->format('N')) as $code => $name) $seed->execute([$day->format('Y-m-d'), $code, $name]);
        }
    }
    $rangeParams = [$week, $end->format('Y-m-d'), $reportStart->format('Y-m-d'), $reportEnd->format('Y-m-d')];
    $stmt = $db->prepare('SELECT k.*,COUNT(h.peserta) jumlah,SUM(h.hadir=1) hadir FROM kegiatan_sekolah k LEFT JOIN kegiatan_kehadiran h ON h.kegiatan_id=k.id WHERE (k.tanggal BETWEEN ? AND ? OR k.tanggal BETWEEN ? AND ?) GROUP BY k.id ORDER BY k.tanggal,k.id');
    $stmt->execute($rangeParams); $events = $stmt->fetchAll();
    // Jadwal piket lama yang kosong digantikan dua sesi; riwayat tersimpan tetap tampil.
    $events = array_values(array_filter($events, static fn($event) =>
        $event['kode'] !== 'piket' || !empty($event['disimpan_pada']) || (int)$event['jumlah'] > 0
    ));
    $reportEvents = array_values(array_filter($events, static fn($event) =>
        $event['tanggal'] >= $reportStart->format('Y-m-d') && $event['tanggal'] <= $reportEnd->format('Y-m-d')
    ));
    foreach ($events as $event) { $byDate[$event['tanggal']][]=$event; if ((int)$event['id']===$selected) $current=$event; }
    // Keep old weekly activity links working; new filters only show the chosen date.
    if ($current && !isset($_GET['tanggal'])) $date = ks_date($current['tanggal']);
    if (!$current || $current['tanggal'] !== $date->format('Y-m-d')) {
        $current = $byDate[$date->format('Y-m-d')][0] ?? null;
        $selected = $current ? (int)$current['id'] : 0;
    }
    $url = 'rekap_kegiatan_guru.php?tanggal='.$date->format('Y-m-d');
    $people = ks_people($db);
    $stmt = $db->prepare('SELECT h.*,k.tanggal,k.nama kegiatan FROM kegiatan_kehadiran h JOIN kegiatan_sekolah k ON k.id=h.kegiatan_id WHERE (k.tanggal BETWEEN ? AND ? OR k.tanggal BETWEEN ? AND ?) ORDER BY h.nama_lengkap,k.tanggal,k.id');
    $stmt->execute($rangeParams); $records = $stmt->fetchAll();
    foreach ($people as $key=>$person) $summary[$key]=$person+['hadir'=>0,'tercatat'=>0];
    foreach ($records as $r) {
        $key=$r['peserta'];
        if ((int)$r['kegiatan_id']===$selected) { $attendance[$key]=$r; $people[$key] ??= $r; }
        if ($r['tanggal'] < $reportStart->format('Y-m-d') || $r['tanggal'] > $reportEnd->format('Y-m-d')) continue;
        if (!isset($summary[$key])) $summary[$key]=$r+['tercatat'=>0];
        if ($summary[$key]['tercatat']===0) $summary[$key]['hadir']=0;
        $summary[$key]['hadir']+=(int)$r['hadir']; $summary[$key]['tercatat']++;
    }
    if (($_GET['format'] ?? '') === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="Rekap_Kegiatan_'.$reportMode.'_'.($reportMode === 'bulanan' ? $reportMonth->format('Y-m') : $week).'.xls"');
        header('Cache-Control: no-store');
        $matrix = [];
        foreach ($records as $record) $matrix[$record['peserta']][(int)$record['kegiatan_id']] = (int)$record['hadir'];
        $exportPeople = $summary;
        uasort($exportPeople, static fn($a, $b) => strnatcasecmp($a['nama_lengkap'], $b['nama_lengkap']));
        echo "\xEF\xBB\xBF".'<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif}th{background:#edf2f7}th,td{padding:8px;vertical-align:middle}th{white-space:normal}</style></head><body><h2>Rekap Kegiatan Guru/Staf</h2><p>Periode: '.$esc($reportStart->format('d/m/Y').' s.d. '.$reportEnd->format('d/m/Y')).'</p><p>Hadir = dicentang; - = tidak dicentang atau belum diisi.</p><table border="1"><thead><tr><th>Nama guru/staf</th>';
        foreach ($reportEvents as $event) {
            echo '<th>'.$esc(ks_date($event['tanggal'])->format('d/m')).'<br>'.$esc($event['nama']).'</th>';
        }
        echo '<th>Jumlah kegiatan dihadiri</th></tr></thead><tbody>';
        foreach ($exportPeople as $key => $person) {
            echo '<tr><td style="mso-number-format:\@">'.$esc($person['nama_lengkap']).'</td>';
            $total = 0;
            foreach ($reportEvents as $event) {
                $present = ($matrix[$key][(int)$event['id']] ?? 0) === 1;
                $total += (int)$present;
                echo '<td style="text-align:center">'.($present ? 'Hadir' : '-').'</td>';
            }
            echo '<td style="text-align:center">'.($total > 0 ? $total : '-').'</td></tr>';
        }
        if (!$exportPeople) echo '<tr><td colspan="'.(count($reportEvents) + 2).'">Belum ada guru/staf untuk ditampilkan.</td></tr>';
        echo '</tbody></table></body></html>'; exit;
    }
}
$success = $_SESSION['ks_success'] ?? ''; unset($_SESSION['ks_success']);
require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/sidebar.php';
?>
<div id="page-content-wrapper" class="bg-light">
<nav class="navbar top-navbar px-4 py-3"><span class="fw-bold">Rekap Kegiatan Guru di Sekolah</span></nav>
<main class="container-fluid p-3 p-md-4 ks-page">
<style>
.ks-page{max-width:1100px;margin:auto;color:#253247}
.ks-heading{display:flex;justify-content:space-between;align-items:start;gap:16px;margin-bottom:24px}
.ks-panel{background:#fff;border:1px solid #e5e9ef;border-radius:12px;padding:24px;margin-bottom:20px}
.ks-filters{display:grid;grid-template-columns:minmax(180px,1fr) minmax(240px,2fr) auto;gap:16px;align-items:end;padding-bottom:24px;border-bottom:1px solid #edf0f3;margin-bottom:24px}
.ks-page .form-label{font-size:.875rem;font-weight:600}.ks-page .form-control,.ks-page .form-select{min-height:42px}
.ks-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:20px 0 8px}.ks-search{max-width:340px;width:100%}
.ks-page .table>:not(caption)>*>*{padding:12px 8px;border-bottom-color:#edf0f3}.ks-page th{font-size:.85rem;font-weight:600;color:#657185}.ks-page .form-check-input{width:20px;height:20px;cursor:pointer}
.ks-attendance-list{max-height:420px;max-height:min(420px,50vh);overflow:auto;scrollbar-gutter:stable;margin-top:12px}
.ks-attendance-list:focus-visible{outline:2px solid #0d6efd;outline-offset:2px}
.ks-people{list-style:none;margin:0;padding:0;column-count:2;column-gap:24px;column-rule:1px solid #edf0f3}
.ks-person{break-inside:avoid-column;border-bottom:1px solid #edf0f3}
.ks-person[hidden]{display:none!important}
@media(max-width:767px){.ks-people{column-count:1;column-rule:0}}
.ks-person-label{display:flex;align-items:center;gap:12px;min-height:44px;padding:10px 8px;line-height:1.4;font-size:.9rem;cursor:pointer;margin:0}
.ks-person-label:hover{background:#f7f9fc}.ks-person-label .ks-check{flex-shrink:0;margin:0}
.ks-person-type{font-size:.75rem;color:#657185;background:#f1f4f8;border-radius:4px;padding:2px 6px;white-space:nowrap}
.ks-person-name{min-width:0;overflow-wrap:anywhere}
.ks-savebar{display:flex;align-items:center;justify-content:space-between;gap:16px;padding-top:16px;border-top:1px solid #edf0f3;background:white}
.ks-extra{background:#fff;border:1px solid #e5e9ef;border-radius:10px}.ks-extra summary{cursor:pointer;font-size:.9rem}.ks-export{position:relative;flex-shrink:0}.ks-export summary{list-style:none}.ks-export summary::-webkit-details-marker{display:none}.ks-export-menu{position:absolute;right:0;top:100%;min-width:220px;padding:12px;background:white;border:1px solid #e5e9ef;border-radius:8px;box-shadow:0 6px 20px #25324712;z-index:10}.ks-export-menu a,.ks-export-menu button{display:block;width:100%;text-align:left;padding:8px;border:0;background:white;color:#253247;text-decoration:none}.ks-export-menu a:hover,.ks-export-menu button:hover{background:#f3f5f8}
@media(max-width:600px){.ks-panel{padding:16px}.ks-filters{grid-template-columns:1fr;gap:12px}.ks-toolbar{align-items:stretch;flex-direction:column}.ks-search{max-width:none}.ks-heading h1{font-size:1.25rem}.ks-savebar{position:sticky;bottom:0;padding:12px 0}.ks-savebar .btn{white-space:nowrap}}
@media print{#sidebar-wrapper,.top-navbar,.ks-controls,.ks-editor,.ks-heading{display:none!important}#page-content-wrapper{margin:0!important;width:100%!important}.ks-page{max-width:none}.ks-summary{border:0!important}.ks-summary>.ks-summary-body{display:block!important}.ks-summary>summary{list-style:none}body{font-size:11px}}
.ks-action-panel{max-width:480px;padding:20px;background:#fff;border:1px solid #e5e9ef;border-radius:10px}
.ks-summary-heading{display:flex;align-items:center;flex-wrap:wrap;gap:8px 16px;list-style:none}
.ks-summary-heading::-webkit-details-marker{display:none}.ks-summary-heading:before{content:'\25B8';color:#657185}.ks-summary[open]>.ks-summary-heading:before{content:'\25BE'}
.ks-summary-period{margin-left:auto;font-size:.8rem;font-weight:400}
.ks-summary-scroll{max-height:360px;overflow:auto;scrollbar-gutter:stable}
.ks-summary-scroll:focus-visible{outline:2px solid #0d6efd;outline-offset:2px}
.ks-page .ks-summary-table{font-size:.875rem;min-width:510px}
.ks-page .ks-summary-table>:not(caption)>*>*{padding:9px 12px;vertical-align:middle;background:#fff;border-bottom:1px solid #edf0f3;box-shadow:none}
.ks-summary-table thead th{position:sticky;top:0;z-index:1;font-size:.8rem;white-space:nowrap}
.ks-summary-table th:not(:first-child),.ks-summary-table td:not(:first-child){text-align:center;width:110px;font-variant-numeric:tabular-nums}
.ks-summary-table td:first-child{overflow-wrap:anywhere}
@media(max-width:600px){.ks-summary-period{flex-basis:100%;margin-left:20px}.ks-action-panel{max-width:none}}
@media print{.ks-summary-scroll{max-height:none!important;overflow:visible!important}.ks-page .ks-summary-table{min-width:0;font-size:10px}.ks-summary-table thead{display:table-header-group}.ks-summary-table thead th{position:static}.ks-summary-table tr{break-inside:avoid}.ks-summary-heading:before{display:none}}
</style>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= $esc($error) ?></div><?php endif ?>
<?php if ($success): ?><div class="alert alert-success" role="status"><?= $esc($success) ?></div><?php endif ?>
<div class="ks-heading">
<div><h1 class="h4 mb-2">Rekap Kegiatan Guru</h1><p class="text-muted mb-0">Pilih tanggal dan kegiatan, lalu isi kehadiran.</p></div>
<?php if ($ready): ?><details class="ks-export ks-controls"><summary class="btn btn-outline-secondary">Ekspor &#9662;</summary><div class="ks-export-menu"><p class="small text-muted mb-1">Rekap <?= $esc($reportMode) ?><br><?= $reportStart->format('d/m/Y') ?> &ndash; <?= $reportEnd->format('d/m/Y') ?></p><a href="<?= $esc($url.$reportQuery) ?>&amp;format=excel">Unduh Excel</a><button type="button" id="ks-print">Cetak / PDF</button></div></details><?php endif ?>
</div>
<?php if ($ready): ?>
<section class="ks-panel ks-editor" id="kehadiran">
<form method="get" class="ks-filters" id="ks-filter">
<input type="hidden" name="rekap" value="<?= $esc($reportMode) ?>"><input type="hidden" name="bulan" value="<?= $reportMonth->format('Y-m') ?>">
<div><label class="form-label" for="ks-date">Tanggal</label><input id="ks-date" type="date" name="tanggal" value="<?= $date->format('Y-m-d') ?>" class="form-control" required></div>
<div><label class="form-label" for="ks-event">Kegiatan</label><select id="ks-event" name="kegiatan" class="form-select" <?= empty($byDate[$date->format('Y-m-d')])?'disabled':'' ?>>
<?php foreach ($byDate[$date->format('Y-m-d')] ?? [] as $event): ?><option value="<?= (int)$event['id'] ?>" <?= (int)$event['id']===$selected?'selected':'' ?>><?= $esc($event['nama']) ?></option><?php endforeach ?>
<?php if (empty($byDate[$date->format('Y-m-d')])): ?><option>Tidak ada kegiatan</option><?php endif ?>
</select></div><button class="btn btn-outline-secondary" type="submit">Tampilkan</button>
</form>
<?php if ($current): ?>
<div class="d-flex flex-wrap justify-content-between gap-2"><h2 class="h5 mb-0">Daftar Guru &amp; Staf</h2><span class="small text-muted"><?= $current['disimpan_pada']?'Kehadiran sudah tersimpan':'Kehadiran belum diisi' ?></span></div>
<p class="small text-muted mt-2 mb-0">Centang guru/staf yang hadir. Yang tidak dicentang ditampilkan sebagai &quot;-&quot;.</p>
<form method="post" id="ks-form"><input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="kegiatan_id" value="<?= $selected ?>"><input type="hidden" name="versi" value="<?= (int)$current['versi'] ?>">
<div class="ks-toolbar"><div class="ks-search"><label class="visually-hidden" for="ks-search">Cari nama guru atau staf</label><input id="ks-search" type="search" class="form-control" placeholder="Cari nama..."></div><label class="small d-flex align-items-center gap-2"><input type="checkbox" id="ks-all" class="form-check-input mt-0">Pilih semua hasil pencarian</label></div>
<div class="ks-attendance-list" role="region" aria-label="Daftar kehadiran guru dan staf, gulir untuk melihat peserta lainnya" tabindex="0"><ul class="ks-people" aria-label="Guru dan staf">
<?php foreach ($people as $key=>$person): ?><li class="ks-person" data-name="<?= $esc($person['nama_lengkap']) ?>"><label class="ks-person-label" for="hadir-<?= $esc($key) ?>"><input id="hadir-<?= $esc($key) ?>" aria-label="Hadir: <?= $esc($person['nama_lengkap']) ?>" class="form-check-input ks-check" type="checkbox" name="hadir[]" value="<?= $esc($key) ?>" <?= !empty($attendance[$key]['hadir'])?'checked':'' ?>><span class="ks-person-name"><?= $esc($person['nama_lengkap']) ?></span><?php if ($person['jenis'] !== 'Guru'): ?><span class="ks-person-type"><?= $esc($person['jenis']) ?></span><?php endif ?></label></li><?php endforeach ?>
</ul>
<?php if (!$people): ?><p class="text-muted py-4 mb-0">Belum ada guru/staf. Tambahkan peserta terlebih dahulu.</p><?php endif ?>
<p id="ks-no-results" class="text-muted py-4 mb-0" hidden>Nama tidak ditemukan.</p>
</div><input type="hidden" name="form_complete" value="1"><div class="ks-savebar"><span id="ks-count" class="small text-muted" role="status"></span><button class="btn btn-primary" <?= !$people?'disabled':'' ?>>Simpan kehadiran</button></div></form>
<?php else: ?><div class="text-center py-4"><h2 class="h5">Belum ada kegiatan pada tanggal ini</h2><p class="text-muted mb-0">Pilih tanggal lain atau tambahkan rapat melalui formulir di bawah.</p></div><?php endif ?>
</section>
<div class="ks-editor ks-actions mb-4">
<div class="d-flex flex-wrap gap-2"><button type="button" class="btn btn-sm btn-outline-secondary" data-ks-panel="ks-meeting-panel" aria-controls="ks-meeting-panel" aria-expanded="false">+ Tambah rapat</button><button type="button" class="btn btn-sm btn-outline-secondary" data-ks-panel="ks-staff-panel" aria-controls="ks-staff-panel" aria-expanded="false">+ Tambah staf</button></div>
<section id="ks-meeting-panel" class="ks-action-panel mt-3" aria-labelledby="ks-meeting-title" hidden><h2 id="ks-meeting-title" class="h6">Tambah rapat</h2><form method="post" class="mt-3"><input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="meeting"><label class="form-label" for="rapat-tanggal">Tanggal rapat</label><input id="rapat-tanggal" class="form-control mb-2" type="date" name="tanggal" min="<?= $week ?>" max="<?= $end->format('Y-m-d') ?>" value="<?= $date->format('Y-m-d') ?>" required><label class="form-label" for="rapat-nama">Nama / agenda rapat</label><input id="rapat-nama" class="form-control mb-2" name="nama" maxlength="140" required><button class="btn btn-primary">Tambah rapat</button></form></section>
<section id="ks-staff-panel" class="ks-action-panel mt-3" aria-labelledby="ks-staff-title" hidden><h2 id="ks-staff-title" class="h6">Tambah staf</h2><p class="small text-muted mt-2">Seluruh guru dari Data Guru otomatis tersedia. Tambahkan staf yang belum terdaftar sebagai guru.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="staff"><label class="form-label" for="staf-nama">Nama lengkap staf</label><input id="staf-nama" class="form-control mb-2" name="nama" maxlength="100" required><label class="form-label" for="staf-nip">NIP / identitas (opsional)</label><input id="staf-nip" class="form-control mb-2" name="nip" maxlength="30"><button class="btn btn-primary">Tambah staf</button></form></section>
</div>
<form method="get" class="ks-controls d-flex flex-wrap align-items-end gap-3 mb-3" action="rekap_kegiatan_guru.php#ks-report">
<input type="hidden" name="tanggal" value="<?= $date->format('Y-m-d') ?>"><input type="hidden" name="kegiatan" value="<?= $selected ?>">
<div><label class="form-label" for="ks-report-mode">Periode rekap</label><select class="form-select" name="rekap" id="ks-report-mode"><option value="mingguan" <?= $reportMode === 'mingguan' ? 'selected' : '' ?>>Mingguan</option><option value="bulanan" <?= $reportMode === 'bulanan' ? 'selected' : '' ?>>Bulanan</option></select></div>
<div id="ks-month-field"><label class="form-label" for="ks-report-month">Bulan dan tahun</label><input class="form-control" type="month" id="ks-report-month" name="bulan" value="<?= $reportMonth->format('Y-m') ?>" required></div>
<button class="btn btn-outline-secondary" type="submit">Tampilkan rekap</button>
</form>
<details id="ks-report" class="ks-summary ks-extra p-3 p-md-4" <?= isset($_GET['rekap']) ? 'open' : '' ?>><summary class="ks-summary-heading"><span class="fw-semibold">Rekap <?= $esc($reportMode) ?> guru &amp; staf</span><span class="ks-summary-period text-muted"><?= $reportStart->format('d/m/Y') ?> &ndash; <?= $reportEnd->format('d/m/Y') ?></span></summary>
<div class="ks-summary-body mt-3"><p class="small text-muted mb-3">Hanya kehadiran yang dijumlahkan. Tanda &quot;-&quot; berarti belum ada kehadiran yang dicentang.</p>
<div class="ks-summary-scroll" role="region" aria-label="Rekap <?= $esc($reportMode) ?> guru dan staf" tabindex="0"><table class="table ks-summary-table mb-0"><thead><tr><th scope="col">Nama</th><th scope="col">Jumlah kegiatan dihadiri</th><th scope="col">Belum tercatat</th></tr></thead><tbody>
<?php foreach ($summary as $r): ?><tr><td><?= $esc($r['nama_lengkap']) ?><?php if ($r['jenis'] !== 'Guru'): ?> <span class="ks-person-type"><?= $esc($r['jenis']) ?></span><?php endif ?></td><td><?= (int)$r['hadir'] > 0 ? (int)$r['hadir'] : '-' ?></td><td><?= count($reportEvents)-(int)$r['tercatat'] ?></td></tr><?php endforeach ?>
<?php if (!$summary): ?><tr><td colspan="3" class="text-muted">Belum ada guru/staf untuk ditampilkan.</td></tr><?php endif ?>
</tbody></table></div></div></details>
<?php endif ?>
</main></div>
<script>
const reportMode = document.getElementById('ks-report-mode');
if (reportMode) {
    const syncReportMonth = () => {
        const monthly = reportMode.value === 'bulanan';
        document.getElementById('ks-month-field').hidden = !monthly;
        document.getElementById('ks-report-month').disabled = !monthly;
    };
    reportMode.addEventListener('change', syncReportMonth);
    syncReportMonth();
}
const panelButtons = [...document.querySelectorAll('[data-ks-panel]')];
panelButtons.forEach(button => button.addEventListener('click', () => {
    const open = button.getAttribute('aria-expanded') !== 'true';
    panelButtons.forEach(other => {
        const active = other === button && open;
        other.setAttribute('aria-expanded', String(active));
        document.getElementById(other.dataset.ksPanel).hidden = !active;
    });
}));
const ksForm = document.getElementById('ks-form');
if (ksForm) {
    let dirty = false;
    const checks = [...ksForm.querySelectorAll('.ks-check')];
    const rows = [...ksForm.querySelectorAll('.ks-person')];
    const all = document.getElementById('ks-all');
    const search = document.getElementById('ks-search');
    const visibleChecks = () => checks.filter(c => !c.closest('.ks-person').hidden);
    const sync = () => {
        const visible = visibleChecks();
        all.checked = visible.length > 0 && visible.every(c => c.checked);
        all.indeterminate = visible.some(c => c.checked) && !all.checked;
        all.disabled = visible.length === 0;
        document.getElementById('ks-count').textContent = checks.filter(c => c.checked).length + ' dari ' + checks.length + ' peserta hadir' + (dirty ? ' - Belum disimpan' : '');
    };
    search.addEventListener('input', () => {
        const query = search.value.trim().toLocaleLowerCase('id');
        ksForm.querySelector('.ks-attendance-list').scrollTop = 0;
        rows.forEach(row => row.hidden = !row.dataset.name.toLocaleLowerCase('id').includes(query));
        document.getElementById('ks-no-results').hidden = !rows.length || rows.some(row => !row.hidden);
        sync();
    });
    all.addEventListener('change', () => { visibleChecks().forEach(c => c.checked = all.checked); dirty = true; sync(); });
    checks.forEach(c => c.addEventListener('change', () => { dirty = true; sync(); }));
    sync();
    ksForm.addEventListener('submit', () => dirty = false);
    window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
}
const dateInput = document.getElementById('ks-date');
if (dateInput) dateInput.addEventListener('change', () => {
    if (dateInput.validity.valid) {
        document.getElementById('ks-filter').requestSubmit();
    }
});
const printButton = document.getElementById('ks-print');
if (printButton) printButton.addEventListener('click', () => window.print());
let summaryWasOpen = false;
window.addEventListener('beforeprint', () => { const summary = document.querySelector('.ks-summary'); if (summary) { summaryWasOpen = summary.open; summary.open = true; } });
window.addEventListener('afterprint', () => { const summary = document.querySelector('.ks-summary'); if (summary) summary.open = summaryWasOpen; });
</script>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
