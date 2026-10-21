<?php
// File konfigurasi diuji di folder sementara, tanpa mengakses database aplikasi.
$root=sys_get_temp_dir().'/lms-pkl-test-'.bin2hex(random_bytes(8));
mkdir($root.'/includes',0750,true);
copy(__DIR__.'/../includes/monitoring_kbm_pkl.php',$root.'/includes/monitoring_kbm_pkl.php');
require $root.'/includes/monitoring_kbm_pkl.php';
function expect_pkl(bool $condition,string $message): void {if(!$condition)throw new RuntimeException($message);}
function reject_pkl(callable $fn): void {
    try{$fn();}catch(InvalidArgumentException|RuntimeException $e){return;}
    throw new RuntimeException('Invalid operation accepted');
}
$classes=[['id'=>1],['id'=>2],['id'=>3]];
$input=['action'=>'pkl_save','pkl_nama'=>'PKL A','pkl_kelas'=>['1','2']];
try{
    expect_pkl(kbm_pkl_read()===[],'Missing config should be empty');
    reject_pkl(fn()=>kbm_pkl_save(array_replace($input,['pkl_kelas'=>['1']]),$classes));
    reject_pkl(fn()=>kbm_pkl_save(array_replace($input,['pkl_kelas'=>['1','99']]),$classes));
    reject_pkl(fn()=>kbm_pkl_save(array_replace($input,['pkl_kelas'=>['1','1']]),$classes));
    kbm_pkl_save($input,$classes);$groups=kbm_pkl_read();$id=$groups[0]['id'];
    expect_pkl(count($groups)===1&&$groups[0]['kelas_ids']===[1,2],'Saved membership');
    reject_pkl(fn()=>kbm_pkl_save(array_replace($input,['pkl_nama'=>'PKL B','pkl_kelas'=>['2','3']]),$classes));
    reject_pkl(fn()=>kbm_pkl_save(array_replace($input,['pkl_id'=>str_repeat('a',24)]),$classes));
    kbm_pkl_save(array_replace($input,['pkl_id'=>$id,'pkl_nama'=>'PKL Baru','pkl_kelas'=>['1','3']]),$classes);
    expect_pkl(kbm_pkl_read()[0]['nama']==='PKL Baru','Updated name');
    expect_pkl(kbm_pkl_read()[0]['kelas_ids']===[1,3],'Updated membership');
    expect_pkl(str_starts_with(file_get_contents(kbm_pkl_path()),'<?php exit; ?>'),'HTTP execution guard');
    file_put_contents(kbm_pkl_path(),'broken');
    reject_pkl(fn()=>kbm_pkl_save($input,$classes));
    expect_pkl(file_get_contents(kbm_pkl_path())==='broken','Corruption must not be overwritten');
    file_put_contents(kbm_pkl_path(),"<?php exit; ?>\n".json_encode(kbm_pkl_read_recovery($id)));
    kbm_pkl_save(['action'=>'pkl_delete','pkl_id'=>$id],$classes);
    expect_pkl(kbm_pkl_read()===[],'Deleted group');
    echo "PKL file configuration checks passed\n";
}finally{
    foreach(glob($root.'/storage/monitoring-pkl/*')?:[] as $file)unlink($file);
    if(is_dir($root.'/storage/monitoring-pkl'))rmdir($root.'/storage/monitoring-pkl');
    if(is_dir($root.'/storage'))rmdir($root.'/storage');
    unlink($root.'/includes/monitoring_kbm_pkl.php');rmdir($root.'/includes');rmdir($root);
}
function kbm_pkl_read_recovery(string $id): array {return [['id'=>$id,'nama'=>'PKL Baru','kelas_ids'=>[1,3]]];}
