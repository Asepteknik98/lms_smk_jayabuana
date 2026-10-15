<?php
require_once __DIR__.'/rekap_tugas_module.php';
require_once __DIR__.'/rekap_tugas_tools.php';
require_once __DIR__.'/jenis_tugas_remedial.php';

function pr_ready(PDO $db): bool {
    try { $db->query('SELECT 1 FROM penugasan_remedial LIMIT 0'); $db->query('SELECT 1 FROM peserta_penugasan_remedial LIMIT 0'); return rt_remedial_ready($db); }
    catch (PDOException $e) { if ($e->getCode()==='42S02') return false; throw $e; }
}

function pr_eligible(array $data, int $tid): array {
    $task=null;
    foreach ($data['tasks'] as $t) if ((int)$t['id']===$tid) $task=$t;
    if (!$task || !$task['selesai']) throw new RuntimeException('Pilih tugas yang sudah ditutup.');
    return array_values(array_filter($data['students'],static function($s) use($tid) {
        $value=$s['cells'][$tid]['nilai'];
        return $value!==null && is_numeric($value) && (float)$value<75;
    }));
}

function pr_create(PDO $db,int $guru,int $user,int $pid,int $tid,string $title,string $instructions,string $deadline,string $token,string $type='teks',array $input=[]): int {
    $config=$type==='teks'?[]:pr_config($type,$input);
    if($type!=='teks'&&!pr_types_ready($db))throw new RuntimeException('Pilihan jenis tugas belum diaktifkan. Hubungi admin.');
    $title=trim($title); $instructions=trim($instructions);
    $date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$deadline);
    if ($title==='' || mb_strlen($title)>200 || $instructions==='' || mb_strlen($instructions)>20000) throw new RuntimeException('Isi judul (maksimal 200 karakter) dan instruksi (maksimal 20.000 karakter).');
    if (!$date || $date->format('Y-m-d\TH:i')!==$deadline || $date->getTimestamp()<=time()) throw new RuntimeException('Tenggat harus berupa waktu yang akan datang.');
    if (!preg_match('/^[a-f0-9]{48}$/D',$token)) throw new RuntimeException('Form tidak valid. Muat ulang halaman.');
    $db->beginTransaction();
    try {
        $students=pr_eligible(rt_data($db,$guru,$pid,rt_schema_ready($db)),$tid);
        if (!$students) throw new RuntimeException('Tidak ada siswa yang perlu remedial.');
        $ids=[];
        foreach ($students as $s) {
            rt_lock_pair($db,$guru,$user,$pid,(int)$s['id'],$tid);
            $q=$db->prepare('SELECT nilai FROM pengumpulan_tugas WHERE tugas_id=? AND siswa_id=? FOR UPDATE'); $q->execute([$tid,$s['id']]); $old=$q->fetch();
            if (!$old || ($old['nilai']!==null && (float)$old['nilai']<75)) $ids[]=(int)$s['id'];
        }
        if (!$ids) throw new RuntimeException('Nilai sudah berubah. Tidak ada siswa yang perlu remedial.');
        $db->prepare('INSERT INTO penugasan_remedial(tugas_id,judul,instruksi,deadline,token) VALUES(?,?,?,?,?)')->execute([$tid,$title,$instructions,$date->format('Y-m-d H:i:s'),$token]);
        $id=(int)$db->lastInsertId();
        if($type!=='teks')$db->prepare('UPDATE penugasan_remedial SET jenis=?,konfigurasi=? WHERE id=?')->execute([$type,json_encode($config,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$id]);
        $q=$db->prepare('INSERT INTO peserta_penugasan_remedial(penugasan_id,siswa_id) VALUES(?,?)');
        foreach ($ids as $sid) $q->execute([$id,$sid]);
        $db->commit(); return $id;
    } catch (Throwable $e) { if($db->inTransaction())$db->rollBack(); throw $e; }
}

