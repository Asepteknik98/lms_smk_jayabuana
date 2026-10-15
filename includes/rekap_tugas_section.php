<?php
require_once __DIR__.'/rekap_tugas_module.php';
require_once __DIR__.'/rekap_tugas_tools.php';

function rt_inline_context(array $query): array {
    $context=[];
    foreach(['page','pengajaran_id','pertemuan','rekap_kelas','rekap_pengajaran','rekap_tugas_id','impor_tugas_id'] as $key){
        if(isset($query[$key]) && is_scalar($query[$key]) && (int)$query[$key]>0)$context[$key]=(int)$query[$key];
    }
    $context['rekap_mode']=($query['rekap_mode']??'')==='tugas'?'tugas':'semua';
    return $context;
}


function rt_inline_url(array $context): string {
    return 'tugas.php?'.http_build_query(rt_inline_context($context)).'#rekap-tugas';
}



function rt_inline_prepare(PDO $db, int $guru, array $query): array {
    $schema=rt_schema_ready($db);
    $context=rt_inline_context($query);
    $action=$_POST['action']??'';
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST' && in_array($action,['rekap_tugas_save','rekap_tugas_import_preview','rekap_tugas_import_apply','rekap_tugas_remedial','rekap_tugas_remedial_cancel'],true)){
        $context=rt_inline_context($_POST);$pid=(int)($context['rekap_pengajaran']??0);
        try{
            if(!is_string($_POST['csrf_token']??null) || !verify_csrf($_POST['csrf_token']))throw new RuntimeException('Sesi keamanan berakhir. Muat ulang halaman.');
            if(!$schema)throw new RuntimeException('Pencatatan status belum tersedia. Hubungi admin.');
            if($action==='rekap_tugas_save'){
                foreach(['status','nilai','catatan'] as $key)if(!is_string($_POST[$key]??null))throw new RuntimeException('Format isian tidak valid.');
                rt_save($db,$guru,(int)$_SESSION['user_id'],$pid,(int)($_POST['siswa_id']??0),(int)($_POST['tugas_id']??0),$_POST['status'],$_POST['nilai'],$_POST['catatan']);
                $_SESSION['rekap_tugas_success']='Status dan nilai tugas berhasil disimpan.';
            }elseif($action==='rekap_tugas_import_preview'){
                unset($_SESSION['rekap_tugas_import']);
                $target=(int)($context['impor_tugas_id']??0);
                $data=rt_data($db,$guru,$pid,$schema);rt_import_target($data,$target);
                $file=$_FILES['file_nilai']??null;
                if(!$file || $file['error']!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']))throw new RuntimeException('File nilai gagal diunggah.');
                if(!in_array(strtolower(pathinfo($file['name'],PATHINFO_EXTENSION)),['xlsx','xls'],true) || $file['size']>5*1024*1024)throw new RuntimeException('Gunakan file .xlsx atau .xls, maksimal 5 MB.');
                $book=ni_read_excel($file['tmp_name']);
                $rows=rt_parse_import($data,$book['rows'],$pid,$target);
                $_SESSION['rekap_tugas_import']=['guru'=>$guru,'user'=>(int)$_SESSION['user_id'],'pid'=>$pid,'tid'=>$target,'expires'=>time()+900,'token'=>bin2hex(random_bytes(24)),'rows'=>$rows];
            }elseif($action==='rekap_tugas_import_apply'){
                $plan=$_SESSION['rekap_tugas_import']??[];
                if((int)($plan['tid']??0)<1 || (int)$plan['tid']!==(int)($context['impor_tugas_id']??0))throw new RuntimeException('Target tugas berubah. Periksa dan pratinjau ulang file.');
                if(!is_string($_POST['import_token']??null) || !isset($plan['token']) || !hash_equals($plan['token'],$_POST['import_token']))throw new RuntimeException('Pratinjau impor tidak valid. Unggah ulang file.');
                $count=rt_import_apply($db,$guru,(int)$_SESSION['user_id'],$pid,$plan);
                unset($_SESSION['rekap_tugas_import']);$_SESSION['rekap_tugas_success']=$count.' nilai berhasil diimpor.';
            }elseif($action==='rekap_tugas_remedial_cancel'){
                if(!is_string($_POST['alasan_batal']??null))throw new RuntimeException('Alasan pembatalan tidak valid.');
                rt_remedial_cancel($db,$guru,(int)$_SESSION['user_id'],$pid,(int)($_POST['remedial_id']??0),$_POST['alasan_batal']);
                $_SESSION['rekap_tugas_success']='Remedial dibatalkan. Nilai akhir sudah dihitung ulang; riwayat tetap tersimpan.';
            }else{
                if(!is_array($_POST['nilai_remedial']??null) || !is_array($_POST['catatan_remedial']??null))throw new RuntimeException('Format nilai remedial tidak valid.');
                $count=rt_remedial_save($db,$guru,(int)$_SESSION['user_id'],$pid,(int)($_POST['tugas_id']??0),$_POST['nilai_remedial'],$_POST['catatan_remedial']);
                $_SESSION['rekap_tugas_success']=$count.' nilai remedial disimpan. Nilai tertinggi dipakai sebagai nilai akhir.';
            }
        }catch(Throwable $e){
            error_log('Rekap tugas: '.$e->getMessage());
            $_SESSION['rekap_tugas_error']=$e instanceof PDOException?'Data gagal disimpan. Tidak ada perubahan yang diterapkan.':$e->getMessage();
        }
        $return=rt_inline_url($context);
        if($action==='rekap_tugas_import_preview')$return=str_replace('#rekap-tugas','&rekap_panel=impor#rekap-tugas',$return);
        if($action==='rekap_tugas_remedial_cancel')$return=str_replace('#rekap-tugas','&rekap_panel=remedial&remedial_tugas_id='.(int)($_POST['tugas_id']??0).'#rekap-tugas',$return);
        header('Location: '.$return);exit;
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
    $mode=$context['rekap_mode'];$selectedTask=(int)($context['rekap_tugas_id']??0);
    $taskIds=array_map(static fn($t)=>(int)$t['id'],$data['tasks']);
    if(!in_array($selectedTask,$taskIds,true))$selectedTask=(int)($taskIds[0]??0);
    $visibleTasks=$mode==='tugas'?array_values(array_filter($data['tasks'],static fn($t)=>(int)$t['id']===$selectedTask)):$data['tasks'];
    $context['rekap_kelas']=$class;$context['rekap_pengajaran']=$pid;$context['rekap_tugas_id']=$selectedTask;
    $remedialReady=rt_remedial_ready($db);
    $importPlan=$_SESSION['rekap_tugas_import']??null;
    if($importPlan && ($importPlan['pid']!==$pid || $importPlan['guru']!==$guru || $importPlan['user']!==(int)($_SESSION['user_id']??0) || $importPlan['expires']<time() || (int)($importPlan['tid']??0)<1 || (int)$importPlan['tid']!==(int)($context['impor_tugas_id']??0)))$importPlan=null;
    $panel=in_array($query['rekap_panel']??'',['impor','remedial'],true)?$query['rekap_panel']:'';
    $cancelReady=rt_cancel_ready($db);
    $remedialSelected=(int)($query['remedial_tugas_id']??$selectedTask);
    $success=$_SESSION['rekap_tugas_success']??'';$error=$_SESSION['rekap_tugas_error']??$loadError;
    unset($_SESSION['rekap_tugas_success'],$_SESSION['rekap_tugas_error']);
    return compact('schema','context','classes','classAssignments','class','pid','data','mode','selectedTask','visibleTasks','success','error','remedialReady','importPlan','panel','cancelReady','remedialSelected');
}

function rt_inline_render(array $view): void {
    require_once __DIR__.'/rekap_tugas_view.php';
    rt_inline_render_template($view);
}

function rt_inline_export(array $view): void {
    $data=$view['data'];$info=$data['info'];
    if(!$info){http_response_code(404);exit('Tidak ada rekap tugas.');}
    header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="Rekap_Tugas_'.$view['pid'].'_'.date('Ymd').'.csv"');header('Cache-Control: no-store');
    $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBFsep=;\r\n");
    $safe=static fn($v)=>is_string($v)&&preg_match('/^[=+\-@\t\r\n]/',$v)?"'".$v:$v;
    fputcsv($out,array_map($safe,[$info['nama_mapel'],$info['nama_kelas'],$info['tahun_ajaran'],$info['semester']]),';');
    $headers=['No','NIS','NISN','Nama Siswa'];
    foreach($view['visibleTasks'] as $task){$label='Tugas '.($task['nomor']??1).' '.$task['judul'];$headers[]=$label.' - Nilai';$headers[]=$label.' - Status';}
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
        $options[]=['tugasId'=>(int)$t['id'],'judul'=>'Tugas '.($t['nomor']??1).' · '.$t['judul'],
            'status'=>$c['status']==='terbuka'?'otomatis':$c['status'],
            'nilai'=>$c['nilai']!==null?number_format($c['nilai'],2,'.',''):'','catatan'=>$c['catatan'],
            'pengumpulan'=>(string)$c['pengumpulan_id'],'metode'=>$t['metode_pengumpulan']];
    }
    return sanitize(json_encode($options,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT));
}
