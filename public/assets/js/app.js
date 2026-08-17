'use strict';

document.addEventListener('click', (event) => {
  const tab = event.target.closest('[data-master-course-section]');
  if (!tab) return;
  const detail = tab.closest('[data-master-course-detail]');
  if (!detail) return;

  event.preventDefault();
  const section = tab.dataset.masterCourseSection || 'curation';
  detail.querySelectorAll('[data-master-course-section]').forEach((item) => {
    const active = item.dataset.masterCourseSection === section;
    item.classList.toggle('is-active', active);
    item.setAttribute('aria-selected', active ? 'true' : 'false');
  });
  detail.querySelectorAll('[data-master-course-panel]').forEach((panel) => {
    panel.hidden = panel.dataset.masterCoursePanel !== section;
  });
}, true);

document.addEventListener('click', (event) => {
  const button = event.target.closest('[data-print-page]');
  if (!button) return;
  event.preventDefault();
  window.print();
});

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

const postalMask = (value) => {
  const number = digits(value, 8);
  return number.replace(/^(\d{5})(\d)/, '$1-$2');
};

document.querySelectorAll('[data-mask]').forEach((input) => {
  if (!(input instanceof HTMLInputElement)) return;
  const format = input.dataset.mask === 'phone'
    ? phoneMask
    : (input.dataset.mask === 'postal' ? postalMask : documentMask);
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
    const studentActions = Number(data?.student_actions?.total || 0);
    const recoveryInitial = Number(data?.recovery?.initial || 0);
    const recoveryDay = Number(data?.recovery?.day || 0);
    const recoveryCritical = Number(data?.recovery?.critical || 0);
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
      if (studentActions > 0) addItem(`${basePath}/students/actions`, 'fa-list-check', `${studentActions} ação(ões) de alunos`, 'Cadastro, financeiro, AVA e pedagógico');
      if (ticketUnread > 0) addItem(`${basePath}/tickets?scope=mine`, 'fa-ticket', `${ticketUnread} ticket(s) atualizado(s)`, 'Abrir demandas recebidas');
      if (ticketOverdue > 0) addItem(`${basePath}/tickets?scope=overdue`, 'fa-clock', `${ticketOverdue} ticket(s) atrasado(s)`, 'Prazo vencido');
      if (unread > 0) addItem(`${basePath}/whatsapp?scope=unread`, 'fa-comments', `${unread} mensagem(ns) não lida(s)`, 'Abrir caixa do WhatsApp');
      const userId = document.body.dataset.currentUserId || '';
      if (overdue > 0) addItem(`${basePath}/crm/follow-ups?status=pending&period=overdue&responsible=${userId}`, 'fa-triangle-exclamation', `${overdue} retorno(s) atrasado(s)`, 'Exigem atenção');
      if (today > 0) addItem(`${basePath}/crm/follow-ups?status=pending&period=today&responsible=${userId}`, 'fa-calendar-day', `${today} retorno(s) para hoje`, 'Abrir agenda');
      if (recoveryCritical > 0) addItem(`${basePath}/admin/site/funnel#recuperacao`, 'fa-fire', `${recoveryCritical} checkout(s) parado(s) há 3 dias`, 'Prioridade máxima de recuperação');
      if (recoveryDay > 0) addItem(`${basePath}/admin/site/funnel#recuperacao`, 'fa-clock-rotate-left', `${recoveryDay} checkout(s) parado(s) há 24h`, 'Retomar contato comercial');
      if (recoveryInitial > 0) addItem(`${basePath}/admin/site/funnel#recuperacao`, 'fa-cart-arrow-down', `${recoveryInitial} checkout(s) parado(s) há 30 min`, 'Nova oportunidade para recuperar');
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

document.querySelectorAll('[data-ticket-students]').forEach((picker) => {
  const unit = document.querySelector('#ticket-unit');
  const search = picker.querySelector('#ticket-contact-search');
  const contactId = picker.querySelector('#ticket-contact-id');
  const results = picker.querySelector('[data-ticket-contact-results]');
  const help = picker.querySelector('[data-ticket-contact-help]');
  if (!(unit instanceof HTMLSelectElement) || !(search instanceof HTMLInputElement) || !(contactId instanceof HTMLInputElement) || !(results instanceof HTMLElement)) return;

  let students = [];
  try { students = JSON.parse(picker.dataset.ticketStudents || '[]'); } catch (_) { students = []; }

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
    const matches = students.filter((contact) => Number(contact.unit_id) === selectedUnit && [contact.name, contact.document, contact.phone, contact.email].join(' ').toLocaleLowerCase('pt-BR').includes(term)).slice(0, 12);
    matches.forEach((contact) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'ticket-contact-option';
      const title = document.createElement('strong');
      title.textContent = contact.name;
      const meta = document.createElement('small');
      meta.textContent = [contact.document, contact.phone, contact.email].filter(Boolean).join(' · ');
      button.append(title, meta);
      button.addEventListener('click', () => {
        search.value = contact.name;
        search.dataset.selected = '1';
        contactId.value = String(contact.id);
        results.hidden = true;
        if (help) help.textContent = `Aluno selecionado: ${contact.name}`;
      });
      results.append(button);
    });
    if (matches.length === 0) {
      const empty = document.createElement('p');
      empty.className = 'meta';
      empty.textContent = 'Nenhum aluno ativo encontrado nesta unidade.';
      results.append(empty);
    }
    results.hidden = false;
  };

  unit.addEventListener('change', () => {
    search.value = '';
    clear();
    results.hidden = true;
    if (help) help.textContent = 'Consulte os alunos ativos do Financeiro da unidade escolhida.';
  });
  search.addEventListener('input', render);
  search.addEventListener('focus', render);
  search.form?.addEventListener('submit', (event) => {
    if (contactId.value) return;
    event.preventDefault();
    search.setCustomValidity('Selecione um aluno ativo na lista de resultados.');
    search.reportValidity();
  });
  document.addEventListener('click', (event) => {
    if (event.target instanceof Element && !event.target.closest('.ticket-contact-picker')) results.hidden = true;
  });
});

