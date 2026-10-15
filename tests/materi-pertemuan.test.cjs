const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');

test('Material meeting labels follow teaching assignment, combined classes, edit and reset',()=>{
    const events={};
    const options=[{value:''},{value:'1',disabled:false},{value:'2',disabled:false},{value:'3',disabled:false}];
    const teaching={value:'1',disabled:false};
    const mode={checked:false};
    const action={value:'create_materi'};
    const form={addEventListener:(event,fn)=>{events['form:'+event]=fn;}};
    const select={options,value:'2'};
    const boxes=[{value:'1',disabled:false,parentElement:{querySelector:()=>({textContent:'Kelas A'})}},
        {value:'2',disabled:false,parentElement:{querySelector:()=>({textContent:'Kelas B'})}}];
    const elements={statusPertemuanMateri:{textContent:JSON.stringify({1:{1:['Algoritma <script>'],3:['Materi A']},2:{3:['Materi B']}})},pertemuanMateri:select,pengajaranMateri:teaching,modeMateriGabungan:mode,aksiMateri:action,formMateri:form};
    const window={};
    vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../assets/js/materi-pertemuan.js'),'utf8'),{
        window,document:{getElementById:id=>elements[id],querySelectorAll:()=>boxes,addEventListener:(event,fn)=>{events[event]=fn;}},queueMicrotask:fn=>fn()
    });
    assert.equal(options[1].textContent,'Pertemuan 1 — Sudah ada materi: Algoritma <script>');
    assert.equal(options[2].textContent,'Pertemuan 2 — Belum ada materi');
    assert.equal(select.value,'2');assert.equal(options[1].disabled,false);assert.equal(options[1].innerHTML,undefined);
    teaching.value='2';events.change({target:teaching});
    assert.equal(options[1].textContent,'Pertemuan 1 — Belum ada materi');assert.match(options[3].textContent,/Materi B/);
    mode.checked=true;teaching.disabled=true;window.refreshPertemuanMateri();
    assert.match(options[1].textContent,/Kelas A: Algoritma/);assert.match(options[1].textContent,/Belum ada materi: Kelas B/);
    assert.match(options[3].textContent,/Kelas A: Materi A/);assert.match(options[3].textContent,/Kelas B: Materi B/);
    action.value='update_materi';teaching.disabled=false;teaching.value='1';window.refreshPertemuanMateri();
    assert.equal(options[1].textContent,'Pertemuan 1 — Sudah ada materi: Algoritma <script>');
    teaching.value='';action.value='create_materi';events['form:reset']();assert.equal(options[1].textContent,'Pertemuan 1');
});
