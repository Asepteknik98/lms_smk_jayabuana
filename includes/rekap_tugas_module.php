<?php

function rt_schema_ready(PDO $db): bool {
    $q=$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='rekap_tugas_status'");
    return (bool)$q->fetchColumn();
}

function rt_cell(array $task, ?array $submission, ?array $manual): array {
    $score=$submission['nilai']??null;
    $closed=(bool)$task['selesai'];
    // Real submissions always take precedence over an earlier manual absence.
    if ($score!==null) { $status='mengerjakan'; $label='Mengerjakan'; $score=(float)$score; }
    elseif ($submission!==null || ($manual['status']??'')==='belum_dinilai') { $status='belum_dinilai'; $label='Belum Dinilai'; }
    elseif ($closed) { $status='tidak_mengerjakan'; $label='Tidak Mengerjakan'; $score=0.0; }
    else { $status='terbuka'; $label='Masih Terbuka'; }
    return ['status'=>$status,'label'=>$label,'nilai'=>$score,'dihitung'=>$closed && $score!==null,
        'menunggu_nilai'=>$closed && $status==='belum_dinilai',
        'catatan'=>(string)($submission['catatan_guru']??$manual['catatan']??''),
        'pengumpulan_id'=>(int)($submission['id']??0),'manual'=>$manual!==null];
}

function rt_data(PDO $db, int $guru, int $pid, bool $schema): array {
    $q=$db->prepare('SELECT p.id,p.kelas_id,p.semester,p.tahun_ajaran,m.nama_mapel,k.nama_kelas FROM pengajaran p JOIN mapel m ON m.id=p.mapel_id JOIN kelas k ON k.id=p.kelas_id WHERE p.id=? AND p.guru_id=?');
    $q->execute([$pid,$guru]);$info=$q->fetch();
    if(!$info)throw new RuntimeException('Pengajaran tidak ditemukan atau bukan milik Anda.');
    // Older installations have only online tasks and no collection-method column.
    $columns=$db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='tugas' AND column_name='metode_pengumpulan'");
    $method=$columns->fetchColumn()?'metode_pengumpulan':"'online' AS metode_pengumpulan";
    $q=$db->prepare('SELECT id,judul,pertemuan_ke,deadline,'.$method.',(deadline<=NOW()) selesai FROM tugas WHERE pengajaran_id=? ORDER BY pertemuan_ke,deadline,id');
    $q->execute([$pid]);$tasks=$q->fetchAll();
    $q=$db->prepare('SELECT id,nis,nisn,nama_lengkap FROM siswa WHERE kelas_id=? ORDER BY nama_lengkap,id');
    $q->execute([$info['kelas_id']]);$students=$q->fetchAll();
    $q=$db->prepare('SELECT pt.id,pt.tugas_id,pt.siswa_id,pt.nilai,pt.catatan_guru FROM pengumpulan_tugas pt JOIN tugas t ON t.id=pt.tugas_id WHERE t.pengajaran_id=?');
    $q->execute([$pid]);$submissions=[];
    foreach($q->fetchAll() as $row)$submissions[$row['siswa_id']][$row['tugas_id']]=$row;
    $manual=[];
    if($schema){
        $q=$db->prepare('SELECT rs.* FROM rekap_tugas_status rs JOIN tugas t ON t.id=rs.tugas_id WHERE t.pengajaran_id=?');$q->execute([$pid]);
        foreach($q->fetchAll() as $row)$manual[$row['siswa_id']][$row['tugas_id']]=$row;
    }
    foreach($students as &$student){
        $sid=(int)$student['id'];$student['cells']=[];$sum=0;$count=0;$pending=0;$missing=0;
        foreach($tasks as $task){
            $tid=(int)$task['id'];$cell=rt_cell($task,$submissions[$sid][$tid]??null,$manual[$sid][$tid]??null);
            $student['cells'][$tid]=$cell;
            if($cell['dihitung']){$sum+=$cell['nilai'];$count++;}
            if($cell['menunggu_nilai'])$pending++;
            if($cell['status']==='tidak_mengerjakan')$missing++;
        }
        $student['rata_rata']=$count?round($sum/$count,2):null;
        $student['jumlah_dihitung']=$count;$student['belum_dinilai']=$pending;$student['tidak_mengerjakan']=$missing;
    }unset($student);
    return ['info'=>$info,'tasks'=>$tasks,'students'=>$students];
}