document.querySelectorAll('[data-contract-rich-editor]').forEach((form) => {
  if (!(form instanceof HTMLFormElement)) return;
  const editor = form.querySelector('[data-contract-editor]');
  const input = form.querySelector('[data-contract-editor-input]');
  const preview = form.querySelector('[data-contract-preview]');
  if (!(editor instanceof HTMLElement) || !(input instanceof HTMLTextAreaElement)) return;
  input.required = false;

  let savedRange = null;
  const saveSelection = () => {
    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0) return;
    const range = selection.getRangeAt(0);
    if (editor.contains(range.commonAncestorContainer)) savedRange = range.cloneRange();
  };
  const restoreSelection = () => {
    editor.focus();
    const selection = window.getSelection();
    if (!selection) return;
    selection.removeAllRanges();
    if (savedRange) selection.addRange(savedRange);
  };
  const sync = () => {
    input.value = editor.innerHTML.trim();
    if (preview instanceof HTMLElement) preview.innerHTML = editor.innerHTML;
    saveSelection();
  };

  editor.addEventListener('input', sync);
  editor.addEventListener('keyup', saveSelection);
  editor.addEventListener('mouseup', saveSelection);
  form.querySelectorAll('[data-editor-command]').forEach((button) => {
    if (!(button instanceof HTMLButtonElement)) return;
    button.addEventListener('mousedown', (event) => event.preventDefault());
    button.addEventListener('click', () => {
      restoreSelection();
      document.execCommand(button.dataset.editorCommand || '', false, button.dataset.editorValue || null);
      sync();
    });
  });
  form.querySelectorAll('[data-contract-variable]').forEach((button) => {
    if (!(button instanceof HTMLButtonElement)) return;
    button.addEventListener('mousedown', (event) => event.preventDefault());
    button.addEventListener('click', () => {
      restoreSelection();
      document.execCommand('insertText', false, button.dataset.contractVariable || '');
      sync();
    });
  });
  form.addEventListener('submit', sync);
  sync();
});

document.querySelectorAll('.color-field').forEach((group) => {
  const picker = group.querySelector('input[type="color"]');
  const text = group.querySelector('[data-color-text]');
  if (!(picker instanceof HTMLInputElement) || !(text instanceof HTMLInputElement)) return;
  picker.addEventListener('input', () => { text.value = picker.value; });
});

