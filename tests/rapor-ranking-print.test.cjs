const {test}=require('node:test');const assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');const fs=require('node:fs');const os=require('node:os');const path=require('node:path');const {pathToFileURL}=require('node:url');
const chrome=process.env.CHROME_BINARY||'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
test('Real Chrome fits rank-bearing 9- and 12-subject reports into two F4 sheets each',{skip:!fs.existsSync(chrome)},()=>{
    const php=String.raw`<?php
require 'includes/rapor_print_view.php';
foreach([9,12] as $count){
 $r=['nama_siswa'=>'Siswa Uji '.$count,'nis'=>'001','nisn'=>'0001','nama_kelas'=>'X A','jurusan'=>'TKJ','tahun_ajaran'=>'2026/2027','semester'=>'Ganjil','jenis'=>'UTS','sakit'=>0,'izin'=>0,'alfa'=>0,'catatan_wali_kelas'=>'Terus tingkatkan semangat belajar.','nama_kepala_sekolah'=>'Kepala Sekolah','nama_wali_kelas'=>'Wali Kelas','tempat_terbit'=>'Tangerang','tanggal_terbit'=>'2026-11-03','mapel'=>[],'ekstra'=>[['nama_kegiatan'=>'Pramuka','grade'=>'A']],'peringkat'=>['peringkat'=>2,'peserta'=>30,'sementara'=>false]];
 for($i=0;$i<$count;$i++)$r['mapel'][]=['nama_mapel'=>'Mata pelajaran '.($i+1),'nama_guru'=>'Guru Pengajar','kelompok'=>$i<4?'Umum':'Kejuruan','nilai_akhir'=>85,'deskripsi'=>'Memahami dan menerapkan materi pembelajaran dengan baik.','saran'=>'Pertahankan ketelitian dalam menyelesaikan tugas.'];
 rapor_print_render($r);
}
?>`;
    const rendered=spawnSync(process.env.PHP_BINARY||'C:\\xampp\\php\\php.exe',[],{input:php,encoding:'utf8',cwd:path.resolve(__dirname,'..')});assert.equal(rendered.status,0,rendered.stderr);
    const temp=fs.mkdtempSync(path.join(os.tmpdir(),'lms-rapor-rank-print-'));
    const html=`<!doctype html><html><head><meta charset="utf-8"><base href="${pathToFileURL(path.resolve('admin')+path.sep).href}"><style>${fs.readFileSync('assets/css/rapor-print.css','utf8')}</style></head><body><button id="print-button" disabled>Cetak</button><p id="layout-status"></p>${rendered.stdout}<script>${fs.readFileSync('assets/js/rapor-print.js','utf8')}</script></body></html>`;
    const file=path.join(temp,'fixture.html');fs.writeFileSync(file,html);
    try{
        const result=spawnSync(chrome,['--headless','--no-sandbox','--in-process-gpu','--disable-gpu','--disable-software-rasterizer','--no-first-run','--disable-background-networking','--disable-extensions',`--user-data-dir=${path.join(temp,'profile')}`,'--virtual-time-budget=3000','--dump-dom',pathToFileURL(file).href],{encoding:'utf8',timeout:25000,maxBuffer:4000000});
        assert.equal(result.status,0,result.error?.message||result.stderr);
        assert.match(result.stdout,/<body class="print-ready">/,result.stdout.match(/id="layout-status"[^>]*>([^<]*)/)?.[1]);
        assert.match(result.stdout,/2 siswa, 4 halaman F4/);assert.equal((result.stdout.match(/class="ranking-total"/g)||[]).length,2);assert.match(result.stdout,/2 dari 30 siswa/);
    }finally{
        const target=fs.realpathSync(temp);assert.equal(path.dirname(target),fs.realpathSync(os.tmpdir()));assert.ok(path.basename(target).startsWith('lms-rapor-rank-print-'));fs.rmSync(target,{recursive:true,force:true,maxRetries:5,retryDelay:100});
    }
});
