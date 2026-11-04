const assert = require('node:assert/strict');
const {spawnSync} = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const {pathToFileURL} = require('node:url');
const root = path.resolve(__dirname, '..');
const php = process.env.PHP_BINARY || 'C:\\xampp\\php\\php.exe';
function render(role, page, method='GET', post={}, get={siswa_id:'999999'}) {
    const code = `<?php
    require 'config/session.php'; require 'config/database.php';
    $db=Database::getInstance();
    $q=$db->prepare('SELECT u.* FROM users u WHERE u.role_id=? AND u.is_active=1 AND (u.role_id<>3 OR EXISTS(SELECT 1 FROM siswa s JOIN kelas k ON k.id=s.kelas_id WHERE s.user_id=u.id)) ORDER BY u.id LIMIT 1');
    $q->execute([${role}]);$u=$q->fetch();if(!$u)throw new RuntimeException('No test actor');
    $_SESSION['user_id']=$u['id'];$_SESSION['role_id']=(int)$u['role_id'];
    $_SERVER['SCRIPT_NAME']='/${page}';$_SERVER['PHP_SELF']='/${page}';$_SERVER['REQUEST_METHOD']='${method}';
    $_POST=json_decode(getenv('KPS_TEST_POST'),true);$_GET=json_decode(getenv('KPS_TEST_GET'),true);
    register_shutdown_function(function(){file_put_contents('php://stderr','STATUS:'.(http_response_code()?:200));});
    require '${page}';`;
    const sessions=fs.mkdtempSync(path.join(os.tmpdir(),'lms-kps-session-'));
    let out;
    try { out=spawnSync(php,['-d',`session.save_path=${sessions}`],{cwd:root,input:code,encoding:'utf8',env:{...process.env,KPS_TEST_POST:JSON.stringify(post),KPS_TEST_GET:JSON.stringify(get)},timeout:15000,maxBuffer:4000000}); }
    finally { fs.rmSync(sessions,{recursive:true,force:true}); }
    assert.equal(out.status,0,out.stderr);
    assert.doesNotMatch(out.stdout,/Fatal error|Warning:|Parse error/);
    return {html:out.stdout,status:Number((out.stderr.match(/STATUS:(\d+)/)||[])[1])};
}
for(const [role,page] of [[1,'admin/kredit_poin.php'],[2,'guru/kredit_poin.php'],[3,'siswa/kredit_poin.php']]) {
    const out=render(role,page);
    if(role===2&&out.status===403){assert.match(out.html,/guru yang ditunjuk admin/);continue;}
    assert.equal(out.status,200);assert.match(out.html,/Kredit Poin Siswa/);
    assert.doesNotMatch(out.html,/Penambahan|Tambah poin|value="tambah"/);
    if(role===3)assert.doesNotMatch(out.html,/name="poin"/);else assert.match(out.html,/name="csrf_token"/);
}
const student=render(3,'siswa/kredit_poin.php');
const admin=render(1,'admin/kredit_poin.php');
const history=render(1,'admin/kredit_poin.php','GET',{}, {halaman:'riwayat'});
assert.doesNotMatch(admin.html,/id="kpsHistory"/);
assert.doesNotMatch(history.html,/id="kpsPelanggaran"/);
assert.match(history.html,/Rekap Excel/);assert.match(history.html,/name="halaman" value="riwayat"/);
assert.match(history.html,/id="kpsSearch"/);
assert.match(admin.html,/kredit-poin-manage\.js\?v=\d+/,'Point autofill script URL must change when source changes');
const master=render(1,'admin/master_pelanggaran.php');
assert.equal(master.status,200);assert.match(master.html,/Master Pelanggaran/);
assert.equal(render(2,'admin/master_pelanggaran.php').status,403);
assert.equal(render(3,'admin/master_pelanggaran.php').status,403);
assert.match(render(1,'admin/master_pelanggaran.php','POST',{csrf_token:'invalid'}).html,/Sesi keamanan berakhir/);
assert.match(render(1,'admin/master_pelanggaran.php','POST',{action:'hapus',id:'1',csrf_token:'invalid'}).html,/Sesi keamanan berakhir/,'Delete requires CSRF');
assert.match(student.html,/Riwayat Pelanggaran/);
assert.match(student.html,/Saldo awal: <strong>100 poin/);
assert.match(student.html,/role="progressbar"/);
assert.equal(render(3,'admin/kredit_poin.php').status,403);
assert.equal(render(3,'guru/kredit_poin.php').status,403);
assert.equal(render(2,'siswa/kredit_poin.php').status,403);
assert.equal(render(3,'siswa/kredit_poin.php','POST').status,405);
assert.match(render(1,'admin/kredit_poin.php','POST',{csrf_token:'invalid'}).html,/Sesi keamanan berakhir/);
const dashboard=render(3,'siswa/index.php');assert.equal(dashboard.status,200);assert.match(dashboard.html,/Kredit Poin Siswa/);
console.log('PASS: real student/admin/teacher pages, role isolation, student read-only, CSRF rejection, dashboard.');

