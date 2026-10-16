// Run: node --test tests/absensi-manual-pkl.test.cjs
// Exercise the page's save loop with a fake statement; no database is accessed.
const {test} = require('node:test');
const assert = require('node:assert/strict');
const {spawnSync} = require('node:child_process');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'guru/absensi_manual.php'), 'utf8');

test('PKL persists as Hadir with a recognizable note; other statuses keep their behavior', () => {
    const php = String.raw`<?php
    $source=file_get_contents('guru/absensi_manual.php');
    $start=strpos($source,'        foreach ($siswa_kelas as $siswa) {',strpos($source,'$stmt_detail ='));
    $end=strpos($source,'        $db->commit();',$start);
    $loop=substr($source,$start,$end-$start);
    $sesi_id=7;$waktu='2026-10-16 08:00:00';
    $siswa_kelas=[];
    $statuses=[1=>'PKL',2=>'PKL',3=>'PKL',4=>'Hadir',5=>'Sakit',6=>'Izin',7=>'Alpa',8=>'PKL',9=>'PKL'];
    foreach($statuses as $sid=>$unused)$siswa_kelas[]=['id'=>$sid];
    $keterangan=[1=>'',2=>'Bengkel sekolah',3=>'pkl - Kantor',4=>'',5=>'Surat dokter',6=>'',7=>'',8=>str_repeat('あ',300),9=>'PKL'];
    $stmt_detail=new class {public $rows=[];function execute($args){$this->rows[]=$args;}};
    eval($loop);
    echo json_encode($stmt_detail->rows,JSON_UNESCAPED_UNICODE);
    `;
    const bin = process.env.PHP_BINARY || (process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php');
    const result = spawnSync(bin, [], {cwd:root,input:php,encoding:'utf8'});
    assert.equal(result.status, 0, result.stderr || result.stdout);
    const rows = JSON.parse(result.stdout);
    for (const index of [0,1,2,7,8]) {
        assert.equal(rows[index][2], 'Hadir');
        assert.equal(rows[index][3], '2026-10-16 08:00:00');
        assert.match(rows[index][4], /^PKL(?:\s*-\s*)?/i);
    }
    assert.equal(rows[0][4], 'PKL');
    assert.equal(rows[1][4], 'PKL - Bengkel sekolah');
    assert.equal(rows[2][4], 'PKL - Kantor');
    assert.equal(rows[8][4], 'PKL');
    assert.equal(Array.from(rows[7][4]).length, 255);
    assert.deepEqual(rows[3].slice(2), ['Hadir','2026-10-16 08:00:00','Dicatat manual oleh guru']);
    assert.deepEqual(rows[4].slice(2), ['Sakit',null,'Surat dokter']);
    assert.deepEqual(rows[5].slice(2), ['Izin',null,null]);
    assert.deepEqual(rows[6].slice(2), ['Alpa',null,null]);
});

test('summary counts PKL separately and updates when its selection changes', () => {
    const checked = [{value:'Hadir'},{value:'PKL'},{value:'PKL'},{value:'Sakit'},{value:'Izin'},{value:'Alpa'}];
    const labels = Object.fromEntries(['Hadir','PKL','Sakit','Izin','Alpa'].map(status => ['count'+status, {textContent:''}]));
    const context = vm.createContext({document:{
        querySelectorAll:() => checked,
        getElementById:id => labels[id]
    }});
    const counter = source.match(/function hitungStatus\(\).*?(?=\r?\n)/)[0];
    vm.runInContext(counter+';hitungStatus();', context);
    assert.equal(labels.countPKL.textContent, 2);
    assert.equal(labels.countHadir.textContent, 1);
    checked[1].value = 'Hadir';
    vm.runInContext('hitungStatus()', context);
    assert.equal(labels.countPKL.textContent, 1);
    assert.equal(labels.countHadir.textContent, 2);
});
