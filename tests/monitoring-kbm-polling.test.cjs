const {test}=require('node:test');
const assert=require('node:assert/strict');
const {createPoller,createServerClock,mount}=require('../assets/js/monitoring-kbm-dashboard.js');
function clock(){let id=0;const timers=new Map();return {setTimeout(fn,ms){timers.set(++id,{fn,ms});return id;},clearTimeout(id){timers.delete(id);},timers,async fire(ms){const entry=[...timers].find(([,t])=>t.ms===ms);assert.ok(entry,'Missing timer '+ms);timers.delete(entry[0]);await entry[1].fn();}};}
function deferred(){let resolve,reject;const promise=new Promise((a,b)=>{resolve=a;reject=b;});return {promise,resolve,reject};}
test('WIB clock advances from server time, crosses midnight and resumes without duplicate timers',async()=>{
 const time=clock(),node={textContent:''};let elapsed=100;
 const live=createServerClock(node,{...time,now:()=>elapsed});
 live.sync('2026-10-19 23:59:59');live.start();assert.equal(node.textContent,'23:59:59');
 elapsed+=1000;await time.fire(1000);assert.equal(node.textContent,'00:00:00');
 elapsed+=61000;await time.fire(1000);assert.equal(node.textContent,'00:01:01');
 live.stop();assert.equal(time.timers.size,0);elapsed+=60000;live.start();assert.equal(node.textContent,'00:02:01');
 live.start();assert.equal(time.timers.size,1);
 live.sync('2026-10-20 07:08:09');assert.equal(node.textContent,'07:08:09');
 live.sync('invalid');assert.equal(node.textContent,'07:08:09');
 elapsed+=1000;await time.fire(1000);assert.equal(node.textContent,'07:08:10');live.stop();assert.equal(time.timers.size,0);
});
test('Polling coalesces changes and ignores stale responses without overlapping requests',async()=>{
 const time=clock(),calls=[],applied=[];
 const poller=createPoller({...time,hidden:()=>false,fetchData:()=>{const p=deferred();calls.push(p);return p.promise;},apply:r=>applied.push(r),error:()=>assert.fail('Unexpected failure')});
 const first=poller.refresh();await poller.refresh();await poller.refresh();assert.equal(calls.length,1);
 calls[0].resolve('old filter');await first;assert.deepEqual(applied,[]);assert.equal(poller.state().inflight,false);
 const next=time.fire(0);assert.equal(calls.length,2);calls[1].resolve('new filter');await next;
 assert.deepEqual(applied,['new filter']);assert.ok([...time.timers.values()].some(t=>t.ms===12000));poller.stop();assert.equal(time.timers.size,0);
});
test('Polling retries after failure, preserves last successful state, and pauses hidden tabs',async()=>{
 const time=clock();let hidden=false,fail=false,state='initial',errors=0,calls=0;
 const poller=createPoller({...time,hidden:()=>hidden,fetchData:async()=>{calls++;if(fail)throw Error('Offline');return 'server '+calls;},apply:r=>state=r,error:()=>errors++});
 await poller.refresh();assert.equal(state,'server 1');fail=true;await time.fire(12000);assert.equal(state,'server 1');assert.equal(errors,1);
 hidden=true;poller.visibility();assert.equal(time.timers.size,0);await poller.refresh();assert.equal(calls,2);
 hidden=false;fail=false;poller.visibility();await new Promise(resolve=>setImmediate(resolve));assert.equal(state,'server 3');poller.stop();
});
test('Timeout aborts a request and expired access stops automatic polling',async()=>{
 const time=clock();let failure;
 const poller=createPoller({...time,hidden:()=>false,fetchData:signal=>new Promise((_,reject)=>signal.addEventListener('abort',()=>reject(Error('Timeout')))),apply:()=>assert.fail(),error:e=>failure=e});
 const pending=poller.refresh();await time.fire(10000);await pending;assert.match(failure.message,/Timeout/);assert.equal(poller.state().inflight,false);poller.stop();
 const denied=createPoller({...time,hidden:()=>false,fetchData:async()=>{const e=Error('Forbidden');e.status=403;throw e;},apply:()=>assert.fail(),error:()=>{}});
 await denied.refresh();assert.equal(denied.state().stopped,true);assert.equal(time.timers.size,0);
 const late=deferred();let notified=0;
 const expired=createPoller({...time,hidden:()=>false,fetchData:()=>late.promise,apply:()=>assert.fail(),error:()=>notified++});
 const old=expired.refresh();await expired.refresh();const expiredError=Error('Expired');expiredError.status=401;late.reject(expiredError);await old;assert.equal(notified,1);assert.equal(expired.state().stopped,true);
});
test('Dashboard DOM keeps cards and server timestamp on connection failure, then updates without reload',async()=>{
 const saved={fetch:global.fetch,document:global.document,window:global.window,location:global.location,history:global.history,FormData:global.FormData};
 function node(){return {textContent:'',value:'',dataset:{},options:[],listeners:{},classList:{add(){},remove(){}},addEventListener(e,fn){this.listeners[e]=fn;},setAttribute(){},replaceChildren(){this.options=[];},appendChild(n){this.options.push(n);},querySelectorAll(){return [];},querySelector(){return null;}};}
 const ids={};for(const id of ['kbmDashboardConfig','kbmFilters','kbmCards','kbmConnection','kbmDate','kbmClass','kbmSession','kbmStatus','kbmIncomplete','kbmScope','kbmUpdated','kbmRefresh','kbmPollingNote','kbmCount_merah','kbmCount_hijau','kbmCount_kuning','kbmCount_abu'])ids[id]=node();
 ids.kbmDashboardConfig.textContent=JSON.stringify({endpoint:'monitoring_kbm_data.php',today:'2026-10-16'});ids.kbmDate.value='2026-10-16';
 ids.kbmCards.innerHTML='old cards';ids.kbmUpdated.textContent='old server time';
 const formFields=[ids.kbmDate,ids.kbmSession,ids.kbmClass,ids.kbmStatus];ids.kbmFilters.querySelectorAll=()=>formFields;
 const choices=['','pagi','siang'].map(value=>{const n=node();n.dataset.kbmSession=value;n.classList.toggle=(_,active)=>{n.active=active;};n.removeAttribute=()=>{};return n;});
 const doc={hidden:false,getElementById:id=>ids[id],createElement:()=>node(),addEventListener(){},querySelectorAll:()=>choices};
 let offline=true,requests=[];
 global.window={addEventListener(){}};global.location={href:'https://example.test/akademik/index.php'};global.history={replaceState(){}};
 global.FormData=class extends Map{constructor(){super([['tanggal',ids.kbmDate.value],['sesi',ids.kbmSession.value],['kelas',ids.kbmClass.value],['status',ids.kbmStatus.value]]);}};
 global.fetch=async(url)=>{requests.push(url);if(offline)throw Error('Network failed');return {ok:true,headers:{get:()=> 'application/json'},json:async()=>({ok:true,html:'new cards',server_time:'2026-10-16 07:10:12',today:'2026-10-16',date:'2026-10-16',summary:{merah:2,hijau:1,kuning:3,abu:2,incomplete:1},shown:6,total:9,class_options:[{id:1,name:'X A'}]})};};
 let poller;
 try{
  poller=mount(doc);await poller.refresh();assert.equal(ids.kbmCards.innerHTML,'old cards');assert.equal(ids.kbmUpdated.textContent,'old server time');assert.match(ids.kbmConnection.textContent,/Data belum diperbarui/);
  offline=false;ids.kbmSession.value='pagi';await poller.refresh();assert.equal(ids.kbmCards.innerHTML,'new cards');assert.equal(ids.kbmUpdated.textContent,'2026-10-16 07:10:12 WIB');assert.equal(ids.kbmCount_merah.textContent,2);assert.equal(ids.kbmCount_abu.textContent,2);assert.match(requests[1],/sesi=pagi/);assert.doesNotMatch(requests[1],/tanggal=/);assert.equal(ids.kbmConnection.textContent,'');
  assert.equal(choices[1].active,true);ids.kbmClass.value='1';ids.kbmStatus.value='merah';
  choices[2].listeners.click({preventDefault(){}});await new Promise(resolve=>setImmediate(resolve));
  assert.equal(ids.kbmClass.value,'');assert.equal(ids.kbmStatus.value,'');assert.match(requests.at(-1),/sesi=siang/);assert.equal(choices[2].active,true);
  choices[0].listeners.click({preventDefault(){}});await new Promise(resolve=>setImmediate(resolve));assert.equal(ids.kbmSession.value,'');assert.equal(choices[0].active,true);
 }finally{poller?.stop();for(const [key,value]of Object.entries(saved)){if(value===undefined)delete global[key];else global[key]=value;}}
});
