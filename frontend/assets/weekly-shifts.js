document.querySelectorAll('.shift-row').forEach(row => {
  const checkbox = row.querySelector('[data-shift-day]');
  const times = row.querySelector('.shift-times');
  function update() {
    times.hidden = !checkbox.checked;
    row.classList.toggle('is-selected', checkbox.checked);
    times.querySelectorAll('input').forEach(input => {
      input.disabled = !checkbox.checked;
      if (input.type === 'time') input.required = checkbox.checked;
    });
  }
  checkbox.addEventListener('change', update);
  update();
});
