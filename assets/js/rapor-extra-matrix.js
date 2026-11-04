(() => {
    'use strict';
    const form = document.getElementById('rapor-extra-matrix-form');
    if (!form) return;
    const table = form.querySelector('table');
    const rows = [...table.tBodies[0].rows];
    const activityKey = name => name.toLocaleLowerCase().replace(/[^\p{L}\p{N}]/gu, '');
    const grades = ['', 'A', 'B', 'C', 'D'];
    const studentSearch = document.getElementById('rapor-matrix-student-search');
    const activitySearch = document.getElementById('rapor-matrix-activity-search');
    const filterStatus = document.getElementById('rapor-matrix-filter-status');
    function filterMatrix() {
        const studentQuery = (studentSearch?.value || '').trim().toLocaleLowerCase();
        const activityQuery = (activitySearch?.value || '').trim().toLocaleLowerCase();
        const headings = [...table.querySelectorAll('[data-extra-column]')];
        headings.forEach(heading => { heading.hidden = !heading.dataset.extraColumn.toLocaleLowerCase().includes(activityQuery); });
        rows.forEach(row => {
            row.hidden = !row.dataset.studentName.toLocaleLowerCase().includes(studentQuery);
            row.querySelectorAll('[data-extra-name]').forEach(cell => { cell.hidden = !cell.dataset.extraName.toLocaleLowerCase().includes(activityQuery); });
        });
        const students = rows.filter(row => !row.hidden).length, activities = headings.filter(heading => !heading.hidden).length;
        if (filterStatus) filterStatus.textContent = students && activities ? `${students} siswa · ${activities} kegiatan ditampilkan. Isian yang tersembunyi tetap disimpan.` : 'Tidak ada siswa atau kegiatan yang sesuai. Ubah pencarian atau klik Tampilkan semua.';
    }
    [studentSearch, activitySearch].forEach(search => {
        search?.addEventListener('input', filterMatrix);
        search?.addEventListener('keydown', event => { if (event.key === 'Enter') event.preventDefault(); });
    });
    document.getElementById('rapor-matrix-search-reset')?.addEventListener('click', () => {
        if (studentSearch) studentSearch.value = '';
        if (activitySearch) activitySearch.value = '';
        filterMatrix();
    });
    filterMatrix();
    function count(row) {
        row.querySelector('[data-extra-count]').textContent = `${[...row.querySelectorAll('[data-extra-name]')].filter(cell => cell.dataset.grade).length} kegiatan diikuti`;
    }
    rows.forEach(row => {
        row.querySelectorAll('[data-extra-name]').forEach(cell => { cell.dataset.originalGrade = cell.dataset.grade; });
        count(row);
    });
    table.addEventListener('click', event => {
        const button = event.target.closest('button[data-grade-value]');
        if (!button) return;
        const cell = button.closest('[data-extra-name]');
        cell.dataset.grade = button.dataset.gradeValue;
        cell.querySelectorAll('button').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
        count(cell.parentElement);
        form.dispatchEvent(new Event('change', {bubbles: true}));
    });
    const input = document.getElementById('rapor-extra-new');
    document.getElementById('rapor-extra-add').addEventListener('click', () => {
        const name = input.value.trim();
        if (!name) { input.focus(); return; }
        const headings = [...table.querySelectorAll('[data-extra-column]')];
        const existing = headings.find(item => activityKey(item.dataset.extraColumn) === activityKey(name));
        if (existing) { if (activitySearch) activitySearch.value = ''; filterMatrix(); existing.scrollIntoView({block: 'nearest', inline: 'center'}); input.value = ''; return; }
        if (headings.length >= 100) { input.setCustomValidity('Maksimal 100 kegiatan.'); input.reportValidity(); return; }
        const th = document.createElement('th'); th.scope = 'col'; th.dataset.extraColumn = name; th.textContent = name;
        table.tHead.rows[0].append(th);
        rows.forEach(row => {
            const cell = document.createElement('td'); cell.dataset.extraName = name; cell.dataset.grade = ''; cell.dataset.originalGrade = '';
            const group = document.createElement('div'); group.className = 'rapor-grade-buttons'; group.setAttribute('role', 'group'); group.setAttribute('aria-label', `${row.dataset.studentName} - ${name}`);
            grades.forEach(grade => {
                const button = document.createElement('button'); button.type = 'button'; button.dataset.gradeValue = grade; button.textContent = grade || '−';
                button.setAttribute('aria-label', grade ? `Grade ${grade}` : 'Kosongkan'); button.setAttribute('aria-pressed', String(!grade)); group.append(button);
            });
            cell.append(group); row.append(cell);
        });
        input.value = ''; if (activitySearch) activitySearch.value = ''; filterMatrix(); th.scrollIntoView({block: 'nearest', inline: 'center'});
        form.dispatchEvent(new Event('change', {bubbles: true}));
    });
    input.addEventListener('input', () => input.setCustomValidity(''));
    input.addEventListener('keydown', event => {
        if (event.key === 'Enter') { event.preventDefault(); document.getElementById('rapor-extra-add').click(); }
    });
    form.addEventListener('submit', () => {
        document.getElementById('rapor-extras-payload').value = JSON.stringify(rows.map(row => ({
            id: row.dataset.reportId, version: row.dataset.version,
            grades: Object.fromEntries([...row.querySelectorAll('[data-extra-name]')].filter(cell => cell.dataset.grade !== cell.dataset.originalGrade).map(cell => [cell.dataset.extraName, cell.dataset.grade]))
        })));
    });
    document.getElementById('rapor-extras-save').disabled = false;
})();
