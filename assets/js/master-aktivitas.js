const activityDialog = document.querySelector('#activityDialog');
const activityDeleteDialog = document.querySelector('#activityDeleteDialog');
if (activityDeleteDialog) {
    document.body.append(activityDeleteDialog);
    const deleteForm = document.querySelector('#activityDeleteForm');
    document.querySelectorAll('[data-activity-delete]').forEach(button => button.addEventListener('click', () => {
        deleteForm.elements.id.value = button.dataset.id;
        activityDeleteDialog.querySelector('[data-activity-delete-name]').textContent = button.dataset.name;
        deleteForm.querySelector('[type=submit]').disabled = false;
        activityDeleteDialog.showModal();
        document.body.classList.add('master-dialog-open');
    }));
    activityDeleteDialog.querySelectorAll('[data-activity-delete-close]').forEach(button => button.addEventListener('click', () => activityDeleteDialog.close()));
    activityDeleteDialog.addEventListener('close', () => document.body.classList.remove('master-dialog-open'));
    activityDeleteDialog.addEventListener('click', event => {
        const box = activityDeleteDialog.getBoundingClientRect();
        if (event.target === activityDeleteDialog && (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom)) activityDeleteDialog.close();
    });
    deleteForm.addEventListener('submit', () => { deleteForm.querySelector('[type=submit]').disabled = true; });
}
if (activityDialog) {
    document.body.append(activityDialog);
    const form = document.querySelector('#activityForm');
    function openActivity() { activityDialog.showModal(); document.body.classList.add('master-dialog-open'); }
    document.querySelector('[data-activity-add]').addEventListener('click', () => {
        form.elements.id.value = '0'; form.elements.tingkat.value = 'X';
        form.elements.nama.value = ''; form.elements.aktif.checked = true;
        activityDialog.querySelector('.alert')?.remove();
        document.querySelector('#activityDialogTitle').textContent = 'Tambah Aktivitas';
        openActivity();
    });
    activityDialog.querySelectorAll('[data-activity-close]').forEach(button => button.addEventListener('click', () => activityDialog.close()));
    activityDialog.addEventListener('close', () => document.body.classList.remove('master-dialog-open'));
    activityDialog.addEventListener('click', event => {
        const box = activityDialog.getBoundingClientRect();
        if (event.target === activityDialog && (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom)) activityDialog.close();
    });
    form.addEventListener('submit', () => { form.querySelector('[type=submit]').disabled = true; });
    if (activityDialog.dataset.autoOpen === 'true') openActivity();
}
