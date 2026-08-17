(() => {
  const body = document.body;
  const organization = body.dataset.siteOrganization || '0';
  const eventUrl = body.dataset.siteEventUrl || '';
  const consentKey = `site-metrics-consent-${organization}`;
  const attributionKey = `site-attribution-${organization}`;
  const sessionKey = `site-commercial-session-${organization}`;
  const query = new URLSearchParams(location.search);
  const savedAttribution = (() => {
    try { return JSON.parse(sessionStorage.getItem(attributionKey) || '{}'); } catch { return {}; }
  })();
  const attribution = {
    landing_page: savedAttribution.landing_page || `${location.pathname}${location.search}`,
    utm_source: query.get('utm_source') || savedAttribution.utm_source || '',
    utm_medium: query.get('utm_medium') || savedAttribution.utm_medium || '',
    utm_campaign: query.get('utm_campaign') || savedAttribution.utm_campaign || '',
    utm_content: query.get('utm_content') || savedAttribution.utm_content || '',
    utm_term: query.get('utm_term') || savedAttribution.utm_term || '',
  };
  try { sessionStorage.setItem(attributionKey, JSON.stringify(attribution)); } catch {}
  const siteSessionId = (() => {
    try {
      let value = sessionStorage.getItem(sessionKey) || '';
      if (!value) {
        value = globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(36).slice(2)}`;
        sessionStorage.setItem(sessionKey, value);
      }
      return value;
    } catch { return ''; }
  })();

  document.querySelectorAll('form[action*="/site/"]').forEach((form) => {
    Object.entries({ ...attribution, site_session_id: siteSessionId }).forEach(([name, value]) => {
      if (!value || form.querySelector(`[name="${name}"]`)) return;
      const field = document.createElement('input');
      field.type = 'hidden';
      field.name = name;
      field.value = value;
      form.append(field);
    });
  });

  const metricsAllowed = () => localStorage.getItem(consentKey) === 'accepted';
  const track = (eventType, options = {}) => {
    if (!eventUrl || !metricsAllowed()) return;
    const data = new FormData();
    data.set('event_type', eventType);
    data.set('page_path', `${location.pathname}${location.search}`.slice(0, 500));
    data.set('entity_type', options.entityType || body.dataset.siteEntityType || 'site');
    data.set('entity_id', String(options.entityId || body.dataset.siteEntityId || ''));
    data.set('utm_source', attribution.utm_source);
    data.set('utm_medium', attribution.utm_medium);
    data.set('utm_campaign', attribution.utm_campaign);
    data.set('utm_content', attribution.utm_content);
    data.set('utm_term', attribution.utm_term);
    data.set('landing_page', attribution.landing_page);
    data.set('site_session_id', siteSessionId);
    navigator.sendBeacon?.(eventUrl, data) || fetch(eventUrl, { method: 'POST', body: data, credentials: 'same-origin', keepalive: true }).catch(() => {});
  };

  const cookieBanner = document.querySelector('[data-cookie-banner]');
  const consent = localStorage.getItem(consentKey);
  if (cookieBanner instanceof HTMLElement && !consent) cookieBanner.hidden = false;
  document.querySelectorAll('[data-cookie-accept]').forEach((button) => button.addEventListener('click', () => {
    localStorage.setItem(consentKey, 'accepted');
    if (cookieBanner instanceof HTMLElement) cookieBanner.hidden = true;
    track(body.dataset.siteEventType || 'page_view');
  }));
  document.querySelectorAll('[data-cookie-essential]').forEach((button) => button.addEventListener('click', () => {
    localStorage.setItem(consentKey, 'essential');
    if (cookieBanner instanceof HTMLElement) cookieBanner.hidden = true;
  }));
  if (metricsAllowed()) track(body.dataset.siteEventType || 'page_view');

  document.querySelectorAll('[data-site-offer],a[href*="/site/curso/"]').forEach((link) => link.addEventListener('click', () => {
    const match = link.getAttribute('href')?.match(/\/site\/curso\/(\d+)/);
    track('course_click', { entityType: link.dataset.offerKind || 'course', entityId: link.dataset.offerId || match?.[1] || '' });
  }));
  document.querySelectorAll('a[href*="wa.me"]').forEach((link) => link.addEventListener('click', () => track('whatsapp_click')));
  document.querySelectorAll('form[action$="/contato"]').forEach((form) => form.addEventListener('submit', () => track('contact_submit')));
  document.querySelectorAll('form[action$="/bolsas"]').forEach((form) => form.addEventListener('submit', () => track('scholarship_submit')));
  document.querySelectorAll('a[href*="/site/checkout/"]').forEach((link) => link.addEventListener('click', () => track('checkout_start', { entityType: 'course' })));

  const catalogSearch = document.querySelector('[data-catalog-search]');
  const catalogFormation = document.querySelector('[data-catalog-formation]');
  const catalogCategory = document.querySelector('[data-catalog-category]');
  const catalogSort = document.querySelector('[data-catalog-sort]');
  const catalogSubmit = document.querySelector('[data-catalog-submit]');
  const catalogGrid = document.querySelector('[data-course-grid]');
  const catalogCards = Array.from(document.querySelectorAll('[data-course-card]'));
  const catalogEmpty = document.querySelector('[data-catalog-empty]');
  const favoritesKey = `site-course-favorites-${organization}`;
  const normalizeCatalog = (value) => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
  const readFavorites = () => { try { return new Set(JSON.parse(localStorage.getItem(favoritesKey) || '[]').map(String)); } catch { return new Set(); } };
  let favorites = readFavorites();
  const syncFavoriteButtons = () => catalogCards.forEach((card) => { const button = card.querySelector('[data-course-favorite]'); const active = favorites.has(String(card.dataset.courseId || '')); if (button) { button.setAttribute('aria-pressed', active ? 'true' : 'false'); button.title = active ? 'Remover dos favoritos' : 'Adicionar aos favoritos'; const icon = button.querySelector('i'); if (icon) icon.className = active ? 'fa-solid fa-heart' : 'fa-regular fa-heart'; } });
  const catalogBatch = 24;
  let catalogVisible = catalogBatch;
  const catalogMore = document.querySelector('[data-catalog-more]');
  const filterCatalog = () => {
    if (!(catalogGrid instanceof HTMLElement)) return;
    const term = normalizeCatalog(catalogSearch?.value || '');
    const formation = normalizeCatalog(catalogFormation?.value || '');
    const category = normalizeCatalog(catalogCategory?.value || '');
    const sort = catalogSort?.value || 'featured';
    const matching = catalogCards.filter((card) => {
      const searchable = normalizeCatalog(card.textContent || '');
      return (!term || searchable.includes(term)) && (!formation || normalizeCatalog(card.dataset.courseFormation) === formation) && (!category || normalizeCatalog(card.dataset.courseCategory) === category) && (sort !== 'favorites' || favorites.has(String(card.dataset.courseId || '')));
    });
    const sorted = [...matching].sort((a, b) => {
      if (sort === 'name') return String(a.dataset.courseName).localeCompare(String(b.dataset.courseName), 'pt-BR');
      if (sort === 'price-asc' || sort === 'price-desc') return (Number(a.dataset.coursePrice) - Number(b.dataset.coursePrice)) * (sort === 'price-desc' ? -1 : 1);
      return 0;
    });
    sorted.forEach((card) => catalogGrid.append(card));
    const shown = new Set(sorted.slice(0, catalogVisible));
    catalogCards.forEach((card) => { card.hidden = !shown.has(card); });
    if (catalogEmpty instanceof HTMLElement) catalogEmpty.hidden = matching.length !== 0;
    if (catalogMore instanceof HTMLButtonElement) catalogMore.hidden = matching.length <= catalogVisible;
  };
  catalogCards.forEach((card) => card.querySelector('[data-course-favorite]')?.addEventListener('click', () => { const id = String(card.dataset.courseId || ''); favorites.has(id) ? favorites.delete(id) : favorites.add(id); localStorage.setItem(favoritesKey, JSON.stringify([...favorites])); syncFavoriteButtons(); filterCatalog(); }));
  const catalogPills = Array.from(document.querySelectorAll('[data-category-pill]'));
  const syncPills = () => {
    const active = normalizeCatalog(catalogCategory?.value || '');
    catalogPills.forEach((pill) => { pill.setAttribute('aria-pressed', normalizeCatalog(pill.dataset.category || '') === active ? 'true' : 'false'); });
  };
  const syncUrl = () => {
    const params = new URLSearchParams(location.search);
    const values = { q: catalogSearch?.value || '', formacao: catalogFormation?.value || '', categoria: catalogCategory?.value || '', ordenar: catalogSort?.value || '' };
    Object.entries(values).forEach(([key, value]) => {
      if (value && value !== 'featured') params.set(key, value);
      else params.delete(key);
    });
    const search = params.toString();
    history.replaceState(null, '', `${location.pathname}${search ? `?${search}` : ''}${location.hash}`);
  };
  const applyCatalogInit = () => {
    let changed = false;
    if (body.dataset.catalogInitQ && catalogSearch instanceof HTMLInputElement) { catalogSearch.value = body.dataset.catalogInitQ; changed = true; }
    if (body.dataset.catalogInitFormacao && catalogFormation instanceof HTMLSelectElement) { catalogFormation.value = body.dataset.catalogInitFormacao; changed = true; }
    if (body.dataset.catalogInitCategoria && catalogCategory instanceof HTMLSelectElement) { catalogCategory.value = body.dataset.catalogInitCategoria; changed = true; }
    if (body.dataset.catalogInitSort && catalogSort instanceof HTMLSelectElement) { catalogSort.value = body.dataset.catalogInitSort; changed = true; }
    if (changed) { catalogVisible = catalogBatch; }
  };
  [catalogSearch, catalogFormation, catalogCategory, catalogSort].forEach((field) => field?.addEventListener('input', () => { catalogVisible = catalogBatch; filterCatalog(); syncPills(); syncUrl(); }));
  catalogSubmit?.addEventListener('click', () => {
    catalogVisible = catalogBatch;
    filterCatalog();
    syncPills();
    syncUrl();
    catalogGrid?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
  catalogMore?.addEventListener('click', () => { catalogVisible += catalogBatch; filterCatalog(); });
  catalogPills.forEach((pill) => pill.addEventListener('click', () => {
    const value = normalizeCatalog(pill.dataset.category || '');
    const current = normalizeCatalog(catalogCategory?.value || '');
    if (catalogCategory instanceof HTMLSelectElement) catalogCategory.value = current === value ? '' : value;
    catalogVisible = catalogBatch;
    filterCatalog();
    syncPills();
    syncUrl();
    catalogGrid?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }));
  catalogSearch?.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter') return;
    event.preventDefault();
    catalogSubmit?.click();
  });
  syncFavoriteButtons();
  applyCatalogInit();
  filterCatalog();
  syncPills();

  const searchDialog = document.querySelector('#site-search-dialog');
  const searchInput = searchDialog?.querySelector('[data-site-search-input]');
  const items = Array.from(searchDialog?.querySelectorAll('[data-site-search-item]') || []);
  const empty = searchDialog?.querySelector('[data-site-search-empty]');
  if (searchDialog instanceof HTMLDialogElement && searchInput instanceof HTMLInputElement) {
    const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    const filter = () => {
      const value = normalize(searchInput.value.trim());
      let visible = 0;
      items.forEach((item) => { const match = value === '' || normalize(item.textContent || '').includes(value); item.hidden = !match; if (match) visible += 1; });
      if (empty instanceof HTMLElement) empty.hidden = visible !== 0;
    };
    document.querySelectorAll('[data-site-search-open]').forEach((button) => button.addEventListener('click', () => { if (!searchDialog.open) searchDialog.showModal(); window.setTimeout(() => searchInput.focus(), 0); }));
    document.querySelectorAll('[data-site-search-close]').forEach((button) => button.addEventListener('click', () => searchDialog.close()));
    searchDialog.addEventListener('click', (event) => { if (event.target === searchDialog) searchDialog.close(); });
    searchInput.addEventListener('input', filter);
    filter();
  }

  document.querySelectorAll('[data-legal-open]').forEach((button) => button.addEventListener('click', () => {
    const target = document.querySelector(`#${button.dataset.legalOpen}`);
    if (target instanceof HTMLDialogElement && !target.open) target.showModal();
  }));
  document.querySelectorAll('[data-legal-close]').forEach((button) => button.addEventListener('click', () => button.closest('dialog')?.close()));

  const scholarship = document.querySelector('#scholarship-dialog');
  if (scholarship instanceof HTMLDialogElement) {
    const open = () => { if (!scholarship.open) scholarship.showModal(); };
    document.querySelectorAll('[data-scholarship-open]').forEach((button) => button.addEventListener('click', open));
    document.querySelectorAll('[data-scholarship-close]').forEach((button) => button.addEventListener('click', () => scholarship.close()));
    scholarship.addEventListener('click', (event) => { if (event.target === scholarship) scholarship.close(); });
    if (query.has('bolsas')) open();
    else if (body.dataset.scholarshipPopup === '1') {
      const storageKey = body.dataset.scholarshipKey || 'site-scholarship-v2';
      const repeat = Math.max(1, Number(body.dataset.scholarshipRepeat || 24)) * 60 * 60 * 1000;
      const delay = Math.max(5, Number(body.dataset.scholarshipDelay || 15)) * 1000;
      const last = Number(localStorage.getItem(storageKey) || 0);
      if (Date.now() - last > repeat) window.setTimeout(() => { open(); localStorage.setItem(storageKey, String(Date.now())); }, delay);
    }
  }

  const ga4Id = body.dataset.siteGa4 || '';
  if (ga4Id) {
    const loadGa4 = () => {
      if (document.querySelector('script[data-site-ga4-script]')) return;
      window.dataLayer = window.dataLayer || [];
      window.gtag = function gtag() { window.dataLayer.push(arguments); };
      window.gtag('js', new Date());
      window.gtag('config', ga4Id);
      const script = document.createElement('script');
      script.dataset.siteGa4Script = '1';
      script.async = true;
      script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(ga4Id)}`;
      document.head.append(script);
    };
    if (metricsAllowed()) loadGa4();
    document.querySelectorAll('[data-cookie-accept]').forEach((button) => button.addEventListener('click', loadGa4));
  }

  document.querySelectorAll('input[name="phone"]').forEach((input) => {
    input.addEventListener('input', () => {
      const digits = input.value.replace(/\D/g, '').slice(0, 11);
      if (digits.length === 0) { input.value = ''; return; }
      input.value = digits.length <= 2 ? `(${digits}` : digits.length <= 7 ? `(${digits.slice(0, 2)}) ${digits.slice(2)}` : `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
    });
  });

  const desiredSelect = document.querySelector('[data-desired-course]');
  const desiredOther = document.querySelector('[data-desired-course-other]');
  if (desiredSelect instanceof HTMLSelectElement && desiredOther instanceof HTMLInputElement) {
    const syncDesiredOther = () => {
      const show = desiredSelect.value === '__outro__';
      desiredOther.hidden = !show;
      desiredOther.required = show;
    };
    desiredSelect.addEventListener('change', syncDesiredOther);
    const prefill = (query.get('curso') || '').trim();
    if (prefill) {
      const match = Array.from(desiredSelect.options).find((option) => option.value.toLowerCase() === prefill.toLowerCase());
      if (match) desiredSelect.value = match.value;
      else { desiredSelect.value = '__outro__'; desiredOther.value = prefill; }
    }
    syncDesiredOther();
  }
})();
