(() => {
    const editor = document.querySelector('.editor-layout');
    if (!editor) return;

    const menu = editor.querySelector('.editor-menu');
    const links = [...menu.querySelectorAll('a[href^="#"]')];
    const panels = [...editor.querySelectorAll('.editor-card')];
    const form = editor.querySelector('.editor-content > form');
    const activeSection = form.elements.active_section;
    const actions = form.querySelector('.editor-actions');
    const mobileMenu = document.createElement('label');
    mobileMenu.className = 'editor-mobile-menu';
    mobileMenu.textContent = 'หัวข้อในโปรไฟล์';
    const select = document.createElement('select');
    for (const link of links) {
        select.add(new Option(link.textContent, link.hash.slice(1)));
    }
    mobileMenu.append(select);
    menu.before(mobileMenu);
    editor.classList.add('editor-enhanced');

    function show(section) {
        const id = panels.some(panel => panel.id === section) ? section : 'basic';
        for (const panel of panels) panel.hidden = panel.id !== id;
        for (const link of links) {
            if (link.hash === '#' + id) link.setAttribute('aria-current', 'location');
            else link.removeAttribute('aria-current');
        }
        actions.hidden = id === 'accounts';
        activeSection.value = id;
        select.value = id;
    }

    function selectSection(id) {
        if (location.hash !== '#' + id) history.pushState(null, '', '#' + id);
        show(id);
    }

    menu.addEventListener('click', event => {
        const link = event.target.closest('a');
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.altKey || event.shiftKey) return;
        event.preventDefault();
        selectSection(link.hash.slice(1));
    });
    select.addEventListener('change', () => selectSection(select.value));
    window.addEventListener('hashchange', () => show(location.hash.slice(1)));

    // The browser can report invalid controls in several hidden panels at once.
    let revealingInvalid = false;
    form.addEventListener('invalid', event => {
        if (revealingInvalid) return;
        const panel = event.target.closest('.editor-card');
        if (!panel) return;
        revealingInvalid = true;
        selectSection(panel.id);
        setTimeout(() => { revealingInvalid = false; }, 0);
    }, true);

    const errorSection = editor.dataset.errorSection;
    if (panels.some(panel => panel.id === errorSection)) {
        history.replaceState(null, '', '#' + errorSection);
        show(errorSection);
        const alert = document.querySelector('main [role="alert"]');
        if (alert) {
            alert.tabIndex = -1;
            alert.focus();
        }
    } else {
        show(location.hash.slice(1));
    }
})();
