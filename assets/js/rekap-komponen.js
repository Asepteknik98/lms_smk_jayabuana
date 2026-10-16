(() => {
    document.querySelectorAll('.rekap-component-row').forEach(row => {
        const input = row.querySelector('.component-weight');
        const edit = row.querySelector('.component-edit');
        const save = row.querySelector('.component-save');
        const cancel = row.querySelector('.component-cancel');
        const remove = row.querySelector('.component-delete');
        const deleteBlocked = remove.disabled;
        const original = input.value;
        function editing(active) {
            input.readOnly = !active;
            edit.hidden = active;
            edit.setAttribute('aria-expanded', String(active));
            save.hidden = cancel.hidden = !active;
            row.classList.toggle('is-editing', active);
            remove.disabled = active || deleteBlocked;
        }
        edit.addEventListener('click', () => { editing(true); input.focus(); input.select(); });
        cancel.addEventListener('click', () => { input.value = original; editing(false); edit.focus(); });
        row.querySelector('.component-delete-form').addEventListener('submit', event => {
            const name = event.currentTarget.dataset.componentName;
            if (!window.confirm('Hapus komponen "' + name + '"? Setelah dihapus, sesuaikan kembali total bobot menjadi 100%.')) event.preventDefault();
        });
    });
})();