(() => {
  const tabs = Array.from(document.querySelectorAll('[data-catalog-tab]'));
  const panels = Array.from(document.querySelectorAll('[data-catalog-panel]'));
  const subtabs = Array.from(document.querySelectorAll('[data-catalog-subtab]'));
  const subpanels = Array.from(document.querySelectorAll('[data-catalog-subpanel]'));
  const catalogShell = document.querySelector('[data-catalog-content-provider]');
  const loadedContentProvider = catalogShell?.dataset.catalogContentProvider || '';
  if (tabs.length === 0) return;

  const openSection = (provider, requestedSection) => {
    const providerTabs = subtabs.filter((item) => item.dataset.provider === provider);
    if (providerTabs.length === 0) return;
    const legacySections = {
      access: 'connection',
      classes: 'connection',
      capabilities: 'connection',
      homologation: 'connection',
      courses: 'modules',
      contents: 'modules',
    };
    const normalizedSection = legacySections[requestedSection] || requestedSection;
    const section = providerTabs.some((item) => item.dataset.catalogSubtab === normalizedSection) ? normalizedSection : 'commercial';
    providerTabs.forEach((item) => {
      const active = item.dataset.catalogSubtab === section;
      item.classList.toggle('is-active', active);
      item.setAttribute('aria-selected', active ? 'true' : 'false');
      item.tabIndex = active ? 0 : -1;
    });
    const panelSections = (() => {
      if (section === 'connection') {
        return provider === 'ava_cursos'
          ? ['connection', 'access', 'classes', 'capabilities', 'queue']
          : ['connection', 'homologation', 'capabilities', 'queue'];
      }
      if (section === 'commercial') return ['commercial'];
      if (section === 'modules') {
        return provider !== 'iesde' && provider !== 'ava_cursos'
          ? ['modules', 'courses', 'contents']
          : ['modules', 'courses'];
      }
      return [section];
    })();
    subpanels
      .filter((panel) => (panel.dataset.catalogSubpanel || '').startsWith(`${provider}:`))
      .forEach((panel) => {
        const panelSection = (panel.dataset.catalogSubpanel || '').slice(provider.length + 1);
        panel.hidden = !panelSections.includes(panelSection);
      });
  };

  const showCatalog = (requestedName, updateUrl = true) => {
    const name = tabs.some((tab) => tab.dataset.catalogTab === requestedName)
      ? requestedName
      : (tabs[0]?.dataset.catalogTab || '');
    tabs.forEach((tab) => {
      const active = tab.dataset.catalogTab === name;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
      tab.tabIndex = active ? 0 : -1;
    });
    panels.forEach((panel) => { panel.hidden = panel.dataset.catalogPanel !== name; });
    if (updateUrl && history.replaceState) {
      const url = new URL(location.href);
      url.searchParams.set('catalog', name);
      history.replaceState(null, '', url);
    }
    return name;
  };

  tabs.forEach((tab) => tab.addEventListener('click', () => {
    const provider = showCatalog(tab.dataset.catalogTab || '');
    openSection(provider, 'commercial');
  }));
  subtabs.forEach((button) => button.addEventListener('click', () => {
    const provider = button.dataset.provider || '';
    const section = button.dataset.catalogSubtab || 'connection';
    if (section === 'connection' && provider !== loadedContentProvider) {
      const url = new URL(location.href);
      url.searchParams.set('catalog', provider);
      url.searchParams.set('section', 'connection');
      location.assign(url.toString());
      return;
    }
    if (section === 'modules' && !['iesde', 'ava_cursos'].includes(provider) && provider !== loadedContentProvider) {
      const url = new URL(location.href);
      url.searchParams.set('catalog', provider);
      url.searchParams.set('section', 'modules');
      url.searchParams.delete('content_page');
      location.assign(url.toString());
      return;
    }
    openSection(provider, section);
  }));

  const params = new URL(location.href).searchParams;
  const activeProvider = showCatalog(params.get('catalog') || tabs[0]?.dataset.catalogTab || '', false);
  openSection(activeProvider, params.get('section') || 'commercial');
})();

(() => {
  const selectAll = document.querySelector('[data-master-commercial-select-all]');
  if (!(selectAll instanceof HTMLInputElement)) return;
  selectAll.addEventListener('change', () => {
    document.querySelectorAll('[data-master-commercial-item]').forEach((item) => {
      if (item instanceof HTMLInputElement) item.checked = selectAll.checked;
    });
  });
})();

(() => {
  const rows = Array.from(document.querySelectorAll('.content-curation-row'));
  if (rows.length === 0) return;

  const close = (row) => {
    row.hidden = true;
    const trigger = document.querySelector(`[data-content-curation-toggle="${row.id}"]`);
    if (trigger) trigger.setAttribute('aria-expanded', 'false');
  };

  document.querySelectorAll('[data-content-curation-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const row = document.getElementById(button.dataset.contentCurationToggle || '');
      if (!row) return;
      const opening = row.hidden;
      rows.forEach(close);
      if (opening) {
        row.hidden = false;
        button.setAttribute('aria-expanded', 'true');
        row.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      }
    });
  });

  document.querySelectorAll('[data-content-curation-close]').forEach((button) => {
    button.addEventListener('click', () => {
      const row = document.getElementById(button.dataset.contentCurationClose || '');
      if (row) close(row);
    });
  });
})();

(() => {
  const rows = Array.from(document.querySelectorAll('.course-curation-row'));
  if (rows.length === 0) return;

  const close = (row) => {
    row.hidden = true;
    const trigger = document.querySelector(`[data-course-curation-toggle="${row.id}"]`);
    if (trigger) trigger.setAttribute('aria-expanded', 'false');
  };

  document.querySelectorAll('[data-course-curation-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const row = document.getElementById(button.dataset.courseCurationToggle || '');
      if (!row) return;
      const opening = row.hidden;
      rows.forEach(close);
      if (opening) {
        row.hidden = false;
        button.setAttribute('aria-expanded', 'true');
        row.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      }
    });
  });

  document.querySelectorAll('[data-course-curation-close]').forEach((button) => {
    button.addEventListener('click', () => {
      const row = document.getElementById(button.dataset.courseCurationClose || '');
      if (row) close(row);
    });
  });
})();

