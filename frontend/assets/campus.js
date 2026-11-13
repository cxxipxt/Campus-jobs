const header = document.querySelector('.campus-header');
const nav = header?.querySelector('nav');

if (nav) {
  const menu = document.createElement('button');
  menu.type = 'button';
  menu.className = 'mobile-menu';
  menu.textContent = 'เมนู';
  nav.id ||= 'campus-navigation';
  menu.setAttribute('aria-controls', nav.id);
  const setMenu = open => {
    menu.setAttribute('aria-expanded', String(open));
    header.classList.toggle('menu-open', open);
  };
  setMenu(false);
  nav.before(menu);
  menu.addEventListener('click', () => setMenu(menu.getAttribute('aria-expanded') !== 'true'));
  header.addEventListener('keydown', event => {
    if (event.key === 'Escape' && menu.getAttribute('aria-expanded') === 'true') {
      setMenu(false);
      menu.focus();
    }
  });
}

document.querySelectorAll('input[type="password"]:not(:disabled)').forEach(input => {
  const toggle = document.createElement('button');
  toggle.type = 'button';
  toggle.className = 'password-toggle';
  toggle.textContent = 'แสดงรหัสผ่าน';
  toggle.setAttribute('aria-controls', input.id);
  toggle.setAttribute('aria-pressed', 'false');
  input.after(toggle);
  toggle.addEventListener('click', () => {
    const reveal = input.type === 'password';
    input.type = reveal ? 'text' : 'password';
    toggle.setAttribute('aria-pressed', String(reveal));
  });
});

document.querySelectorAll('[data-submit-on-change]').forEach(field => {
  field.addEventListener('change', () => field.form?.requestSubmit());
});

const loginTabs = document.querySelector('[data-login-tabs]');
if (loginTabs) {
  const tabs = [...loginTabs.querySelectorAll('[aria-controls]')]
    .filter(link => link.matches('.company-auth-tabs a'));
  const panels = [...loginTabs.querySelectorAll('[data-login-panel]')];
  const showPanel = () => {
    const requested = location.hash.slice(1);
    const active = panels.some(panel => panel.id === requested)
      ? requested : loginTabs.dataset.defaultPanel;
    panels.forEach(panel => { panel.hidden = panel.id !== active; });
    tabs.forEach(tab => {
      if (tab.getAttribute('aria-controls') === active) tab.setAttribute('aria-current', 'true');
      else tab.removeAttribute('aria-current');
    });
  };
  tabs.forEach(tab => tab.addEventListener('click', event => {
    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    if (location.hash !== tab.hash) history.pushState(null, '', tab.hash);
    showPanel();
  }));
  window.addEventListener('popstate', showPanel);
  window.addEventListener('hashchange', showPanel);
  showPanel();
}
