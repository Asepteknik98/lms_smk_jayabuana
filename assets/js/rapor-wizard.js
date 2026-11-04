(() => {
    'use strict';
    const root = document.getElementById('rapor-wizard');
    if (!root) return;
    const steps = [
        {id: 'class', title: 'Pilih kelas', icon: 'fa-users', selector: '.rapor-picker'},
        {id: 'prepare', title: 'Siapkan rapor', icon: 'fa-file-pen', selector: '#rapor-prepare'},
        {id: 'attendance', title: 'Kehadiran & catatan', icon: 'fa-calendar-check', selector: '#rapor-review'},
        {id: 'extras', title: 'Nilai eskul', icon: 'fa-medal', selector: '#rapor-extras'},
        {id: 'print', title: 'Periksa & cetak', icon: 'fa-print', selector: '#rapor-print-class'}
    ];
    const make = (tag, className, text) => {
        const node = document.createElement(tag); node.className = className;
        if (text) node.textContent = text;
        return node;
    };
    const nav = make('ol', 'rapor-wizard-steps'); nav.setAttribute('aria-label', 'Tahapan pengisian rapor');
    const panels = make('div', 'rapor-wizard-panels');
    const items = steps.map((step, index) => {
        const item = make('li', '', '');
        const label = make('strong', '');
        const icon = make('i', `fa-solid ${step.icon}`); icon.setAttribute('aria-hidden', 'true');
        label.append(icon, make('span', '', step.title));
        item.append(make('span', '', String(index + 1)), label); nav.append(item);
        const panel = make('section', 'rapor-wizard-panel'); panel.id = `wizard-${step.id}`;
        panel.setAttribute('aria-label', step.title);
        const content = document.querySelector(step.selector);
        if (content) panel.append(content);
        if (step.id === 'print') {
            ['#rapor-progress', '.rapor-history', '.rapor-audit-history'].forEach(selector => {
                const node = document.querySelector(selector); if (node) panel.append(node);
            });
        }
        panels.append(panel);
        return {panel, item, content, form: content?.querySelector('form')};
    });
    const footer = make('div', 'rapor-wizard-footer');
    const previous = make('button', 'btn btn-outline-secondary', 'Sebelumnya'); previous.type = 'button';
    const status = make('p', 'rapor-wizard-status'); status.setAttribute('role', 'status');
    const next = make('button', 'btn btn-primary btn-lg', 'Selanjutnya'); next.type = 'button';
    const setButton = (button, label, iconName, before = false) => {
        button.textContent = '';
        const icon = make('i', `fa-solid ${iconName}`); icon.setAttribute('aria-hidden', 'true');
        const text = make('span', '', label);
        button.append(...(before ? [icon, text] : [text, icon]));
    };
    setButton(previous, 'Sebelumnya', 'fa-arrow-left', true);
    footer.append(previous, status, next); root.append(nav, panels, footer);
    let current = steps.findIndex(step => step.id === root.dataset.step);
    if (root.dataset.hasClass !== '1') current = 0;
    else if (current < 0) current = 1;
    if (current > 1 && root.dataset.hasReports !== '1') current = 1;
    const dirty = new Set();
    items.forEach(({form}, index) => {
        if (!form) return;
        form.querySelectorAll('button:not([type]), button[type="submit"]').forEach(button => button.classList.add('wizard-form-submit'));
        ['input', 'change'].forEach(type => form.addEventListener(type, event => { if (!event?.target?.closest?.('[data-rapor-view-control]')) dirty.add(index); }));
    });
    function show(index, focus = true) {
        current = index;
        items.forEach(({panel, item}, i) => {
            panel.hidden = i !== index;
            item.classList.toggle('is-current', i === index);
            item.classList.toggle('is-past', i < index);
            if (i === index) item.setAttribute('aria-current', 'step'); else item.removeAttribute('aria-current');
        });
        previous.disabled = index === 0;
        next.hidden = index === steps.length - 1;
        setButton(next, root.dataset.locked !== '1' && index > 0 && index < 4 ? 'Simpan & selanjutnya' : 'Selanjutnya', 'fa-arrow-right');
        status.textContent = `Langkah ${index + 1} dari ${steps.length} · ${steps[index].title}`;
        if (focus) {
            const heading = items[index].panel.querySelector('h2, h6');
            if (heading) { heading.tabIndex = -1; heading.focus({preventScroll: true}); }
            root.scrollIntoView({block: 'start', behavior: 'auto'});
        }
    }
    function move(target) {
        if (target < 0 || target >= steps.length) return;
        const {form} = items[current];
        if (current === 0 && target > 0 && root.dataset.hasClass !== '1') { form?.requestSubmit(); return; }
        if (current === 1 && target > 1 && root.dataset.hasStudents !== '1') {
            status.textContent = 'Kelas ini belum memiliki siswa. Pilih kelas lain terlebih dahulu.'; return;
        }
        const mustSave = dirty.has(current) || (target > current && current > 0 && current < 4 && !(current === 1 && root.dataset.hasReports === '1' && root.dataset.needsPreparation !== '1'));
        if (mustSave && form && root.dataset.locked !== '1') {
            if (current === 1 && target > current && !form.querySelector('input[name="pengajaran[]"]:checked')) {
                status.textContent = 'Pilih sedikitnya satu mata pelajaran sebelum melanjutkan.'; return;
            }
            let field = form.querySelector('input[name="wizard_step"]');
            if (!field) { field = document.createElement('input'); field.type = 'hidden'; field.name = 'wizard_step'; form.append(field); }
            field.value = steps[target].id;
            form.requestSubmit(); return;
        }
        show(target);
    }
    previous.addEventListener('click', () => move(current - 1));
    next.addEventListener('click', () => move(current + 1));
    show(current, false);
})();
