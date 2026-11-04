const kpsClassStudents = JSON.parse(document.querySelector('#kpsClassStudents')?.textContent || '[]');
const kpsViolation = document.querySelector('#kpsPelanggaran');
if (kpsViolation) {
    const points = document.querySelector('#kpsPoin');
    const summary = document.querySelector('[data-kps-violation-summary]');
    function updateViolation(resetPoints) {
        const option = kpsViolation.selectedOptions[0];
        const selected = !!kpsViolation.value;
        if (resetPoints || !points.value) points.value = selected ? option.dataset.poin : '';
        summary.hidden = !selected;
        summary.querySelector('[data-kps-violation-name]').textContent = selected ? option.dataset.nama : '';
        summary.querySelector('[data-kps-handling]').textContent = selected
            ? 'Penanganan: ' + (option.dataset.penanganan || 'Tidak ada penanganan khusus yang ditetapkan.') : '';
    }
    kpsViolation.addEventListener('change', () => updateViolation(true));
    kpsViolation.addEventListener('input', () => updateViolation(true));
    updateViolation(false);
    window.addEventListener('pageshow', () => updateViolation(false));
}
document.querySelectorAll('[data-kps-student-group]').forEach(group => {
    const classSelect = group.querySelector('[data-kps-class]');
    const studentSelect = group.querySelector('[data-kps-student]');
    function updateStudents() {
        const classId = classSelect.value;
        const previous = studentSelect.dataset.classId === undefined || studentSelect.dataset.classId === classId ? studentSelect.value : '';
        const students = kpsClassStudents.filter(student => String(student.kelas_id) === classId);
        studentSelect.replaceChildren(new Option(classId ? studentSelect.dataset.emptyLabel : 'Pilih kelas terlebih dahulu', ''));
        students.forEach(student => studentSelect.add(new Option(student.nama_lengkap + (student.nis ? ' · NIS ' + student.nis : ''), String(student.id))));
        if (students.some(student => String(student.id) === previous)) studentSelect.value = previous;
        studentSelect.dataset.classId = classId;
        studentSelect.disabled = !classId;
        const help = group.querySelector('[data-kps-class-help]');
        if (help) help.textContent = !classId ? 'Pilih kelas untuk menampilkan siswa.' : students.length ? students.length + ' siswa di kelas ini.' : 'Belum ada siswa di kelas ini.';
    }
    classSelect.addEventListener('change', updateStudents);
    updateStudents();
    window.addEventListener('pageshow', updateStudents);
});
