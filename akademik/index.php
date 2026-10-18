<?php
require_once __DIR__.'/../config/auth.php';
check_akademik_access();
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true)){http_response_code(405);exit;}
$kbmAcademicBoard=true;
require __DIR__.'/../includes/monitoring_kbm_board.php';
