const masterDialog = document.querySelector('#kpsMasterDialog');
const masterDeleteDialog = document.querySelector('#kpsMasterDeleteDialog');
if (masterDeleteDialog) {
    document.body.appendChild(masterDeleteDialog);
    let deleteTrigger;
    const deleteForm = masterDeleteDialog.querySelector('form');
    document.querySelectorAll('[data-master-delete]').forEach(button => button.addEventListener('click', () => {
        deleteTrigger = button;
        deleteForm.querySelector('[name="id"]').value = button.dataset.masterId;
        masterDeleteDialog.querySelector('[data-master-delete-name]').textContent = button.dataset.masterName;
        deleteForm.querySelector('button[type="submit"]').disabled = false;
        deleteForm.removeAttribute('aria-busy');
        masterDeleteDialog.showModal();
        document.documentElement.classList.add('master-dialog-open');
    }));
    masterDeleteDialog.querySelectorAll('[data-master-delete-close]').forEach(button => button.addEventListener('click', () => masterDeleteDialog.close()));
    masterDeleteDialog.addEventListener('click', event => {
        if (event.target !== masterDeleteDialog) return;
        const rect = masterDeleteDialog.getBoundingClientRect();
        if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) masterDeleteDialog.close();
    });
    masterDeleteDialog.addEventListener('close', () => {
        if (masterDeleteDialog.open) return;
        if (!masterDialog?.open) document.documentElement.classList.remove('master-dialog-open');
        deleteTrigger?.focus({ preventScroll: true });
    });
    deleteForm.addEventListener('submit', () => {
        deleteForm.querySelector('button[type="submit"]').disabled = true;
        deleteForm.setAttribute('aria-busy', 'true');
    });
}
const masterAdd = document.querySelector('[data-master-add]');
if (masterDialog && masterAdd) {
    const form = masterDialog.querySelector('#kpsMasterForm');
    document.body.appendChild(masterDialog);
    function openMaster() {
        if (!masterDialog.open) masterDialog.showModal();
        document.documentElement.classList.add('master-dialog-open');
    }
    masterAdd.addEventListener('click', () => {
        form.querySelector('[name="id"]').value = '0';
        form.querySelector('[name="kategori"]').value = 'A';
        ['nama', 'poin', 'penanganan'].forEach(name => { form.querySelector('[name="' + name + '"]').value = ''; });
        form.querySelector('[name="aktif"]').checked = true;
        masterDialog.querySelector('#masterDialogTitle').textContent = 'Tambah Pelanggaran';
        const error = masterDialog.querySelector('.master-modal-error');
        if (error) error.hidden = true;
        form.querySelector('button[type="submit"]').disabled = false;
        form.removeAttribute('aria-busy');
        openMaster();
    });
    masterDialog.querySelectorAll('[data-master-close]').forEach(button => button.addEventListener('click', () => masterDialog.close()));
    masterDialog.addEventListener('click', event => {
        if (event.target !== masterDialog) return;
        const bounds = masterDialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) masterDialog.close();
    });
    masterDialog.addEventListener('close', () => {
        if (masterDialog.open || masterDeleteDialog?.open) return;
        document.documentElement.classList.remove('master-dialog-open');
        const url = new URL(location.href);
        if (url.searchParams.has('edit')) {
            url.searchParams.delete('edit');
            history.replaceState(null, '', url.pathname + url.search + url.hash);
        }
        masterAdd.focus({ preventScroll: true });
    });
    form.addEventListener('submit', () => {
        form.querySelector('button[type="submit"]').disabled = true;
        form.setAttribute('aria-busy', 'true');
    });
    window.addEventListener('pageshow', event => {
        if (!event.persisted) return;
        form.querySelector('button[type="submit"]').disabled = false;
        form.removeAttribute('aria-busy');
    });
    if (masterDialog.dataset.autoOpen === 'true') openMaster();
}
