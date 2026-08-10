(() => {
  const dialog = document.querySelector('#site-search-dialog');
  const input = dialog?.querySelector('[data-site-search-input]');
  const items = Array.from(dialog?.querySelectorAll('[data-site-search-item]') || []);
  const empty = dialog?.querySelector('[data-site-search-empty]');
  if (dialog instanceof HTMLDialogElement && input instanceof HTMLInputElement) {
    const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    const filter = () => {
      const query = normalize(input.value.trim());
      let visible = 0;
      items.forEach((item) => {
        const match = query === '' || normalize(item.textContent || '').includes(query);
        item.hidden = !match;
        if (match) visible += 1;
      });
      if (empty instanceof HTMLElement) empty.hidden = visible !== 0;
    };
    document.querySelectorAll('[data-site-search-open]').forEach((button) => button.addEventListener('click', () => {
      if (!dialog.open) dialog.showModal();
      window.setTimeout(() => input.focus(), 0);
    }));
    document.querySelectorAll('[data-site-search-close]').forEach((button) => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
    input.addEventListener('input', filter);
    filter();
  }

  const scholarship = document.querySelector('#scholarship-dialog');
  if (!(scholarship instanceof HTMLDialogElement)) return;
  const open = () => { if (!scholarship.open) scholarship.showModal(); };
  document.querySelectorAll('[data-scholarship-open]').forEach((button) => button.addEventListener('click', open));
  document.querySelectorAll('[data-scholarship-close]').forEach((button) => button.addEventListener('click', () => scholarship.close()));
  scholarship.addEventListener('click', (event) => { if (event.target === scholarship) scholarship.close(); });
  if (new URLSearchParams(location.search).has('bolsas')) { open(); return; }
  if (document.body.dataset.scholarshipPopup !== '1') return;
  const storageKey = document.body.dataset.scholarshipKey || 'site-scholarship-v2';
  const repeat = Math.max(1, Number(document.body.dataset.scholarshipRepeat || 24)) * 60 * 60 * 1000;
  const delay = Math.max(5, Number(document.body.dataset.scholarshipDelay || 15)) * 1000;
  const last = Number(localStorage.getItem(storageKey) || 0);
  if (Date.now() - last > repeat) window.setTimeout(() => {
    open();
    localStorage.setItem(storageKey, String(Date.now()));
  }, delay);
})();
