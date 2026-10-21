<?php
// Verifikasi aturan penugasan dan tampilan memakai data uji, tanpa DB aplikasi.
$root=sys_get_temp_dir().'/lms-pkl-users-'.bin2hex(random_bytes(8));
mkdir($root.'/includes',0750,true);
copy(__DIR__.'/../includes/monitoring_kbm_pkl.php',$root.'/includes/monitoring_kbm_pkl.php');
require $root.'/includes/monitoring_kbm_pkl.php';
class PklUsersTestDb extends PDO {
    public array $slots=[];
    public array $students=[11=>['kelas_id'=>1],12=>['kelas_id'=>2],13=>['kelas_id'=>2],14=>['kelas_id'=>1],15=>['kelas_id'=>2],99=>['kelas_id'=>3]];
    public function __construct(){}
    public function prepare(string $query,array $options=[]): PDOStatement|false {return new PklUsersTestStatement($this);}
}
class PklUsersTestStatement extends PDOStatement {
    public function __construct(private PklUsersTestDb $db){}
    public function execute(?array $params=null): bool {$this->db->slots[]=['kelas_id'=>$params[0],'siswa_id'=>$params[1]];return true;}
}
function kbm_input(array $input,string $key): string {if(!is_string($input[$key]??null))throw new InvalidArgumentException('Invalid input');return $input[$key];}
function kbm_rows(PDO $db,string $sql,array $params=[]): array {
    if(str_contains($sql,'FROM kelas'))return in_array($params[0],[1,2],true)?[['id'=>$params[0]]]:[];
    return isset($db->students[$params[0]])?[$db->students[$params[0]]]:[];
}
function kbm_simple_chair_slots(PDO $db): array {return $db->slots;}
function kbm_admin_write(PDO $db,int $admin,callable $fn): mixed {
    $before=$db->slots;try{return $fn();}catch(Throwable $e){$db->slots=$before;throw $e;}
}
function pkl_users_ok(bool $condition,string $message): void {if(!$condition)throw new RuntimeException($message);}
function pkl_users_reject(callable $fn): void {try{$fn();}catch(InvalidArgumentException $e){return;}throw new RuntimeException('Invalid selection accepted');}
function kbm_simple_h(mixed $v): string {return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function kbm_simple_token(): void {echo '<input name="csrf_token" value="test">';}
try{
    kbm_pkl_save(['action'=>'pkl_save','pkl_nama'=>'PKL <Gabungan>','pkl_kelas'=>['1','2']],[['id'=>1],['id'=>2]]);
    $group=kbm_pkl_read()[0];$db=new PklUsersTestDb();
    $input=['pkl_id'=>$group['id'],'siswa_ids'=>['11','12']];
    kbm_pkl_add_users($db,1,$input);
    pkl_users_ok(array_column($db->slots,'kelas_id')===[1,2],'Users must retain original classes');
    kbm_pkl_add_users($db,1,$input);
    pkl_users_ok(count($db->slots)===2,'Repeat submission must not duplicate users');
    pkl_users_reject(fn()=>kbm_pkl_add_users($db,1,array_replace($input,['siswa_ids'=>['13','99']])));
    pkl_users_ok(count($db->slots)===2,'Invalid member must roll back whole selection');
    kbm_pkl_add_users($db,1,array_replace($input,['siswa_ids'=>['13','14']]));
    pkl_users_ok(count($db->slots)===4,'Four members across classes allowed');
    pkl_users_reject(fn()=>kbm_pkl_add_users($db,1,array_replace($input,['siswa_ids'=>['15']])));
    pkl_users_reject(fn()=>kbm_pkl_add_users($db,1,array_replace($input,['siswa_ids'=>[]])));
    pkl_users_reject(fn()=>kbm_pkl_add_users($db,1,array_replace($input,['siswa_ids'=>['bad']])));
    $db->slots=array_slice($db->slots,0,3);
    kbm_pkl_add_users($db,1,array_replace($input,['siswa_ids'=>['15']]));
    pkl_users_ok(count($db->slots)===4,'Revoked slot can be reused');
    $_SESSION=['user_id'=>1,'role_id'=>1];$pklGroups=[$group];$pklId=$group['id'];$selectedPkl=$group;$classId=1;
    $classes=[['id'=>1,'nama_kelas'=>'XI A'],['id'=>2,'nama_kelas'=>'XI B']];
    $selectedUsers=[];$slotsFull=false;$students=$availableStudents=[['id'=>11,'nama_lengkap'=>'Siswa <A>','nama_kelas'=>'XI A','nis'=>'11'],['id'=>12,'nama_lengkap'=>'Siswa B','nama_kelas'=>'XI B','nis'=>'12']];
    require_once __DIR__.'/../includes/monitoring_kbm_users_page.php';
    ob_start();kbm_simple_render_users($classes,$pklGroups,$pklId,$selectedPkl,$classId,$selectedUsers,$slotsFull,$students,$availableStudents);$html=ob_get_clean();
    pkl_users_ok(str_contains($html,'PKL &lt;Gabungan&gt;'),'Escaped group option');
    pkl_users_ok(str_contains($html,'Siswa &lt;A&gt; · XI A')&&str_contains($html,'Siswa B · XI B'),'Names and source classes displayed');
    pkl_users_ok(substr_count($html,'name="siswa_ids[]"')===2,'Batch selection checkboxes');
    echo "PKL user selection checks passed\n";
}finally{
    foreach(glob($root.'/storage/monitoring-pkl/*')?:[] as $file)unlink($file);
    if(is_dir($root.'/storage/monitoring-pkl'))rmdir($root.'/storage/monitoring-pkl');
    if(is_dir($root.'/storage'))rmdir($root.'/storage');
    unlink($root.'/includes/monitoring_kbm_pkl.php');rmdir($root.'/includes');rmdir($root);
}
