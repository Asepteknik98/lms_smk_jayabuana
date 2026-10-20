<?php
require_once __DIR__.'/../config/auth.php';
check_access([1]);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
if($_SERVER['REQUEST_METHOD']!=='GET'){header('Allow: GET');http_response_code(405);echo json_encode(['ok'=>false,'message'=>'Gunakan permintaan GET.']);exit;}
require_once __DIR__.'/../includes/target_jp_guru.php';
$actor=(int)$_SESSION['user_id'];$db=Database::getInstance();session_write_close();
try{echo json_encode(['ok'=>true,'data'=>rm_target_input_summary($db,$actor,$_GET)],JSON_THROW_ON_ERROR);}
catch(InvalidArgumentException $e){http_response_code(400);echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);}
catch(Throwable $e){error_log('Acuan target JP: '.$e->getMessage());http_response_code(503);echo json_encode(['ok'=>false,'message'=>'Target belum dapat dimuat. Input manual tetap tersedia.']);}
