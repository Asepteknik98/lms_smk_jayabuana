(() => {
    'use strict';
    const form = document.getElementById('rapor-picker-form');
    if (!form) return;
    const student = document.getElementById('siswa');
    const open = document.getElementById('open-rapor');
    const filters = ['tahun', 'semester', 'jenis', 'kelas'].map(id => document.getElementById(id));
    student.addEventListener('change', () => { open.disabled = !student.value; });
    filters.forEach(filter => filter.addEventListener('change', () => {
        student.value = '';
        student.disabled = true;
        open.disabled = true;
        // Submit only the new period/class; never carry the previous student over.
        if (form.checkValidity()) form.requestSubmit();
    }));
    form.addEventListener('submit', event => {
        if (event.submitter !== open) student.disabled = true;
        else if (!student.value) event.preventDefault();
    });
})();
