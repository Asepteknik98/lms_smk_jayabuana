(function () {
    const area = document.querySelector('.integrated-grades');
    if (!area) return;
    const dirty = new Set();
    const scoreForm = document.getElementById('scoreForm');
    const key = 'rekap-capaian-' + area.dataset.pengajaran;
    function toggle(button, open) {
        document.getElementById(button.getAttribute('aria-controls')).hidden = !open;
        button.setAttribute('aria-expanded', String(open));
        button.classList.toggle('btn-primary', open);
        button.classList.toggle('btn-outline-primary', !open);
    }
    area.querySelectorAll('.capaian-toggle').forEach(button => {
        button.addEventListener('click', () => toggle(button, button.getAttribute('aria-expanded') !== 'true'));
    });
    area.querySelectorAll('.inline-capaian').forEach(panel => {
        const button = document.querySelector('[aria-controls="' + panel.closest('tr').id + '"]');
        panel.querySelector('.capaian-close').addEventListener('click', () => {
            button.focus();
            toggle(button, false);
        });
        panel.querySelectorAll('.capaian-period').forEach(tab => {
            tab.addEventListener('click', () => {
                panel.querySelectorAll('.capaian-period').forEach(other => {
                    const active = other === tab;
                    other.setAttribute('aria-pressed', String(active));
                    other.classList.toggle('btn-primary', active);
                    other.classList.toggle('btn-outline-primary', !active);
                });
                panel.querySelectorAll('.capaian-period-panel').forEach(part => {
                    part.hidden = part.dataset.period !== tab.dataset.period;
                });
            });
        });
    });
    // Keep the two views of the same UTS/UAS grade in sync without submitting data.
    area.addEventListener('input', event => {
        const input = event.target;
        if (input.form) dirty.add(input.form);
        if (input.matches('.score-input[data-period="UTS"], .score-input[data-period="UAS"]')) {
            const panel = area.querySelector('.inline-capaian[data-student="' + input.dataset.student + '"]');
            const peer = panel?.querySelector('form[data-period="' + input.dataset.period + '"] .period-score');
            if (peer) peer.value = input.value;
        } else if (input.matches('.period-score')) {
            const sid = input.closest('.inline-capaian').dataset.student;
            const peer = area.querySelector('.score-input[data-student="' + sid + '"][data-period="' + input.form.dataset.period + '"]');
            if (peer) peer.value = input.value;
        }
    });
    area.addEventListener('click', event => {
        if (event.target.closest('.score-toggle')) dirty.add(scoreForm);
    });
    // Native POST actions stay unchanged. Warn before losing edits in another form.
    area.addEventListener('submit', event => {
        const form = event.target;
        const others = [...dirty].some(item => item !== form);
        if (others && !window.confirm('Ada perubahan lain yang belum disimpan. Lanjut menyimpan formulir ini dan meninggalkan perubahan lainnya?')) {
            event.preventDefault();
            return;
        }
        if (form.matches('.inline-capaian-form')) {
            try { sessionStorage.setItem(key, JSON.stringify({ student: form.closest('.inline-capaian').dataset.student, period: form.dataset.period })); } catch (_) {}
        }
        dirty.clear();
    });
    window.addEventListener('beforeunload', event => {
        if (dirty.size) { event.preventDefault(); event.returnValue = ''; }
    });
    try {
        const saved = JSON.parse(sessionStorage.getItem(key));
        sessionStorage.removeItem(key);
        if (saved) {
            const panel = [...area.querySelectorAll('.inline-capaian')].find(item => item.dataset.student === saved.student);
            if (panel) {
                const button = document.querySelector('[aria-controls="' + panel.closest('tr').id + '"]');
                toggle(button, true);
                [...panel.querySelectorAll('.capaian-period')].find(tab => tab.dataset.period === saved.period)?.click();
                panel.scrollIntoView({ block: 'nearest' });
            }
        }
    } catch (_) {}
})();