function rt_save(PDO $db, int $guru, int $user, int $pid, int $sid, int $tid, string $status, string $rawScore, string $note): void {
    if(!in_array($status,['mengerjakan','tidak_mengerjakan','belum_dinilai','otomatis'],true))throw new RuntimeException('Status tugas tidak valid.');
    $rawScore=trim($rawScore);$note=trim($note);
    if(mb_strlen($note)>1000)throw new RuntimeException('Catatan maksimal 1.000 karakter.');
    $score=null;
    if($status==='mengerjakan'){
        if($rawScore==='' || !is_numeric($rawScore) || !is_finite((float)$rawScore) || (float)$rawScore<0 || (float)$rawScore>100)throw new RuntimeException('Nilai wajib diisi dengan angka 0 sampai 100.');
        $score=round((float)$rawScore,2);
    }
    $db->beginTransaction();
    try {
        $q=$db->prepare('SELECT t.id,t.judul,(t.deadline<=NOW()) selesai FROM tugas t JOIN pengajaran p ON p.id=t.pengajaran_id JOIN guru g ON g.id=p.guru_id JOIN users u ON u.id=g.user_id AND u.role_id=2 AND u.is_active=1 JOIN siswa s ON s.kelas_id=p.kelas_id WHERE t.id=? AND p.id=? AND p.guru_id=? AND g.user_id=? AND s.id=? FOR UPDATE');
        $q->execute([$tid,$pid,$guru,$user,$sid]);$task=$q->fetch();
        if(!$task)throw new RuntimeException('Tugas atau siswa bukan bagian kelas yang Anda ampu.');
        $q=$db->prepare('SELECT id,nilai FROM pengumpulan_tugas WHERE tugas_id=? AND siswa_id=? FOR UPDATE');$q->execute([$tid,$sid]);$old=$q->fetch();
        if($status==='tidak_mengerjakan'){
            if(!$task['selesai'])throw new RuntimeException('Tugas masih terbuka. Status tidak mengerjakan berlaku setelah tenggat.');
            if($old)throw new RuntimeException('Siswa sudah mengumpulkan tugas. Gunakan Mengerjakan atau Belum Dinilai.');
        }
        if($status==='belum_dinilai' && $old && $old['nilai']!==null)throw new RuntimeException('Nilai sudah tersimpan. Gunakan Mengerjakan untuk memperbarui nilai.');
        if($status==='mengerjakan'){
            // Preserve every existing answer, attachment and submission timestamp.
            $db->prepare('INSERT INTO pengumpulan_tugas(tugas_id,siswa_id,nilai,catatan_guru) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE nilai=VALUES(nilai),catatan_guru=VALUES(catatan_guru)')->execute([$tid,$sid,$score,$note!==''?$note:null]);
        }
        if($status==='otomatis')$db->prepare('DELETE FROM rekap_tugas_status WHERE tugas_id=? AND siswa_id=?')->execute([$tid,$sid]);
        else $db->prepare('INSERT INTO rekap_tugas_status(tugas_id,siswa_id,guru_id,status,catatan) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE guru_id=VALUES(guru_id),status=VALUES(status),catatan=VALUES(catatan),updated_at=CURRENT_TIMESTAMP')->execute([$tid,$sid,$guru,$status,$note]);
        $description='Rekap tugas: '.json_encode(['tugas_id'=>$tid,'siswa_id'=>$sid,'status'=>$status,'nilai_lama'=>$old['nilai']??null,'nilai_baru'=>$score],JSON_UNESCAPED_UNICODE);
        $db->prepare('INSERT INTO log_aktivitas(user_id,aktivitas,ip_address,user_agent) VALUES(?,?,?,?)')->execute([$user,$description,$_SERVER['REMOTE_ADDR']??'CLI',substr($_SERVER['HTTP_USER_AGENT']??'CLI',0,255)]);
        $db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
