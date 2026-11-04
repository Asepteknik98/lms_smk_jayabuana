const kakTarget = document.querySelector('#acaraKelas');
const kakStudentTarget = document.querySelector('#acaraSiswaTarget');
const kakStudents = JSON.parse(document.querySelector('#kakTargetStudents')?.textContent || '[]');
const kakMasterActivities = JSON.parse(document.querySelector('#kakMasterActivities')?.textContent || '[]');
const kakActivity = document.querySelector('#acaraNama');
function kakUpdateActivities() {
    if (!kakActivity) return;
    const selected = kakTarget.selectedOptions[0];
    const grade = kakTarget.value.startsWith('tingkat:') ? kakTarget.value.slice(8) : selected?.dataset.tingkat;
    const previous = kakActivity.value;
    const activities = kakMasterActivities.filter(activity => activity.tingkat === grade);
    kakActivity.replaceChildren(new Option(grade ? 'Pilih aktivitas ' + grade : 'Pilih kelas terlebih dahulu', ''));
    activities.forEach(activity => {
        const option = new Option(activity.nama, String(activity.id));
        option.dataset.nama = activity.nama;
        kakActivity.add(option);
    });
    kakActivity.disabled = !grade;
    if (activities.some(activity => String(activity.id) === previous)) kakActivity.value = previous;
    document.querySelector('#acaraAktivitasHelp').textContent = !grade ? 'Pilih kelas atau angkatan terlebih dahulu.' : activities.length ? activities.length + ' aktivitas tersedia untuk tingkat ' + grade + '.' : 'Belum ada aktivitas aktif untuk tingkat ini.';
}

function kakUpdateTarget() {
    const selectedClass = kakTarget.value;
    kakUpdateActivities();
    const select = kakStudentTarget.querySelector('select');
    const gradeTarget = ['tingkat:X', 'tingkat:XI', 'tingkat:XII'].includes(selectedClass);
    const targetHelp = document.querySelector('#acaraTargetHelp');
    if (gradeTarget) {
        select.replaceChildren(new Option('Seluruh siswa dalam angkatan', ''));
        select.dataset.classId = selectedClass;
        select.disabled = true;
        kakStudentTarget.hidden = true;
        if (targetHelp) targetHelp.textContent = 'Acara tersedia untuk seluruh siswa ' + kakTarget.selectedOptions[0].textContent.toLowerCase() + '.';
        return;
    }
    if (targetHelp) targetHelp.textContent = 'Pilih satu kelas atau seluruh kelas dalam satu angkatan.';
    const previous = select.dataset.classId === selectedClass ? select.value : '';
    const students = kakStudents.filter(student => String(student.kelas_id) === selectedClass);
    select.replaceChildren(new Option('Semua siswa di kelas ini', ''));
    students.forEach(student => {
        select.add(new Option(student.nama_lengkap + (student.nis ? ' · NIS ' + student.nis : ''), String(student.id)));
    });
    if (students.some(student => String(student.id) === previous)) select.value = previous;
    select.dataset.classId = selectedClass;
    select.disabled = !selectedClass;
    kakStudentTarget.hidden = !selectedClass;
    document.querySelector('#acaraSiswaHelp').textContent = selectedClass
        ? (students.length ? students.length + ' siswa. Pilih seluruh kelas atau satu siswa tertentu.' : 'Belum ada siswa yang terdaftar di kelas ini.')
        : 'Pilih kelas untuk menampilkan siswa.';
}
if (kakTarget) { kakTarget.addEventListener('change', kakUpdateTarget); kakUpdateTarget(); }
window.addEventListener('pageshow', () => { if (kakTarget) kakUpdateTarget(); });
document.querySelectorAll('[data-kak-review-form]').forEach(form => {
    const decision = form.querySelector('[name="disetujui"]');
    const updateReview = () => { form.querySelector('[name="catatan_verifikasi"]').required = !decision.checked; };
    decision.addEventListener('change', updateReview);
    updateReview();
});
document.querySelectorAll('[data-kak-staff-form],[data-kak-review-form]').forEach(form => {
    form.addEventListener('submit', () => { form.querySelector('button[type="submit"]').disabled = true; });
});

const kakComposer = document.querySelector('[data-kak-staff-form]');
if (kakComposer && document.querySelector('[data-preview-name]')) {
    const preview = document.querySelector('.kak-event-preview');
    const composerGrid = kakComposer.closest('.kak-composer-grid');
    let lastPreviewLayout;
    function syncPreviewLayout() {
        const sideBySide = getComputedStyle(composerGrid).gridTemplateColumns.split(' ').length > 1;
        if (sideBySide !== lastPreviewLayout) {
            preview.open = sideBySide;
            lastPreviewLayout = sideBySide;
        }
    }
    syncPreviewLayout();
    if (typeof ResizeObserver !== 'undefined') new ResizeObserver(syncPreviewLayout).observe(composerGrid);
    else window.addEventListener('resize', syncPreviewLayout);
    function updateEventPreview() {
        const name = kakActivity.selectedOptions[0]?.dataset.nama || '';
        const description = kakComposer.querySelector('[name="deskripsi"]').value.trim();
        const date = kakComposer.querySelector('[name="tanggal_acara"]').value;
        const student = document.querySelector('#acaraSiswa');
        document.querySelector('[data-preview-name]').textContent = name || 'Nama kegiatan akan muncul di sini';
        document.querySelector('[data-preview-description]').textContent = description || 'Keterangan bukti yang perlu dikirim siswa.';
        const parsedDate = date ? new Date(date + 'T12:00:00') : null;
        document.querySelector('[data-preview-date]').textContent = parsedDate && !Number.isNaN(parsedDate.getTime()) ? parsedDate.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : 'Tanggal belum dipilih';
        document.querySelector('[data-preview-target]').textContent = !kakTarget.value ? 'Peserta belum dipilih' : !student.disabled && student.value ? student.selectedOptions[0].textContent : kakTarget.selectedOptions[0].textContent;
    }
    kakComposer.addEventListener('input', updateEventPreview);
    kakComposer.addEventListener('change', updateEventPreview);
    window.addEventListener('pageshow', updateEventPreview);
    updateEventPreview();
}
