<?php
function kak_claim_ready(PDO $db): bool {
    return (int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('kak_acara','kak_klaim')")->fetchColumn() === 2
        && (int)$db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='kak_acara' AND column_name IN ('siswa_id','aktivitas_asal_id','target_tingkat')")->fetchColumn() === 3;
}

function kak_claim_total(PDO $db, int $siswa_id, ?string $tingkat=null): int {
    if (!kak_claim_ready($db)) return 0;
    if($tingkat!==null&&!in_array($tingkat,['X','XI','XII'],true))return 0;
    $q=$db->prepare("SELECT COALESCE(SUM(a.poin),0) FROM kak_aktivitas_siswa a JOIN kak_klaim c
        ON c.aktivitas_id=a.id AND c.siswa_id=a.siswa_id AND c.status='Disetujui' WHERE a.siswa_id=?".($tingkat!==null?' AND c.tingkat_aktivitas=?':''));
    $q->execute($tingkat!==null?[$siswa_id,$tingkat]:[$siswa_id]);
    return (int)$q->fetchColumn();
}

function kak_claim_student(PDO $db, int $user_id): array {
    $q = $db->prepare('SELECT s.id,s.kelas_id,k.tingkat FROM siswa s JOIN users u ON u.id=s.user_id LEFT JOIN kelas k ON k.id=s.kelas_id WHERE u.id=? AND u.role_id=3 AND u.is_active=1');
    $q->execute([$user_id]);
    $student = $q->fetch();
    if (!$student) throw new DomainException('Hanya siswa aktif yang dapat mengajukan klaim.');
    return $student;
}

function kak_claim_events(PDO $db, int $user_id): array {
    $student = kak_claim_student($db, $user_id);
    $q = $db->prepare("SELECT a.*,c.id klaim_id,c.status status_klaim,c.catatan_verifikasi,c.created_at diklaim_pada
        FROM kak_acara a LEFT JOIN kak_klaim c ON c.acara_id=a.id AND c.siswa_id=?
        WHERE (a.status='Terbit' AND (a.kelas_id IS NULL OR a.kelas_id=?) AND (a.siswa_id IS NULL OR a.siswa_id=?) AND (a.target_tingkat IS NULL OR a.target_tingkat=?)) OR c.id IS NOT NULL
        ORDER BY (c.id IS NULL AND a.status='Terbit') DESC,a.tanggal_acara DESC,a.id DESC");
    $q->execute([(int)$student['id'],$student['kelas_id'],(int)$student['id'],$student['tingkat']]);
    return $q->fetchAll();
}

function kak_claim_upload(array $files): array {
    $gallery=$files['bukti']??[];$camera=$files['bukti_kamera']??[];
    $has_gallery=($gallery['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE;
    $has_camera=($camera['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE;
    if($has_gallery&&$has_camera)throw new InvalidArgumentException('Pilih satu foto bukti, dari kamera atau galeri.');
    return $has_camera?$camera:$gallery;
}

function kak_claim_submit(PDO $db, int $user_id, array $input, array $file, ?string $storage = null): int {
    $student = kak_claim_student($db, $user_id);
    $acara_id = filter_var($input['acara_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $catatan = is_string($input['catatan'] ?? null) ? trim($input['catatan']) : '';
    if (!$acara_id || mb_strlen($catatan)>500) throw new InvalidArgumentException('Acara tidak valid atau catatan melebihi 500 karakter.');
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('Unggah foto bukti JPG, PNG, atau WebP maksimal 5 MB.');
    }
    $size = filesize($file['tmp_name']);
    if (!$size || $size>5*1024*1024) throw new InvalidArgumentException('Ukuran foto bukti maksimal 5 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($extensions[$mime]) || !@getimagesize($file['tmp_name'])) throw new InvalidArgumentException('Bukti harus berupa foto JPG, PNG, atau WebP yang valid.');
    $storage = $storage ?? __DIR__.'/../storage/kak-klaim';
    $saved = null;
    $db->beginTransaction();
    try {
        // Mengunci acara juga mencegah klaim ganda dari dua tab sekaligus.
        $q = $db->prepare("SELECT * FROM kak_acara WHERE id=? AND status='Terbit'
            AND (kelas_id IS NULL OR kelas_id=?) AND (siswa_id IS NULL OR siswa_id=?) AND (target_tingkat IS NULL OR target_tingkat=?) FOR UPDATE");
        $q->execute([$acara_id,$student['kelas_id'],(int)$student['id'],$student['tingkat']]);
        $event=$q->fetch();
        if (!$event) throw new InvalidArgumentException('Acara belum diterbitkan atau tidak tersedia untuk Anda.');
        $q = $db->prepare('SELECT id,status FROM kak_klaim WHERE acara_id=? AND siswa_id=? FOR UPDATE');
        $q->execute([$acara_id,(int)$student['id']]);
        $existing=$q->fetch();
        if ($existing && $existing['status']!=='Ditolak') throw new InvalidArgumentException('Anda sudah mengajukan klaim untuk acara ini.');
        if (!is_dir($storage) && !mkdir($storage,0750,true) && !is_dir($storage)) throw new RuntimeException('Folder bukti tidak tersedia.');
        $name = bin2hex(random_bytes(24)).'.'.$extensions[$mime];
        $saved = $storage.'/'.$name;
        if (!move_uploaded_file($file['tmp_name'],$saved)) throw new RuntimeException('Bukti gagal disimpan.');
        $q = $db->prepare("INSERT INTO kak_klaim(acara_id,siswa_id,bukti_file,bukti_mime,catatan,tingkat_aktivitas,status) VALUES(?,?,?,?,?,?,'Menunggu')");
        $grade=$event['tingkat_aktivitas']??$event['target_tingkat']??$student['tingkat'];
        if($existing){
            $q=$db->prepare("UPDATE kak_klaim SET bukti_file=?,bukti_mime=?,catatan=?,status='Menunggu',catatan_verifikasi=NULL,diverifikasi_oleh=NULL,diverifikasi_pada=NULL,aktivitas_id=NULL,created_at=NOW() WHERE id=?");
            $q->execute([$name,$mime,$catatan?:null,$existing['id']]);
        }else $q->execute([$acara_id,(int)$student['id'],$name,$mime,$catatan ?: null,$grade]);
        $id = $existing ? (int)$existing['id'] : (int)$db->lastInsertId();
        $db->commit();
        return $id;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        if ($saved && is_file($saved)) unlink($saved);
        throw $e;
    }
}

function kak_status_summary(PDO $db,int $user_id): array {
    $events=kak_claim_ready($db)?kak_claim_events($db,$user_id):[];
    return ['total'=>count($events),'approved'=>count(array_filter($events,static fn($event)=>$event['status_klaim']==='Disetujui'))];
}
