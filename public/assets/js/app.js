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
    const total = dropdown.querySelectorAll('input[type="checkbox"]:checked').length;
    if (summary) summary.textContent = total === 0 ? 'Selecionar etiquetas' : `${total} etiqueta(s) selecionada(s)`;
  };
  dropdown.addEventListener('change', update);
  update();
});
