const {test} = require('node:test');
const assert = require('node:assert/strict');
const {spawnSync} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');

test('cancellation removes one owned session atomically; foreign, missing and failed requests preserve attendance', () => {
    const php = String.raw`<?php
    $source=file_get_contents('guru/rekap_absensi.php');
    $start=strpos($source,"        try {",strpos($source,"=== 'batalkan_pertemuan'"));
    $end=strpos($source,"        header('Location: '",$start);
    $block=substr($source,$start,$end-$start);
    function catat_log($user,$message){global $db;$db->events[]=['log',$user,$message];if($db->failure==='log')throw new Exception('log failed');}
    class FakeDb {
        public $events=[],$active=false,$snapshot,$sessions,$details;
        function __construct(public $failure=''){
            $this->sessions=[11=>['id'=>11,'pertemuan_ke'=>11,'tanggal'=>'2026-11-06','pengajaran_id'=>4,'guru_id'=>7,'status'=>'Ditutup'],12=>['id'=>12,'pertemuan_ke'=>12,'tanggal'=>'2026-11-06','pengajaran_id'=>4,'guru_id'=>7,'status'=>'Dibuka']];
            $this->details=[11=>['Hadir','Alpa','Sakit'],12=>['Hadir']];
        }
        function beginTransaction(){$this->active=true;$this->snapshot=[$this->sessions,$this->details];$this->events[]='begin';}
        function commit(){$this->active=false;$this->events[]='commit';}
        function rollBack(){[$this->sessions,$this->details]=$this->snapshot;$this->active=false;$this->events[]='rollback';}
        function inTransaction(){return $this->active;}
        function prepare($sql){return new FakeStmt($this,$sql);}
    }
    class FakeStmt {
        public $row=false;
        function __construct(public $db,public $sql){}
        function execute($args){
            $this->db->events[]=[$this->sql,$args];
            if(str_starts_with($this->sql,'SELECT')){
                if(!str_contains($this->sql,'FOR UPDATE')||!str_contains($this->sql,'p.guru_id=?'))throw new Exception('missing lock/owner');
                $row=$this->db->sessions[$args[0]]??false;
                $this->row=$row && $row['pengajaran_id']===$args[1] && $row['guru_id']===$args[2] ? $row : false;
            } elseif(str_starts_with($this->sql,'DELETE FROM detail_absensi')){
                unset($this->db->details[$args[0]]);
            } elseif(str_starts_with($this->sql,'DELETE FROM sesi_absensi')){
                if($this->db->failure==='delete')throw new Exception('delete failed');
                if(($this->db->sessions[$args[0]]['pengajaran_id']??null)===$args[1])unset($this->db->sessions[$args[0]]);
            } else throw new Exception('unexpected query');
        }
        function fetch(){return $this->row;}
    }
    $results=[];$_SESSION=['user_id'=>90];
    foreach([[11,4,7,''],[12,4,7,''],[11,4,8,''],[11,5,7,''],[99,4,7,''],[11,4,7,'delete'],[11,4,7,'log']] as $case){
        [$sesi_id,$pengajaran_post,$guru_id,$failure]=$case;
        $db=new FakeDb($failure);$_SESSION=['user_id'=>90];
        eval($block);
        $results[]=[$db->sessions,$db->details,$_SESSION,$db->events,$db->active];
    }
    echo json_encode($results);`;
    const result=spawnSync(process.env.PHP_BINARY || (process.platform==='win32'?'C:/xampp/php/php.exe':'php'),[],{cwd:root,input:php,encoding:'utf8'});
    assert.equal(result.status,0,result.stderr || result.stdout);
    const rows=JSON.parse(result.stdout);
    for(const [index,deleted,remaining] of [[0,'11','12'],[1,'12','11']]){
        const [sessions,details,session,events,active]=rows[index];
        assert.equal(sessions[deleted],undefined);
        assert.equal(details[deleted],undefined);
        assert.ok(sessions[remaining]);
        assert.ok(details[remaining]);
        assert.ok(session.rekap_absensi_success);
        assert.equal(events.at(-1),'commit');
        assert.equal(active,false);
    }
    for(const [sessions,details,session,events,active] of rows.slice(2)){
        assert.deepEqual(Object.keys(sessions),['11','12']);
        assert.deepEqual(details,{'11':['Hadir','Alpa','Sakit'],'12':['Hadir']});
        assert.ok(session.rekap_absensi_error);
        assert.equal(events.at(-1),'rollback');
        assert.equal(active,false);
    }
});

test('cancellation uses the existing CSRF gate and explicit confirmation',()=>{
    const source=fs.readFileSync(path.join(root,'guru/rekap_absensi.php'),'utf8');
    assert.ok(source.indexOf("if (!verify_csrf(")<source.indexOf("=== 'batalkan_pertemuan'"));
    assert.match(source,/name="action" value="batalkan_pertemuan"/);
    assert.match(source,/!confirm\('Batalkan '/);
});