const chrome=process.env.CHROME_BINARY || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
if(!fs.existsSync(chrome)) { console.log('SKIP: Chrome layout checks (Chrome unavailable)');process.exit(0); }
const temp=fs.mkdtempSync(path.join(os.tmpdir(),'lms-kps-layout-'));
try {
    const base=pathToFileURL(path.join(root,'siswa')+path.sep).href;
    const audit=(kind)=>`${['admin','history'].includes(kind)?'<script>'+fs.readFileSync(path.join(root,'assets/js/kredit-poin-manage.js'),'utf8')+'</script>':kind==='master'?'<script>'+fs.readFileSync(path.join(root,'assets/js/master-pelanggaran.js'),'utf8')+'</script>':''}<pre id="kps-layout-result">PENDING</pre><script>
    window.addEventListener('load',()=>{try{
      const check=(v,m)=>{if(!v)throw Error(m)};
      if('${kind}'==='dashboard'){
      const links=document.querySelectorAll('.student-credit-stack .student-credit-link');
      check(links.length===2,'Two credit cards');
      const first=links[0].getBoundingClientRect(),second=links[1].getBoundingClientRect();
      const banner=document.querySelector('.welcome-card').getBoundingClientRect();
      check(first.top>=banner.top&&second.bottom<=banner.bottom,'Credit cards inside welcome banner');
      if(innerWidth<350)check(second.top>=first.bottom,'Smallest screen stacks credit cards');
      else check(second.left>=first.right&&Math.abs(first.top-second.top)<2,'Reference cards side by side');
      check(second.width>=110,'Card readable');
      check(!document.querySelector('.welcome-avatar'),'Profile shows name without initial avatar');
      check(getComputedStyle(document.querySelector('.welcome-card')).backgroundImage.includes('batikjb'),'Batik background retained');
      check(document.querySelectorAll('.credit-banner-track[role=progressbar]').length===2,'Both cards show progress');
      check(second.right<=innerWidth+1,'Card fits screen');
      const score=document.querySelector('.student-credit-points .student-credit-score');
      const checkScore=()=>{
        const range=document.createRange();range.selectNodeContents(score);
        const text=range.getBoundingClientRect(),box=score.getBoundingClientRect();
        check(text.height<=parseFloat(getComputedStyle(score).fontSize)*1.5,'Score stays on one line');
        check(text.left>=box.left&&text.right<=box.right,'Score fits its badge');
        check(text.top>=box.top&&text.bottom<=box.bottom,'Score fits badge height');
      };
      checkScore();
      score.textContent='100';checkScore();
      score.textContent='1000000';score.classList.add('is-long');checkScore();
      }else if(['admin','history'].includes('${kind}')){
        const main=document.querySelector('.kps-manage .kps-main');
        check(main&&main.scrollWidth<=main.clientWidth+1,'Admin content fits screen');
        const filter=document.querySelector('.kps-filter-form');
        const students=JSON.parse(document.querySelector('#kpsClassStudents').textContent);
        document.querySelectorAll('[data-kps-student-group]').forEach(group=>{
          const cls=group.querySelector('[data-kps-class]'),select=group.querySelector('[data-kps-student]');
          check(!cls.value&&select.disabled,'Class comes before students');
          const student=students.find(item=>item.kelas_id);check(student,'Student fixture exists');
          cls.value=String(student.kelas_id);cls.dispatchEvent(new Event('change'));
          const expected=students.filter(item=>String(item.kelas_id)===cls.value);
          check(!select.disabled&&select.options.length===expected.length+1,'Only selected class students shown');
          check(Array.from(select.options).slice(1).every(option=>expected.some(item=>String(item.id)===option.value)),'Other class students excluded');
          select.value=String(student.id);
          const other=Array.from(cls.options).find(option=>option.value&&option.value!==cls.value);
          if(other){cls.value=other.value;cls.dispatchEvent(new Event('change'));check(select.value==='','Changing class resets student');}
          cls.value='';cls.dispatchEvent(new Event('change'));check(select.disabled,'Clear class disables students');
        });
        if(filter){check(filter.getBoundingClientRect().right<=innerWidth+1,'Filter fits screen');
        check(filter.querySelector('button').getBoundingClientRect().height>=44,'Filter touch target');}
        const tabs=document.querySelector('.kps-page-tabs');check(tabs.scrollWidth<=tabs.clientWidth+1,'Page navigation fits screen');
        document.querySelectorAll('.kps-manage input:not([type=hidden]),.kps-manage select,.kps-manage textarea').forEach(input=>check(input.getBoundingClientRect().right<=innerWidth+1,'Form control fits screen'));
        const history=document.querySelector('.kps-table');
        if(history){
          const body=history.querySelector('tbody'),sample=body.querySelector('tr');
          if(sample)for(let i=body.children.length;i<10;i++)body.appendChild(sample.cloneNode(true));
          const wrap=document.querySelector('.kps-history-wrap');
          check(wrap.clientHeight<=parseFloat(getComputedStyle(wrap).maxHeight)+1,'History panel height bounded');
          check(wrap.scrollHeight>wrap.clientHeight,'Long history scrolls inside panel');
          check(getComputedStyle(wrap).overflowY==='auto','History scroll enabled');
          const detail=history.querySelector('details');
          if(detail){check(!detail.open,'Details initially compact');detail.open=true;check(wrap.clientHeight<=parseFloat(getComputedStyle(wrap).maxHeight)+1,'Expanded details stay in bounded panel');}
        }
        if(history&&innerWidth<768){
          check(getComputedStyle(history.querySelector('thead')).display==='table-header-group','Mobile keeps spreadsheet header');
          const wrap=document.querySelector('.kps-history-wrap');
          check(wrap.getBoundingClientRect().right<=innerWidth+1,'Mobile scroll panel fits screen');
          check(wrap.scrollWidth>wrap.clientWidth,'Mobile table can scroll horizontally');
          wrap.scrollLeft=100;check(wrap.scrollLeft>0,'Swipe area scrolls');wrap.scrollLeft=0;
        }
        if(history){
          const heading=history.querySelector('th'),cell=history.querySelector('tbody td');
          check(parseFloat(getComputedStyle(heading).borderRightWidth)>=1&&parseFloat(getComputedStyle(cell).borderRightWidth)>=1,'Column grid lines');
          check(parseFloat(getComputedStyle(cell).borderBottomWidth)>=1,'Row grid lines');
          const rows=history.querySelectorAll('tbody tr');
          check(getComputedStyle(rows[0].querySelector('td')).backgroundColor!==getComputedStyle(rows[1].querySelector('td')).backgroundColor,'Alternating row colors');
        }
        const violation=document.querySelector('#kpsPelanggaran'),points=document.querySelector('#kpsPoin');
        if(violation){
        check(violation.options.length===54,'53 violations plus placeholder');
        const firstOption=Array.from(violation.options).find(option=>option.dataset.poin==='1');
        violation.value=firstOption.value;violation.dispatchEvent(new Event('change'));
        check(points.value==='1'&&!points.readOnly&&!points.disabled,'Standard points auto-filled and editable');
        points.value='7';points.dispatchEvent(new Event('input'));check(points.value==='7','Manual point override preserved');
        const special=Array.from(violation.options).find(option=>option.dataset.penanganan);
        violation.value=special.value;violation.dispatchEvent(new Event('change'));
        check(points.value===special.dataset.poin,'Different violation updates standard points');
        check(document.querySelector('[data-kps-handling]').textContent.includes(special.dataset.penanganan),'Handling displayed');
        }
      }else if('${kind}'==='master'){
        const list=document.querySelector('.kps-master-list');check(list,'Master list exists');
        check(list.getBoundingClientRect().right<=innerWidth+1,'Master list fits screen');
        const grid=list.querySelector('.kps-master-table');check(grid,'Master uses spreadsheet table');
        check(grid.querySelectorAll('th').length===7,'Seven labeled master columns');
        check(grid.querySelectorAll('tbody tr').length<=10,'Master limited to ten rows per page');
        const cells=grid.querySelectorAll('tbody tr:first-child td');
        check(parseFloat(getComputedStyle(cells[0]).borderRightWidth)>=1,'Master column borders');
        check(parseFloat(getComputedStyle(cells[0]).borderBottomWidth)>=1,'Master row borders');
        check(getComputedStyle(grid.querySelector('th')).position==='sticky','Master header stays visible');
        check(list.clientHeight<=parseFloat(getComputedStyle(list).maxHeight)+1,'Master height bounded');
        if(innerWidth<1020){check(list.scrollWidth>list.clientWidth,'Master horizontal scroll available');list.scrollLeft=100;check(list.scrollLeft>0,'Master scroll works');list.scrollLeft=0;}
        const editor=document.querySelector('#kpsMasterDialog'),trigger=document.querySelector('[data-master-add]');
        check(!editor.open,'Master form initially hidden');trigger.focus();trigger.click();check(editor.open&&editor.matches(':modal'),'Add opens master popup');
        check(editor.getBoundingClientRect().left>=0&&editor.getBoundingClientRect().right<=innerWidth+1,'Master popup fits screen');
        editor.querySelectorAll('input:not([type=hidden]),select,textarea').forEach(input=>check(input.getBoundingClientRect().right<=innerWidth+1,'Master editor fits screen'));
        check(editor.querySelector('button[type=submit]').getBoundingClientRect().height>=44,'Master save touch target');
        const form=editor.querySelector('form');form.querySelector('[name=id]').value='42';form.querySelector('[name=nama]').value='Old edit';
        editor.querySelector('[data-master-close]').click();check(!editor.open,'Master close works');
        trigger.click();check(form.querySelector('[name=id]').value==='0'&&form.querySelector('[name=nama]').value==='','New popup clears previous edit');
        form.querySelector('[name=nama]').value='Test violation';form.querySelector('[name=poin]').value='3';
        form.addEventListener('submit',event=>event.preventDefault());form.requestSubmit();check(form.querySelector('button[type=submit]').disabled,'Master duplicate submission prevented');
        editor.close();
        const remove=document.querySelector('[data-master-delete]'),confirmation=document.querySelector('#kpsMasterDeleteDialog');
        check(remove&&confirmation,'Delete action available');remove.focus();remove.click();
        check(confirmation.open&&confirmation.matches(':modal'),'Delete opens confirmation');
        check(confirmation.querySelector('[data-master-delete-name]').textContent===remove.dataset.masterName,'Correct violation shown before delete');
        check(confirmation.querySelector('[name=id]').value===remove.dataset.masterId,'Correct deletion target');
        check(confirmation.getBoundingClientRect().left>=0&&confirmation.getBoundingClientRect().right<=innerWidth+1,'Delete popup fits screen');
        confirmation.querySelector('[data-master-delete-close]').click();check(!confirmation.open,'Delete can be cancelled');
        remove.click();const deleteForm=confirmation.querySelector('form');deleteForm.addEventListener('submit',event=>event.preventDefault());deleteForm.requestSubmit();check(deleteForm.querySelector('button[type=submit]').disabled,'Delete duplicate submission prevented');
      }else{
        const balance=document.querySelector('.kps-balance').getBoundingClientRect();
        const history=document.querySelector('.kps-card').getBoundingClientRect();
        check(history.top>=balance.bottom,'History below summary');
        check(balance.right<=innerWidth+1,'Summary fits screen');
        check(balance.width>200,'Summary readable');
        check(getComputedStyle(document.querySelector('.kps-balance')).backgroundImage.includes('23, 37, 84'),'Blue summary matches activity style');
      }
      check(document.documentElement.scrollWidth<=innerWidth+1,'No horizontal overflow');
      document.getElementById('kps-layout-result').textContent='LAYOUT_PASS';
      parent.postMessage('LAYOUT_PASS','*');
    }catch(e){document.getElementById('kps-layout-result').textContent='LAYOUT_FAIL: '+e.message;parent.postMessage('LAYOUT_FAIL: '+e.message,'*');}});
    </script>`;
    for(const [kind,source] of [['dashboard',dashboard.html],['student',student.html],['admin',admin.html],['history',history.html],['master',master.html]].filter(([kind])=>!process.env.KPS_LAYOUT_TARGET||process.env.KPS_LAYOUT_TARGET.split(',').includes(kind))){
    const html=source.replace('<head>',`<head><base href="${base}">`).replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,script=>script.includes('type="application/json"')?script:'');
    const fixture=path.join(temp,kind+'.html');fs.writeFileSync(fixture,html.replace('</body>',audit(kind)+'</body>'));
    for(const width of (['admin','history','master'].includes(kind)?[1440,1024,768,390,320]:[1440,768,390,320])) {
        const wrapper=path.join(temp,kind+'-'+width+'.html');
        fs.writeFileSync(wrapper,`<!doctype html><html><head><meta charset="UTF-8"></head><body style="margin:0"><iframe src="${pathToFileURL(fixture).href}" style="display:block;border:0;width:${width}px;height:1000px"></iframe><pre id="kps-layout-result">PENDING</pre><script>addEventListener('message',e=>{if(typeof e.data==='string'&&e.data.startsWith('LAYOUT_'))document.getElementById('kps-layout-result').textContent=e.data;});</script></body></html>`);
        const out=spawnSync(chrome,['--headless','--no-sandbox','--disable-gpu','--no-first-run','--disable-background-networking',`--user-data-dir=${path.join(temp,'chrome-'+kind+'-'+width)}`,`--window-size=${Math.max(width,800)},1100`,'--dump-dom',pathToFileURL(wrapper).href],{encoding:'utf8',timeout:25000,maxBuffer:4000000});
        assert.equal(out.status,0,out.error?.message||out.stderr);
        assert.match(out.stdout,/<pre id="kps-layout-result">LAYOUT_PASS<\/pre>/,`Layout at ${width}px: ${(out.stdout.match(/LAYOUT_FAIL:[^<]+/)||[])[0]||'audit not run'}`);
        console.log(`PASS: Chrome ${kind} layout at ${width}px`);
    }
    }
} finally { fs.rmSync(temp,{recursive:true,force:true}); }
