(() => {
  const filters = document.querySelector('.job-filters');
  if (!filters) return;
  const mobile = window.matchMedia('(max-width: 720px)');
  const sync = () => { filters.open = !mobile.matches; };
  sync();
  mobile.addEventListener('change', sync);

  const form = filters.querySelector('form');
  const summary = document.querySelector('[data-active-job-filters]');
  const fields = {
    q: 'ตำแหน่ง', location: 'สถานที่', category_id: 'หมวดงาน',
    min_wage: 'ขั้นต่ำ', max_wage: 'สูงสุด',
  };
  function showActiveFilters() {
    if (!summary) return;
    summary.replaceChildren();
    for (const [name, label] of Object.entries(fields)) {
      const field = form.elements.namedItem(name);
      if (!field?.value.trim()) continue;
      const chip = document.createElement('button');
      chip.type = 'button';
      const value = field.selectedOptions?.[0]?.text ?? field.value.trim();
      chip.textContent = `${label}: ${value} ×`;
      chip.setAttribute('aria-label', `ล้างตัวกรอง ${label}: ${value}`);
      chip.addEventListener('click', () => {
        field.value = '';
        form.requestSubmit();
      });
      summary.append(chip);
    }
    summary.hidden = !summary.childElementCount;
  }
  const preview = document.querySelector('[data-job-preview]');
  if (!form) return;
  if (!preview) {
    showActiveFilters();
    return;
  }

  const cards = [...preview.querySelectorAll('.job-card')];
  const count = preview.querySelector('[data-job-count]');
  const empty = preview.querySelector('[data-job-empty]');
  const filterPreview = () => {
    const data = new FormData(form);
    const term = name => String(data.get(name) ?? '').trim().toLowerCase();
    let matches = 0;
    cards.forEach(card => {
      const wage = Number(card.dataset.wage);
      const visible = card.querySelector('h3').textContent.toLowerCase().includes(term('q'))
        && card.dataset.location.toLowerCase().includes(term('location'))
        && (!term('category_id') || card.dataset.category === term('category_id'))
        && wage >= Number(term('min_wage'))
        && (!term('max_wage') || wage <= Number(term('max_wage')));
      card.hidden = !visible;
      if (visible) matches += 1;
    });
    count.textContent = `พบประกาศตัวอย่าง ${matches} รายการ`;
    empty.hidden = matches > 0;
    showActiveFilters();
  };
  const params = new URLSearchParams(location.search);
  if (!params.has('category_id') && params.has('category')) {
    params.set('category_id', params.get('category'));
  }
  for (const name of Object.keys(fields)) {
    const field = form.elements.namedItem(name);
    if (field && params.has(name)) field.value = params.get(name);
  }
  filterPreview();
  form.addEventListener('submit', event => {
    event.preventDefault();
    filterPreview();
  });
})();
