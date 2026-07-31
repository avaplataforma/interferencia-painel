'use strict';

document.querySelectorAll('.status-dropdown').forEach((dropdown) => {
  const summary = dropdown.querySelector('summary');
  const update = () => {
    const selected = dropdown.querySelector('input[type="radio"]:checked');
    const badge = selected?.closest('label')?.querySelector('.tag-badge');
    if (summary && badge) summary.replaceChildren(badge.cloneNode(true));
  };

  dropdown.addEventListener('change', (event) => {
    if (event.target instanceof HTMLInputElement && event.target.type === 'radio') {
      update();
      dropdown.open = false;
    }
  });
  update();
});

document.querySelectorAll('.tags-dropdown').forEach((dropdown) => {
  const summary = dropdown.querySelector('summary');
  const update = () => {
    if (!summary) return;
    const selected = [...dropdown.querySelectorAll('input[type="checkbox"]:checked')];
    if (selected.length === 0) {
      summary.textContent = 'Selecionar etiquetas';
      return;
    }
    const badges = selected
      .map((input) => input.closest('label')?.querySelector('.tag-badge')?.cloneNode(true))
      .filter(Boolean);
    summary.replaceChildren(...badges);
  };
  dropdown.addEventListener('change', update);
  update();
});
