const {test}=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');
const path=require('node:path');
async function preview(count){
    const classes=new Set(),events={},styles=[new Map(),new Map()];let printMedia=false;
    const visible=()=>!printMedia||classes.has('print-ready')||classes.has('print-measuring');
    const rows=Array.from({length:count},()=>({dataset:{group:'Umum'},firstElementChild:{textContent:''}}));
    const bodies=[{replaceChildren(){},append(){}},null];
    const sheets=styles.map((style,i)=>({
        style:{setProperty:(k,v)=>style.set(k,v)},
        querySelector(selector){
            if(selector==='[data-grade-body]')return bodies[i];
            return {get clientHeight(){return visible()?270:0},get clientWidth(){return visible()?700:0},get scrollWidth(){return visible()?700:0},getBoundingClientRect:()=>({bottom:visible()?270:0}),lastElementChild:{getBoundingClientRect:()=>({bottom:visible()?(i===0?70+count*parseFloat(styles[0].get('--grade-row-height')||19):140):0})}};
        }
    }));
    const report={getAttribute:()=> 'Siswa Uji',querySelectorAll:s=>s==='.sheet'?sheets:rows};
    const status={classList:{add(){},remove(){}},textContent:''};
    const button={disabled:true,addEventListener:(e,f)=>events['button-'+e]=f};
    const doc={body:{classList:{add:c=>classes.add(c),remove:c=>classes.delete(c)}},querySelectorAll:()=>[report],getElementById:id=>id==='layout-status'?status:button,createElement:()=>({append(){}}),fonts:{ready:Promise.resolve()},images:[]};
    vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../assets/js/rapor-print.js'),'utf8'),{document:doc,window:{addEventListener:(e,f)=>events[e]=f,print(){}}});
    await new Promise(resolve=>setImmediate(resolve));
    return {button,status,classes,styles,rows,print(){printMedia=true;events.beforeprint();}};
}
test('Twelve subject rows shrink to fit and remain measurable during beforeprint',async()=>{
    const p=await preview(12);assert.equal(p.button.disabled,false);assert.ok(parseFloat(p.styles[0].get('--grade-row-height'))<=16);
    p.print();assert.equal(p.button.disabled,false);assert.ok(p.classes.has('print-ready'));assert.equal(p.classes.has('print-measuring'),false);assert.equal(p.rows.length,12);
});
test('Truly oversized reports remain blocked instead of clipping content',async()=>{
    const p=await preview(30);assert.equal(p.button.disabled,true);assert.equal(p.classes.has('print-ready'),false);assert.match(p.status.textContent,/kapasitas halaman/);
    p.print();assert.equal(p.button.disabled,true);assert.equal(p.classes.has('print-ready'),false);
});
