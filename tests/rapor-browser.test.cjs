const {test}=require('node:test');
const assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');
const fs=require('node:fs');
const os=require('node:os');
const path=require('node:path');
const {pathToFileURL}=require('node:url');
const chrome=process.env.CHROME_BINARY||'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
test('Real browser preserves individual grades, serializes only changes and navigates the wizard',{skip:!fs.existsSync(chrome)},()=>{
    const temp=fs.mkdtempSync(path.join(os.tmpdir(),'lms-rapor-audit-'));
    const cell=(name,grade)=>`<td data-extra-name="${name}" data-grade="${grade}"><div class="rapor-grade-buttons">${['','A','B','C','D'].map(value=>`<button type="button" data-grade-value="${value}" aria-pressed="${value===grade}">${value||'−'}</button>`).join('')}</div></td>`;
    const scripts=['rapor-extra-matrix','rapor-batch','rapor-lock','rapor-table-tools','rapor-wizard'].map(name=>`<script>${fs.readFileSync(`assets/js/${name}.js`,'utf8')}</script>`).join('');
    const html=`<!doctype html><html><head><meta charset="utf-8"><style>${fs.readFileSync('assets/css/rapor-form.css','utf8')}</style></head><body class="rapor-workspace">
    <script>window.auditErrors=[];window.addEventListener('error',e=>window.auditErrors.push(e.message));</script>
    <div id="rapor-wizard" data-step="extras" data-has-class="1" data-has-reports="1" data-has-students="1"></div>
    <section class="rapor-picker"><form></form></section><section id="rapor-prepare" class="rapor-batch"><form><input type="checkbox" name="pengajaran[]" checked></form></section>
    <section id="rapor-review" class="rapor-batch"><form><input name="sakit" type="number" value="0"></form></section>
    <section id="rapor-extras" class="rapor-batch"><h2>Nilai eskul</h2><form id="rapor-extra-matrix-form"><input type="hidden" id="rapor-extras-payload"><div data-rapor-view-control><input type="search" id="rapor-matrix-student-search"><input type="search" id="rapor-matrix-activity-search"><button type="button" id="rapor-matrix-search-reset">Tout</button><p id="rapor-matrix-filter-status"></p></div><input id="rapor-extra-new"><button id="rapor-extra-add" type="button">Tambah</button><div class="table-responsive rapor-extra-matrix"><table><thead><tr><th>Siswa</th><th data-extra-column="Pramuka">Pramuka</th><th data-extra-column="Futsal">Futsal</th></tr></thead><tbody><tr data-report-id="1" data-version="version1" data-student-name="Uji"><th>Uji<small data-extra-count></small></th>${cell('Pramuka','A')}${cell('Futsal','')}</tr></tbody></table></div><button id="rapor-extras-save" disabled>Simpan</button></form></section>
    <section id="rapor-print-class"><h2>Cetak</h2></section><section id="rapor-progress"></section><details class="rapor-history"></details>
    <form id="generic-filter" data-rapor-filter><div data-rapor-view-control><input data-filter-search><button type="button" data-filter-incomplete aria-pressed="false">Belum lengkap</button><p data-filter-result></p></div><table><tbody><tr data-filter-row data-filter-search-value="Satu" data-incomplete="0"><td><input name="satu" value="1"></td></tr><tr data-filter-row data-filter-search-value="Dua" data-incomplete="1"><td><input name="dua" value="2"></td></tr><tr data-filter-row data-filter-search-value="Tiga" data-incomplete="1"><td><input name="tiga" value="3"></td></tr></tbody></table></form>
    ${scripts}<pre id="audit-result">PENDING</pre><script>
    try{
      const check=(value,message)=>{if(!value)throw Error(message);};
      const root=document.getElementById('rapor-wizard');const form=document.getElementById('rapor-extra-matrix-form');
      const visible=()=>[...root.querySelectorAll('.rapor-wizard-panel')].filter(panel=>getComputedStyle(panel).display!=='none');
      check(visible().length===1&&visible()[0].id==='wizard-extras','Only one active step');
      const studentSearch=document.getElementById('rapor-matrix-student-search'),activitySearch=document.getElementById('rapor-matrix-activity-search');
      const search=(input,value)=>{input.value=value;input.dispatchEvent(new Event('input',{bubbles:true}));};
      search(studentSearch,'Uji');search(activitySearch,'pra');check(document.querySelector('[data-extra-name="Futsal"]').hidden,'Activity search hides unrelated columns');
      const beforeSearchOnly=new Event('beforeunload',{cancelable:true});window.dispatchEvent(beforeSearchOnly);check(!beforeSearchOnly.defaultPrevented,'Search does not mark report as edited');
      search(studentSearch,'Missing');check(document.querySelector('[data-report-id]').hidden,'Student search filters matrix rows');
      document.getElementById('rapor-matrix-search-reset').click();check(!document.querySelector('[data-report-id]').hidden&&!document.querySelector('[data-extra-name="Futsal"]').hidden,'Reset restores rows and columns');
      const generic=document.getElementById('generic-filter');const filterRows=[...generic.querySelectorAll('[data-filter-row]')];generic.querySelector('[data-filter-incomplete]').click();check(filterRows.filter(row=>!row.hidden).length===2,'Incomplete filter excludes completed reports');
      search(generic.querySelector('[data-filter-search]'),'Dua');check(filterRows.filter(row=>!row.hidden).length===1,'Student search combines with incomplete filter');check(new FormData(generic).get('satu')==='1','Hidden rows retain their form data');
      search(generic.querySelector('[data-filter-search]'),'Missing');check(filterRows.every(row=>row.hidden)&&generic.querySelector('[data-filter-result]').textContent.includes('Tidak ada'),'No-result state is clear');

      document.querySelector('[data-extra-name="Futsal"] [data-grade-value="B"]').click();
      check(document.querySelector('[data-extra-name="Pramuka"]').dataset.grade==='A','Previous grade preserved');
      check(document.querySelector('[data-extra-count]').textContent==='2 kegiatan diikuti','Individual count updated');
      const input=document.getElementById('rapor-extra-new');input.value='PRA-MUKA';document.getElementById('rapor-extra-add').click();
      check(document.querySelectorAll('[data-extra-column]').length===2,'Alias does not duplicate a column');
      input.value='Robotik';document.getElementById('rapor-extra-add').click();document.querySelector('[data-extra-name="Robotik"] [data-grade-value="C"]').click();
      search(activitySearch,'Pramuka');search(studentSearch,'Missing');
      let submits=0;form.addEventListener('submit',e=>{e.preventDefault();submits++;});root.querySelector('.rapor-wizard-footer .btn-primary').click();
      check(submits===1,'Next submits the active step');
      const payload=JSON.parse(document.getElementById('rapor-extras-payload').value);
      check(payload[0].grades.Futsal==='B'&&payload[0].grades.Robotik==='C'&&!('Pramuka' in payload[0].grades),'Only changed grades serialized');
      check(form.querySelector('[name="wizard_step"]').value==='print','Next requests print stage');
      root.dataset.locked='1';root.querySelector('.rapor-wizard-footer .btn-primary').click();
      check(submits===1&&visible().length===1&&visible()[0].id==='wizard-print','Locked reports navigate without submitting');
      check(!window.auditErrors.length,window.auditErrors.join('; '));document.getElementById('audit-result').textContent='PASS';
    }catch(error){document.getElementById('audit-result').textContent='FAIL: '+error.message;}
    </script></body></html>`;
    const fixture=path.join(temp,'fixture.html');fs.writeFileSync(fixture,html);
    try{
        const result=spawnSync(chrome,['--headless','--no-sandbox','--in-process-gpu','--disable-gpu','--disable-software-rasterizer','--no-first-run','--no-default-browser-check','--disable-background-networking','--disable-extensions',`--user-data-dir=${path.join(temp,'profile')}`,'--dump-dom',pathToFileURL(fixture).href],{encoding:'utf8',timeout:25000,maxBuffer:4000000});
        assert.equal(result.status,0,result.error?.message||result.stderr);assert.match(result.stdout,/<pre id="audit-result">PASS<\/pre>/);
    }finally{
        const target=fs.realpathSync(temp),parent=fs.realpathSync(os.tmpdir());
        assert.equal(path.dirname(target),parent);assert.ok(path.basename(target).startsWith('lms-rapor-audit-'));
        fs.rmSync(target,{recursive:true,force:true,maxRetries:5,retryDelay:100});
    }
});
