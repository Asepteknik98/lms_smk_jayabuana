const assert=require('node:assert/strict');
const fs=require('node:fs');
const os=require('node:os');
const path=require('node:path');
const {spawnSync}=require('node:child_process');
const {pathToFileURL}=require('node:url');
const root=path.resolve(__dirname,'..');
const php=process.env.PHP_BINARY||'C:\\xampp\\php\\php.exe';
const temp=fs.mkdtempSync(path.join(os.tmpdir(),'lms-kak-pages-'));
function run(code,env={}){
    const result=spawnSync(php,['-d',`session.save_path=${temp}`],{cwd:root,input:'<?php '+code,encoding:'utf8',env:{...process.env,...env},timeout:15000,maxBuffer:4000000});
    assert.equal(result.status,0,result.stderr);assert.doesNotMatch(result.stdout,/Fatal error|Warning:|Parse error/);return result.stdout;
}
try{
    const setup=`require 'config/session.php';require 'config/database.php';$db=Database::getInstance();$user=$db->query('SELECT u.id FROM users u JOIN siswa s ON s.user_id=u.id WHERE u.role_id=3 AND u.is_active=1 LIMIT 1')->fetchColumn();$_SESSION['user_id']=$user;$_SESSION['role_id']=3;$_SERVER['SCRIPT_NAME']='/siswa/kak.php';$_SERVER['PHP_SELF']='/siswa/kak.php';$_SERVER['REQUEST_METHOD']='GET';`;
    const page=run(setup+`require 'siswa/kak.php';`);
    assert.match(page,/Klaim Aktivitas Siswa/);assert.ok(/Belum ada acara untuk diklaim|Klaim Aktivitas<\/span>/.test(page));
    const total=run(setup+`require 'includes/kak_klaim.php';$student=kak_claim_student($db,(int)$user);echo kak_claim_total($db,(int)$student['id'],$student['tingkat']??'');`);
    assert.doesNotMatch(page,/Target kumulatif|Pencapaian kumulatif|Seluruh poin aktivitas dari kelas X sampai XII dijumlahkan/);
    assert.match(page,/Pencapaian tingkat ini/);
    assert.ok(page.includes('class="kak-number fw-bold my-2">'+total+'</div>'),'Student summary only counts approved claims');
    let staffPage;
    const masterSetup=`require 'config/session.php';require 'config/database.php';$db=Database::getInstance();$_SESSION['user_id']=$db->query('SELECT id FROM users WHERE role_id=1 AND is_active=1 LIMIT 1')->fetchColumn();$_SESSION['role_id']=1;$_SERVER['SCRIPT_NAME']='/admin/master_aktivitas.php';$_SERVER['PHP_SELF']='/admin/master_aktivitas.php';$_SERVER['REQUEST_METHOD']='GET';`;
    const masterPage=run(masterSetup+`require 'admin/master_aktivitas.php';`);
    assert.match(masterPage,/33 aktivitas/);assert.match(masterPage,/id="activityDialog"/);assert.match(masterPage,/class="table kps-master-table"/);
    assert.equal((masterPage.match(/class="master-name"/g)||[]).length,10,'Master server page bounded to 10 rows');
    const invalidMaster=run(masterSetup+`$_SERVER['REQUEST_METHOD']='POST';$_POST=['csrf_token'=>'bad'];require 'admin/master_aktivitas.php';`);
    assert.match(invalidMaster,/Sesi keamanan berakhir/);
    const invalidDelete=run(masterSetup+`$_SERVER['REQUEST_METHOD']='POST';$_POST=['action'=>'hapus','id'=>'1','csrf_token'=>'bad'];require 'admin/master_aktivitas.php';`);
    assert.match(invalidDelete,/Sesi keamanan berakhir/);
    assert.doesNotMatch(invalidDelete,/data-auto-open="true"/,'Delete failure does not open edit form');
    for(const role of [1,2]){
        const folder=role===1?'admin':'guru';
        const staffSetup=`require 'config/session.php';require 'config/database.php';$db=Database::getInstance();$_SESSION['user_id']=$db->query('SELECT id FROM users WHERE role_id=${role} AND is_active=1 LIMIT 1')->fetchColumn();$_SESSION['role_id']=${role};$_SERVER['SCRIPT_NAME']='/${folder}/kak.php';$_SERVER['PHP_SELF']='/${folder}/kak.php';$_SERVER['REQUEST_METHOD']='GET';`;
        const staffResult=run(staffSetup+`require '${folder}/kak.php';`);
        if(role===2&&staffResult.includes('guru yang ditunjuk admin'))continue;
        staffPage=staffResult;
        assert.match(staffPage,/Buat Acara untuk Diklaim/);assert.doesNotMatch(staffPage,/id="kakReviewPanel"|id="kakEventsPanel"/);assert.doesNotMatch(staffPage,/value="tambah_aktivitas"/);
        for(const [view,panel,absent] of [['verifikasi','kakReviewPanel','kakEventsPanel'],['acara','kakEventsPanel','kakReviewPanel']]){
            const separate=run(staffSetup+`$_GET=['halaman'=>'${view}'];require '${folder}/kak.php';`);
            assert.ok(separate.includes('id="'+panel+'"'),'Requested page rendered');
            assert.ok(!separate.includes('id="'+absent+'"')&&!separate.includes('data-kak-staff-form'),'Other page content is absent');
            assert.ok(separate.includes('name="halaman" value="'+view+'"'),'Search preserves page');
            assert.match(separate,/Rekap Excel/);assert.match(separate,/export=excel/);
            assert.equal((separate.match(/aria-current="page"/g)||[]).length,1,'One active navigation item');
        }
        assert.doesNotMatch(staffPage,/name="klaim_mulai"|name="klaim_sampai"|Batas klaim \(WIB\)/);
        const refused=run(staffSetup+`$_SERVER['REQUEST_METHOD']='POST';$_POST=['action'=>'tambah_aktivitas','csrf_token'=>$_SESSION['csrf_token']];register_shutdown_function(function(){echo $_SESSION['kak_staff_error']??'NO_ERROR';});require '${folder}/kak.php';`);
        assert.match(refused,/Pemberian poin langsung tidak tersedia/);
        const deniedCsrf=run(staffSetup+`$_SERVER['REQUEST_METHOD']='POST';$_POST=['action'=>'buat_acara','csrf_token'=>'invalid'];register_shutdown_function(function(){echo $_SESSION['kak_staff_error']??'NO_ERROR';});require '${folder}/kak.php';`);
        assert.match(deniedCsrf,/Sesi keamanan berakhir/);
    }
    const proof=run(setup+`$_GET=['id'=>999999];ob_start();require 'siswa/kak_bukti.php';`);
    assert.match(proof,/Bukti tidak ditemukan/);
    const csrf=run(setup+`$_SERVER['REQUEST_METHOD']='POST';$_POST=['csrf_token'=>'invalid'];register_shutdown_function(function(){echo $_SESSION['kak_claim_error']??'NO_ERROR';});require 'siswa/kak.php';`);
    assert.match(csrf,/Sesi keamanan berakhir/);
    const event={id:1,nama:'Acara Wajib Sekolah dan Pembagian Sertifikat Keikutsertaan Siswa',deskripsi:'Unggah sertifikat atau foto keikutsertaan kegiatan.',tanggal_acara:'2026-11-03',poin:25,status:'Terbit',klaim_id:null,status_klaim:null,catatan_verifikasi:null};
    const events=[event,{...event,id:2,nama:'Klaim sedang diperiksa',klaim_id:1,status_klaim:'Menunggu'}, {...event,id:3,nama:'Klaim disetujui',klaim_id:2,status_klaim:'Disetujui'}, {...event,id:4,nama:'Klaim ditolak',klaim_id:3,status_klaim:'Ditolak',catatan_verifikasi:'Foto bukti kurang jelas.'}];
    const view=run(`require 'config/helper.php';$_SESSION=['csrf_token'=>'test'];$klaim_success='';$klaim_error='';$acara_klaim=json_decode(getenv('KAK_VIEW_EVENTS'),true);require 'includes/kak_klaim_siswa_view.php';`,{KAK_VIEW_EVENTS:JSON.stringify(events)});
    assert.match(view,/Menunggu verifikasi/);assert.match(view,/Disetujui/);assert.match(view,/Ditolak/);assert.match(view,/Foto bukti kurang jelas/);assert.doesNotMatch(view,/kode acara/i);
    assert.match(view,/Tanpa batas waktu klaim/);assert.doesNotMatch(view,/Periode klaim sudah berakhir|Batas klaim/);
    console.log('PASS: student summary, admin/teacher pages, direct award disabled, CSRF, missing proof, claim status views.');
    const chrome=process.env.CHROME_BINARY||'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
    if(fs.existsSync(chrome)&&!process.env.KAK_SKIP_BROWSER){
        const masterFixture=masterPage.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'').replace('<head>',`<head><base href="${pathToFileURL(path.join(root,'admin')+path.sep).href}">`);
        const masterAudit=`<script>${fs.readFileSync(path.join(root,'assets/js/master-aktivitas.js'),'utf8')}</script><pre id="master-delete-result">PENDING</pre><script>
        addEventListener('load',()=>{try{
            const check=(v,m)=>{if(!v)throw Error(m)};
            const button=document.querySelector('[data-activity-delete]'),dialog=document.querySelector('#activityDeleteDialog');
            check(button&&dialog&&!dialog.open,'Delete action available');
            check(button.getBoundingClientRect().height>=44,'Delete button touch target');
            button.dataset.name='Activity <b>name</b>';button.click();
            check(dialog.open&&dialog.matches(':modal'),'Delete opens confirmation');
            check(dialog.querySelector('[data-activity-delete-name]').textContent==='Activity <b>name</b>'&&!dialog.querySelector('[data-activity-delete-name] b'),'Activity name renders as text');
            check(dialog.getBoundingClientRect().left>=0&&dialog.getBoundingClientRect().right<=innerWidth+1,'Confirmation fits screen');
            check(dialog.querySelector('[name=id]').value===button.dataset.id,'Correct delete id');
            dialog.querySelector('[data-activity-delete-close]').click();check(!dialog.open,'Cancel closes without deleting');
            document.querySelector('[data-activity-add]').click();check(document.querySelector('#activityDialog').open,'Add form still opens');
            document.querySelector('#activityDialog').close();
            document.querySelector('#master-delete-result').textContent='PASS';
        }catch(e){document.querySelector('#master-delete-result').textContent='FAIL:'+e.message;}});</script>`;
        const masterFile=path.join(temp,'master-delete.html');fs.writeFileSync(masterFile,masterFixture.replace('</body>',masterAudit+'</body>'));
        for(const width of [1440,390,320]){
            const wrapper=path.join(temp,'master-delete-'+width+'.html');
            fs.writeFileSync(wrapper,`<iframe src="${pathToFileURL(masterFile).href}" style="width:${width}px;height:1000px;border:0"></iframe><script>addEventListener('load',()=>{document.body.textContent=document.querySelector('iframe').contentDocument.querySelector('#master-delete-result').textContent;});</script>`);
            const result=spawnSync(chrome,['--headless','--no-sandbox','--disable-gpu','--allow-file-access-from-files',`--user-data-dir=${path.join(temp,'master-chrome-'+width)}`,'--dump-dom',pathToFileURL(wrapper).href],{encoding:'utf8',timeout:25000,maxBuffer:4000000});
            assert.equal(result.status,0,result.stderr);assert.match(result.stdout,/<body>PASS<\/body>/,'Master delete modal at '+width+': '+result.stdout);
        }
        let html=page.replace(/<section[^>]*class="[^"]*kak-claims[^"]*"[\s\S]*?<\/section>/,view).replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'');
        html=html.replace('<head>',`<head><base href="${pathToFileURL(path.join(root,'siswa')+path.sep).href}">`);
        const audit=`<script>${fs.readFileSync(path.join(root,'assets/js/kak-klaim.js'),'utf8')}</script><script>
        addEventListener('load',()=>{try{
            const check=(v,m)=>{if(!v)throw Error(m)};
            const section=document.querySelector('.kak-claims'),dialog=document.querySelector('#kakClaimDialog'),trigger=document.querySelector('[data-open-claims]');
            check(!dialog.open&&section.getBoundingClientRect().height===0,'Claims initially hidden');
            check(trigger.closest('.kak-summary'),'Claim button inside blue summary');
            trigger.focus();trigger.click();check(dialog.open&&dialog.matches(':modal'),'Claim button opens modal');
            check(dialog.getBoundingClientRect().left>=0&&dialog.getBoundingClientRect().right<=innerWidth+1,'Modal fits screen');
            dialog.querySelector('[data-close-claims]').click();check(!dialog.open,'Close button closes popup');
            trigger.click();check(dialog.open,'Popup can reopen');
            const form=section.querySelector('form');form.closest('details').open=true;
            check(form.querySelector('button[type="submit"]').getBoundingClientRect().height>=44,'Touch target');
            check(document.documentElement.scrollWidth<=innerWidth+1,'No page overflow');
            check(section.scrollWidth<=section.clientWidth+1,'No claim overflow');
            const camera=form.querySelector('[data-claim-camera]');check(camera.getAttribute('capture')==='environment','Rear camera capture enabled');
            const input=form.querySelector('[data-claim-file]'),dt=new DataTransfer();dt.items.add(new File(['bad'],'bad.txt',{type:'text/plain'}));input.files=dt.files;input.dispatchEvent(new Event('change'));
            check(!input.validity.valid&&!form.querySelector('[data-claim-error]').hidden,'Invalid image feedback');
            const good=new DataTransfer();good.items.add(new File([Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j5Z0AAAAASUVORK5CYII='),c=>c.charCodeAt(0))],'photo.png',{type:'image/png'}));input.files=good.files;input.dispatchEvent(new Event('change'));
            check(input.validity.valid&&!form.querySelector('[data-claim-preview]').hidden,'Valid image preview');
            const photoFile=input.files[0];const choosePhoto=target=>{const selection=new DataTransfer();selection.items.add(photoFile);target.files=selection.files;target.dispatchEvent(new Event('change'));};
            choosePhoto(camera);check(input.files.length===0&&!input.required&&camera.validity.valid,'Camera replaces gallery and is accepted');
            check(!form.querySelector('[data-claim-result]').hidden,'Camera preview visible');
            form.querySelector('[data-claim-remove]').click();check(camera.files.length===0&&input.files.length===0&&input.required,'Remove clears both sources');
            check(form.querySelector('[data-claim-result]').hidden,'Remove hides preview');
            choosePhoto(camera);
            choosePhoto(input);check(camera.files.length===0&&input.required,'Gallery replaces camera');
            check(form.checkValidity(),Array.from(form.elements).filter(e=>e.validity&&!e.validity.valid).map(e=>e.name+': '+e.validationMessage).join('; '));
            form.addEventListener('submit',e=>e.preventDefault());form.requestSubmit();check(form.querySelector('button[type="submit"]').disabled,'Duplicate click prevented');
            parent.postMessage('KAK_PASS','*');
        }catch(e){parent.postMessage('KAK_FAIL: '+e.message,'*');}});
        </script>`;
        const fixture=path.join(temp,'student.html');fs.writeFileSync(fixture,html.replace('</body>',audit+'</body>'));
        for(const width of [1440,1024,768,390,320]){
            const wrapper=path.join(temp,'frame-'+width+'.html');fs.writeFileSync(wrapper,`<!doctype html><html><body style="margin:0"><iframe src="${pathToFileURL(fixture).href}" style="border:0;width:${width}px;height:1400px"></iframe><pre id="audit">PENDING</pre><script>addEventListener('message',e=>{if(typeof e.data==='string'&&e.data.startsWith('KAK_'))document.getElementById('audit').textContent=e.data;});</script></body></html>`);
            const result=spawnSync(chrome,['--headless','--no-sandbox','--disable-gpu','--no-first-run','--disable-background-networking',`--user-data-dir=${path.join(temp,'chrome-'+width)}`,'--window-size=1500,1500','--dump-dom',pathToFileURL(wrapper).href],{encoding:'utf8',timeout:25000,maxBuffer:4000000});
            assert.equal(result.status,0,result.error?.message||result.stderr);assert.match(result.stdout,/<pre id="audit">KAK_PASS<\/pre>/,(result.stdout.match(/KAK_FAIL:[^<]+/)||[])[0]||'Browser audit did not finish');
            console.log('PASS: claim layout, preview, validation and submit at '+width+'px');
        }
        const staffAudit=`<script>${fs.readFileSync(path.join(root,'assets/js/kak-staff.js'),'utf8')}</script><script>
        addEventListener('load',()=>{try{
          const check=(v,m)=>{if(!v)throw Error(m)};
          check(document.documentElement.scrollWidth<=innerWidth+1,'Staff page overflow');
          const target=document.querySelector('#acaraKelas'),studentSelect=document.querySelector('#acaraSiswa');
          const students=JSON.parse(document.querySelector('#kakTargetStudents').textContent);
          check(target.required&&document.querySelector('#acaraSiswaTarget').hidden&&studentSelect.disabled,'Choose class before students');
          const first=students.find(student=>student.kelas_id);check(first,'Student fixture exists');
          target.value=String(first.kelas_id);target.dispatchEvent(new Event('change'));
          check(!document.querySelector('#acaraSiswaTarget').hidden&&!studentSelect.disabled,'Class reveals students');
          const expected=students.filter(student=>String(student.kelas_id)===target.value);
          check(studentSelect.options.length===expected.length+1,'Only class students plus whole-class option');
          check(Array.from(studentSelect.options).slice(1).every(option=>expected.some(student=>String(student.id)===option.value)),'No other-class students');
          studentSelect.value=String(first.id);
          const other=Array.from(target.options).find(option=>option.value&&option.value!==String(first.kelas_id));
          if(other){target.value=other.value;target.dispatchEvent(new Event('change'));check(studentSelect.value==='','Changing class clears previous student');}
          ['X','XI','XII'].forEach(grade=>{
            target.value='tingkat:'+grade;target.dispatchEvent(new Event('change'));
            check(target.value==='tingkat:'+grade,'Grade option available');
            check(studentSelect.disabled&&document.querySelector('#acaraSiswaTarget').hidden&&studentSelect.value==='','Grade hides and clears individual student');
            check(document.querySelector('#acaraTargetHelp').textContent.includes('seluruh siswa'),'Grade scope explanation');
            check(document.querySelector('#acaraNama').options.length===({X:10,XI:11,XII:12}[grade]+1),'Activity choices match grade');
          });
          target.value='';target.dispatchEvent(new Event('change'));check(studentSelect.disabled&&document.querySelector('#acaraSiswaTarget').hidden,'Clearing class hides students');
          target.value=String(first.kelas_id);target.dispatchEvent(new Event('change'));
          check(document.querySelector('.kak-staff-main').scrollWidth<=document.querySelector('.kak-staff-main').clientWidth+1,'Staff form overflow');
          const preview=document.querySelector('.kak-event-preview');check(preview,'Event preview exists');
          const tabs=document.querySelector('.kak-page-tabs');
          check(tabs.querySelectorAll('a').length===3,'Three page links available');
          check(tabs.getBoundingClientRect().right<=innerWidth+1&&tabs.scrollWidth<=tabs.clientWidth+1,'Page navigation fits viewport');
          check(tabs.querySelector('[aria-current="page"]').getAttribute('href')==='kak.php?halaman=buat','Create page active');
          document.querySelectorAll('.kak-data-scroll').forEach(region=>{
            check(region.querySelector('table thead th'),'Spreadsheet has column headers');
            check(getComputedStyle(region).overflowX==='auto','Spreadsheet scrolls within its panel');
            check(getComputedStyle(region.querySelector('th')).position==='sticky','Headers stay visible when scrolling');
            check(getComputedStyle(region.querySelector('td')).borderRightStyle==='solid','Cells have visible grid lines');
          });
          const composer=document.querySelector('.kak-composer-grid');
          check(preview.open===(getComputedStyle(composer).gridTemplateColumns.split(' ').length>1),'Preview follows actual available content width');
          const name=document.querySelector('#acaraNama'),points=document.querySelector('#acaraPoin');
          name.selectedIndex=1;name.dispatchEvent(new Event('change',{bubbles:true}));
          check(points.value===name.selectedOptions[0].dataset.poin,'Points autofill from master');
          points.value='25';points.dispatchEvent(new Event('input',{bubbles:true}));
          check(document.querySelector('[data-preview-name]').textContent===name.selectedOptions[0].dataset.nama,'Preview uses selected activity name');
          check(document.querySelector('[data-preview-points]').textContent==='25','Preview follows points');
          target.value='tingkat:X';target.dispatchEvent(new Event('change',{bubbles:true}));
          check(document.querySelector('[data-preview-target]').textContent.includes('10'),'Preview follows grade target');
          preview.open=true;check(preview.getBoundingClientRect().right<=innerWidth+1,'Preview fits screen when expanded');
          function checkPanelBounds(){
            const content=document.querySelector('#page-content-wrapper'),main=document.querySelector('.kak-staff-main');
            check(content.getBoundingClientRect().right<=innerWidth+1,'Content not clipped behind viewport');
            check(main.getBoundingClientRect().right<=content.getBoundingClientRect().right+1,'Main fits remaining sidebar space');
            const form=document.querySelector('[data-kak-staff-form]'),aside=document.querySelector('.kak-composer-aside');
            const a=form.getBoundingClientRect(),b=aside.getBoundingClientRect();
            check(b.left>=a.right+12||b.top>=a.bottom+12,'Preview separated from form');
            form.querySelectorAll('input:not([type=hidden]),select,textarea').forEach(input=>{const r=input.getBoundingClientRect();if(r.width)check(r.left>=a.left-1&&r.right<=a.right+1,'Inputs stay inside form');});
            const panels=[...document.querySelectorAll('.kak-management-grid>section')].map(panel=>panel.getBoundingClientRect());
            if(panels.length>1)check(panels[1].left>=panels[0].right+12||panels[1].top>=panels[0].bottom+12,'Management panels do not overlap');
            panels.forEach(panel=>check(panel.right<=main.getBoundingClientRect().right+1,'Panels fit main content'));
          }
          checkPanelBounds();document.body.classList.add('admin-sidebar-compact');checkPanelBounds();document.body.classList.remove('admin-sidebar-compact');checkPanelBounds();
          parent.postMessage('STAFF_PASS','*');
        }catch(e){parent.postMessage('STAFF_FAIL: '+e.message,'*');}});</script>`;
        const staffFixture=path.join(temp,'staff.html');
        const staffHtml=staffPage.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,script=>script.includes('type="application/json"')?script:'').replace('<head>',`<head><base href="${pathToFileURL(path.join(root,'guru')+path.sep).href}">`);
        fs.writeFileSync(staffFixture,staffHtml.replace('</body>',staffAudit+'</body>'));
        for(const width of [1600,1440,1280,1200,1024,768,390,320]){
          const wrapper=path.join(temp,'staff-frame-'+width+'.html');fs.writeFileSync(wrapper,`<!doctype html><html><body style="margin:0"><iframe src="${pathToFileURL(staffFixture).href}" style="border:0;width:${width}px;height:1400px"></iframe><pre id="audit">PENDING</pre><script>addEventListener('message',e=>{if(typeof e.data==='string'&&e.data.startsWith('STAFF_'))document.getElementById('audit').textContent=e.data;});</script></body></html>`);
          const result=spawnSync(chrome,['--headless','--no-sandbox','--disable-gpu','--no-first-run','--disable-background-networking',`--user-data-dir=${path.join(temp,'staff-chrome-'+width)}`,'--window-size=1500,1500','--dump-dom',pathToFileURL(wrapper).href],{encoding:'utf8',timeout:25000,maxBuffer:4000000});
          assert.equal(result.status,0,result.error?.message||result.stderr);assert.match(result.stdout,/<pre id="audit">STAFF_PASS<\/pre>/,(result.stdout.match(/STAFF_FAIL:[^<]+/)||[])[0]||'Staff audit did not finish');console.log('PASS: staff responsive form and target selectors at '+width+'px');
        }
    }
}finally{
    const resolved=path.resolve(temp);assert.ok(resolved.startsWith(path.resolve(os.tmpdir())+path.sep)&&path.basename(resolved).startsWith('lms-kak-pages-'));
    fs.rmSync(resolved,{recursive:true,force:true});
}
