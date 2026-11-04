const creditTeacherSearch = document.querySelector('#creditTeacherSearch');
const creditTabs = [...document.querySelectorAll('[data-credit-tab]')];
function creditSelectModule(tab, focus = false) {
    creditTabs.forEach(item => {
        const selected = item === tab;
        item.setAttribute('aria-selected', String(selected));
        item.tabIndex = selected ? 0 : -1;
        const panel = document.getElementById(item.getAttribute('aria-controls'));
        panel.hidden = !selected;
        panel.setAttribute('role', 'tabpanel');
        panel.setAttribute('aria-labelledby', item.id);
    });
    if (focus) tab.focus();
}
creditTabs.forEach((tab, index) => {
    tab.addEventListener('click', () => creditSelectModule(tab));
    tab.addEventListener('keydown', event => {
        let next;
        if (event.key === 'ArrowRight') next = (index + 1) % creditTabs.length;
        if (event.key === 'ArrowLeft') next = (index - 1 + creditTabs.length) % creditTabs.length;
        if (event.key === 'Home') next = 0;
        if (event.key === 'End') next = creditTabs.length - 1;
        if (next !== undefined) { event.preventDefault(); creditSelectModule(creditTabs[next], true); }
    });
});
if (creditTabs.length) creditSelectModule(creditTabs[0]);
creditTeacherSearch?.addEventListener('input', () => {
    const term = creditTeacherSearch.value.trim().toLocaleLowerCase('id');
    document.querySelectorAll('.credit-access-card').forEach(card => {
        const rows = [...card.querySelectorAll('[data-teacher-search]')];
        rows.forEach(row => { row.hidden = !row.dataset.teacherSearch.includes(term); });
        card.querySelector('[data-search-empty]').hidden = !rows.length || rows.some(row => !row.hidden);
    });
});
document.querySelectorAll('.credit-access-card').forEach(card => {
    const count = card.querySelector('[data-selected-count]');
    card.addEventListener('change', () => { count.textContent = card.querySelectorAll('input:checked').length; });
});
document.querySelector('#creditAccessForm')?.addEventListener('submit', event => {
    event.currentTarget.querySelector('button[type="submit"]').disabled = true;
});
