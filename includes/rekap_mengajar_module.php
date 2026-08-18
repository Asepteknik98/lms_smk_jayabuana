<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helper.php';

$isAdmin = !empty($rm_is_admin);
check_access([$isAdmin ? 1 : 2]);
$db = Database::getInstance();
$pageName = $isAdmin ? 'rekap_guru_mengajar.php' : 'rekap_mengajar.php';
$pageTitle = $isAdmin ? 'Rekap Guru Mengajar' : 'Rekap Mengajar';

function rm_date_valid(string $value): bool {
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
}
function rm_number(mixed $value): string {
    return number_format((float)$value, 2, ',', '.');
}
function rm_pdf_escape(mixed $value): string {
    $value = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', (string)$value);
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string)$value);
}
function rm_pdf(array $lines, string $filename): never {
    $chunks = array_chunk($lines ?: ['Tidak ada data.'], 43);
    $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>'];
    $kids = []; $number = 4;
    foreach ($chunks as $index => $chunk) {
        $page = $number++; $content = $number++; $kids[] = "$page 0 R";
        $command = "BT /F1 8 Tf 32 560 Td 11.5 TL\n";
        foreach ($chunk as $line) $command .= '(' . rm_pdf_escape($line) . ") Tj T*\n";
        $command .= '(Halaman ' . ($index + 1) . ' dari ' . count($chunks) . ") Tj\nET";
        $objects[$page] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R >> >> /Contents $content 0 R >>";
        $objects[$content] = "<< /Length " . strlen($command) . " >>\nstream\n$command\nendstream";
    }
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
    ksort($objects); $pdf = "%PDF-1.4\n"; $offset = [0];
    foreach ($objects as $id => $object) { $offset[$id] = strlen($pdf); $pdf .= "$id 0 obj\n$object\nendobj\n"; }
    $xref = strlen($pdf); $max = max(array_keys($objects));
    $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
    for ($i=1; $i<=$max; $i++) $pdf .= sprintf('%010d 00000 n ', $offset[$i]) . "\n";
    $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: no-store');
    echo $pdf; exit;
}