(() => {
  document.querySelectorAll('[data-master-ai-preview-form]').forEach((assistantForm) => {
    if (!(assistantForm instanceof HTMLFormElement)) return;
    const shell = assistantForm.closest('.course-curation-shell');
    const editor = shell?.querySelector('[data-master-course-curation-form]');
    if (!(editor instanceof HTMLFormElement)) return;

    const button = assistantForm.querySelector('button[type="submit"]');
    const feedback = assistantForm.querySelector('[data-master-ai-feedback]');
    const image = shell.querySelector('[data-master-ai-cover-image]');
    const placeholder = shell.querySelector('[data-master-ai-cover-placeholder]');
    const coverData = editor.querySelector('[data-master-ai-cover-data]');
    const coverPrompt = editor.querySelector('[data-master-ai-cover-prompt]');
    const originalButton = button?.innerHTML || '';
    const announce = (message, isError = false) => {
      if (!(feedback instanceof HTMLElement)) return;
      feedback.hidden = false;
      feedback.classList.toggle('is-error', isError);
      feedback.textContent = message;
    };

    assistantForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (button instanceof HTMLButtonElement) {
        button.disabled = true;
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Gerando prévia...';
      }
      announce('Gerando os textos e a capa para sua revisão...');
      try {
        const response = await fetch(assistantForm.action, {
          method: 'POST',
          body: new FormData(assistantForm),
          credentials: 'same-origin',
          headers: { Accept: 'application/json' },
        });
        const payload = await response.json().catch(() => ({ ok: false, error: 'O servidor retornou uma resposta inválida.' }));
        if (!response.ok || payload.ok !== true) throw new Error(payload.error || 'Não foi possível gerar a prévia.');

        const summary = editor.elements.namedItem('commercial_summary');
        const description = editor.elements.namedItem('commercial_description');
        if (summary instanceof HTMLInputElement) summary.value = payload.short_description || '';
        if (description instanceof HTMLTextAreaElement) description.value = payload.description || '';
        if (coverData instanceof HTMLInputElement) coverData.value = payload.image_data || '';
        if (coverPrompt instanceof HTMLInputElement) coverPrompt.value = payload.prompt || '';
        if (image instanceof HTMLImageElement && payload.image_data) {
          image.src = payload.image_data;
          image.hidden = false;
          if (placeholder instanceof HTMLElement) placeholder.hidden = true;
        }
        announce('Prévia preenchida. Revise os textos e a capa; nada foi salvo.');
        summary?.scrollIntoView({ block: 'center', behavior: 'smooth' });
      } catch (error) {
        announce(error instanceof Error ? error.message : 'Não foi possível gerar a prévia.', true);
      } finally {
        if (button instanceof HTMLButtonElement) {
          button.disabled = false;
          button.innerHTML = originalButton;
        }
      }
    });
  });
})();

