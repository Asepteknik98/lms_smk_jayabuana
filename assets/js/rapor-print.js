(() => {
    'use strict';
    const reports = [...document.querySelectorAll('.report')].map(report => {
        const sheets = [...report.querySelectorAll('.sheet')];
        return {name: report.getAttribute('aria-label'), sheets,
            bodies: sheets.map(sheet => sheet.querySelector('[data-grade-body]')),
            rows: [...report.querySelectorAll('[data-grade-row]')]};
    });
    const status = document.getElementById('layout-status');
    const button = document.getElementById('print-button');
    function fill(body, items) {
        if (!items.length) return;
        body.replaceChildren(); let group = null, number = 0;
        items.forEach(row => {
            if (group !== row.dataset.group) {
                group = row.dataset.group;
                number = 0;
                const heading = document.createElement('tr'); heading.className = 'group-heading';
                const cell = document.createElement('td'); cell.colSpan = 4;
                cell.textContent = /kejuruan/i.test(group) ? 'B. Muatan Mata Pelajaran Kejuruan' : /umum/i.test(group) ? 'A. Muatan Mata Pelajaran Umum' : group;
                heading.append(cell); body.append(heading);
            }
            row.firstElementChild.textContent = ++number; body.append(row);
        });
    }
    function fits(sheet) {
        const content = sheet.querySelector('.sheet-content');
        const last = content.lastElementChild;
        return content.clientHeight > 0 && content.clientWidth > 0 && last.getBoundingClientRect().bottom <= content.getBoundingClientRect().bottom + 1 && content.scrollWidth <= content.clientWidth + 1;
    }
    function layoutReport({sheets, bodies, rows}) {
        fill(bodies[0], rows);
        for (const font of [8.5, 8, 7.5, 7]) {
            sheets.forEach(sheet => sheet.style.setProperty('--report-font', `${font}pt`));
            for (const height of [19, 18, 17, 16, 15, 14]) {
                sheets[0].style.setProperty('--grade-row-height', `${height}mm`);
                sheets[0].style.setProperty('--grade-half-height', `${(height - 1) / 2}mm`);
                if (sheets.every(fits)) return true;
            }
        }
        return false;
    }
    function layout() {
        button.disabled = true;
        // Print media hides unready reports. Keep them measurable during validation.
        document.body.classList.add('print-measuring');
        document.body.classList.remove('print-ready');
        let failed;
        try { failed = reports.filter(report => !layoutReport(report)); }
        finally { document.body.classList.remove('print-measuring'); }
        if (failed.length || !reports.length) {
            status.textContent = 'Cetak dinonaktifkan: isi melebihi kapasitas halaman. Seluruh nilai harus muat di halaman 1; ekstrakurikuler dan pelengkap di halaman 2. Periksa panjang deskripsi, saran, dan catatan. ' + failed.map(report => report.name).join('; ');
            status.classList.add('error'); return;
        }
        document.body.classList.add('print-ready'); button.disabled = false;
        status.textContent = `Siap: ${reports.length} siswa, ${reports.length * 2} halaman F4 (210 × 330 mm). Setiap siswa dimulai pada halaman baru.`;
        status.classList.remove('error');
    }
    button.addEventListener('click', () => { layout(); if (!button.disabled) window.print(); });
    window.addEventListener('beforeprint', layout);
    Promise.all([document.fonts.ready, ...[...document.images].map(img => img.complete ? Promise.resolve() : new Promise(resolve => { img.addEventListener('load', resolve, {once:true}); img.addEventListener('error', resolve, {once:true}); }))]).then(layout);
})();
