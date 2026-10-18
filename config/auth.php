<?php
require_once __DIR__.'/session.php';
require_once __DIR__.'/helper.php';
require_once __DIR__.'/../includes/akun_akademik.php';

function auth_clear_session(): void {
    $_SESSION=[];
    if(session_status()===PHP_SESSION_ACTIVE)session_regenerate_id(true);
    $_SESSION['csrf_token']=bin2hex(random_bytes(32));
}

// Query every protected request: disabling or changing a role takes effect immediately.
function auth_refresh_session(): bool {
    if(empty($_SESSION['user_id']))return false;
    $q=Database::getInstance()->prepare('SELECT u.id,u.username,u.role_id,u.is_active,u.password,r.nama_role FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?');
    $q->execute([$_SESSION['user_id']]);$user=$q->fetch();
    if(!$user||(int)$user['is_active']!==1||(int)$user['role_id']!==(int)($_SESSION['role_id']??0)||
       (isset($_SESSION['auth_password_version'])&&!hash_equals($_SESSION['auth_password_version'],hash('sha256',$user['password'])))){
        auth_clear_session();return false;
    }
    if(!in_array((int)$user['role_id'],[1,2,3],true)&&($user['nama_role']!=='Akademik'||(int)$user['role_id']!==akademik_role_id(Database::getInstance()))){auth_clear_session();return false;}
    $_SESSION['username']=$user['username'];$_SESSION['role_nama']=$user['nama_role'];
    return true;
}

function auth_home(int $role,string $name): ?string {
    if(in_array($role,[1,2,3],true))return [1=>'/admin/index.php',2=>'/guru/index.php',3=>'/siswa/index.php'][$role];
    return $role>3&&$name==='Akademik'?'/akademik/index.php':null;
}

function check_access($allowed_roles=[]){
    if(!auth_refresh_session()){header('Location: /login.php');exit;}
    if($allowed_roles&&!in_array((int)$_SESSION['role_id'],array_map('intval',$allowed_roles),true)){
        http_response_code(403);exit('Anda tidak memiliki akses ke halaman ini.');
    }
    if(!headers_sent())header('Cache-Control: no-store');
}

function check_akademik_access(): void {
    check_access([akademik_role_id(Database::getInstance())]);
}
