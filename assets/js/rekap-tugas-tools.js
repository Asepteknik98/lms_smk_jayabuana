(() => {
    'use strict';
    const payload = document.getElementById('rekapRemedialData');
    if (!payload) return;
    const data = JSON.parse(payload.textContent);
    const select = document.getElementById('remedialPilihanTugas');
    const body = document.getElementById('remedialDaftarSiswa');
    const save = document.getElementById('simpanRemedialTugas');
    const number = value => value === null || value === undefined ? '—' : Number(value).toLocaleString('id-ID', {maximumFractionDigits: 2});
    function addCell(row, text) {
        const cell = document.createElement('td');
        if (text !== undefined) cell.textContent = text;
        row.appendChild(cell);
        return cell;
    }
    function renderStudents() {
        body.replaceChildren();
        const task = data.tasks.find(task => String(task.id) === select.value && Number(task.selesai));
        save.disabled = !data.ready || !task;
        if (!task) return;
        data.students.forEach(student => {
            const cell = student.cells[task.id];
            const row = document.createElement('tr');
            addCell(row, student.nama_lengkap);
            addCell(row, number(cell.nilai_awal));
            addCell(row, number(cell.nilai));
            const inputCell = addCell(row);
            const input = document.createElement('input');
            input.type = 'number'; input.min = '0'; input.max = '100'; input.step = '0.01';
            input.name = `nilai_remedial[${student.id}]`;
            input.className = 'form-control form-control-sm';
            input.style.minWidth = '105px';
            input.setAttribute('aria-label', `Nilai remedial ${student.nama_lengkap}`);
            input.disabled = cell.nilai === null;
            input.placeholder = input.disabled ? 'Nilai awal belum diisi' : '0–100';
            inputCell.appendChild(input);
            const noteCell = addCell(row);
            const note = document.createElement('input');
            note.name = `catatan_remedial[${student.id}]`; note.maxLength = 1000;
            note.className = 'form-control form-control-sm'; note.placeholder = 'Catatan opsional';
            note.setAttribute('aria-label', `Catatan remedial ${student.nama_lengkap}`);
            noteCell.appendChild(note);
            if (cell.remedial.length) {
                const details = document.createElement('details');
                const summary = document.createElement('summary');
                summary.textContent = `${cell.remedial.length} riwayat remedial / undo`;
                details.open = true;
                details.appendChild(summary);
                const list = document.createElement('ul');
                cell.remedial.forEach(attempt => {
                    const item = document.createElement('li');
                    item.textContent = `${attempt.dibuat_pada}: remedial ${number(attempt.nilai_remedial)}, nilai akhir ${number(attempt.nilai_akhir)}${attempt.catatan ? ' · ' + attempt.catatan : ''}`;
                    if (attempt.dibatalkan_pada) {
                        item.className = 'text-muted';
                        const label = document.createElement('strong');
                        label.textContent = ` — Dibatalkan ${attempt.dibatalkan_pada}`;
                        item.appendChild(label);
                    } else if (data.cancelReady) {
                        const cancel = document.createElement('button');
                        cancel.type = 'button';
                        cancel.className = 'btn btn-sm btn-outline-danger ms-2 my-1';
                        cancel.textContent = 'Batalkan';
                        cancel.setAttribute('aria-label', `Batalkan remedial ${number(attempt.nilai_remedial)} untuk ${student.nama_lengkap}`);
                        cancel.addEventListener('click', () => {
                            if (!window.confirm(`Batalkan remedial ${number(attempt.nilai_remedial)} untuk ${student.nama_lengkap}? Nilai akhir akan dihitung ulang dari nilai awal dan remedial lain yang masih berlaku.`)) return;
                            document.getElementById('batalRemedialId').value = attempt.id;
                            document.getElementById('batalRemedialTugasId').value = task.id;
                            document.getElementById('formBatalRemedialTugas').requestSubmit();
                        });
                        item.appendChild(cancel);
                    }
                    list.appendChild(item);
                });
                details.appendChild(list); noteCell.appendChild(details);
            }
            body.appendChild(row);
        });
    }
    select.addEventListener('change', renderStudents);
    document.getElementById('modalRemedialTugas').addEventListener('show.bs.modal', renderStudents);
    const form = document.getElementById('formRemedialTugas');
    form.addEventListener('submit', event => {
        const anyScore = Array.from(body.querySelectorAll('input[type="number"]')).some(input => !input.disabled && input.value !== '');
        if (!anyScore) {
            event.preventDefault();
            const first = body.querySelector('input[type="number"]:not(:disabled)');
            if (first) { first.setCustomValidity('Isi minimal satu nilai remedial.'); first.reportValidity(); first.addEventListener('input', () => first.setCustomValidity(''), {once: true}); }
        }
    });
    document.addEventListener('DOMContentLoaded', () => {
        ['modalImporTugas','modalRemedialTugas'].forEach(id => {
            const modal = document.getElementById(id);
            if (modal && modal.dataset.open === '1' && window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(modal).show();
        });
    });
})();
