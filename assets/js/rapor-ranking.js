(() => {
    'use strict';
    const page = document.querySelector('.rapor-ranking-page');
    if (!page) return;
    let previous = [];
    window.addEventListener('beforeprint', () => {
        if (previous.length) return;
        previous = [...page.querySelectorAll('details')].map(node => ({node, open: node.open}));
        previous.forEach(({node}) => { node.open = true; });
    });
    window.addEventListener('afterprint', () => {
        previous.forEach(({node, open}) => { node.open = open; }); previous = [];
    });
    document.getElementById('rapor-ranking-print')?.addEventListener('click', () => window.print());
})();
