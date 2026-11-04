const assert=require('node:assert/strict');
const fs=require('node:fs');const os=require('node:os');const path=require('node:path');
const {spawnSync}=require('node:child_process');const {pathToFileURL}=require('node:url');
const root=path.resolve(__dirname,'..'),php=process.env.PHP_BINARY||'C:\\xampp\\php\\php.exe';
const temp=fs.mkdtempSync(path.join(os.tmpdir(),'lms-credit-access-pages-'));
function render(role,page,post=null){
 const code=`<?php require 'config/session.php';require 'config/database.php';$db=Database::getInstance();$q=$db->prepare('SELECT id FROM users WHERE role_id=? AND is_active=1 ORDER BY id LIMIT 1');$q->execute([${role}]);$_SESSION['user_id']=$q->fetchColumn();$_SESSION['role_id']=${role};$_SERVER['SCRIPT_NAME']='/${page}';$_SERVER['PHP_SELF']='/${page}';$_SERVER['REQUEST_METHOD']='${post?'POST':'GET'}';$_POST=json_decode(getenv('ACCESS_TEST_POST'),true);register_shutdown_function(function(){file_put_contents('php://stderr','STATUS:'.(http_response_code()?:200));});require '${page}';`;
 const out=spawnSync(php,['-d',`session.save_path=${temp}`],{cwd:root,input:code,encoding:'utf8',env:{...process.env,ACCESS_TEST_POST:JSON.stringify(post||{})},timeout:15000,maxBuffer:4000000});
 assert.equal(out.status,0,out.stderr);assert.doesNotMatch(out.stdout,/Fatal error|Warning:|Parse error/);return {html:out.stdout,status:Number((out.stderr.match(/STATUS:(\d+)/)||[])[1])};
}
try{
 const settings=render(1,'admin/kredit_akses.php');assert.equal(settings.status,200);assert.match(settings.html,/Pengaturan Akses Kredit Siswa/);assert.match(settings.html,/name="csrf_token"/);assert.match(settings.html,/Kredit Poin Siswa/);assert.match(settings.html,/Kredit Aktivitas Siswa/);
 for(const role of [2,3])assert.equal(render(role,'admin/kredit_akses.php').status,403,'Only admin may configure permissions');
 assert.match(render(1,'admin/kredit_akses.php',{csrf_token:'invalid',poin:[],aktivitas:[]}).html,/Sesi keamanan berakhir/);
 for(const page of ['guru/kak.php','guru/kredit_poin.php','guru/kak_bukti.php']){
   const result=render(2,page);
   if(result.status===403)assert.match(result.html,/guru yang ditunjuk admin/);
   else assert.ok([200,404].includes(result.status));
 }
 console.log('PASS: admin settings page, teacher/student denial, CSRF, protected teacher routes.');
 const chrome=process.env.CHROME_BINARY||'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
 if(fs.existsSync(chrome)){
   const audit=`<script>${fs.readFileSync(path.join(root,'assets/js/kredit-akses.js'),'utf8')}</script><script>
   addEventListener('load',()=>{try{
    const check=(v,m)=>{if(!v)throw Error(m)};check(document.documentElement.scrollWidth<=innerWidth+1,'No overflow');
    const cards=[...document.querySelectorAll('.credit-access-card')];check(cards.length===2,'Separate module cards');
    const box=cards[0].querySelector('input[type=checkbox]');
    if(box){
      const other=cards[1].querySelector('input[value="'+box.value+'"]'),original=other.checked;
      box.checked=!box.checked;box.dispatchEvent(new Event('change',{bubbles:true}));
      check(other.checked===original,'Modules independent');check(Number(cards[0].querySelector('[data-selected-count]').textContent)===cards[0].querySelectorAll('input:checked').length,'Selected count');
      const saved=box.checked,search=document.querySelector('#creditTeacherSearch');search.value='NO_MATCH_TEACHER_987';search.dispatchEvent(new Event('input'));
      check(!cards[0].querySelector('[data-search-empty]').hidden,'Search empty state');check(box.checked===saved,'Search preserves selection');
      search.value='';search.dispatchEvent(new Event('input'));check(!box.closest('label').hidden,'Clear search restores teachers');
      check(box.closest('label').getBoundingClientRect().height>=44,'Checkbox touch target');
    }
    const tabs=[...document.querySelectorAll('[data-credit-tab]')];check(tabs.length===2&&!cards[0].hidden&&cards[1].hidden,'One module visible at a time');
    const selectedBefore=cards.map(card=>[...card.querySelectorAll('input:checked')].map(input=>input.value).join(','));
    tabs[1].click();check(cards[0].hidden&&!cards[1].hidden,'Activity tab switches panel');
    check(cards.every((card,i)=>[...card.querySelectorAll('input:checked')].map(input=>input.value).join(',')===selectedBefore[i]),'Switching tabs preserves selections');
    tabs[0].click();
    const form=document.querySelector('#creditAccessForm');form.addEventListener('submit',e=>e.preventDefault());form.requestSubmit();check(form.querySelector('button[type=submit]').disabled,'Duplicate submission prevented');
    parent.postMessage('ACCESS_PASS','*');
   }catch(e){parent.postMessage('ACCESS_FAIL: '+e.message,'*');}});</script>`;
   const html=settings.html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'').replace('<head>',`<head><base href="${pathToFileURL(path.join(root,'admin')+path.sep).href}">`);
   const fixture=path.join(temp,'settings.html');fs.writeFileSync(fixture,html.replace('</body>',audit+'</body>'));
   for(const width of [1440,768,390,320]){
     const wrapper=path.join(temp,'frame-'+width+'.html');fs.writeFileSync(wrapper,`<!doctype html><html><body style="margin:0"><iframe src="${pathToFileURL(fixture).href}" style="border:0;width:${width}px;height:1400px"></iframe><pre id="audit">PENDING</pre><script>addEventListener('message',e=>{if(typeof e.data==='string'&&e.data.startsWith('ACCESS_'))document.getElementById('audit').textContent=e.data;});</script></body></html>`);
     const out=spawnSync(chrome,['--headless','--no-sandbox','--disable-gpu','--no-first-run','--disable-background-networking',`--user-data-dir=${path.join(temp,'chrome-'+width)}`,'--window-size=1500,1500','--dump-dom',pathToFileURL(wrapper).href],{encoding:'utf8',timeout:25000,maxBuffer:4000000});
     assert.equal(out.status,0,out.error?.message||out.stderr);assert.match(out.stdout,/<pre id="audit">ACCESS_PASS<\/pre>/,(out.stdout.match(/ACCESS_FAIL:[^<]+/)||[])[0]||'Browser audit did not finish');console.log('PASS: access settings layout and selection controls at '+width+'px');
   }
 }
}finally{
 const resolved=path.resolve(temp);assert.ok(resolved.startsWith(path.resolve(os.tmpdir())+path.sep)&&path.basename(resolved).startsWith('lms-credit-access-pages-'));fs.rmSync(resolved,{recursive:true,force:true});
}
