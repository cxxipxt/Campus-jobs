document.querySelectorAll('[data-skill-menu]').forEach(menu => {
    const fallback = menu.querySelector('[data-skill-fallback]');
    const value = fallback.querySelector('textarea');
    const ui = menu.querySelector('[data-skill-ui]');
    const search = ui.querySelector('[type=search]');
    const list = ui.querySelector('.skill-choices');
    const custom = ui.querySelector('.skill-custom');
    const count = ui.querySelector('output');
    function sync() {
        const names = Array.from(list.querySelectorAll(':checked'), box => box.value);
        value.value = names.join('\n');
        count.textContent = 'เลือกแล้ว ' + names.length;
    }
    function filter() {
        const term = search.value.trim().toLocaleLowerCase();
        let exact = false;
        for (const label of list.children) {
            label.hidden = !(label.textContent + ' ' + label.dataset.aliases).toLocaleLowerCase().includes(term);
            if (label.querySelector("input").value.toLocaleLowerCase() === term) exact = true;
        }
        custom.hidden = !term || exact;
        custom.lastElementChild.textContent = 'ใช้ทักษะ “' + search.value.trim() + '”';
    }
    custom.addEventListener('change', () => {
        const label = document.createElement('label');
        label.dataset.aliases = '';
        const box = document.createElement('input');
        box.type = 'checkbox';
        box.value = search.value.trim();
        box.checked = true;
        label.append(box, document.createTextNode(box.value));
        list.prepend(label);
        custom.firstElementChild.checked = false;
        search.value = '';
        filter();
        sync();
        search.focus();
    });
    list.addEventListener('change', sync);
    search.addEventListener('input', filter);
    search.addEventListener('keydown', e => { if (e.key === 'Enter') e.preventDefault(); });
    fallback.hidden = true;
    ui.hidden = false;
    sync();
});
