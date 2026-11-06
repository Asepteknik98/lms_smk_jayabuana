const {test} = require('node:test');
const assert = require('node:assert/strict');
const {spawnSync} = require('node:child_process');
const path = require('node:path');

test('meeting filter rejects unrelated sessions, orders arbitrary choices, and counts only closed sessions', () => {
    const script = String.raw`<?php
    $source=file_get_contents('guru/rekap_absensi.php');
    $start=strpos($source,"    if (\$mode_rekap === 'beberapa') {");
    $end=strpos($source,"    if (\$mode_rekap === 'pertemuan') {",$start);
    $block=substr($source,$start,$end-$start);
    $daftar_sesi=[['id'=>50,'pertemuan_ke'=>5,'status'=>'Dibuka'],['id'=>30,'pertemuan_ke'=>3,'status'=>'Ditutup'],['id'=>20,'pertemuan_ke'=>2,'status'=>'Ditutup']];
    $results=[];
    foreach(['beberapa','semua','pertemuan'] as $mode_rekap){
        foreach([[50,20,999,20],[],[999]] as $sesi_filter_ids){
            $kolom_pertemuan=range(1,30);$total_sesi=2;$filter_sesi_sql='';$filter_sesi_params=[];
            eval($block);
            $results[]=[$mode_rekap,$sesi_filter_ids,$kolom_pertemuan,$total_sesi,$filter_sesi_sql,$filter_sesi_params];
        }
    }
    echo json_encode($results);`;
    const result = spawnSync(process.env.PHP_BINARY || (process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php'), [], {
        cwd:path.resolve(__dirname,'..'),input:script,encoding:'utf8'
    });
    assert.equal(result.status,0,result.stderr || result.stdout);
    const rows=JSON.parse(result.stdout);
    assert.deepEqual(rows[0].slice(1),[[20,50],[2,5],1,' AND sa.id IN (?,?)',[20,50]]);
    for(const row of rows.slice(1,3)) assert.deepEqual(row.slice(1),[[],[],0,' AND 1=0',[]]);
    for(const row of rows.slice(3)) {
        assert.equal(row[2].length,30);
        assert.equal(row[3],2);
        assert.equal(row[4],'');
        assert.deepEqual(row[5],[]);
    }
});
