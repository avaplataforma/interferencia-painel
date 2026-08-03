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
  const inputs = [...dropdown.querySelectorAll('input[type="checkbox"]')];
  const update = () => {
    if (!summary) return;
    const selected = inputs.filter((input) => input.checked);
    inputs.forEach((input) => input.setCustomValidity(''));
    if (selected.length === 0 && inputs[0]) inputs[0].setCustomValidity('Selecione pelo menos uma etiqueta.');
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

  dropdown.closest('form')?.addEventListener('submit', (event) => {
    if (inputs.length > 0 && !inputs.some((input) => input.checked)) {
      event.preventDefault();
      dropdown.open = true;
      inputs[0].reportValidity();
    }
  });
});

document.querySelectorAll('[data-normalize="email"]').forEach((input) => {
  if (!(input instanceof HTMLInputElement)) return;
  const normalize = () => { input.value = input.value.trim().toLowerCase().replace(/\s+/g, ''); };
  input.addEventListener('change', normalize);
  input.addEventListener('blur', normalize);
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

document.querySelectorAll('[data-copy]').forEach((button) => {
  button.addEventListener('click', async () => {
    const textarea = button.closest('.iframe-code')?.querySelector('textarea');
    if (!(textarea instanceof HTMLTextAreaElement)) return;
    await navigator.clipboard.writeText(textarea.value);
    const original = button.textContent;
    button.textContent = 'Copiado!';
    window.setTimeout(() => { button.textContent = original; }, 1600);
  });
});

const refreshNotifications = async () => {
  if (document.hidden || !document.querySelector('.notification-center')) return;
  try {
    const basePath = document.body.dataset.basePath || '';
    const response = await fetch(`${basePath}/notifications/summary`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    if (!response.ok) return;
    const data = await response.json();
    const unread = Number(data?.whatsapp?.unread || 0);
    const overdue = Number(data?.followups?.overdue || 0);
    const today = Number(data?.followups?.today || 0);
    const total = Number(data?.total || 0);
    if (unread > 0 && !document.querySelector('[data-whatsapp-count]')) document.querySelectorAll('.nav-link[href$="/whatsapp"]').forEach((link) => { const badge = document.createElement('span'); badge.className = 'nav-count'; badge.dataset.whatsappCount = ''; link.append(badge); });
    document.querySelectorAll('[data-whatsapp-count]').forEach((badge) => { badge.textContent = String(unread); badge.hidden = unread < 1; });
    const summary = document.querySelector('.notification-center > summary');
    let totalBadge = document.querySelector('[data-notification-total]');
    if (!totalBadge && summary && total > 0) { totalBadge = document.createElement('span'); totalBadge.className = 'notification-total'; totalBadge.dataset.notificationTotal = ''; summary.append(totalBadge); }
    if (totalBadge) { totalBadge.textContent = String(total); totalBadge.hidden = total < 1; }
    const panel = document.querySelector('.notification-panel');
    if (panel) {
      panel.querySelectorAll('.notification-item,.notification-empty').forEach((item) => item.remove());
      const addItem = (href, icon, title, subtitle) => { const link = document.createElement('a'); link.className = 'notification-item'; link.href = href; const image = document.createElement('i'); image.className = `fa-solid ${icon}`; const copy = document.createElement('span'); const strong = document.createElement('strong'); strong.textContent = title; const small = document.createElement('small'); small.textContent = subtitle; copy.append(strong, small); link.append(image, copy); panel.append(link); };
      if (unread > 0) addItem(`${basePath}/whatsapp?scope=unread`, 'fa-comments', `${unread} mensagem(ns) não lida(s)`, 'Abrir caixa do WhatsApp');
      const userId = document.body.dataset.currentUserId || '';
      if (overdue > 0) addItem(`${basePath}/crm/follow-ups?status=pending&period=overdue&responsible=${userId}`, 'fa-triangle-exclamation', `${overdue} retorno(s) atrasado(s)`, 'Exigem atenção');
      if (today > 0) addItem(`${basePath}/crm/follow-ups?status=pending&period=today&responsible=${userId}`, 'fa-calendar-day', `${today} retorno(s) para hoje`, 'Abrir agenda');
      if (total < 1) { const empty = document.createElement('p'); empty.className = 'notification-empty'; empty.textContent = 'Nenhuma pendência no momento.'; panel.append(empty); }
    }
  } catch (_) {
    // A navegação continua normal se a atualização em segundo plano falhar.
  }
};

if (document.querySelector('.notification-center')) {
  window.setInterval(refreshNotifications, 30000);
  document.addEventListener('visibilitychange', refreshNotifications);
}