function pr_student_tasks(PDO $db,int $sid): array {
    $files=pr_types_ready($db)?'a.file_jawaban,a.nama_file,':'NULL file_jawaban,NULL nama_file,';
    $q=$db->prepare('SELECT r.*,'.$files.'t.judul AS tugas_asal,m.nama_mapel,g.nama_lengkap AS nama_guru,a.jawaban,a.dikumpulkan_pada,h.nilai_remedial,b.dibatalkan_pada FROM peserta_penugasan_remedial a JOIN penugasan_remedial r ON r.id=a.penugasan_id JOIN tugas t ON t.id=r.tugas_id JOIN pengajaran p ON p.id=t.pengajaran_id JOIN siswa s ON s.id=a.siswa_id AND s.kelas_id=p.kelas_id JOIN mapel m ON m.id=p.mapel_id JOIN guru g ON g.id=p.guru_id LEFT JOIN remedial_tugas h ON h.id=a.remedial_id LEFT JOIN remedial_tugas_batal b ON b.remedial_id=h.id WHERE a.siswa_id=? ORDER BY r.id DESC');
    $q->execute([$sid]); return $q->fetchAll();
}

function pr_submit(PDO $db,int $user,int $id,string $answer,array $file=[]): void {
    $answer=trim($answer);
    if ($answer==='' || mb_strlen($answer)>30000) throw new RuntimeException('Jawaban wajib diisi, maksimal 30.000 karakter.');
    $fields=$file?',a.file_jawaban=?,a.nama_file=?':'';
    $params=$file?[$answer,$file['file_jawaban'],$file['nama_file'],$id,$user]:[$answer,$id,$user];
    $q=$db->prepare('UPDATE peserta_penugasan_remedial a JOIN penugasan_remedial r ON r.id=a.penugasan_id JOIN tugas t ON t.id=r.tugas_id JOIN pengajaran p ON p.id=t.pengajaran_id JOIN siswa s ON s.id=a.siswa_id AND s.kelas_id=p.kelas_id JOIN users u ON u.id=s.user_id AND u.role_id=3 AND u.is_active=1 SET a.jawaban=?,a.dikumpulkan_pada=NOW()'.$fields.' WHERE r.id=? AND s.user_id=? AND a.dikumpulkan_pada IS NULL AND r.deadline>=NOW()');
    $q->execute($params);
    if ($q->rowCount()!==1) throw new RuntimeException('Jawaban tidak dapat dikirim: tugas bukan untuk Anda, sudah dikirim, atau tenggat berakhir.');
}

function pr_grade(PDO $db,int $guru,int $user,int $pid,int $id,int $sid,string $score): void {
    $db->beginTransaction();
    try {
        $q=$db->prepare('SELECT r.tugas_id,a.dikumpulkan_pada,a.remedial_id FROM penugasan_remedial r JOIN peserta_penugasan_remedial a ON a.penugasan_id=r.id JOIN tugas t ON t.id=r.tugas_id JOIN pengajaran p ON p.id=t.pengajaran_id WHERE r.id=? AND a.siswa_id=? AND p.id=? AND p.guru_id=? FOR UPDATE');
        $q->execute([$id,$sid,$pid,$guru]); $row=$q->fetch();
        if (!$row || !$row['dikumpulkan_pada'] || $row['remedial_id']) throw new RuntimeException('Jawaban belum tersedia atau sudah dinilai. Gunakan Riwayat / Undo untuk pembatalan nilai.');
        rt_remedial_save($db,$guru,$user,$pid,(int)$row['tugas_id'],[$sid=>$score],[$sid=>'Penilaian tugas remedial #'.$id]);
        $q=$db->prepare('SELECT MAX(id) FROM remedial_tugas WHERE tugas_id=? AND siswa_id=?'); $q->execute([$row['tugas_id'],$sid]);
        $db->prepare('UPDATE peserta_penugasan_remedial SET remedial_id=? WHERE penugasan_id=? AND siswa_id=?')->execute([(int)$q->fetchColumn(),$id,$sid]);
        $db->commit();
    } catch(Throwable $e) { if($db->inTransaction())$db->rollBack(); throw $e; }
}
