const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
function setup(options={}){
    class Node{
        constructor(tag='div'){this.tag=tag;this.children=[];this.dataset={};this.events={};this.attrs={};this.classList={add(){},toggle(){}};}
        append(...nodes){this.children.push(...nodes);}
        setAttribute(k,v){this.attrs[k]=v;}
        removeAttribute(k){delete this.attrs[k];}
        addEventListener(k,v){this.events[k]=v;}
        querySelector(){return null;}
        querySelectorAll(){return [];}
        scrollIntoView(){}
    }
    const root=new Node();root.dataset={step:'prepare',hasClass:'1',hasReports:'1',hasStudents:'1',...options};
    const selectors=['.rapor-picker','#rapor-prepare','#rapor-review','#rapor-extras','#rapor-print-class'];
    const forms=[];const nodes={};
    selectors.forEach(selector=>{
        const content=new Node(),form=new Node('form');forms.push(form);
        form.querySelector=query=>query.includes(':checked')?(form.checked?{}:null):form.children.find(child=>child.name==='wizard_step')||null;
        form.requestSubmit=()=>{form.submitted=true;};content.querySelector=()=>form;nodes[selector]=content;
    });
    vm.runInNewContext(fs.readFileSync('assets/js/rapor-wizard.js','utf8'),{document:{getElementById:()=>root,createElement:tag=>new Node(tag),querySelector:selector=>nodes[selector]||null}});
    const [nav,panels,footer]=root.children;
    return {root,forms,nav,panels,previous:footer.children[0],status:footer.children[1],next:footer.children[2]};
}
test('Only the requested wizard stage is visible',()=>{
    const w=setup({step:'extras'});assert.deepEqual(w.panels.children.map(p=>p.hidden),[true,true,true,false,true]);assert.equal(w.nav.children[3].attrs['aria-current'],'step');
});
test('Unprepared classes cannot open later stages',()=>{
    const w=setup({step:'extras',hasReports:'0'});assert.equal(w.panels.children[1].hidden,false);
    w.next.events.click();assert.equal(w.forms[1].submitted,undefined);assert.match(w.status.textContent,/Pilih sedikitnya/);
    w.forms[1].checked=true;w.next.events.click();assert.equal(w.forms[1].submitted,true);assert.equal(w.forms[1].children[0].value,'attendance');
});
test('Next saves attendance and requests the extracurricular step',()=>{
    const w=setup({step:'attendance'});w.next.events.click();assert.equal(w.forms[2].submitted,true);assert.equal(w.forms[2].children[0].value,'extras');
});
test('Previous saves changed grades before leaving the step',()=>{
    const w=setup({step:'extras'});w.forms[3].events.change();w.previous.events.click();assert.equal(w.forms[3].submitted,true);assert.equal(w.forms[3].children[0].value,'attendance');
});
test('Previous without changes keeps entered DOM in place',()=>{
    const w=setup({step:'extras'});w.previous.events.click();assert.equal(w.forms[3].submitted,undefined);assert.equal(w.panels.children[2].hidden,false);
});
test('Last step hides Next and includes the print panel',()=>{
    const w=setup({step:'print'});assert.equal(w.next.hidden,true);assert.equal(w.panels.children[4].hidden,false);
});
test('Classes without students cannot advance to attendance',()=>{
    const w=setup({hasStudents:'0'});w.next.events.click();assert.match(w.status.textContent,/belum memiliki siswa/);assert.equal(w.forms[1].submitted,undefined);
});
test('Locked reports allow moving forward without submitting disabled forms',()=>{
    const w=setup({step:'attendance',locked:'1'});w.next.events.click();
    assert.equal(w.forms[2].submitted,undefined);assert.equal(w.panels.children[3].hidden,false);
});
test('Missing drafts are prepared even when some classmates already have reports',()=>{
    const w=setup({needsPreparation:'1'});w.next.events.click();assert.equal(w.forms[1].submitted,undefined);assert.match(w.status.textContent,/Pilih sedikitnya/);
    w.forms[1].checked=true;w.next.events.click();assert.equal(w.forms[1].submitted,true);
});
