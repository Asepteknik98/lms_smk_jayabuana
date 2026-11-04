const alpaDialog = document.querySelector('#alpaWarningDialog');
const alpaOpener = document.querySelector('[data-alpa-open]');
if (alpaDialog && alpaOpener) {
    document.body.append(alpaDialog);
    alpaOpener.addEventListener('click', () => alpaDialog.showModal());
    alpaDialog.querySelectorAll('[data-alpa-close]').forEach(button => button.addEventListener('click', () => alpaDialog.close()));
    alpaDialog.addEventListener('close', () => alpaOpener.focus({preventScroll:true}));
}
