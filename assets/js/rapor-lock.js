(() => {
    'use strict';
    if (!document.getElementById('rapor-locked-notice')) return;
    document.querySelectorAll('.rapor-batch form, #rapor-form').forEach(form => {
        form.querySelectorAll('input:not([type="hidden"]), select, textarea, button').forEach(control => { if (!control.closest('[data-rapor-view-control]')) control.disabled = true; });
        form.addEventListener('submit', event => event.preventDefault());
    });
})();
