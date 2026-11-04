<?php
require_once __DIR__.'/../config/auth.php';
check_access([1]);
// Existing direct report links and editing requests retain their original behavior.
$routeInput=$_SERVER['REQUEST_METHOD']==='POST'?$_POST:$_GET;
if(isset($routeInput['id'])||isset($routeInput['siswa_id'])||($routeInput['mode']??'')==='isi'||($_SERVER['REQUEST_METHOD']==='POST'&&!isset($_POST['admin_action']))){require __DIR__.'/../includes/rapor_page.php';}
else{require __DIR__.'/../includes/rapor_admin_page.php';}
