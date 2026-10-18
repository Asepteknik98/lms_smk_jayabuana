<?php
require_once __DIR__.'/config/auth.php';
check_access();
$home=auth_home((int)$_SESSION['role_id'],$_SESSION['role_nama']);
if(!$home){auth_clear_session();redirect('login.php');}
redirect($home);
