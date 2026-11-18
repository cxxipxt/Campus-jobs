(() => {
  const sub = document.querySelector('select#subcategory');
  if (sub) {
    const parent = document.querySelector('#category_id');
    const groups = [...sub.querySelectorAll('optgroup')];
    function update() {
      const old = sub.value;
      groups.forEach(group => group.remove());
      const group = groups.find(item => item.dataset.parent === parent.value);
      if (group) sub.append(group);
      sub.value = [...sub.options].some(o => o.value === old) ? old : '';
      sub.dispatchEvent(new Event('change', {bubbles:true}));
    }
    update();
    parent.addEventListener('change', update);
  }

  const menu = document.querySelector('[data-category-picker]');
  if (!menu) return;
  const tags = menu.parentElement.querySelector('[data-cat-tags]');
  const groups = [...menu.querySelectorAll('.cat-group')];
  const search = menu.querySelector('[data-cat-search]');
  const clear = menu.querySelector('[data-cat-clear]');
  function expand(group, open) {
    group.querySelector('.cat-children').hidden = !open;
    group.querySelector('[data-expand]').setAttribute('aria-expanded', String(open));
  }
  function render() {
    tags.replaceChildren();
    const checked = [...menu.querySelectorAll('input:checked')];
    for (const box of checked) {
      const chip = document.createElement('button');
      chip.type = 'button';
      const name = box.closest('label').textContent.trim();
      chip.textContent = name + ' ×';
      chip.setAttribute('aria-label', 'ลบ ' + name);
      chip.onclick = () => { box.checked = false; render(); menu.querySelector('summary').focus(); };
      tags.append(chip);
    }
    menu.querySelector('[data-cat-count]').textContent = checked.length ? '(' + checked.length + ')' : 'ทั้งหมด';
  }
  function filter() {
    const text = search.value.trim().toLocaleLowerCase();
    for (const group of groups) {
      const parentMatch = group.querySelector('.cat-heading label').textContent.toLocaleLowerCase().includes(text);
      const children = [...group.querySelectorAll('.cat-children label')];
      for (const label of children) label.hidden = !parentMatch && !label.textContent.toLocaleLowerCase().includes(text);
      group.hidden = !parentMatch && children.every(label => label.hidden);
      expand(group, Boolean(text) || Boolean(group.querySelector('.cat-children :checked')));
    }
    menu.querySelector('[data-cat-empty]').hidden = groups.some(group => !group.hidden);
  }
  groups.forEach(group => {
    const btn = group.querySelector('[data-expand]');
    btn.onclick = () => expand(group, btn.getAttribute('aria-expanded') !== 'true');
  });
  menu.addEventListener('change', event => {
    const box = event.target;
    if (!box.checked || !box.matches('input[type=checkbox]')) { render(); return; }
    const group = box.closest('.cat-group');
    if (box.name === 'category_id[]') group.querySelectorAll('.cat-children input').forEach(child => child.checked = false);
    else group.querySelector('.cat-heading input').checked = false;
    render();
  });
  clear.hidden = false;
  clear.onclick = () => {menu.querySelectorAll('input:checked').forEach(box => box.checked = false);search.value='';filter();render();};
  search.addEventListener('input', filter);
  search.addEventListener('keydown', e => {if(e.key==='Enter')e.preventDefault();});
  menu.addEventListener('keydown', e => {if(e.key==='Escape'){menu.open=false;menu.querySelector('summary').focus();}});
  document.addEventListener('click', e => {if (!menu.parentElement.contains(e.target)) menu.open=false;});
  filter();
  render();
})();
