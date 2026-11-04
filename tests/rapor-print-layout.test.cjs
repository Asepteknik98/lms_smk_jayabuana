const {test}=require('node:test');
const assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');
const path=require('node:path');
test('Report keeps all grades/totals on page one and supplements on page two',()=>{
const php=String.raw`<?php
require 'includes/rapor_print_view.php';
$r=['nama_siswa'=>'Contoh','nis'=>'001','nisn'=>'0001','nama_kelas'=>'X A','jurusan'=>'TKJ','tahun_ajaran'=>'2026/2027','semester'=>'Ganjil','jenis'=>'UTS','sakit'=>0,'izin'=>0,'alfa'=>0,'catatan_wali_kelas'=>'Catatan','nama_kepala_sekolah'=>'Kepala','nama_wali_kelas'=>'Wali','tempat_terbit'=>'Tangerang','tanggal_terbit'=>'2026-10-28','mapel'=>[],'ekstra'=>[['nama_kegiatan'=>'Pramuka','grade'=>'A']]];
$r['peringkat']=['peringkat'=>2,'peserta'=>30,'sementara'=>false];
$r['mapel']=rapor_print_template_rows([]);foreach($r['mapel'] as $i=>&$m){$m['nama_mapel']='Pilihan Wali Kelas '.($i+1);$m['nama_guru']='Guru Pilihan '.($i+1);$m['nilai_akhir']=80;$m['deskripsi']='Deskripsi <script>contoh</script>';$m['saran']='Saran';}unset($m);
ob_start();rapor_print_render($r);$html=ob_get_clean();$d=new DOMDocument();libxml_use_internal_errors(true);$d->loadHTML($html);$x=new DOMXPath($d);$s=$x->query('//section[contains(@class,"sheet-")]');
function check($v){if(!$v)throw new RuntimeException('Incorrect report layout');}
check($s->length===2);check($x->query('.//tr[@class="ranking-total"]',$s[0])->length===1);check($x->query('.//tr[@class="ranking-total"]',$s[1])->length===0);check(str_contains($html,'2 dari 30 siswa'));check($x->query('//header[@class="letterhead"]')->length===2);
check($x->query('//img[@class="sheet-watermark" and @aria-hidden="true" and @src="../assets/img/jb.png"]')->length===2);
check($x->query('//header[@data-image-header]/img[contains(@src,"rapor-header.png")]')->length===2);check($x->query('//div[@class="ornament"]')->length===0);
check($x->query('.//tr[@data-grade-row]',$s[0])->length===9);check($x->query('.//table[@class="totals"]',$s[0])->length===1);
check($x->query('.//table[@class="grades"]',$s[1])->length===0);check($x->query('.//div[@class="signatures"]',$s[1])->length===1);
$e=$x->query('.//table[@class="extras"]',$s[1]);check($x->query('.//tbody/tr',$e[0])->length===11);check($x->query('.//tbody/tr',$e[1])->length===12);check($x->query('.//tbody/tr[2]/td[3]',$e[0])->item(0)->textContent==='A');check($x->query('//script')->length===0);
$r['peringkat']=['peringkat'=>null,'peserta'=>30,'sementara'=>true];ob_start();rapor_print_render($r);$pendingRank=ob_get_clean();check(str_contains($pendingRank,'Peringkat kelas (sementara)')&&str_contains($pendingRank,'Belum tersedia'));unset($r['peringkat']);
$r['mapel']=[];ob_start();rapor_print_render($r);$empty=ob_get_clean();check(substr_count($empty,'data-grade-row')===9);check(!str_contains($empty,'Belum diisi'));check(!str_contains($empty,'Belum ada mata pelajaran'));check(!str_contains($empty,'Pendidikan Agama'));check(substr_count($empty,'class="score"></td>')===9);check(rapor_print_number(0)==='0');
$chosen=[['nama_mapel'=>'Mapel Khusus','nama_guru'=>'Guru B','kelompok'=>'Lokal','nilai_akhir'=>75,'deskripsi'=>'Isi','saran'=>'Saran'],['nama_mapel'=>'Bahasa Indonesia','nama_guru'=>'Guru A','kelompok'=>'Umum','nilai_akhir'=>85,'deskripsi'=>'Capaian','saran'=>'Latihan']];$custom=rapor_print_template_rows($chosen);check($custom===$chosen);$r['mapel']=$chosen;ob_start();rapor_print_render($r);$selected=ob_get_clean();check(substr_count($selected,'data-grade-row')===2);check(strpos($selected,'Mapel Khusus')<strpos($selected,'Bahasa Indonesia'));check(str_contains($selected,'Guru B')&&str_contains($selected,'Guru A'));echo 'PASS';
?>`;
const r=spawnSync(process.env.PHP_BINARY||'C:\\xampp\\php\\php.exe',[],{input:php,encoding:'utf8',cwd:path.resolve(__dirname,'..')});assert.equal(r.status,0,r.stderr+r.stdout);assert.equal(r.stdout,'PASS');
});
