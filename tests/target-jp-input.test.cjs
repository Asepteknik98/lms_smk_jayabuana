const {test}=require('node:test');
const assert=require('node:assert/strict');
const {suggestedQuantity,mount}=require('../assets/js/target-jp-input.js');
const data=(remaining=4)=>({ready:true,configured:true,guru_id:1,tanggal:'2026-10-19',hari:'Senin',harian:{target:6,aktual:6-remaining,kurang:remaining},mingguan:{target:24}});
test('Suggestions use remaining daily JP only within a valid single-session range',()=>{
 assert.equal(suggestedQuantity(data(4)),4);for(const n of [0,7,1.5,-1])assert.equal(suggestedQuantity(data(n)),null);
 assert.equal(suggestedQuantity({...data(),configured:false}),null);assert.equal(suggestedQuantity({...data(),ready:false}),null);assert.equal(suggestedQuantity({...data(),harian:{target:0,kurang:4}}),null);
});
const flush=()=>new Promise(r=>setImmediate(r));
function fixture(editing=false){
 const ids={};for(const id of ['rmInputTarget','rmTeacher','rmEntryDate','rmStart','rmEnd','rmInputTargetMessage','rmInputTargetRefresh','rmInputTarget_day','rmInputTarget_week','rmInputTarget_actual','rmInputTarget_remaining'])ids[id]={value:'',textContent:'',dataset:{},events:{},setAttribute(){},addEventListener(k,f){(this.events[k]??=[]).push(f)},dispatchEvent(e){for(const f of this.events[e.type]??[])f(e)}};
 ids.rmInputTarget.dataset={endpoint:'target_jp_guru_data.php',editing:editing?'1':'0'};ids.rmTeacher.value='1';ids.rmEntryDate.value='2026-10-19';ids.rmStart.value=editing?'3':'1';ids.rmEnd.value=editing?'4':'1';
 const requests=[];const fetch=(url,options)=>new Promise((resolve,reject)=>requests.push({url,options,resolve:value=>resolve({ok:true,headers:{get:()=> 'application/json'},json:async()=>({ok:true,data:value})}),reject}));
 return {ids,requests,fetch,doc:{getElementById:id=>ids[id]}};
}
test('Input preview fills new records, preserves manual changes, ignores stale requests and never submits',async()=>{
 const saved=global.fetch,f=fixture();global.fetch=f.fetch;const controller=mount(f.doc);
 try{
  f.requests[0].resolve(data());await flush();assert.equal(f.ids.rmStart.value,'1');assert.equal(f.ids.rmEnd.value,'4');assert.match(f.ids.rmInputTarget_day.textContent,/6 JP/);assert.match(f.ids.rmInputTargetMessage.textContent,/terisi otomatis/);
  f.ids.rmEnd.value='2';f.ids.rmEnd.dispatchEvent(new Event('change'));const refreshed=controller.load();f.requests[1].resolve(data());await refreshed;assert.equal(f.ids.rmEnd.value,'2');
  f.ids.rmTeacher.value='2';f.ids.rmTeacher.dispatchEvent(new Event('change'));f.ids.rmTeacher.value='1';f.ids.rmTeacher.dispatchEvent(new Event('change'));assert.equal(f.requests[2].options.signal.aborted,true);
  f.requests[3].resolve(data(3));await flush();f.requests[2].resolve({...data(6),guru_id:2});await flush();assert.equal(f.ids.rmEnd.value,'3');
  f.ids.rmEntryDate.value='2026-10-20';f.ids.rmEntryDate.dispatchEvent(new Event('change'));assert.equal(f.ids.rmEnd.value,'1');f.requests[4].reject(Error('Offline'));await flush();assert.match(f.ids.rmInputTargetMessage.textContent,/Input manual tetap tersedia/);assert.equal(f.ids.rmInputTarget_day.textContent,'—');
  f.ids.rmTeacher.value='';await controller.load(true);assert.match(f.ids.rmInputTargetMessage.textContent,/Pilih guru/);
  assert.ok(f.requests.every(r=>!r.options.method||r.options.method==='GET'));
 }finally{controller.stop();global.fetch=saved;}
});
test('Editing, zero targets, missing migration, large and fractional targets keep existing JP',async()=>{
 const saved=global.fetch;
 try{
  for(const [editing,result] of [[true,data(4)],[false,data(0)],[false,data(7)],[false,data(1.5)],[false,{...data(),harian:{target:0,aktual:0,kurang:0}}],[false,{ready:false,guru_id:1,tanggal:'2026-10-19',message:'Belum aktif'}],[false,{...data(),configured:false,harian:{target:null,aktual:0,kurang:null},mingguan:{target:null}}]]){
   const f=fixture(editing);global.fetch=f.fetch;const c=mount(f.doc);try{f.requests[0].resolve(result);await flush();assert.equal(f.ids.rmStart.value,editing?'3':'1');assert.equal(f.ids.rmEnd.value,editing?'4':'1');}finally{c.stop();}
  }
 }finally{global.fetch=saved;}
});
