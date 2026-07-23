<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helper.php';
check_access([2]);
$db=Database::getInstance();$action=$_GET['action']??'';

if($action==='template'){
 header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
 header('Content-Disposition: attachment; filename="Template_Import_Soal_Ujian.xls"');
 header('Cache-Control: no-store');
 echo '<?xml version="1.0" encoding="UTF-8"?><?mso-application progid="Excel.Sheet"?>';?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
<Styles><Style ss:ID="Title"><Font ss:Bold="1" ss:Size="14"/></Style><Style ss:ID="Info"><Font ss:Color="#666666"/><Interior ss:Color="#FFF2CC" ss:Pattern="Solid"/></Style><Style ss:ID="Header"><Font ss:Bold="1"/><Interior ss:Color="#D9EAF7" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style></Styles>
<Worksheet ss:Name="Template Soal"><Table>
<Column ss:Width="45"/><Column ss:Width="75"/><Column ss:Width="300"/><Column ss:Width="170"/><Column ss:Width="170"/><Column ss:Width="170"/><Column ss:Width="170"/><Column ss:Width="170"/><Column ss:Width="100"/><Column ss:Width="70"/>
<Row><Cell ss:MergeAcross="9" ss:StyleID="Title"><Data ss:Type="String">TEMPLATE IMPORT SOAL UJIAN - SMKS JAYA BUANA</Data></Cell></Row>
<Row><Cell ss:MergeAcross="9" ss:StyleID="Info"><Data ss:Type="String">Isi mulai baris di bawah header. Jangan mengubah nama atau urutan kolom. Kunci jawaban wajib A, B, C, D, atau E.</Data></Cell></Row>
<Row><Cell ss:MergeAcross="9" ss:StyleID="Info"><Data ss:Type="String">Tipe saat ini khusus PG. Pilihan A-D wajib; Pilihan E boleh dikosongkan. Bobot berupa bilangan bulat minimal 1.</Data></Cell></Row>
<Row/>
<Row><?php foreach(['No','Tipe Soal','Pertanyaan','Pilihan A','Pilihan B','Pilihan C','Pilihan D','Pilihan E','Kunci Jawaban','Bobot'] as $h): ?><Cell ss:StyleID="Header"><Data ss:Type="String"><?= htmlspecialchars($h,ENT_XML1|ENT_QUOTES,'UTF-8') ?></Data></Cell><?php endforeach ?></Row>
<Row><Cell><Data ss:Type="Number">1</Data></Cell><Cell><Data ss:Type="String">PG</Data></Cell><Cell><Data ss:Type="String">HAPUS CONTOH INI: Ibu kota Indonesia adalah ...</Data></Cell><Cell><Data ss:Type="String">Bandung</Data></Cell><Cell><Data ss:Type="String">Jakarta</Data></Cell><Cell><Data ss:Type="String">Surabaya</Data></Cell><Cell><Data ss:Type="String">Medan</Data></Cell><Cell><Data ss:Type="String">Makassar</Data></Cell><Cell><Data ss:Type="String">B</Data></Cell><Cell><Data ss:Type="Number">1</Data></Cell></Row>
</Table></Worksheet></Workbook><?php exit;
}

