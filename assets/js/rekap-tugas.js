(() => {
    'use strict';
    const modal = document.getElementById('modalStatusTugas');
    const status = document.getElementById('statusTugas');
    const score = document.getElementById('nilaiStatusTugas');
    function updateScore() {
        const graded = status.value === 'mengerjakan';
        score.required = graded;
        score.readOnly = !graded;
        if (status.value === 'tidak_mengerjakan') score.value = '0';
        else if (!graded) score.value = '';
    }
    modal.addEventListener('show.bs.modal', event => {
        const button = event.relatedTarget;
        if (!button) return;
        const data = button.dataset;
        document.getElementById('statusSiswaId').value = data.siswaId;
        document.getElementById('statusTugasId').value = data.tugasId;
        document.getElementById('statusNamaSiswa').textContent = data.nama;
        document.getElementById('statusJudulTugas').textContent = data.judul;
        document.getElementById('catatanStatusTugas').value = data.catatan;
        status.value = data.status;
        score.value = data.nilai;
        updateScore();
        const link = document.getElementById('lihatJawabanTugas');
        link.hidden = data.pengumpulan === '0';
        link.href = (data.metode === 'offline' ? 'tugas_nilai_offline.php' : 'tugas_penilaian.php') + '?tugas_id=' + encodeURIComponent(data.tugasId);
    });
    status.addEventListener('change', updateScore);
    const search = document.getElementById('cariSiswaRekap');
    if (search) search.addEventListener('input', () => {
        const term = search.value.trim().toLocaleLowerCase('id');
        let visible = 0;
        document.querySelectorAll('.recap-student-row').forEach(row => {
            row.hidden = !row.dataset.search.includes(term);
            if (!row.hidden) visible++;
        });
        document.getElementById('rekapSearchEmpty').hidden = visible > 0;
    });
})();
