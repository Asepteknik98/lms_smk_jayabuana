const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
function setup(){
    const nodes={};
    for(const id of ['rapor-picker-form','siswa','open-rapor','tahun','semester','jenis','kelas'])nodes[id]={value:'1',disabled:false,listeners:{},addEventListener(type,fn){this.listeners[type]=fn;}};
    const form=nodes['rapor-picker-form'];form.checkValidity=()=>true;form.requestSubmit=()=>{form.submitted=true;};
    vm.runInNewContext(fs.readFileSync('assets/js/rapor-picker.js','utf8'),{document:{getElementById:id=>nodes[id]}});
    return nodes;
}
for(const id of ['tahun','semester','jenis','kelas'])test(`Changing ${id} clears the previous student before loading`,()=>{
    const n=setup();n[id].listeners.change();
    assert.equal(n.siswa.value,'');assert.equal(n.siswa.disabled,true);assert.equal(n['open-rapor'].disabled,true);assert.equal(n['rapor-picker-form'].submitted,true);
});
test('Open report is enabled only after choosing a student',()=>{
    const n=setup();n.siswa.value='';n.siswa.listeners.change();assert.equal(n['open-rapor'].disabled,true);
    n.siswa.value='23';n.siswa.listeners.change();assert.equal(n['open-rapor'].disabled,false);
});
test('Loading a class excludes the previously selected student',()=>{
    const n=setup();n['rapor-picker-form'].listeners.submit({submitter:{}});assert.equal(n.siswa.disabled,true);
});
