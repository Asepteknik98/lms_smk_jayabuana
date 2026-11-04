(() => {
    'use strict';
    const normalize = text => text.normalize('NFKC').toLocaleLowerCase().trim();
    document.querySelectorAll('[data-rapor-filter]').forEach(root => {
        const search = root.querySelector('[data-filter-search]');
        const toggle = root.querySelector('[data-filter-incomplete]');
        const status = root.querySelector('[data-filter-result]');
        const rows = [...root.querySelectorAll('[data-filter-row]')];
        let incompleteOnly = false;
        function update() {
            const query = normalize(search?.value || '');
            let visible = 0;
            rows.forEach(row => {
                row.hidden = !normalize(row.dataset.filterSearchValue || row.textContent).includes(query) || (incompleteOnly && row.dataset.incomplete !== '1');
                if (!row.hidden) visible++;
            });
            if (status) status.textContent = visible ? `${visible} dari ${rows.length} ditampilkan${incompleteOnly ? ' · belum lengkap' : ''}` : 'Tidak ada hasil yang sesuai.';
            if (toggle) toggle.setAttribute('aria-pressed', String(incompleteOnly));
        }
        search?.addEventListener('input', update);
        search?.addEventListener('keydown', event => { if (event.key === 'Enter') event.preventDefault(); });
        toggle?.addEventListener('click', () => { incompleteOnly = !incompleteOnly; update(); });
        update();
    });
})();