document.querySelectorAll('[data-master-course-detail]').forEach((detail) => {
  const tabs = Array.from(detail.querySelectorAll('[data-master-course-section]'));
  const panels = Array.from(detail.querySelectorAll('[data-master-course-panel]'));
  const show = (requested) => {
    const section = tabs.some((tab) => tab.dataset.masterCourseSection === requested) ? requested : 'curation';
    tabs.forEach((tab) => {
      const active = tab.dataset.masterCourseSection === section;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    panels.forEach((panel) => { panel.hidden = panel.dataset.masterCoursePanel !== section; });
  };
  tabs.forEach((tab) => tab.addEventListener('click', () => show(tab.dataset.masterCourseSection || 'curation')));
  show('curation');
});

(() => {
  const tabs = Array.from(document.querySelectorAll('[data-site-tab]'));
  const panels = Array.from(document.querySelectorAll('[data-site-panel]'));
  const savebar = document.querySelector('[data-site-savebar]');
  const activeInput = document.querySelector('[data-site-active-tab]');
  if (tabs.length === 0) return;

  const validTabs = tabs.map((tab) => tab.dataset.siteTab || '');
  const show = (requestedName, updateHash = true) => {
    const legacyGroups = { publicacao: 'geral', 'pagina-inicial': 'geral', buscadores: 'geral', contato: 'comunicacao', links: 'comunicacao', whatsapp: 'comunicacao', blocos: 'conteudo', banners: 'conteudo', paginas: 'conteudo' };
    const normalizedName = legacyGroups[requestedName] || requestedName;
    const name = validTabs.includes(normalizedName) ? normalizedName : 'geral';
    const activeTab = tabs.find((tab) => tab.dataset.siteTab === name);
    const targets = new Set((activeTab?.dataset.siteTargets || name).split(',').filter(Boolean));
    tabs.forEach((tab) => {
      const active = tab.dataset.siteTab === name;
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
      tab.tabIndex = active ? 0 : -1;
    });
    panels.forEach((panel) => { panel.hidden = !targets.has(panel.dataset.sitePanel || ''); });
    if (savebar instanceof HTMLElement) savebar.hidden = !['geral', 'cursos', 'comunicacao', 'bolsas'].includes(name);
    if (activeInput instanceof HTMLInputElement) activeInput.value = name;
    if (updateHash && history.replaceState) history.replaceState(null, '', `#${name}`);
  };

  tabs.forEach((tab) => tab.addEventListener('click', () => show(tab.dataset.siteTab || 'geral')));
  window.addEventListener('hashchange', () => show(location.hash.replace('#', ''), false));
  show(location.hash.replace('#', '') || 'geral', false);
})();

(() => {
  const tabs = Array.from(document.querySelectorAll('[data-organization-tab]'));
  const panels = Array.from(document.querySelectorAll('[data-organization-panel]'));
  const form = document.querySelector('.organization-editor-form');
  const savebar = document.querySelector('[data-organization-savebar]');
  if (tabs.length === 0) return;

  const show = (requestedName, updateHash) => {
    const name = tabs.some((tab) => tab.dataset.organizationTab === requestedName) ? requestedName : 'dados';
    tabs.forEach((tab) => {
      const active = tab.dataset.organizationTab === name;
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
      tab.tabIndex = active ? 0 : -1;
    });
    panels.forEach((panel) => { panel.hidden = panel.dataset.organizationPanel !== name; });
    if (savebar instanceof HTMLElement) savebar.hidden = ['contrato', 'documentos', 'ava', 'polos', 'site', 'integracoes'].includes(name);
    if (updateHash && history.replaceState) history.replaceState(null, '', `#${name}`);
  };

  tabs.forEach((tab) => tab.addEventListener('click', () => show(tab.dataset.organizationTab || 'dados', true)));
  if (form instanceof HTMLFormElement) {
    form.addEventListener('submit', (event) => {
      if (form.checkValidity()) return;
      event.preventDefault();
      const invalid = form.querySelector(':invalid');
      const panel = invalid instanceof Element ? invalid.closest('[data-organization-panel]') : null;
      if (panel instanceof HTMLElement) show(panel.dataset.organizationPanel || 'dados', true);
      setTimeout(() => {
        if (!(invalid instanceof HTMLElement)) return;
        invalid.focus();
        form.reportValidity();
      }, 0);
    });
  }
  show(location.hash.replace('#', '') || 'dados', false);
})();

(() => {
  const modes = Array.from(document.querySelectorAll('input[name="account_mode"]'));
  const settings = document.querySelector('[data-exclusive-asaas-settings]');
  if (modes.length === 0 || !(settings instanceof HTMLElement)) return;

  const refresh = () => {
    const exclusive = modes.some((input) => input instanceof HTMLInputElement && input.checked && input.value === 'exclusive');
    settings.hidden = !exclusive;
    modes.forEach((input) => input.closest('.integration-mode')?.classList.toggle('selected', input instanceof HTMLInputElement && input.checked));
  };

  modes.forEach((input) => input.addEventListener('change', refresh));
  refresh();
})();

(() => {
  const form = document.querySelector('[data-document-upload-form]');
  if (!(form instanceof HTMLFormElement)) return;
  const replace = form.querySelector('[data-document-replace-id]');
  const category = form.querySelector('[data-document-category]');
  const title = form.querySelector('[data-document-upload-title]');
  const help = form.querySelector('[data-document-upload-help]');
  const cancel = form.querySelector('[data-document-version-cancel]');
  if (!(replace instanceof HTMLInputElement) || !(category instanceof HTMLSelectElement) || !(title instanceof HTMLElement) || !(help instanceof HTMLElement) || !(cancel instanceof HTMLButtonElement)) return;

  const reset = () => {
    replace.value = '';
    title.textContent = 'Novo documento';
    help.textContent = 'Selecione o tipo e o arquivo que deseja anexar.';
    cancel.hidden = true;
  };
  document.querySelectorAll('[data-document-version]').forEach((button) => {
    if (!(button instanceof HTMLButtonElement)) return;
    button.addEventListener('click', () => {
      replace.value = button.dataset.documentId || '';
      category.value = button.dataset.documentCategory || '';
      title.textContent = `Nova versão de ${button.dataset.documentName || 'documento'}`;
      help.textContent = 'O arquivo atual será preservado no histórico.';
      cancel.hidden = false;
      form.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  });
  cancel.addEventListener('click', reset);
})();

(() => {
  const product = document.querySelector('[data-enrollment-product]');
  const ava = document.querySelector('[data-enrollment-ava]');
  const source = document.querySelector('[data-enrollment-ava-options]');
  if (!(product instanceof HTMLSelectElement) || !(ava instanceof HTMLSelectElement) || !(source instanceof HTMLScriptElement)) return;
  let destinations = {};
  try { destinations = JSON.parse(source.textContent || '{}'); } catch (_) { destinations = {}; }
  const refresh = () => {
    const options = Array.isArray(destinations[product.value]) ? destinations[product.value] : [];
    ava.replaceChildren();
    if (options.length === 0) {
      ava.append(new Option(product.value ? 'Curso sem AVA sincronizado' : 'Escolha primeiro o curso contratado', ''));
      ava.disabled = true;
      return;
    }
    ava.append(new Option('Selecione o AVA', ''));
    options.forEach((destination) => {
      const option = new Option(`${destination.name} · ${destination.remote_course_name}`, String(destination.connection_id));
      option.selected = destination.primary === true;
      ava.append(option);
    });
    ava.disabled = false;
  };
  product.addEventListener('change', refresh);
  refresh();
})();

(() => {
  document.querySelectorAll('[data-waiver-unit]').forEach((unit) => {
    if (!(unit instanceof HTMLSelectElement)) return;
    const form = unit.closest('form');
    const student = form?.querySelector('[data-waiver-student]');
    if (!(student instanceof HTMLSelectElement)) return;

    const refresh = () => {
      const unitId = unit.value;
      student.value = '';
      student.disabled = unitId === '';
      Array.from(student.options).forEach((option) => {
        if (!option.dataset.unitId) return;
        option.hidden = option.dataset.unitId !== unitId;
      });
      student.options[0].textContent = unitId === '' ? 'Escolha primeiro a unidade' : 'Selecione o aluno';
    };

    unit.addEventListener('change', refresh);
    refresh();
  });
})();

(() => {
  const form = document.querySelector('[data-trail-editor-form]');
  if (!(form instanceof HTMLFormElement)) return;
  const name = form.elements.namedItem('name');
  const slug = form.elements.namedItem('slug');
  if (!(name instanceof HTMLInputElement) || !(slug instanceof HTMLInputElement)) return;

  const slugify = (value) => String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
  let generated = slug.value.trim() === '';
  let lastGenerated = generated ? '' : slug.value.trim();

  name.addEventListener('input', () => {
    if (!generated && slug.value.trim() !== lastGenerated) return;
    lastGenerated = slugify(name.value);
    slug.value = lastGenerated;
    generated = true;
  });
  slug.addEventListener('input', () => {
    generated = slug.value.trim() === '' || slug.value.trim() === lastGenerated;
  });
  form.addEventListener('submit', () => {
    if (slug.value.trim() === '') slug.value = slugify(name.value);
  });
})();

(() => {
  document.querySelectorAll('[data-trail-item-grid]').forEach((grid) => {
    if (!(grid instanceof HTMLElement) || grid.dataset.trailPickerReady === '1') return;
    grid.dataset.trailPickerReady = '1';

    const picker = grid.closest('.item-picker');
    const search = picker?.querySelector('[data-trail-item-search]');
    const catalog = picker?.querySelector('[data-trail-catalog-filter]');
    const modeButtons = Array.from(picker?.querySelectorAll('[data-trail-filter-mode]') || []);
    const packageFilter = picker?.querySelector('[data-trail-package-filter]');
    const packageField = picker?.querySelector('[data-trail-package-field]');
    const packageResults = picker?.querySelector('[data-trail-package-results]');
    const searchLabel = picker?.querySelector('[data-trail-search-label]');
    const packageNote = picker?.querySelector('[data-trail-package-note]');
    const packageActions = picker?.querySelector('[data-trail-package-actions]');
    const selectVisible = picker?.querySelector('[data-trail-select-visible]');
    const clearVisible = picker?.querySelector('[data-trail-clear-visible]');
    const selectedOnly = picker?.querySelector('[data-trail-selected-only]');
    const selectedCount = picker?.querySelector('[data-trail-selection-count]');
    const visibleCount = picker?.querySelector('[data-trail-visible-count]');
    const items = Array.from(grid.querySelectorAll('[data-trail-item]'));
    const normalize = (value) => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    const packages = packageFilter instanceof HTMLSelectElement
      ? Array.from(packageFilter.options).filter((option) => option.value !== '').map((option) => ({
        value: option.value,
        label: option.textContent?.trim() || '',
        catalog: option.parentElement instanceof HTMLOptGroupElement ? option.parentElement.label : ''
      }))
      : [];
    let filterMode = 'items';

    const apply = () => {
      const term = filterMode === 'items' ? normalize(search instanceof HTMLInputElement ? search.value : '') : '';
      const selectedCatalog = catalog instanceof HTMLSelectElement ? catalog.value : '';
      const selectedPackage = filterMode === 'packages' && packageFilter instanceof HTMLSelectElement ? packageFilter.value : '';
      const onlySelected = selectedOnly instanceof HTMLInputElement && selectedOnly.checked;
      let visible = 0;
      let selected = 0;

      items.forEach((item) => {
        if (!(item instanceof HTMLElement)) return;
        const checkbox = item.querySelector('input[type="checkbox"]');
        const checked = checkbox instanceof HTMLInputElement && checkbox.checked;
        if (checked) selected += 1;
        const matchesText = term === '' || normalize(item.dataset.trailItem).includes(term);
        const matchesCatalog = selectedCatalog === '' || item.dataset.trailCatalog === selectedCatalog;
        const matchesPackage = filterMode !== 'packages' || (selectedPackage !== '' && String(item.dataset.trailPackages || '').includes(`,${selectedPackage},`));
        item.hidden = !(matchesText && matchesCatalog && matchesPackage && (!onlySelected || checked));
        if (!item.hidden) visible += 1;
      });

      if (selectedCount instanceof HTMLElement) selectedCount.textContent = String(selected);
      if (visibleCount instanceof HTMLElement) visibleCount.textContent = String(visible);
    };

    const renderPackageResults = () => {
      if (!(packageResults instanceof HTMLElement)) return;
      const query = filterMode === 'packages' && search instanceof HTMLInputElement ? normalize(search.value.trim()) : '';
      if (query === '') {
        packageResults.hidden = true;
        packageResults.replaceChildren();
        return;
      }

      const selectedCatalog = catalog instanceof HTMLSelectElement ? catalog.value : '';
      const matches = packages.filter((item) => normalize(item.label).includes(query) && (selectedCatalog === '' || item.catalog === selectedCatalog));
      const heading = document.createElement('strong');
      heading.textContent = matches.length === 1 ? '1 Trilha encontrada' : `${matches.length} Trilhas encontradas`;
      const options = document.createElement('div');
      options.className = 'package-search-options';

      matches.slice(0, 12).forEach((item) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'package-search-option';
        button.classList.toggle('is-active', packageFilter instanceof HTMLSelectElement && packageFilter.value === item.value);
        const name = document.createElement('span');
        name.textContent = item.label;
        const source = document.createElement('small');
        source.textContent = item.catalog;
        button.append(name, source);
        button.addEventListener('click', () => {
          if (packageFilter instanceof HTMLSelectElement) packageFilter.value = item.value;
          apply();
          renderPackageResults();
        });
        options.append(button);
      });

      packageResults.replaceChildren(heading);
      if (matches.length === 0) {
        const empty = document.createElement('p');
        empty.className = 'package-search-empty';
        empty.textContent = 'Nenhuma Trilha do fornecedor corresponde à pesquisa.';
        packageResults.append(empty);
      } else {
        packageResults.append(options);
      }
      packageResults.hidden = false;
    };

    const setFilterMode = (mode) => {
      const nextMode = mode === 'packages' ? 'packages' : 'items';
      if (filterMode !== nextMode && search instanceof HTMLInputElement) search.value = '';
      filterMode = nextMode;
      modeButtons.forEach((button) => {
        if (!(button instanceof HTMLButtonElement)) return;
        const active = button.dataset.trailFilterMode === filterMode;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
      if (packageField instanceof HTMLElement) packageField.hidden = filterMode !== 'packages';
      if (packageNote instanceof HTMLElement) packageNote.hidden = filterMode !== 'packages';
      if (packageActions instanceof HTMLElement) packageActions.hidden = filterMode !== 'packages';
      if (searchLabel instanceof HTMLElement) searchLabel.textContent = filterMode === 'packages' ? 'Pesquisar Trilha do fornecedor' : 'Pesquisar Curso individual';
      if (search instanceof HTMLInputElement) search.placeholder = filterMode === 'packages' ? 'Digite parte do nome da Trilha' : 'Digite parte do nome do curso';
      renderPackageResults();
      apply();
    };

    const setVisibleSelection = (checked) => {
      items.forEach((item) => {
        if (!(item instanceof HTMLElement) || item.hidden) return;
        const checkbox = item.querySelector('input[type="checkbox"]');
        if (!(checkbox instanceof HTMLInputElement) || checkbox.checked === checked) return;
        checkbox.checked = checked;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
      });
      apply();
    };

    search?.addEventListener('input', () => { renderPackageResults(); apply(); });
    catalog?.addEventListener('change', () => { renderPackageResults(); apply(); });
    packageFilter?.addEventListener('change', () => { renderPackageResults(); apply(); });
    modeButtons.forEach((button) => button.addEventListener('click', () => setFilterMode(button.dataset.trailFilterMode)));
    selectVisible?.addEventListener('click', () => setVisibleSelection(true));
    clearVisible?.addEventListener('click', () => setVisibleSelection(false));
    selectedOnly?.addEventListener('change', apply);
    items.forEach((item) => item.querySelector('input[type="checkbox"]')?.addEventListener('change', apply));
    const form = grid.closest('form');
    form?.addEventListener('submit', (event) => {
      const selected = items.filter((item) => item.querySelector('input[type="checkbox"]:checked')).length;
      if (selected >= 2) return;
      event.preventDefault();
      let feedback = picker?.querySelector('[data-trail-picker-validation]');
      if (!(feedback instanceof HTMLElement)) {
        feedback = document.createElement('div');
        feedback.className = 'alert alert-danger';
        feedback.dataset.trailPickerValidation = '1';
        picker?.prepend(feedback);
      }
      feedback.textContent = 'Selecione pelo menos dois Cursos individuais. Tudo o que você já preencheu foi mantido.';
      feedback.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
    setFilterMode('items');
  });
})();

(() => {
  const assistant = document.querySelector('[data-trail-ai]');
  const form = document.querySelector('[data-trail-editor-form]');
  if (!(assistant instanceof HTMLElement) || !(form instanceof HTMLFormElement)) return;

  const textButton = assistant.querySelector('[data-trail-ai-text]');
  const coverButton = assistant.querySelector('[data-trail-ai-cover]');
  const textGuidance = assistant.querySelector('[data-trail-ai-text-guidance]');
  const coverGuidance = assistant.querySelector('[data-trail-ai-cover-guidance]');
  const feedback = assistant.querySelector('[data-trail-ai-feedback]');
  const coverData = form.querySelector('[data-trail-ai-cover-data]');
  const coverPrompt = form.querySelector('[data-trail-ai-cover-prompt]');
  const coverPreview = form.querySelector('[data-trail-cover-preview]');
  const coverImage = form.querySelector('[data-trail-cover-image]');
  const coverPlaceholder = form.querySelector('[data-trail-cover-placeholder]');
  const coverLabel = form.querySelector('[data-trail-cover-label]');
  const coverNote = form.querySelector('[data-trail-cover-note]');

  const announce = (message, error = false) => {
    if (!(feedback instanceof HTMLElement)) return;
    feedback.hidden = false;
    feedback.classList.toggle('is-error', error);
    const icon = document.createElement('i');
    icon.className = `fa-solid ${error ? 'fa-triangle-exclamation' : 'fa-circle-check'}`;
    const text = document.createElement('span');
    text.textContent = String(message);
    feedback.replaceChildren(icon, text);
  };

  const request = async (url, additions, button, loadingLabel) => {
    if (!(button instanceof HTMLButtonElement) || !url) return null;
    const original = button.innerHTML;
    button.disabled = true;
    button.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${loadingLabel}`;
    try {
      const payload = new FormData(form);
      Object.entries(additions).forEach(([key, value]) => payload.set(key, value));
      const response = await fetch(url, { method: 'POST', body: payload, headers: { Accept: 'application/json' } });
      const result = await response.json();
      if (!response.ok || result.ok !== true) throw new Error(result.error || 'Não foi possível gerar a prévia.');
      return result;
    } catch (error) {
      announce(error instanceof Error ? error.message : 'Não foi possível gerar a prévia.', true);
      return null;
    } finally {
      button.disabled = false;
      button.innerHTML = original;
    }
  };

  textButton?.addEventListener('click', async () => {
    const result = await request(
      assistant.dataset.textUrl || '',
      { ai_text_guidance: textGuidance instanceof HTMLInputElement ? textGuidance.value : '' },
      textButton,
      'Gerando textos...'
    );
    if (!result) return;
    const shortDescription = form.elements.namedItem('short_description');
    const description = form.elements.namedItem('description');
    if (shortDescription instanceof HTMLTextAreaElement) shortDescription.value = String(result.short_description || '');
    if (description instanceof HTMLTextAreaElement) description.value = String(result.description || '');
    announce('Textos gerados e exibidos para revisão. Salve somente quando estiverem aprovados.');
  });

  coverButton?.addEventListener('click', async () => {
    coverPreview?.classList.add('is-generating');
    const result = await request(
      assistant.dataset.coverUrl || '',
      { ai_cover_guidance: coverGuidance instanceof HTMLInputElement ? coverGuidance.value : '' },
      coverButton,
      'Gerando capa...'
    );
    coverPreview?.classList.remove('is-generating');
    if (!result) return;
    const imageData = String(result.image_data || '');
    if (coverData instanceof HTMLInputElement) coverData.value = imageData;
    if (coverPrompt instanceof HTMLInputElement) coverPrompt.value = String(result.prompt || '');
    if (coverImage instanceof HTMLImageElement) { coverImage.src = imageData; coverImage.hidden = false; }
    if (coverPlaceholder instanceof HTMLElement) coverPlaceholder.hidden = true;
    if (coverLabel instanceof HTMLElement) coverLabel.textContent = 'Prévia gerada com IA';
    if (coverNote instanceof HTMLElement) coverNote.textContent = 'Esta imagem será otimizada no Spaces somente quando você salvar a Trilha.';
    announce('Capa gerada e exibida para aprovação. Você ainda pode gerar outra antes de salvar.');
  });
})();