$ownGuruId = 0;
if (!$isAdmin) {
    $stmt = $db->prepare('SELECT id FROM guru WHERE user_id=?');
    $stmt->execute([$_SESSION['user_id']]);
    $ownGuruId = (int)$stmt->fetchColumn();
    if (!$ownGuruId) { http_response_code(403); exit('Profil Guru tidak ditemukan.'); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdmin) { http_response_code(405); exit('Metode tidak diizinkan.'); }
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Token keamanan tidak valid.';
        redirect($pageName);
    }
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $teachingId = (int)($_POST['pengajaran_id'] ?? 0);
            $date = trim($_POST['tanggal'] ?? '');
            $start = trim($_POST['jam_mulai'] ?? '');
            $end = trim($_POST['jam_selesai'] ?? '');
            $session = trim($_POST['sesi'] ?? '');
            $jp = filter_var($_POST['jumlah_jp'] ?? null, FILTER_VALIDATE_FLOAT);
            if (!rm_date_valid($date) || !preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end)
                || $end <= $start || $session === '' || mb_strlen($session) > 100 || $jp === false || $jp <= 0 || $jp > 100) {
                throw new InvalidArgumentException('Tanggal, jam/sesi, atau jumlah JP tidak valid.');
            }
            $check = $db->prepare('SELECT COUNT(*) FROM pengajaran WHERE id=?'); $check->execute([$teachingId]);
            if (!(int)$check->fetchColumn()) throw new InvalidArgumentException('Data pengajaran tidak ditemukan.');
            if ($id) {
                $stmt = $db->prepare('UPDATE rekap_mengajar SET pengajaran_id=?,tanggal=?,jam_mulai=?,jam_selesai=?,sesi=?,jumlah_jp=? WHERE id=?');
                $stmt->execute([$teachingId,$date,$start,$end,$session,$jp,$id]);
                $check = $db->prepare('SELECT COUNT(*) FROM rekap_mengajar WHERE id=?'); $check->execute([$id]);
                if (!(int)$check->fetchColumn()) throw new InvalidArgumentException('Realisasi tidak ditemukan.');
                catat_log($_SESSION['user_id'], "Mengubah realisasi mengajar ID: $id");
                $_SESSION['flash_success'] = 'Realisasi berhasil diperbarui.';
            } else {
                $stmt = $db->prepare('INSERT INTO rekap_mengajar (pengajaran_id,tanggal,jam_mulai,jam_selesai,sesi,jumlah_jp,dibuat_oleh) VALUES (?,?,?,?,?,?,?)');
                $stmt->execute([$teachingId,$date,$start,$end,$session,$jp,$_SESSION['user_id']]);
                $id = (int)$db->lastInsertId();
                catat_log($_SESSION['user_id'], "Menambahkan realisasi mengajar ID: $id");
                $_SESSION['flash_success'] = 'Realisasi berhasil divalidasi dan disimpan.';
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $db->prepare('DELETE FROM rekap_mengajar WHERE id=?'); $stmt->execute([$id]);
            if (!$stmt->rowCount()) throw new InvalidArgumentException('Realisasi tidak ditemukan.');
            catat_log($_SESSION['user_id'], "Menghapus realisasi mengajar ID: $id");
            $_SESSION['flash_success'] = 'Realisasi berhasil dihapus.';
        } elseif ($action === 'allocation') {
            $teachingId = (int)($_POST['pengajaran_id'] ?? 0);
            $allocation = filter_var($_POST['alokasi_jp_mingguan'] ?? null, FILTER_VALIDATE_FLOAT);
            if ($teachingId < 1 || $allocation === false || $allocation < 0 || $allocation > 100) throw new InvalidArgumentException('Alokasi tidak valid.');
            $stmt = $db->prepare('UPDATE pengajaran SET alokasi_jp_mingguan=? WHERE id=?'); $stmt->execute([$allocation,$teachingId]);
            $check = $db->prepare('SELECT COUNT(*) FROM pengajaran WHERE id=?'); $check->execute([$teachingId]);
            if (!(int)$check->fetchColumn()) throw new InvalidArgumentException('Pengajaran tidak ditemukan.');
            catat_log($_SESSION['user_id'], "Mengubah alokasi pengajaran ID: $teachingId menjadi $allocation JP/minggu");
            $_SESSION['flash_success'] = 'Alokasi JP mingguan berhasil disimpan.';
        } else throw new InvalidArgumentException('Aksi tidak dikenali.');
    } catch (InvalidArgumentException $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Rekap mengajar: ' . $e->getMessage());
        $_SESSION['flash_error'] = 'Data gagal diproses. Pastikan migrasi database telah dijalankan.';
    }
    redirect($pageName);
}

