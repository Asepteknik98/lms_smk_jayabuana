<?php
require_once __DIR__.'/monitoring_kbm_status.php';
require_once __DIR__.'/monitoring_kbm_pkl_cards.php';
require_once __DIR__.'/monitoring_kbm_cards.php';

function kbm_dashboard_filters(array $query): array {
    $result=[];
    foreach(['tanggal','sesi','kelas','status'] as $key) {
        $v=$query[$key]??'';
        if(!is_string($v)) throw new InvalidArgumentException('Filter '.$key.' tidak valid.');
        $result[$key]=trim($v);
    }
    if($result['tanggal']!=='') kbm_date($result['tanggal']);
    if(!in_array($result['sesi'],['','pagi','siang','belum_diatur'],true)) throw new InvalidArgumentException('Filter sesi tidak valid.');
    if(!in_array($result['status'],['','merah','hijau','kuning','abu','belum_dikonfigurasi'],true)) throw new InvalidArgumentException('Filter status tidak valid.');
    if($result['kelas']!=='' && !preg_match('/^(?:[1-9][0-9]{0,9}|pkl:[a-f0-9]{24})$/D',$result['kelas'])) throw new InvalidArgumentException('Filter kelas tidak valid.');
    return $result;
}

function kbm_board_h(mixed $v): string {return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}

function kbm_dashboard_payload(PDO $db,array $query,bool $groupPkl=false): array {
    $filters=kbm_dashboard_filters($query);
    $snapshot=kbm_monitoring_snapshot($db,null,$filters['tanggal']?:null);
    if($groupPkl){
        $snapshot['classes']=kbm_pkl_dashboard_cards($snapshot['classes'],kbm_pkl_read());
        foreach($snapshot['classes'] as $card)foreach($card['pkl_members']??[] as $member)if($filters['kelas']===(string)$member['class']['id'])$filters['kelas']=(string)$card['class']['id'];
    }
    $options=array_map(fn($c)=>['id'=>isset($c['pkl_members'])?$c['class']['id']:(int)$c['class']['id'],'name'=>$c['class']['nama_kelas']],$snapshot['classes']);
    if($filters['kelas']!=='' && !in_array($filters['kelas'],array_map('strval',array_column($options,'id')),true)) throw new InvalidArgumentException('Kelas yang dipilih tidak ditemukan.');
    $groups=['pagi'=>[],'siang'=>[],'belum_diatur'=>[]];
    $summary=['merah'=>0,'hijau'=>0,'kuning'=>0,'abu'=>0,'incomplete'=>0];$shown=0;
    foreach($snapshot['classes'] as $card) {
        $sessions=$card['context']['sessions'];$group=count($sessions)===1?$sessions[0]:'belum_diatur';
        $status=$card['state']['primary'];$incomplete=$card['context']['incomplete'];
        if($filters['sesi']!=='' && $group!==$filters['sesi']) continue;
        if($filters['kelas']!=='' && (string)$card['class']['id']!==$filters['kelas']) continue;
        if($filters['status']==='belum_dikonfigurasi' && !$incomplete) continue;
        if(in_array($filters['status'],['merah','hijau','kuning','abu'],true) && $status['color']!==$filters['status']) continue;
        $groups[$group][]=$card;$summary[$status['color']]++;$shown++;
        if($incomplete) $summary['incomplete']++;
    }
    ob_start();
    try {kbm_dashboard_render_cards($groups,$shown);$html=ob_get_clean();}
    catch(Throwable $e) {ob_end_clean();throw $e;}
    return ['ok'=>true,'html'=>$html,'summary'=>$summary,'shown'=>$shown,'total'=>count($snapshot['classes']),
        'server_time'=>$snapshot['now'],'date'=>$snapshot['date'],'today'=>substr($snapshot['now'],0,10),
        'class_options'=>$options,'filters'=>$filters,'interval_ms'=>12000];
}
