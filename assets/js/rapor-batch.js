(() => {
    'use strict';
    const forms = [...document.querySelectorAll('.rapor-batch form')];
    const dirty = new Set();
    const statuses = new Map();
    forms.forEach(form => {
        const status = document.createElement('p');
        status.className = 'text-primary mt-3';
        status.setAttribute('role', 'status');
        form.append(status);
        statuses.set(form, status);
        const changed = event => {
            if (event.target.closest('[data-rapor-view-control]')) return;
            dirty.add(form);
            status.textContent = 'Ada isian belum disimpan. Gunakan tombol di bawah formulir ini sebelum berpindah atau mencetak.';
        };
        form.addEventListener('input', changed);
        form.addEventListener('change', changed);
        form.addEventListener('submit', () => { dirty.delete(form); });
    });
    document.querySelectorAll('a[href^="rapor_cetak.php"]').forEach(link => {
        link.addEventListener('click', event => {
            if (!dirty.size) return;
            event.preventDefault();
            const form = [...dirty][0];
            statuses.get(form).textContent = 'Simpan isian kelas terlebih dahulu sebelum membuka pratinjau.';
            statuses.get(form).scrollIntoView({behavior: 'smooth', block: 'center'});
        });
    });
    window.addEventListener('beforeunload', event => {
        if (dirty.size) { event.preventDefault(); event.returnValue = ''; }
    });
})();