$from = trim($_GET['tanggal_mulai'] ?? date('Y-m-01'));
$to = trim($_GET['tanggal_selesai'] ?? date('Y-m-t'));
if (!rm_date_valid($from)) $from = date('Y-m-01');
if (!rm_date_valid($to)) $to = date('Y-m-t');
if ($from > $to) [$from,$to] = [$to,$from];
$teacherFilter = $isAdmin ? max(0,(int)($_GET['guru_id'] ?? 0)) : $ownGuruId;
$classFilter = max(0,(int)($_GET['kelas_id'] ?? 0));
$subjectFilter = max(0,(int)($_GET['mapel_id'] ?? 0));
$semesterFilter = in_array($_GET['semester'] ?? '', ['Ganjil','Genap'], true) ? $_GET['semester'] : '';
$yearFilter = preg_match('/^\d{4}\/\d{4}$/', $_GET['tahun_ajaran'] ?? '') ? $_GET['tahun_ajaran'] : '';
$where = ['r.tanggal BETWEEN ? AND ?']; $params = [$from,$to];
foreach ([['p.guru_id',$teacherFilter],['p.kelas_id',$classFilter],['p.mapel_id',$subjectFilter]] as [$column,$value]) {
    if ($value) { $where[] = "$column=?"; $params[] = $value; }
}
if ($semesterFilter) { $where[]='p.semester=?'; $params[]=$semesterFilter; }
if ($yearFilter) { $where[]='p.tahun_ajaran=?'; $params[]=$yearFilter; }
$whereSql = implode(' AND ', $where);
$joins = ' FROM rekap_mengajar r JOIN pengajaran p ON p.id=r.pengajaran_id JOIN guru g ON g.id=p.guru_id JOIN kelas k ON k.id=p.kelas_id JOIN mapel m ON m.id=p.mapel_id ';
$select = 'SELECT r.id,r.pengajaran_id,r.tanggal,r.jam_mulai,r.jam_selesai,r.sesi,r.jumlah_jp,p.semester,p.tahun_ajaran,p.alokasi_jp_mingguan,g.id guru_id,g.nip,g.nama_lengkap,k.nama_kelas,m.nama_mapel';
$stmt = $db->prepare($select.$joins.'WHERE '.$whereSql.' ORDER BY r.tanggal DESC,r.jam_mulai DESC,g.nama_lengkap');
$stmt->execute($params); $details = $stmt->fetchAll();
$stmt = $db->prepare('SELECT r.tanggal,g.nama_lengkap,SUM(r.jumlah_jp) total'.$joins.'WHERE '.$whereSql.' GROUP BY r.tanggal,g.id,g.nama_lengkap ORDER BY r.tanggal DESC,g.nama_lengkap');
$stmt->execute($params); $daily = $stmt->fetchAll();
$stmt = $db->prepare('SELECT YEARWEEK(r.tanggal,3) week_code,MIN(r.tanggal) reference_date,g.nama_lengkap,SUM(r.jumlah_jp) total'.$joins.'WHERE '.$whereSql.' GROUP BY YEARWEEK(r.tanggal,3),g.id,g.nama_lengkap ORDER BY week_code DESC,g.nama_lengkap');
$stmt->execute($params); $weekly = $stmt->fetchAll();
$weekStart=(new DateTime($from))->modify('monday this week'); $weekEnd=(new DateTime($to))->modify('monday this week');
$weekCount=(int)(($weekEnd->getTimestamp()-$weekStart->getTimestamp())/604800)+1;
$summary=[];
$allocationWhere=[];$allocationParams=[];
foreach([['p.guru_id',$teacherFilter],['p.kelas_id',$classFilter],['p.mapel_id',$subjectFilter]] as [$column,$value]){
    if($value){$allocationWhere[]="$column=?";$allocationParams[]=$value;}
}
if($semesterFilter){$allocationWhere[]='p.semester=?';$allocationParams[]=$semesterFilter;}
if($yearFilter){$allocationWhere[]='p.tahun_ajaran=?';$allocationParams[]=$yearFilter;}
$allocationSql='SELECT p.id pengajaran_id,p.guru_id,p.alokasi_jp_mingguan,g.nama_lengkap FROM pengajaran p JOIN guru g ON g.id=p.guru_id';
if($allocationWhere)$allocationSql.=' WHERE '.implode(' AND ',$allocationWhere);
$stmt=$db->prepare($allocationSql);$stmt->execute($allocationParams);
foreach($stmt->fetchAll() as $row){
    $gid=(int)$row['guru_id'];
    if(!isset($summary[$gid]))$summary[$gid]=['name'=>$row['nama_lengkap'],'actual'=>0.0,'teaching'=>[]];
    $summary[$gid]['teaching'][(int)$row['pengajaran_id']]=(float)$row['alokasi_jp_mingguan'];
}
foreach($details as $row){
    $gid=(int)$row['guru_id'];
    if(!isset($summary[$gid])) $summary[$gid]=['name'=>$row['nama_lengkap'],'actual'=>0.0,'teaching'=>[]];
    $summary[$gid]['actual']+=(float)$row['jumlah_jp'];
    $summary[$gid]['teaching'][(int)$row['pengajaran_id']]=(float)$row['alokasi_jp_mingguan'];
}
foreach($summary as &$item){
    $item['weekly_allocation']=array_sum($item['teaching']); $item['allocation']=$item['weekly_allocation']*$weekCount;
    $item['difference']=$item['actual']-$item['allocation']; $item['percentage']=$item['allocation']>0?$item['actual']/$item['allocation']*100:null;
} unset($item);
$totalActual=array_sum(array_column($summary,'actual')); $totalAllocation=array_sum(array_column($summary,'allocation'));
$totalPercentage=$totalAllocation>0?$totalActual/$totalAllocation*100:null;
$state=array_filter(['tanggal_mulai'=>$from,'tanggal_selesai'=>$to,'guru_id'=>$isAdmin?$teacherFilter:null,'kelas_id'=>$classFilter,'mapel_id'=>$subjectFilter,'semester'=>$semesterFilter,'tahun_ajaran'=>$yearFilter],static fn($v)=>$v!==''&&$v!==null&&$v!==0);

