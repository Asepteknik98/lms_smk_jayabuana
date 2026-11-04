(() => {
    'use strict';
    const data = JSON.parse(document.getElementById('rapor-data').textContent);
    const form = document.getElementById('rapor-form');
    const mapel = document.getElementById('mapel-picker');
    const guru = document.getElementById('guru-picker');
    const sources = new Map(data.sources.map(row => [String(row.pengajaran_id), row]));
    const message = document.getElementById('mapel-message');
    const saveStatus = document.getElementById('rapor-save-status');
    function markDirty() { dirty = true; if (saveStatus) saveStatus.textContent = 'Ada perubahan belum disimpan. Klik Simpan rapor sebelum mencetak.'; }
    function updateEmpty() { const empty = document.getElementById('mapel-empty'); if (empty) empty.hidden = Boolean(document.querySelector('#mapel-rows tr')); }
    let rowIndex = 0, extraIndex = 0, dirty = false;
    const element = (tag, text, className) => {
        const node = document.createElement(tag);
        if (text !== undefined) node.textContent = text;
        if (className) node.className = className;
        return node;
    };
    const hidden = (name, value) => {
        const input = element('input'); input.type = 'hidden'; input.name = name; input.value = value ?? ''; return input;
    };
    const subjects = new Map(data.sources.map(row => [String(row.mapel_id), row.nama_mapel]));
    function buttonIcon(button, icon) {
        const node = element('i', undefined, `fa-solid ${icon}`);
        node.setAttribute('aria-hidden', 'true'); button.prepend(node, document.createTextNode(' '));
    }
    buttonIcon(document.getElementById('add-mapel'), 'fa-plus');
    subjects.forEach((name, id) => mapel.add(new Option(name, id)));
    guru.disabled = true;
    mapel.addEventListener('change', () => {
        guru.replaceChildren(new Option('Pilih guru', ''));
        data.sources.filter(row => String(row.mapel_id) === mapel.value).forEach(row => {
            guru.add(new Option(row.nama_guru, row.pengajaran_id));
        });
        guru.disabled = !mapel.value;
    });
    function addMapel(row) {
        const index = rowIndex++, tr = element('tr');
        tr.dataset.pengajaran = row.pengajaran_id ?? '';
        const id = hidden(`mapel[${index}][id]`, row.id ?? 0);
        const pid = hidden(`mapel[${index}][pengajaran_id]`, row.pengajaran_id ?? 0);
        const refresh = hidden(`mapel[${index}][refresh]`, row.refresh ? 1 : 0);
        const title = element('td'); title.append(element('strong', row.nama_mapel), element('div', row.nama_guru, 'small text-muted'), id, pid, refresh);
        const score = element('td'), description = element('td'), advice = element('td'), actions = element('td');
        [title, score, description, advice, actions].forEach((cell, i) => cell.dataset.label = ['Mata pelajaran / guru', 'Nilai akhir', 'Deskripsi capaian', 'Saran', 'Tindakan'][i]);
        function narrative(cell, text, label) {
            cell.replaceChildren();
            if (!text) { cell.textContent = 'Belum diisi'; return; }
            const details = element('details'); details.append(element('summary', label), element('p', text)); cell.append(details);
        }
        const status = element('div', '', 'small text-warning'); title.append(status);
        function display(value) {
            score.replaceChildren(element('span', value.nilai_akhir === null ? 'Belum lengkap' : value.nilai_akhir, 'rapor-grade-score'));
            narrative(description, value.deskripsi, 'Lihat deskripsi'); narrative(advice, value.saran, 'Lihat saran');
            status.textContent = [value.peringatan, !value.deskripsi || !value.saran ? 'Deskripsi/saran belum lengkap.' : ''].filter(Boolean).join(' ');
        }
        display(row);
        if (row.id && sources.has(String(row.pengajaran_id))) {
            const update = element('button', 'Perbarui dari rekap', 'btn btn-sm btn-outline-primary mb-2'); update.type = 'button';
            buttonIcon(update, 'fa-arrows-rotate');
            update.addEventListener('click', () => { refresh.value = '1'; display(sources.get(String(row.pengajaran_id))); update.textContent = 'Siap diperbarui · klik Simpan'; markDirty(); });
            actions.append(update);
        }
        const remove = element('button', 'Hapus baris', 'btn btn-sm btn-outline-danger'); remove.type = 'button';
        buttonIcon(remove, 'fa-trash-can');
        remove.addEventListener('click', () => { tr.remove(); markDirty(); updateEmpty(); }); actions.className = 'rapor-grade-actions'; actions.append(remove);
        tr.append(title, score, description, advice, actions); document.getElementById('mapel-rows').append(tr);
    }
    document.getElementById('add-mapel').addEventListener('click', () => {
        message.textContent = '';
        const source = sources.get(guru.value);
        if (!source) { message.textContent = 'Pilih mata pelajaran dan guru terlebih dahulu.'; return; }
        if ([...document.querySelectorAll('#mapel-rows tr')].some(row => row.dataset.pengajaran === guru.value)) {
            message.textContent = 'Pengajaran tersebut sudah ditambahkan.'; return;
        }
        addMapel(source); markDirty(); updateEmpty();
    });
    function addEkstra(row = {}) {
        const index = extraIndex++, container = element('div', undefined, 'rapor-ekstra-item');
        const number = element('span', `${index + 1}`, 'rapor-ekstra-number');
        const nameWrap = element('div');
        const name = element('input', undefined, 'form-control'); name.name = `ekstra[${index}][nama_kegiatan]`; name.value = row.nama_kegiatan ?? ''; name.maxLength = 150; name.placeholder = 'Nama kegiatan'; name.setAttribute('aria-label', 'Nama kegiatan ekstrakurikuler'); nameWrap.append(name);
        name.readOnly = Boolean(row.template);
        if (row.template) { name.type = 'hidden'; nameWrap.append(element('span', row.nama_kegiatan, 'rapor-ekstra-name')); }
        const gradeWrap = element('div'); const grade = element('select', undefined, 'form-select'); grade.name = `ekstra[${index}][grade]`; grade.setAttribute('aria-label', `Grade ${row.nama_kegiatan || 'kegiatan tambahan'}`);
        grade.add(new Option('–', '')); ['A', 'B', 'C', 'D'].forEach(value => grade.add(new Option(value, value))); grade.value = row.grade ?? ''; gradeWrap.append(grade);
        const updateGrade = () => { container.classList.toggle('is-graded', Boolean(grade.value)); updateEkstraCount(); };
        grade.addEventListener('change', updateGrade); container.classList.toggle('is-graded', Boolean(grade.value));
        const actionWrap = element('div');
        if (!row.template) { const remove = element('button', 'Hapus', 'btn btn-outline-danger'); remove.type = 'button'; remove.addEventListener('click', () => { container.remove(); markDirty(); updateEkstraCount(); }); actionWrap.append(remove); }
        container.append(number, nameWrap, gradeWrap, actionWrap); document.getElementById('ekstra-rows').append(container);
    }
    function updateEkstraCount() { const count = [...document.querySelectorAll('#ekstra-rows select')].filter(select => select.value).length; document.getElementById('ekstra-count').textContent = `${count} kegiatan diberi grade`; }
    document.getElementById('add-ekstra').addEventListener('click', () => { addEkstra(); markDirty(); document.querySelector('#ekstra-rows .rapor-ekstra-item:last-child input:not([type="hidden"])')?.focus(); });
    data.rows.forEach(addMapel); data.extras.forEach(addEkstra);
    updateEmpty(); updateEkstraCount();
    document.getElementById('details-ready').value = '1';
    form.addEventListener('input', markDirty); form.addEventListener('change', markDirty);
    form.addEventListener('submit', () => { dirty = false; });
    form.querySelectorAll('a[href^="rapor_cetak.php"]').forEach(link => {
        link.addEventListener('click', event => {
            if (!dirty) return;
            event.preventDefault();
            saveStatus.textContent = 'Simpan perubahan terlebih dahulu agar pratinjau memakai data terbaru.';
            saveStatus.scrollIntoView({ block: 'center', behavior: 'smooth' });
        });
    });
    form.addEventListener('invalid', event => {
        let parent = event.target.parentElement;
        while (parent && parent !== form) { if (parent.tagName === 'DETAILS') parent.open = true; parent = parent.parentElement; }
    }, true);
    window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
})();
