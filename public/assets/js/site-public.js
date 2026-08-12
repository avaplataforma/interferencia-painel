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
  const catalogGrid = document.querySelector('[data-course-grid]');
  const catalogCards = Array.from(document.querySelectorAll('[data-course-card]'));
  const catalogEmpty = document.querySelector('[data-catalog-empty]');
  const favoritesKey = `site-course-favorites-${organization}`;
  const normalizeCatalog = (value) => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
  const readFavorites = () => { try { return new Set(JSON.parse(localStorage.getItem(favoritesKey) || '[]').map(String)); } catch { return new Set(); } };
  let favorites = readFavorites();
  const syncFavoriteButtons = () => catalogCards.forEach((card) => { const button = card.querySelector('[data-course-favorite]'); const active = favorites.has(String(card.dataset.courseId || '')); if (button) { button.setAttribute('aria-pressed', active ? 'true' : 'false'); button.title = active ? 'Remover dos favoritos' : 'Adicionar aos favoritos'; const icon = button.querySelector('i'); if (icon) icon.className = active ? 'fa-solid fa-heart' : 'fa-regular fa-heart'; } });
  const filterCatalog = () => {
    if (!(catalogGrid instanceof HTMLElement)) return;
    const term = normalizeCatalog(catalogSearch?.value || '');
    const formation = normalizeCatalog(catalogFormation?.value || '');
    const category = normalizeCatalog(catalogCategory?.value || '');
    const sort = catalogSort?.value || 'featured';
    const matching = catalogCards.filter((card) => {
      const searchable = normalizeCatalog(card.textContent || '');
      const visible = (!term || searchable.includes(term)) && (!formation || normalizeCatalog(card.dataset.courseFormation) === formation) && (!category || normalizeCatalog(card.dataset.courseCategory) === category) && (sort !== 'favorites' || favorites.has(String(card.dataset.courseId || '')));
      card.hidden = !visible;
      return visible;
    });
    const sorted = [...matching].sort((a, b) => {
      if (sort === 'name') return String(a.dataset.courseName).localeCompare(String(b.dataset.courseName), 'pt-BR');
      if (sort === 'price-asc' || sort === 'price-desc') return (Number(a.dataset.coursePrice) - Number(b.dataset.coursePrice)) * (sort === 'price-desc' ? -1 : 1);
      return 0;
    });
    sorted.forEach((card) => catalogGrid.append(card));
    if (catalogEmpty instanceof HTMLElement) catalogEmpty.hidden = matching.length !== 0;
  };
  catalogCards.forEach((card) => card.querySelector('[data-course-favorite]')?.addEventListener('click', () => { const id = String(card.dataset.courseId || ''); favorites.has(id) ? favorites.delete(id) : favorites.add(id); localStorage.setItem(favoritesKey, JSON.stringify([...favorites])); syncFavoriteButtons(); filterCatalog(); }));
  [catalogSearch, catalogFormation, catalogCategory, catalogSort].forEach((field) => field?.addEventListener('input', filterCatalog));
  syncFavoriteButtons();
  filterCatalog();

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
})();