if(in_array($_GET['format']??'', ['excel','pdf'], true)){
    $filename=($isAdmin?'Rekap_Guru_Mengajar_':'Rekap_Mengajar_Saya_').date('Ymd_His');
    if($_GET['format']==='excel'){
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8'); header('Content-Disposition: attachment; filename="'.$filename.'.xls"'); header('Cache-Control: no-store');
        echo "\xEF\xBB\xBF"; ?><table border="1"><tr><th colspan="10">REKAP JAM MENGAJAR GURU - SMKS JAYA BUANA</th></tr><tr><td colspan="10">Periode <?= date('d/m/Y',strtotime($from)) ?> s.d. <?= date('d/m/Y',strtotime($to)) ?></td></tr><tr><th>No</th><th>Tanggal</th><th>Guru</th><th>Kelas</th><th>Mata Pelajaran</th><th>Semester</th><th>Tahun Ajaran</th><th>Jam/Sesi</th><th>JP</th><th>Validasi</th></tr><?php foreach($details as $i=>$row): ?><tr><td><?= $i+1 ?></td><td><?= date('d/m/Y',strtotime($row['tanggal'])) ?></td><td><?= sanitize($row['nama_lengkap']) ?></td><td><?= sanitize($row['nama_kelas']) ?></td><td><?= sanitize($row['nama_mapel']) ?></td><td><?= sanitize($row['semester']) ?></td><td><?= sanitize($row['tahun_ajaran']) ?></td><td><?= substr($row['jam_mulai'],0,5).'-'.substr($row['jam_selesai'],0,5).' / '.sanitize($row['sesi']) ?></td><td><?= (float)$row['jumlah_jp'] ?></td><td>Divalidasi Admin</td></tr><?php endforeach ?><tr><th colspan="8">TOTAL REALISASI</th><th><?= $totalActual ?></th><th></th></tr><tr></tr><tr><th>Guru</th><th>Alokasi/Minggu</th><th>Alokasi Periode</th><th>Realisasi</th><th>Selisih</th><th>Capaian</th></tr><?php foreach($summary as $item): ?><tr><td><?= sanitize($item['name']) ?></td><td><?= $item['weekly_allocation'] ?></td><td><?= $item['allocation'] ?></td><td><?= $item['actual'] ?></td><td><?= $item['difference'] ?></td><td><?= $item['percentage']===null?'Belum diatur':$item['percentage'].'%' ?></td></tr><?php endforeach ?></table><?php exit;
    }
    $lines=['REKAP JAM MENGAJAR GURU - SMKS JAYA BUANA','Periode: '.date('d/m/Y',strtotime($from)).' s.d. '.date('d/m/Y',strtotime($to)),'Dicetak: '.date('d/m/Y H:i').' | Realisasi tervalidasi Admin',str_repeat('-',125),sprintf('%-3s %-10s %-24s %-13s %-24s %-15s %6s','No','Tanggal','Guru','Kelas','Mata Pelajaran','Jam/Sesi','JP'),str_repeat('-',125)];
    foreach($details as $i=>$row){$short=static fn($v,$w)=>function_exists('mb_strimwidth')?mb_strimwidth($v,0,$w,'..','UTF-8'):substr($v,0,$w);$lines[]=sprintf('%-3d %-10s %-24s %-13s %-24s %-15s %6s',$i+1,date('d/m/Y',strtotime($row['tanggal'])),$short($row['nama_lengkap'],24),$short($row['nama_kelas'],13),$short($row['nama_mapel'],24),substr($row['jam_mulai'],0,5).'-'.substr($row['jam_selesai'],0,5),rm_number($row['jumlah_jp']));}
    $lines[]=str_repeat('-',125);$lines[]='Realisasi: '.rm_number($totalActual).' JP | Alokasi: '.rm_number($totalAllocation).' JP | Capaian: '.($totalPercentage===null?'Belum diatur':rm_number($totalPercentage).'%');$lines[]='';$lines[]='RINGKASAN PER GURU';foreach($summary as $item)$lines[]=$item['name'].' | Alokasi '.rm_number($item['allocation']).' | Realisasi '.rm_number($item['actual']).' | Selisih '.rm_number($item['difference']).' | '.($item['percentage']===null?'Belum diatur':rm_number($item['percentage']).'%');$lines[]='';$lines[]='Mengetahui,';$lines[]='';$lines[]='';$lines[]='Kepala Sekolah / Pejabat Berwenang';
    rm_pdf($lines,$filename);
}

