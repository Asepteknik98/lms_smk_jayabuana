(() => {
    'use strict';
    const payload = document.getElementById('statusPertemuanMateri');
    const select = document.getElementById('pertemuanMateri');
    if (!payload || !select) return;
    const materials = JSON.parse(payload.textContent);
    const teaching = document.getElementById('pengajaranMateri');
    function refresh() {
        const combined = document.getElementById('aksiMateri').value === 'create_materi' && document.getElementById('modeMateriGabungan')?.checked && teaching.disabled;
        const selected = combined
            ? Array.from(document.querySelectorAll('.kelas-materi:checked')).filter(box => !box.disabled).map(box => ({id: box.value, name: box.parentElement.querySelector('label')?.textContent.trim() || box.value}))
            : (teaching.value ? [{id: teaching.value, name: ''}] : []);
        Array.from(select.options).forEach(option => {
            if (!option.value) return;
            const prefix = `Pertemuan ${option.value}`;
            if (!selected.length) { option.textContent = prefix; return; }
            const existing = selected.filter(item => materials[item.id]?.[option.value]?.length);
            if (!existing.length) { option.textContent = `${prefix} — Belum ada materi`; return; }
            const titles = existing.map(item => (selected.length > 1 ? `${item.name}: ` : '') + materials[item.id][option.value].join('; '));
            const missing = selected.filter(item => !existing.includes(item));
            option.textContent = `${prefix} — Sudah ada materi: ${titles.join(' | ')}${missing.length ? ' — Belum ada materi: ' + missing.map(item => item.name).join(', ') : ''}`;
        });
    }
    window.refreshPertemuanMateri = refresh;
    document.addEventListener('change', event => {
        if (event.target === teaching || event.target.matches('.kelas-materi, input[name="mode_kelas_materi"]')) refresh();
    });
    document.getElementById('formMateri').addEventListener('reset', () => queueMicrotask(refresh));
    document.addEventListener('DOMContentLoaded', refresh);
    refresh();
})();
