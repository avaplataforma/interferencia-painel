'use strict';

document.addEventListener('click', async (event) => {
  const button = event.target.closest('[data-copy-target]');
  if (!button) return;
  const field = document.getElementById(button.dataset.copyTarget || '');
  if (!field || !navigator.clipboard) return;
  try {
    await navigator.clipboard.writeText(field.value || '');
    const original = button.textContent;
    button.textContent = 'Copiado';
    window.setTimeout(() => { button.textContent = original; }, 1600);
  } catch (_) {
    field.focus();
    field.select();
  }
});

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
    const ticketUnread = Number(data?.tickets?.unread || 0);
    const ticketOverdue = Number(data?.tickets?.overdue || 0);
    const total = Number(data?.total || 0);
    if (unread > 0 && !document.querySelector('[data-whatsapp-count]')) document.querySelectorAll('.nav-link[href$="/whatsapp"]').forEach((link) => { const badge = document.createElement('span'); badge.className = 'nav-count'; badge.dataset.whatsappCount = ''; link.append(badge); });
    document.querySelectorAll('[data-whatsapp-count]').forEach((badge) => { badge.textContent = String(unread); badge.hidden = unread < 1; });
    if (ticketUnread > 0 && !document.querySelector('[data-ticket-count]')) document.querySelectorAll('.nav-link[href$="/tickets"]').forEach((link) => { const badge = document.createElement('span'); badge.className = 'nav-count'; badge.dataset.ticketCount = ''; link.append(badge); });
    document.querySelectorAll('[data-ticket-count]').forEach((badge) => { badge.textContent = String(ticketUnread); badge.hidden = ticketUnread < 1; });
    const summary = document.querySelector('.notification-center > summary');
    let totalBadge = document.querySelector('[data-notification-total]');
    if (!totalBadge && summary && total > 0) { totalBadge = document.createElement('span'); totalBadge.className = 'notification-total'; totalBadge.dataset.notificationTotal = ''; summary.append(totalBadge); }
    if (totalBadge) { totalBadge.textContent = String(total); totalBadge.hidden = total < 1; }
    const panel = document.querySelector('.notification-panel');
    if (panel) {
      panel.querySelectorAll('.notification-item,.notification-empty').forEach((item) => item.remove());
      const addItem = (href, icon, title, subtitle) => { const link = document.createElement('a'); link.className = 'notification-item'; link.href = href; const image = document.createElement('i'); image.className = `fa-solid ${icon}`; const copy = document.createElement('span'); const strong = document.createElement('strong'); strong.textContent = title; const small = document.createElement('small'); small.textContent = subtitle; copy.append(strong, small); link.append(image, copy); panel.append(link); };
      if (ticketUnread > 0) addItem(`${basePath}/tickets?scope=mine`, 'fa-ticket', `${ticketUnread} ticket(s) atualizado(s)`, 'Abrir demandas recebidas');
      if (ticketOverdue > 0) addItem(`${basePath}/tickets?scope=overdue`, 'fa-clock', `${ticketOverdue} ticket(s) atrasado(s)`, 'Prazo vencido');
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

document.querySelectorAll('[data-template-preview]').forEach((preview) => {
  const select = preview.querySelector('[data-template-select]');
  const output = preview.querySelector('[data-template-output]');
  const status = preview.querySelector('[data-template-status]');
  if (!(select instanceof HTMLSelectElement) || !(output instanceof HTMLTextAreaElement)) return;
  const values = {
    nome: preview.dataset.name || 'Contato',
    curso: preview.dataset.course || 'curso de interesse',
    unidade: preview.dataset.unit || '',
    atendente: preview.dataset.agent || 'Atendimento',
  };
  select.addEventListener('change', () => {
    const option = select.selectedOptions[0];
    let body = option?.dataset.body || '';
    Object.entries(values).forEach(([key, value]) => {
      body = body.replace(new RegExp(`{{\\s*${key}\\s*}}`, 'gi'), value);
    });
    output.value = body;
    if (status) status.textContent = option?.dataset.status === 'approved' ? 'Modelo marcado como aprovado.' : 'Modelo ainda não está aprovado para envio.';
  });
});

document.querySelectorAll('a.message-attachment').forEach((link) => {
  if (!(link instanceof HTMLAnchorElement)) return;
  const imageIcon = link.querySelector('.fa-image');
  const audioIcon = link.querySelector('.fa-file-audio');
  if (!imageIcon && !audioIcon) return;
  const mediaUrl = `${link.href}${link.href.includes('?') ? '&' : '?'}inline=1`;
  if (imageIcon) {
    const image = document.createElement('img');
    image.className = 'message-media-preview';
    image.src = mediaUrl;
    image.alt = link.querySelector('strong')?.textContent || 'Imagem recebida';
    image.loading = 'lazy';
    link.before(image);
    return;
  }
  const audio = document.createElement('audio');
  audio.className = 'message-audio-preview';
  audio.controls = true;
  audio.preload = 'metadata';
  audio.src = mediaUrl;
  link.before(audio);
});

document.querySelectorAll('[data-finance-payment-form]').forEach((form) => {
  if (!(form instanceof HTMLFormElement)) return;
  const kind = form.querySelector('#charge-kind');
  const field = form.querySelector('[data-installment-field]');
  const count = form.querySelector('#installment-count');
  const value = form.querySelector('#value');
  const summary = form.querySelector('[data-installment-summary]');
  const help = form.querySelector('[data-value-help]');
  if (!(kind instanceof HTMLSelectElement) || !(count instanceof HTMLInputElement) || !(value instanceof HTMLInputElement)) return;
  const update = () => {
    const parcelled = kind.value === 'installment';
    if (field instanceof HTMLElement) field.hidden = !parcelled;
    count.required = parcelled;
    if (summary instanceof HTMLElement) summary.hidden = !parcelled;
    if (help instanceof HTMLElement) help.textContent = parcelled ? 'Valor total do parcelamento.' : 'Valor da cobrança única.';
    if (!parcelled || !(summary instanceof HTMLElement)) return;
    const total = Number(value.value || 0);
    const installments = Number(count.value || 0);
    const approximate = installments > 0 ? total / installments : 0;
    const money = (amount) => amount.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    summary.textContent = `Total de ${money(total)} em ${installments || 0} parcelas de aproximadamente ${money(approximate)}. Primeira parcela na data informada; as seguintes serão mensais.`;
  };
  kind.addEventListener('change', update);
  count.addEventListener('input', update);
  value.addEventListener('input', update);
  form.addEventListener('submit', () => {
    const button = form.querySelector('button[type="submit"]');
    if (!(button instanceof HTMLButtonElement)) return;
    button.disabled = true;
    button.textContent = 'Emitindo…';
  });
  update();
});

document.querySelectorAll('[data-subscription-form]').forEach((form) => {
  if (!(form instanceof HTMLFormElement)) return;
  const type = form.querySelector('[name="limit_type"]');
  const countWrap = form.querySelector('[data-subscription-count]');
  const dateWrap = form.querySelector('[data-subscription-end]');
  const count = form.querySelector('[name="max_payments"]');
  const endDate = form.querySelector('[name="end_date"]');
  if (!(type instanceof HTMLSelectElement) || !(count instanceof HTMLInputElement) || !(endDate instanceof HTMLInputElement)) return;
  const update = () => {
    const byCount = type.value === 'count';
    const byDate = type.value === 'date';
    if (countWrap instanceof HTMLElement) countWrap.hidden = !byCount;
    if (dateWrap instanceof HTMLElement) dateWrap.hidden = !byDate;
    count.required = byCount;
    endDate.required = byDate;
  };
  type.addEventListener('change', update);
  form.addEventListener('submit', () => {
    const button = form.querySelector('button[type="submit"]');
    if (!(button instanceof HTMLButtonElement)) return;
    button.disabled = true;
    button.textContent = 'Criando…';
  });
  update();
});

document.querySelectorAll('[data-confirm-submit]').forEach((form) => {
  if (!(form instanceof HTMLFormElement)) return;
  form.addEventListener('submit', (event) => {
    if (!window.confirm(form.dataset.confirmSubmit || 'Confirma esta alteração?')) event.preventDefault();
  });
});

document.querySelectorAll('[data-contact-unit-form]').forEach((form) => {
  if (!(form instanceof HTMLFormElement)) return;
  const unit = form.querySelector('[data-contact-unit]');
  const responsible = form.querySelector('[data-contact-responsible]');
  if (!(unit instanceof HTMLSelectElement) || !(responsible instanceof HTMLSelectElement)) return;
  const updateResponsibles = () => {
    const unitId = unit.value;
    let selectedVisible = false;
    Array.from(responsible.options).forEach((option, index) => {
      if (index === 0) return;
      const visible = unitId !== '' && (option.dataset.unitIds || '').split(',').includes(unitId);
      option.hidden = !visible;
      option.disabled = !visible;
      if (visible && option.selected) selectedVisible = true;
    });
    if (!selectedVisible) responsible.value = '';
    responsible.disabled = unitId === '';
    responsible.options[0].textContent = unitId === '' ? 'Selecione a unidade primeiro' : 'Selecione o atendente';
  };
  unit.addEventListener('change', updateResponsibles);
  updateResponsibles();
});

document.querySelectorAll('[data-ticket-contacts]').forEach((picker) => {
  const unit = document.querySelector('#ticket-unit');
  const search = picker.querySelector('#ticket-contact-search');
  const contactId = picker.querySelector('#ticket-contact-id');
  const results = picker.querySelector('[data-ticket-contact-results]');
  const help = picker.querySelector('[data-ticket-contact-help]');
  if (!(unit instanceof HTMLSelectElement) || !(search instanceof HTMLInputElement) || !(contactId instanceof HTMLInputElement) || !(results instanceof HTMLElement)) return;

  let contacts = [];
  try { contacts = JSON.parse(picker.dataset.ticketContacts || '[]'); } catch (_) { contacts = []; }

  const clear = () => {
    contactId.value = '';
    delete search.dataset.selected;
    search.setCustomValidity('');
  };
  const render = () => {
    clear();
    const term = search.value.trim().toLocaleLowerCase('pt-BR');
    const selectedUnit = Number(unit.value || 0);
    results.replaceChildren();
    if (term.length < 2 || selectedUnit < 1) { results.hidden = true; return; }
    const matches = contacts.filter((contact) => Number(contact.unit_id) === selectedUnit && [contact.name, contact.phone, contact.email].join(' ').toLocaleLowerCase('pt-BR').includes(term)).slice(0, 12);
    matches.forEach((contact) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'ticket-contact-option';
      const title = document.createElement('strong');
      title.textContent = contact.name;
      const meta = document.createElement('small');
      meta.textContent = [contact.phone, contact.email].filter(Boolean).join(' · ');
      button.append(title, meta);
      button.addEventListener('click', () => {
        search.value = contact.name;
        search.dataset.selected = '1';
        contactId.value = String(contact.id);
        results.hidden = true;
        if (help) help.textContent = `Aluno/Contato selecionado: ${contact.name}`;
      });
      results.append(button);
    });
    if (matches.length === 0) {
      const empty = document.createElement('p');
      empty.className = 'meta';
      empty.textContent = 'Nenhum cadastro encontrado nesta unidade.';
      results.append(empty);
    }
    results.hidden = false;
  };

  unit.addEventListener('change', () => {
    search.value = '';
    clear();
    results.hidden = true;
    if (help) help.textContent = 'Comece a digitar para localizar um cadastro da unidade escolhida.';
  });
  search.addEventListener('input', render);
  search.addEventListener('focus', render);
  search.form?.addEventListener('submit', (event) => {
    if (contactId.value) return;
    event.preventDefault();
    search.setCustomValidity('Selecione um aluno ou contato na lista de resultados.');
    search.reportValidity();
  });
  document.addEventListener('click', (event) => {
    if (event.target instanceof Element && !event.target.closest('.ticket-contact-picker')) results.hidden = true;
  });
});