$teachers=$isAdmin?$db->query('SELECT id,nama_lengkap FROM guru ORDER BY nama_lengkap')->fetchAll():[];
$classes=$db->query('SELECT id,nama_kelas FROM kelas ORDER BY nama_kelas')->fetchAll();
$subjects=$db->query('SELECT id,nama_mapel FROM mapel ORDER BY nama_mapel')->fetchAll();
$years=$db->query('SELECT DISTINCT tahun_ajaran FROM pengajaran ORDER BY tahun_ajaran DESC')->fetchAll(PDO::FETCH_COLUMN);
$teachingSql='SELECT p.id,p.alokasi_jp_mingguan,p.semester,p.tahun_ajaran,g.nama_lengkap,k.nama_kelas,m.nama_mapel FROM pengajaran p JOIN guru g ON g.id=p.guru_id JOIN kelas k ON k.id=p.kelas_id JOIN mapel m ON m.id=p.mapel_id';$teachingParams=[];
if(!$isAdmin){$teachingSql.=' WHERE p.guru_id=?';$teachingParams[]=$ownGuruId;}$teachingSql.=' ORDER BY p.tahun_ajaran DESC,p.semester,g.nama_lengkap,m.nama_mapel,k.nama_kelas';
$stmt=$db->prepare($teachingSql);$stmt->execute($teachingParams);$teachings=$stmt->fetchAll();
$edit=null;if($isAdmin&&($editId=(int)($_GET['edit_id']??0))){$stmt=$db->prepare('SELECT * FROM rekap_mengajar WHERE id=?');$stmt->execute([$editId]);$edit=$stmt->fetch()?:null;}
$success=$_SESSION['flash_success']??'';$error=$_SESSION['flash_error']??'';unset($_SESSION['flash_success'],$_SESSION['flash_error']);
require_once __DIR__.'/header.php';require_once __DIR__.'/sidebar.php';
?>
<style>
.rm-page .table-responsive table{min-width:720px}
@media(max-width:575.98px){
    .rm-page .d-flex.flex-wrap.justify-content-between.mb-3>div:last-child{display:grid;grid-template-columns:1fr 1fr;width:100%;gap:.5rem;margin-top:.5rem}
    .rm-page .d-flex.flex-wrap.justify-content-between.mb-3>div:last-child .btn{width:100%;white-space:nowrap}
    .rm-page .table-responsive table{font-size:.82rem}
}
</style>
<div id="page-content-wrapper" class="rm-page" style="background:#f5f7fb;min-width:0"><style>.rm-page .card{border-radius:16px}.rm-page .form-control,.rm-page .form-select{min-height:38px}.rm-report-table{min-width:720px}.rm-actions{white-space:nowrap}@media(max-width:575.98px){.rm-page .card-body{padding:1rem}.rm-page h4{font-size:1.15rem}.rm-page .top-navbar small{font-size:.75rem}.rm-export-actions{display:grid;grid-template-columns:1fr 1fr;width:100%;gap:.5rem;margin-top:.5rem}.rm-export-actions .btn{width:100%;white-space:nowrap}.rm-page main{overflow-x:hidden}.rm-report-table{font-size:.82rem}}</style><nav class="navbar top-navbar px-3 px-md-4 py-3"><div><h5 class="fw-bold mb-0"><i class="fa-solid fa-person-chalkboard text-primary me-2"></i><?= sanitize($pageTitle) ?></h5><small class="text-muted">Realisasi jam mengajar tervalidasi Admin, tanpa status kehadiran</small></div></nav><main class="container-fluid p-3 p-md-4" style="max-width:1450px;margin:auto">
<?php if($success): ?><div class="alert alert-success"><?= sanitize($success) ?></div><?php endif ?><?php if($error): ?><div class="alert alert-danger"><?= sanitize($error) ?></div><?php endif ?>
<?php if($isAdmin): ?><section class="card border-0 shadow-sm mb-4"><div class="card-body"><h6 class="fw-bold"><?= $edit?'Ubah':'Input' ?> Realisasi Mengajar</h6><form method="post" class="row g-2 align-items-end"><input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id']??0) ?>"><div class="col-lg-4"><label class="form-label small mb-1">Guru · Mapel · Kelas · Periode</label><select name="pengajaran_id" class="form-select" required><option value="">Pilih pengajaran</option><?php foreach($teachings as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (int)($edit['pengajaran_id']??0)===(int)$p['id']?'selected':'' ?>><?= sanitize($p['nama_lengkap'].' · '.$p['nama_mapel'].' · '.$p['nama_kelas'].' · '.$p['semester'].' '.$p['tahun_ajaran']) ?></option><?php endforeach ?></select></div><div class="col-sm-3 col-lg-2"><label class="form-label small mb-1">Tanggal</label><input type="date" name="tanggal" class="form-control" value="<?= sanitize($edit['tanggal']??date('Y-m-d')) ?>" required></div><div class="col-6 col-sm-2 col-lg-1"><label class="form-label small mb-1">Mulai</label><input type="time" name="jam_mulai" class="form-control" value="<?= sanitize(isset($edit['jam_mulai'])?substr($edit['jam_mulai'],0,5):'07:00') ?>" required></div><div class="col-6 col-sm-2 col-lg-1"><label class="form-label small mb-1">Selesai</label><input type="time" name="jam_selesai" class="form-control" value="<?= sanitize(isset($edit['jam_selesai'])?substr($edit['jam_selesai'],0,5):'08:30') ?>" required></div><div class="col-sm-3 col-lg-2"><label class="form-label small mb-1">Jam/Sesi</label><input name="sesi" maxlength="100" class="form-control" value="<?= sanitize($edit['sesi']??'Sesi 1') ?>" placeholder="Contoh: Sesi 1" required></div><div class="col-6 col-lg-1"><label class="form-label small mb-1">Jumlah JP</label><input type="number" name="jumlah_jp" min=".01" max="100" step=".01" class="form-control" value="<?= sanitize($edit['jumlah_jp']??2) ?>" required></div><div class="col-6 col-lg-1"><button class="btn btn-primary w-100">Simpan</button></div></form></div></section><?php endif ?>
<section class="card border-0 shadow-sm mb-4"><div class="card-body"><form method="get" class="row g-2 align-items-end"><div class="col-6 col-md"><label class="form-label small">Dari</label><input type="date" name="tanggal_mulai" class="form-control" value="<?= sanitize($from) ?>"></div><div class="col-6 col-md"><label class="form-label small">Sampai</label><input type="date" name="tanggal_selesai" class="form-control" value="<?= sanitize($to) ?>"></div><?php if($isAdmin): ?><div class="col-md"><label class="form-label small">Guru</label><select name="guru_id" class="form-select"><option value="">Semua</option><?php foreach($teachers as $g): ?><option value="<?= (int)$g['id'] ?>" <?= $teacherFilter===(int)$g['id']?'selected':'' ?>><?= sanitize($g['nama_lengkap']) ?></option><?php endforeach ?></select></div><?php endif ?><div class="col-6 col-md"><label class="form-label small">Kelas</label><select name="kelas_id" class="form-select"><option value="">Semua</option><?php foreach($classes as $k): ?><option value="<?= (int)$k['id'] ?>" <?= $classFilter===(int)$k['id']?'selected':'' ?>><?= sanitize($k['nama_kelas']) ?></option><?php endforeach ?></select></div><div class="col-6 col-md"><label class="form-label small">Mapel</label><select name="mapel_id" class="form-select"><option value="">Semua</option><?php foreach($subjects as $m): ?><option value="<?= (int)$m['id'] ?>" <?= $subjectFilter===(int)$m['id']?'selected':'' ?>><?= sanitize($m['nama_mapel']) ?></option><?php endforeach ?></select></div><div class="col-6 col-md"><label class="form-label small">Semester</label><select name="semester" class="form-select"><option value="">Semua</option><option <?= $semesterFilter==='Ganjil'?'selected':'' ?>>Ganjil</option><option <?= $semesterFilter==='Genap'?'selected':'' ?>>Genap</option></select></div><div class="col-6 col-md"><label class="form-label small">Tahun</label><select name="tahun_ajaran" class="form-select"><option value="">Semua</option><?php foreach($years as $y): ?><option <?= $yearFilter===$y?'selected':'' ?>><?= sanitize($y) ?></option><?php endforeach ?></select></div><div class="col-md-auto"><button class="btn btn-primary w-100">Filter</button></div></form></div></section>
<div class="row g-3 mb-4"><?php foreach([['Realisasi',rm_number($totalActual).' JP','text-primary'],['Alokasi '.$weekCount.' minggu',rm_number($totalAllocation).' JP',''],['Selisih',rm_number($totalActual-$totalAllocation).' JP',$totalActual-$totalAllocation<0?'text-danger':'text-success'],['Capaian',$totalPercentage===null?'Belum diatur':rm_number($totalPercentage).'%','']] as $card): ?><div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted"><?= sanitize($card[0]) ?></small><h4 class="fw-bold <?= $card[2] ?>"><?= sanitize($card[1]) ?></h4></div></div></div><?php endforeach ?></div>
<section class="card border-0 shadow-sm mb-4"><div class="card-body"><h6 class="fw-bold">Perbandingan Alokasi dan Realisasi per Guru</h6><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Guru</th><th class="text-end">Alokasi/Minggu</th><th class="text-end">Alokasi Periode</th><th class="text-end">Realisasi</th><th class="text-end">Selisih</th><th class="text-end">Capaian</th></tr></thead><tbody><?php foreach($summary as $item): ?><tr><td><?= sanitize($item['name']) ?></td><td class="text-end"><?= rm_number($item['weekly_allocation']) ?> JP</td><td class="text-end"><?= rm_number($item['allocation']) ?> JP</td><td class="text-end fw-bold"><?= rm_number($item['actual']) ?> JP</td><td class="text-end <?= $item['difference']<0?'text-danger':'text-success' ?>"><?= rm_number($item['difference']) ?> JP</td><td class="text-end"><?= $item['percentage']===null?'Belum diatur':rm_number($item['percentage']).'%' ?></td></tr><?php endforeach ?><?php if(!$summary): ?><tr><td colspan="6" class="text-center text-muted">Belum ada pengajaran sesuai filter.</td></tr><?php endif ?></tbody></table></div></div></section>
<section class="card border-0 shadow-sm mb-4"><div class="card-body"><div class="d-flex flex-wrap justify-content-between mb-3"><h6 class="fw-bold">Detail Realisasi</h6><div><a class="btn btn-sm btn-success" href="?<?= sanitize(http_build_query(array_merge($state,['format'=>'excel']))) ?>">Download Excel</a> <a class="btn btn-sm btn-danger" href="?<?= sanitize(http_build_query(array_merge($state,['format'=>'pdf']))) ?>">Download PDF</a></div></div><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Hari/Tanggal</th><?php if($isAdmin): ?><th>Guru</th><?php endif ?><th>Kelas / Mapel</th><th>Periode</th><th>Jam/Sesi</th><th>JP</th><?php if($isAdmin): ?><th>Aksi</th><?php endif ?></tr></thead><tbody><?php $days=['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];foreach($details as $r): ?><tr><td><?= $days[date('l',strtotime($r['tanggal']))] ?><br><small><?= date('d/m/Y',strtotime($r['tanggal'])) ?></small></td><?php if($isAdmin): ?><td><?= sanitize($r['nama_lengkap']) ?></td><?php endif ?><td><?= sanitize($r['nama_kelas']) ?><br><small><?= sanitize($r['nama_mapel']) ?></small></td><td><?= sanitize($r['semester'].' '.$r['tahun_ajaran']) ?></td><td><?= substr($r['jam_mulai'],0,5).'–'.substr($r['jam_selesai'],0,5) ?><br><small><?= sanitize($r['sesi']) ?></small></td><td><b><?= rm_number($r['jumlah_jp']) ?></b></td><?php if($isAdmin): ?><td><a class="btn btn-sm btn-outline-primary" href="?<?= sanitize(http_build_query(array_merge($state,['edit_id'=>$r['id']]))) ?>">Ubah</a> <form method="post" class="d-inline" onsubmit="return confirm('Hapus realisasi ini?')"><input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger">Hapus</button></form></td><?php endif ?></tr><?php endforeach ?><?php if(!$details): ?><tr><td colspan="<?= $isAdmin?7:5 ?>" class="text-center text-muted py-4">Belum ada data sesuai filter.</td></tr><?php endif ?></tbody></table></div></div></section>
<div class="row g-4"><div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body"><h6 class="fw-bold">Rekap Harian</h6><table class="table table-sm"><tr><th>Tanggal</th><?php if($isAdmin): ?><th>Guru</th><?php endif ?><th class="text-end">JP</th></tr><?php foreach($daily as $r): ?><tr><td><?= date('d/m/Y',strtotime($r['tanggal'])) ?></td><?php if($isAdmin): ?><td><?= sanitize($r['nama_lengkap']) ?></td><?php endif ?><td class="text-end"><?= rm_number($r['total']) ?></td></tr><?php endforeach ?></table></div></div></div><div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body"><h6 class="fw-bold">Rekap Mingguan</h6><table class="table table-sm"><tr><th>Minggu</th><?php if($isAdmin): ?><th>Guru</th><?php endif ?><th class="text-end">JP</th></tr><?php foreach($weekly as $r): ?><tr><td><?= (new DateTime($r['reference_date']))->format('W/Y') ?></td><?php if($isAdmin): ?><td><?= sanitize($r['nama_lengkap']) ?></td><?php endif ?><td class="text-end"><?= rm_number($r['total']) ?></td></tr><?php endforeach ?></table></div></div></div></div>
<?php if($isAdmin): ?><section class="card border-0 shadow-sm mt-4"><div class="card-body"><h6 class="fw-bold">Alokasi JP Mingguan</h6><p class="small text-muted">Menjadi target pembanding pada periode filter.</p><div class="table-responsive"><table class="table table-sm"><?php foreach($teachings as $p): ?><tr><td><?= sanitize($p['nama_lengkap'].' · '.$p['nama_mapel'].' · '.$p['nama_kelas'].' · '.$p['semester'].' '.$p['tahun_ajaran']) ?></td><td style="width:210px"><form method="post" class="d-flex gap-1"><input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="allocation"><input type="hidden" name="pengajaran_id" value="<?= (int)$p['id'] ?>"><input type="number" name="alokasi_jp_mingguan" min="0" max="100" step=".01" class="form-control form-control-sm" value="<?= sanitize($p['alokasi_jp_mingguan']) ?>"><button class="btn btn-sm btn-outline-primary">Simpan</button></form></td></tr><?php endforeach ?></table></div></div></section><?php endif ?>
</main></div>
<?php require_once __DIR__.'/footer.php'; ?>
