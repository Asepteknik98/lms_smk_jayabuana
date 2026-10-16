const {test}=require('node:test');
const assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');
const fs=require('node:fs');
const vm=require('node:vm');
const path=require('node:path');

test('Only empty components owned by the teacher can be deleted',()=>{
 const php=String.raw`<?php
 require 'config/database.php';require 'includes/komponen_penilaian_tools.php';
 $db=Database::getInstance();
 $db->exec('CREATE TEMPORARY TABLE pengajaran(id INT PRIMARY KEY,guru_id INT) ENGINE=InnoDB');
 $db->exec('CREATE TEMPORARY TABLE komponen_penilaian(id INT PRIMARY KEY,pengajaran_id INT) ENGINE=InnoDB');
 foreach(['nilai_komponen','riwayat_nilai','capaian_penilaian'] as $t)$db->exec("CREATE TEMPORARY TABLE $t(komponen_id INT) ENGINE=InnoDB");
 $db->exec('INSERT INTO pengajaran VALUES(1,10),(2,20);INSERT INTO komponen_penilaian VALUES(1,1),(2,1),(3,1),(4,1),(5,2);INSERT INTO nilai_komponen VALUES(2);INSERT INTO riwayat_nilai VALUES(3);INSERT INTO capaian_penilaian VALUES(4)');
 kp_delete_empty($db,10,1,1);
 if($db->query('SELECT id FROM komponen_penilaian WHERE id=1')->fetchColumn())throw new RuntimeException('Empty component remains');
 foreach([2,3,4,5,999] as $id){
  $blocked=false;try{kp_delete_empty($db,10,1,$id);}catch(RuntimeException $e){$blocked=true;}
  if(!$blocked||$db->inTransaction())throw new RuntimeException('Unsafe delete accepted or transaction left open');
 }
 if((int)$db->query('SELECT COUNT(*) FROM komponen_penilaian')->fetchColumn()!==4)throw new RuntimeException('Protected component lost');
 echo 'PASS';`;
 const r=spawnSync('C:/xampp/php/php.exe',[],{cwd:path.join(__dirname,'..'),input:php,encoding:'utf8'});
 assert.equal(r.status,0,r.stderr+r.stdout);assert.match(r.stdout,/PASS/);
});

test('Edit enables weight, cancel restores it, and delete requires confirmation',()=>{
 const controls={};
 for(const cls of ['component-weight','component-edit','component-save','component-cancel','component-delete','component-delete-form']){
  controls[cls]={value:'15.00',hidden:false,disabled:false,events:{},dataset:{componentName:'UTS'},hasAttribute:()=>false,setAttribute(){},focus(){},select(){},addEventListener(name,fn){this.events[name]=fn;}};
 }
 const row={querySelector:s=>controls[s.slice(1)],classList:{toggle(){}}};
 const context={document:{querySelectorAll:()=>[row]},window:{confirm:()=>false}};
 vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../assets/js/rekap-komponen.js'),'utf8'),context);
 controls['component-edit'].events.click();assert.equal(controls['component-weight'].readOnly,false);assert.equal(controls['component-save'].hidden,false);
 controls['component-weight'].value='30';controls['component-cancel'].events.click();
 assert.equal(controls['component-weight'].value,'15.00');assert.equal(controls['component-weight'].readOnly,true);assert.equal(controls['component-save'].hidden,true);
 let prevented=false;controls['component-delete-form'].events.submit({currentTarget:controls['component-delete-form'],preventDefault(){prevented=true;}});assert.equal(prevented,true);
});
