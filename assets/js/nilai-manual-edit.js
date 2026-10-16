(() => {
    document.querySelectorAll('.period-grade-row').forEach(row => {
        const score = row.querySelector('.period-score');
        const input = row.querySelector('.period-edit-input');
        const edit = row.querySelector('.period-edit');
        const save = row.querySelector('.period-save');
        const cancel = row.querySelector('.period-cancel');
        const original = input.value;
        function editing(active) {
            score.hidden = active;
            input.hidden = input.disabled = !active;
            edit.hidden = active;
            save.hidden = cancel.hidden = !active;
        }
        edit.addEventListener('click', () => { editing(true); input.focus(); input.select(); });
        cancel.addEventListener('click', () => { input.value = original; editing(false); edit.focus(); });
    });
})();
