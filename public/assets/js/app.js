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
  dropdown.addEventListener('change', (event) => {
    if (event.target instanceof HTMLInputElement && event.target.type === 'checkbox') {
      update();
      dropdown.open = false;
    }
  });
  update();
});

const digits = (value, limit) => value.replace(/\D/g, '').slice(0, limit);

const phoneMask = (value) => {
  const number = digits(value, 11);
  if (number.length === 0) return '';
  if (number.length <= 2) return number.replace(/^(\d{0,2})/, '($1');
  if (number.length <= 6) return number.replace(/^(\d{2})(\d+)/, '($1) $2');
  if (number.length <= 10) return number.replace(/^(\d{2})(\d{4})(\d+)/, '($1) $2-$3');
  return number.replace(/^(\d{2})(\d{5})(\d+)/, '($1) $2-$3');
};

const documentMask = (value) => {
  const number = digits(value, 14);
  if (number.length <= 11) {
    return number
      .replace(/^(\d{3})(\d)/, '$1.$2')
      .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
      .replace(/\.(\d{3})(\d)/, '.$1-$2');
  }
  return number
    .replace(/^(\d{2})(\d)/, '$1.$2')
    .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
    .replace(/\.(\d{3})(\d)/, '.$1/$2')
    .replace(/(\/\d{4})(\d)/, '$1-$2');
};

document.querySelectorAll('[data-mask]').forEach((input) => {
  if (!(input instanceof HTMLInputElement)) return;
  const format = input.dataset.mask === 'phone' ? phoneMask : documentMask;
  const update = () => { input.value = format(input.value); };
  input.addEventListener('input', update);
  update();
});
