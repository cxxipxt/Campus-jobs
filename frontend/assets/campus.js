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
  const wrap = document.createElement('span');
  wrap.className = 'password-field';
  input.before(wrap);
  wrap.append(input);
  const toggle = document.createElement('button');
  toggle.type = 'button';
  toggle.className = 'password-toggle';
  toggle.setAttribute('aria-label', 'แสดงรหัสผ่าน');
  toggle.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m3 3 18 18"/></svg>';
  toggle.setAttribute('aria-controls', input.id);
  toggle.setAttribute('aria-pressed', 'false');
  wrap.append(toggle);
  if (input.autocomplete === 'new-password') {
    const hint = document.createElement('span');
    hint.className = 'password-hint';
    hint.id = `${input.id}-hint`;
    hint.textContent = input.id.includes('confirmation')
      ? 'กรอกรหัสผ่านให้ตรงกับช่องด้านบน'
      : 'แนะนำให้ใช้ภาษาอังกฤษ 12–72 ตัวอักษร ผสมตัวพิมพ์ใหญ่ ตัวพิมพ์เล็ก ตัวเลข และสัญลักษณ์';
    wrap.append(hint);
    input.setAttribute('aria-describedby', [input.getAttribute('aria-describedby'), hint.id].filter(Boolean).join(' '));
  }
  toggle.addEventListener('click', () => {
    const reveal = input.type === 'password';
    input.type = reveal ? 'text' : 'password';
    toggle.setAttribute('aria-label', reveal ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน');
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

// Progressive enhancement: keep native values, form validation and change events.
(() => {
  let closeActive = null;
  document.querySelectorAll('select:not([multiple])').forEach((select, index) => {
    if (select.size > 1 || select.classList.contains('select-native')) return;
    const uid = `campus-select-${index}`;
    const label = [...select.labels].map(item => item.textContent.trim()).join(' ') || select.getAttribute('aria-label') || 'เลือกตัวเลือก';
    const wrapper = document.createElement('span');
    wrapper.className = 'select-control';
    select.before(wrapper);
    wrapper.append(select);
    select.classList.add('select-native');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');
    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'select-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-controls', uid);
    const caption = document.createElement('span');
    trigger.append(caption);
    wrapper.append(trigger);
    const popup = document.createElement('div');
    popup.className = 'select-popup';
    popup.hidden = true;
    const searchWrap = document.createElement('div');
    searchWrap.className = 'select-search-wrap';
    const search = document.createElement('input');
    search.type = 'search';
    search.className = 'select-search';
    search.placeholder = `ค้นหา${label}`;
    search.setAttribute('aria-label', `ค้นหา${label}`);
    search.setAttribute('role', 'combobox');
    search.setAttribute('aria-autocomplete', 'list');
    search.setAttribute('aria-controls', uid);
    search.setAttribute('aria-expanded', 'false');
    search.autocomplete = 'off';
    const customNumber = select.hasAttribute('data-custom-number');
    if (customNumber) {
      search.inputMode = 'decimal';
      search.placeholder = 'พิมพ์ตัวเลข หรือเลือกด้านล่าง';
    }
    searchWrap.append(search);
    const list = document.createElement('div');
    list.className = 'select-options';
    list.id = uid;
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', label);
    const status = document.createElement('p');
    status.className = 'select-status';
    status.setAttribute('role', 'status');
    status.hidden = true;
    popup.append(searchWrap, list, status);
    document.body.append(popup);
    let items = [];
    let active = -1;
    const sync = () => {
      caption.textContent = select.selectedOptions[0]?.textContent || label;
      trigger.setAttribute('aria-label', `${label}: ${caption.textContent}`);
      trigger.disabled = select.disabled;
      if (select.validity.valid) trigger.removeAttribute('aria-invalid');
    };
    const position = () => {
      if (popup.hidden) return;
      const rect = trigger.getBoundingClientRect();
      const viewport = window.visualViewport;
      const top = viewport?.offsetTop || 0;
      const height = viewport?.height || window.innerHeight;
      const width = Math.min(Math.max(rect.width, 280), window.innerWidth - 16);
      popup.style.width = `${width}px`;
      popup.style.left = `${Math.max(8, Math.min(rect.left, window.innerWidth - width - 8))}px`;
      const below = top + height - rect.bottom - 14;
      const above = rect.top - top - 14;
      const upwards = below < 220 && above > below;
      popup.style.maxHeight = `${Math.max(100, Math.min(390, upwards ? above : below))}px`;
      const desiredTop = upwards ? rect.top - popup.offsetHeight - 6 : rect.bottom + 6;
      popup.style.top = `${Math.max(top + 8, Math.min(desiredTop, top + height - popup.offsetHeight - 8))}px`;
    };
    const activate = next => {
      active = next;
      items.forEach((item, i) => item.node.classList.toggle('is-active', i === active));
      if (items[active]) {
        search.setAttribute('aria-activedescendant', items[active].node.id);
        items[active].node.scrollIntoView({ block: 'nearest' });
      } else search.removeAttribute('aria-activedescendant');
    };
    const close = (restore = false) => {
      popup.hidden = true;
      trigger.setAttribute('aria-expanded', 'false');
      search.setAttribute('aria-expanded', 'false');
      search.removeAttribute('aria-activedescendant');
      if (closeActive === close) closeActive = null;
      if (restore) trigger.focus();
    };
    const choose = option => {
      if (option.disabled || option.parentElement.disabled) return;
      const previous = select.selectedIndex;
      select.selectedIndex = option.index;
      sync();
      close(true);
      if (previous !== select.selectedIndex) {
        select.dispatchEvent(new Event('input', { bubbles: true }));
        select.dispatchEvent(new Event('change', { bubbles: true }));
      }
    };
    const render = () => {
      list.replaceChildren();
      items = [];
      const term = search.value.trim().toLocaleLowerCase();
      let lastGroup = null;
      [...select.options].forEach(option => {
        if (option.hidden || !option.textContent.toLocaleLowerCase().includes(term)) return;
        const group = option.parentElement.tagName === 'OPTGROUP' ? option.parentElement : null;
        if (group && group !== lastGroup) {
          const title = document.createElement('div');
          title.className = 'select-group';
          title.setAttribute('role', 'presentation');
          title.textContent = group.label;
          list.append(title);
        }
        lastGroup = group;
        const node = document.createElement('div');
        node.className = 'select-option';
        node.id = `${uid}-option-${option.index}`;
        node.setAttribute('role', 'option');
        node.setAttribute('aria-selected', String(option.selected));
        node.textContent = option.textContent;
        const disabled = option.disabled || group?.disabled;
        if (disabled) node.setAttribute('aria-disabled', 'true');
        else items.push({ node, option });
        node.addEventListener('pointerdown', event => event.preventDefault());
        node.addEventListener('click', () => choose(option));
        list.append(node);
      });
      status.hidden = list.childElementCount > 0;
      status.textContent = 'ไม่พบตัวเลือกที่ตรงกัน';
      if (customNumber && term !== '') {
        const valid = /^\d+(\.\d{1,2})?$/.test(term) && Number(term) <= Number(select.dataset.numberMax);
        const exact = [...select.options].some(option => option.value !== '' && Number(option.value) === Number(term));
        if (valid && !exact) {
          const node = document.createElement('div');
          node.className = 'select-option';
          node.id = `${uid}-custom`;
          node.setAttribute('role', 'option');
          node.setAttribute('aria-selected', 'false');
          node.textContent = `ใช้ค่า ${Number(term)}`;
          const option = new Option(String(Number(term)), String(Number(term)));
          const commit = () => { select.add(option); choose(option); };
          node.addEventListener('pointerdown', event => event.preventDefault());
          node.addEventListener('click', commit);
          list.prepend(node);
          items.unshift({ node, option, commit });
          status.hidden = true;
        } else if (!valid) {
          status.hidden = false;
          status.textContent = `กรอกตัวเลข 0–${select.dataset.numberMax} ทศนิยมไม่เกิน 2 ตำแหน่ง`;
        }
      }
      position();
      const selected = items.findIndex(item => item.option.selected);
      activate(selected >= 0 ? selected : (items.length ? 0 : -1));
    };
    const open = () => {
      if (select.disabled) return;
      closeActive?.();
      closeActive = close;
      popup.hidden = false;
      trigger.setAttribute('aria-expanded', 'true');
      search.setAttribute('aria-expanded', 'true');
      search.value = '';
      render();
      search.focus({ preventScroll: true });
    };
    trigger.addEventListener('click', () => popup.hidden ? open() : close());
    trigger.addEventListener('keydown', event => {
      if (['ArrowDown', 'ArrowUp'].includes(event.key)) { event.preventDefault(); open(); }
    });
    search.addEventListener('input', render);
    search.addEventListener('keydown', event => {
      if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); close(true); }
      else if (event.key === 'Tab') close(true);
      else if (event.key === 'Enter') { event.preventDefault(); if (items[active]) { if (items[active].commit) items[active].commit(); else choose(items[active].option); } }
      else if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
        event.preventDefault();
        if (items.length) activate((active + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length);
      }
    });
    document.addEventListener('pointerdown', event => {
      if (!popup.contains(event.target) && !wrapper.contains(event.target)) close();
    });
    window.addEventListener('resize', position);
    window.visualViewport?.addEventListener('resize', position);
    window.visualViewport?.addEventListener('scroll', position);
    window.addEventListener('scroll', event => { if (!popup.contains(event.target)) position(); }, true);
    select.addEventListener('change', sync);
    select.addEventListener('focus', open);
    select.addEventListener('invalid', event => {
      event.preventDefault();
      trigger.setAttribute('aria-invalid', 'true');
      // Match native validation: focus the first invalid form control only.
      if (select.form?.querySelector(':invalid') === select) {
        open(); status.hidden = false; status.textContent = select.validationMessage;
      }
    });
    select.form?.addEventListener('reset', () => setTimeout(() => { close(); sync(); }, 0));
    new MutationObserver(() => { sync(); if (!popup.hidden) render(); }).observe(select, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled', 'selected', 'label', 'hidden'] });
    sync();
  });
})();

// Result notifications remain readable inline when JavaScript is unavailable.
(() => {
  const messages = [...document.querySelectorAll('main > .notice, main > .error')];
  if (!messages.length) return;
  const stack = document.createElement('div');
  stack.className = 'toast-stack';
  stack.setAttribute('aria-label', 'ผลการทำรายการ');
  document.body.append(stack);
  messages.forEach(source => {
    const message = source.textContent.trim();
    const error = source.classList.contains('error');
    const warning = !error && /ไม่สามารถ|ปิดรับสมัครแล้ว|กรอกชื่อ|สมัครงานนี้แล้ว|เชื่อมต่ออยู่แล้ว|เชื่อมกับบัญชีของคุณแล้ว/.test(message) && !message.includes('ดำเนินการปิด');
    const kind = error ? 'error' : warning ? 'warning' : 'success';
    const card = document.createElement('section');
    card.className = `result-toast result-toast--${kind}`;
    const icon = document.createElement('span');
    icon.className = 'toast-icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = kind === 'success' ? '✓' : '!';
    const copy = document.createElement('div');
    copy.setAttribute('role', error ? 'alert' : 'status');
    copy.setAttribute('aria-atomic', 'true');
    const title = document.createElement('strong');
    title.textContent = error ? 'ยังทำรายการไม่สำเร็จ' : warning ? 'โปรดตรวจสอบ' : 'เรียบร้อยแล้ว';
    const detail = document.createElement('p');
    detail.textContent = message;
    const dismiss = document.createElement('button');
    dismiss.type = 'button';
    dismiss.className = 'toast-close';
    dismiss.setAttribute('aria-label', 'ปิดข้อความแจ้งผล');
    dismiss.textContent = '×';
    const close = () => {
      if (card.contains(document.activeElement)) {
        const main = document.querySelector('main');
        main.setAttribute('tabindex', '-1'); main.focus({ preventScroll: true });
      }
      card.remove();
    };
    dismiss.addEventListener('click', close);
    card.append(icon, copy, dismiss);
    stack.append(card);
    // Preserve validation details next to the form; move success notices into the popup.
    if (!error) source.remove();
    else source.removeAttribute('role');
    requestAnimationFrame(() => { copy.append(title, detail); });
    // Persistent dismissible messages avoid time limits for reading or screen readers.
  });
})();

(() => {
  const carousel = document.querySelector('.job-carousel');
  if (!carousel) return;
  const slides = [...carousel.querySelectorAll('.job-slide')];
  const controls = carousel.querySelector('.carousel-controls');
  const pause = controls.querySelector('[data-carousel-pause]');
  const reduce = matchMedia('(prefers-reduced-motion: reduce)');
  let current = 0, paused = reduce.matches, timer, busy = false;
  const show = index => {
    if (busy) return;
    const old = slides[current];
    const next = (index + slides.length) % slides.length;
    if (next !== current && !reduce.matches) {
      busy = true; old.classList.add('is-dealing');
      setTimeout(() => { old.classList.remove('is-dealing'); busy = false; }, 850);
    }
    current = next;
    slides.forEach((slide, i) => {
      slide.classList.toggle('is-active', i === current);
      const depth = (i - current + slides.length) % slides.length;
      slide.classList.toggle('is-second', depth === 1);
      slide.classList.toggle('is-third', depth === 2);
      slide.inert = i !== current;
      slide.setAttribute('aria-hidden', String(i !== current));
      if (i === current) slide.querySelector('img').loading = 'eager';
    });
    controls.querySelector('.carousel-count').textContent = `${current + 1} / ${slides.length}`;
  };
  const schedule = () => {
    clearInterval(timer);
    pause.textContent = paused ? 'เล่นสลับภาพ' : 'หยุดสลับภาพ';
    if (!paused && !document.hidden && !carousel.contains(document.activeElement)) timer = setInterval(() => show(current + 1), 4200);
  };
  controls.hidden = false;
  controls.querySelector('[data-carousel-next]').onclick = () => { show(current + 1); schedule(); };
  controls.querySelector('[data-carousel-prev]').onclick = () => { show(current - 1); schedule(); };
  pause.onclick = () => { paused = !paused; schedule(); };

  carousel.addEventListener('focusin', schedule);
  carousel.addEventListener('focusout', () => setTimeout(schedule, 0));
  document.addEventListener('visibilitychange', schedule);
  reduce.addEventListener('change', () => { paused = reduce.matches; schedule(); });
  show(0);
  schedule();
})();
