(() => {
    'use strict';
    const modal = document.getElementById('modalStatusTugas');
    if (!modal) return;
    const status = document.getElementById('statusTugas');
    const score = document.getElementById('nilaiStatusTugas');
    const taskSelect = document.getElementById('statusPilihanTugas');
    let taskOptions = [];
    function updateScore() {
        const graded = status.value === 'mengerjakan';
        score.required = graded;
        score.readOnly = !graded;
        if (status.value === 'tidak_mengerjakan') score.value = '0';
        else if (!graded) score.value = '';
    }
    function selectTask(data) {
        document.getElementById('statusTugasId').value = data.tugasId;
        document.getElementById('statusJudulTugas').textContent = data.judul;
        document.getElementById('catatanStatusTugas').value = data.catatan;
        status.value = data.status;
        score.value = data.nilai;
        updateScore();
        const link = document.getElementById('lihatJawabanTugas');
        link.hidden = data.pengumpulan === '0';
        link.href = (data.metode === 'offline' ? 'tugas_nilai_offline.php' : 'tugas_penilaian.php') + '?tugas_id=' + encodeURIComponent(data.tugasId);
    }
    modal.addEventListener('show.bs.modal', event => {
        const button = event.relatedTarget;
        if (!button) return;
        const data = button.dataset;
        document.getElementById('statusSiswaId').value = data.siswaId;
        document.getElementById('statusNamaSiswa').textContent = data.nama;
        if (taskSelect && data.tugasList) {
            taskOptions = JSON.parse(data.tugasList);
            taskSelect.replaceChildren();
            taskOptions.forEach(task => {
                const option = document.createElement('option');
                option.value = String(task.tugasId);
                option.textContent = task.judul;
                taskSelect.appendChild(option);
            });
            if (taskOptions.length) selectTask(taskOptions[0]);
        } else selectTask(data);
    });
    if (taskSelect) taskSelect.addEventListener('change', () => {
        const task = taskOptions.find(item => String(item.tugasId) === taskSelect.value);
        if (task) selectTask(task);
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
    const filterForm = document.getElementById('formFilterRekapTugas');
    const classFilter = document.getElementById('rekapKelas');
    const subjectFilter = document.getElementById('rekapPengajaran');
    const modeFilter = document.getElementById('rekapMode');
    const meetingFilter = document.getElementById('rekapPertemuan');
    if (filterForm) {
        classFilter.addEventListener('change', () => {
            subjectFilter.disabled = true;
            meetingFilter.disabled = true;
            filterForm.requestSubmit();
        });
        subjectFilter.addEventListener('change', () => {
            meetingFilter.disabled = true;
            filterForm.requestSubmit();
        });
        modeFilter.addEventListener('change', () => {
            meetingFilter.disabled = modeFilter.value !== 'pertemuan';
            filterForm.requestSubmit();
        });
    }
})();