if($action!=='import'||$_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(400);exit('Permintaan tidak valid.');}
if(!verify_csrf($_POST['csrf_token']??'')){http_response_code(403);exit('Permintaan tidak sah.');}
$ujianId=(int)($_POST['ujian_id']??0);$stmt=$db->prepare('SELECT u.id,u.nama_ujian FROM ujian u JOIN pengajaran p ON p.id=u.pengajaran_id JOIN guru g ON g.id=p.guru_id WHERE u.id=? AND g.user_id=?');$stmt->execute([$ujianId,$_SESSION['user_id']]);$ujian=$stmt->fetch();
if(!$ujian){$_SESSION['flash_error']='Ujian tidak ditemukan atau bukan milik Anda.';redirect('ujian.php');}
$stmt=$db->prepare('SELECT EXISTS(SELECT 1 FROM sesi_ujian WHERE ujian_id=?)');$stmt->execute([$ujianId]);
if($stmt->fetchColumn()){$_SESSION['flash_error']='Soal tidak dapat diimpor karena ujian sudah memiliki peserta.';redirect('ujian.php');}
$file=$_FILES['file_excel']??null;
if(!$file||$file['error']!==UPLOAD_ERR_OK){$_SESSION['flash_error']='File Excel gagal diunggah.';redirect('ujian.php');}
if($file['size']>2*1024*1024||strtolower(pathinfo($file['name'],PATHINFO_EXTENSION))!=='xls'){$_SESSION['flash_error']='Gunakan file template .xls dengan ukuran maksimal 2 MB.';redirect('ujian.php');}
libxml_use_internal_errors(true);$xml=simplexml_load_file($file['tmp_name'],'SimpleXMLElement',LIBXML_NONET);
if(!$xml){$_SESSION['flash_error']='File tidak dapat dibaca. Gunakan template Excel yang disediakan.';redirect('ujian.php');}
$ns=$xml->getNamespaces(true);$xml->registerXPathNamespace('x',$ns['']??'urn:schemas-microsoft-com:office:spreadsheet');$xml->registerXPathNamespace('ss',$ns['ss']??'urn:schemas-microsoft-com:office:spreadsheet');$rows=$xml->xpath('//x:Worksheet[@ss:Name="Template Soal"]/x:Table/x:Row');
if(!$rows){$rows=$xml->xpath('//x:Worksheet[1]/x:Table/x:Row');}
$data=[];$headerFound=false;$errors=[];
foreach($rows?:[] as $row){
 $row->registerXPathNamespace('x',$ns['']??'urn:schemas-microsoft-com:office:spreadsheet');$cells=[];foreach($row->xpath('./x:Cell') as $cell){$attrs=$cell->attributes($ns['ss']??'urn:schemas-microsoft-com:office:spreadsheet');$index=(int)($attrs['Index']??0);if($index>0)while(count($cells)<$index-1)$cells[]='';$cell->registerXPathNamespace('x',$ns['']??'urn:schemas-microsoft-com:office:spreadsheet');$d=$cell->xpath('./x:Data');$cells[]=trim((string)($d[0]??''));}
 if(!$headerFound){if(strcasecmp($cells[0]??'','No')===0&&strcasecmp($cells[2]??'','Pertanyaan')===0)$headerFound=true;continue;}
 if(implode('',$cells)==='')continue;$cells=array_pad($cells,10,'');$line=count($data)+1;
 $tipe=strtoupper($cells[1]);$pertanyaan=trim($cells[2]);$opsi=array_map('trim',array_slice($cells,3,5));$kunci=strtoupper(trim($cells[8]));$bobot=filter_var($cells[9],FILTER_VALIDATE_INT);
 if($tipe!=='PG')$errors[]="Baris data $line: Tipe Soal harus PG.";
 if($pertanyaan===''||str_starts_with(strtoupper($pertanyaan),'HAPUS CONTOH INI'))$errors[]="Baris data $line: Pertanyaan kosong atau baris contoh belum dihapus.";
 if($opsi[0]===''||$opsi[1]===''||$opsi[2]===''||$opsi[3]==='')$errors[]="Baris data $line: Pilihan A sampai D wajib diisi.";
 if(!in_array($kunci,['A','B','C','D','E'],true))$errors[]="Baris data $line: Kunci jawaban harus A-E.";
 if($kunci==='E'&&$opsi[4]==='')$errors[]="Baris data $line: Pilihan E wajib diisi karena kuncinya E.";
 if($bobot===false||$bobot<1||$bobot>100)$errors[]="Baris data $line: Bobot harus bilangan bulat 1-100.";
 $data[]=[$pertanyaan,...$opsi,$kunci,$bobot];
}
if(!$headerFound)$errors[]='Header template tidak ditemukan.';
if(!$data)$errors[]='Tidak ada soal yang dapat diimpor.';
if($errors){$_SESSION['flash_error']='Impor dibatalkan. '.implode(' ',$errors);redirect('ujian.php');}
try{$db->beginTransaction();$insert=$db->prepare("INSERT INTO soal_ujian(ujian_id,tipe_soal,pertanyaan,opsi_a,opsi_b,opsi_c,opsi_d,opsi_e,kunci_jawaban,bobot) VALUES(?,'PG',?,?,?,?,?,?,?,?)");foreach($data as $r)$insert->execute([$ujianId,...$r]);$db->commit();catat_log($_SESSION['user_id'],'Mengimpor '.count($data).' soal Excel ke ujian: '.$ujian['nama_ujian']);$_SESSION['flash_success']=count($data).' soal dan kunci jawaban berhasil diimpor.';}catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('Impor soal Excel gagal: '.$e->getMessage());$_SESSION['flash_error']='Impor gagal disimpan ke database.';}
redirect('ujian.php');
