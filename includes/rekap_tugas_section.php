<?php
require_once __DIR__.'/rekap_tugas_module.php';

function rt_inline_context(array $query): array {
    $context=[];
    foreach(['page','pengajaran_id','pertemuan','rekap_kelas','rekap_pengajaran','rekap_pertemuan'] as $key){
        if(isset($query[$key]) && is_scalar($query[$key]) && (int)$query[$key]>0)$context[$key]=(int)$query[$key];
    }
    $context['rekap_mode']=($query['rekap_mode']??'')==='pertemuan'?'pertemuan':'semua';
    return $context;
}

function rt_inline_url(array $context): string {
    return 'tugas.php?'.http_build_query(rt_inline_context($context)).'#rekap-tugas';
}

function rt_inline_prepare(PDO $db, int $guru, array $query): array {
    $schema=rt_schema_ready($db);
    $context=rt_inline_context($query);
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST' && ($_POST['action']??'')==='rekap_tugas_save'){
        $context=rt_inline_context($_POST);
        try{
            if(!is_string($_POST['csrf_token']??null) || !verify_csrf($_POST['csrf_token']))throw new RuntimeException('Sesi keamanan berakhir. Muat ulang halaman.');
            if(!$schema)throw new RuntimeException('Pencatatan status belum tersedia. Hubungi admin.');
            foreach(['status','nilai','catatan'] as $key)if(!is_string($_POST[$key]??null))throw new RuntimeException('Format isian tidak valid.');
            rt_save($db,$guru,(int)$_SESSION['user_id'],(int)($context['rekap_pengajaran']??0),(int)($_POST['siswa_id']??0),(int)($_POST['tugas_id']??0),$_POST['status'],$_POST['nilai'],$_POST['catatan']);
            $_SESSION['rekap_tugas_success']='Status dan nilai tugas berhasil disimpan.';
        }catch(Throwable $e){
            error_log('Rekap tugas: '.$e->getMessage());
            $_SESSION['rekap_tugas_error']=$e instanceof PDOException?'Data gagal disimpan. Tidak ada perubahan yang diterapkan.':$e->getMessage();
        }
        header('Location: '.rt_inline_url($context));exit;
    }
    $q=$db->prepare('SELECT p.id,p.kelas_id,p.semester,p.tahun_ajaran,m.nama_mapel,k.nama_kelas FROM pengajaran p JOIN mapel m ON m.id=p.mapel_id JOIN kelas k ON k.id=p.kelas_id WHERE p.guru_id=? ORDER BY k.nama_kelas,p.tahun_ajaran DESC,p.semester,m.nama_mapel');
    $q->execute([$guru]);$assignments=$q->fetchAll();$classes=[];
    foreach($assignments as $p)$classes[(int)$p['kelas_id']]=$p['nama_kelas'];
    $pid=(int)($context['rekap_pengajaran']??0);
    $requested=null;
    if($pid){
        foreach($assignments as $p)if((int)$p['id']===$pid)$requested=$p;
        if(!$requested){http_response_code(403);exit('Pengajaran tidak ditemukan atau bukan milik Anda.');}
    }
    $class=(int)($context['rekap_kelas']??($requested['kelas_id']??array_key_first($classes)??0));
    if(!isset($classes[$class]))$class=(int)(array_key_first($classes)??0);
    $classAssignments=array_values(array_filter($assignments,static fn($p)=>(int)$p['kelas_id']===$class));
    if(!$requested || (int)$requested['kelas_id']!==$class)$pid=(int)($classAssignments[0]['id']??0);
    $data=['info'=>null,'tasks'=>[],'students'=>[]];$loadError='';
    if($pid){
        try{$data=rt_data($db,$guru,$pid,$schema);}
        catch(PDOException $e){error_log('Baca rekap tugas: '.$e->getMessage());$loadError='Rekap tugas belum dapat dimuat. Hubungi admin.';}
    }
    $meetings=[];$byMeeting=[];
    foreach($data['tasks'] as $task){$meeting=(int)$task['pertemuan_ke'];$meetings[$meeting]=$meeting;$byMeeting[$meeting][]=$task;}
    ksort($meetings);
    $mode=$context['rekap_mode'];$meeting=(int)($context['rekap_pertemuan']??0);
    if(!isset($meetings[$meeting]))$meeting=(int)(array_key_first($meetings)??0);
    $visibleTasks=$mode==='pertemuan'?($byMeeting[$meeting]??[]):$data['tasks'];
    $context['rekap_kelas']=$class;$context['rekap_pengajaran']=$pid;$context['rekap_pertemuan']=$meeting;
    $success=$_SESSION['rekap_tugas_success']??'';$error=$_SESSION['rekap_tugas_error']??$loadError;
    unset($_SESSION['rekap_tugas_success'],$_SESSION['rekap_tugas_error']);
    return compact('schema','context','classes','classAssignments','class','pid','data','meetings','byMeeting','mode','meeting','visibleTasks','success','error');
}

function rt_inline_render(array $view): void {
    // Local scope prevents the recap from overwriting the task list's variables.
    extract($view,EXTR_SKIP);
    require __DIR__.'/rekap_tugas_view.php';
}

function rt_inline_export(array $view): void {
    $data=$view['data'];$info=$data['info'];
    if(!$info){http_response_code(404);exit('Tidak ada rekap tugas.');}
    header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="Rekap_Tugas_'.$view['pid'].'_'.date('Ymd').'.csv"');header('Cache-Control: no-store');
    $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBFsep=;\r\n");
    $safe=static fn($v)=>is_string($v)&&preg_match('/^[=+\-@\t\r\n]/',$v)?"'".$v:$v;
    fputcsv($out,array_map($safe,[$info['nama_mapel'],$info['nama_kelas'],$info['tahun_ajaran'],$info['semester']]),';');
    $headers=['No','NIS','NISN','Nama Siswa'];
    foreach($view['visibleTasks'] as $task){$label='P'.$task['pertemuan_ke'].' '.$task['judul'];$headers[]=$label.' - Nilai';$headers[]=$label.' - Status';}
    $headers=array_merge($headers,['Rata-rata semua tugas selesai','Jumlah tugas dihitung','Tugas selesai belum dinilai']);fputcsv($out,array_map($safe,$headers),';');
    foreach($data['students'] as $i=>$s){
        $row=[$i+1,(string)($s['nis']??''),(string)($s['nisn']??''),$s['nama_lengkap']];
        foreach($view['visibleTasks'] as $task){$c=$s['cells'][$task['id']];$row[]=$c['nilai']??'';$row[]=$c['label'];}
        $row[]=$s['rata_rata']??'';$row[]=$s['jumlah_dihitung'];$row[]=$s['belum_dinilai'];fputcsv($out,array_map($safe,$row),';');
    }
    fclose($out);exit;
}

function rt_inline_options(array $student,array $tasks): string {
    $options=[];
    foreach($tasks as $t){
        $c=$student['cells'][$t['id']];
        $options[]=['tugasId'=>(int)$t['id'],'judul'=>'P'.$t['pertemuan_ke'].' · '.$t['judul'],
            'status'=>$c['status']==='terbuka'?'otomatis':$c['status'],
            'nilai'=>$c['nilai']!==null?number_format($c['nilai'],2,'.',''):'','catatan'=>$c['catatan'],
            'pengumpulan'=>(string)$c['pengumpulan_id'],'metode'=>$t['metode_pengumpulan']];
    }
    return sanitize(json_encode($options,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT));
}
