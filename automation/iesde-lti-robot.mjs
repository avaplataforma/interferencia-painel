import { chromium } from 'playwright';

const input = await new Promise((resolve, reject) => {
  let body = '';
  process.stdin.setEncoding('utf8');
  process.stdin.on('data', chunk => { body += chunk; });
  process.stdin.on('end', () => {
    try { resolve(JSON.parse(body)); } catch (error) { reject(error); }
  });
  process.stdin.on('error', reject);
});

const loginUrl = String(input.login_url || '');
const courseName = String(input.course_name || '').trim();
if (!loginUrl.startsWith('https://') || !courseName) {
  process.stdout.write(JSON.stringify({ ok: false, error: 'Dados incompletos para iniciar a seleção.' }));
  process.exit(2);
}

let browser;
try {
  browser = await chromium.launch({ headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage'] });
  const context = await browser.newContext({ locale: 'pt-BR', viewport: { width: 1440, height: 1100 } });
  const page = await context.newPage();
  page.setDefaultTimeout(30000);
  await page.goto(loginUrl, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
  await page.waitForTimeout(800);

  const choose = page.getByRole('button', { name: /selecionar conte[uú]do/i }).or(page.getByText(/selecionar conte[uú]do/i, { exact: true })).first();
  await choose.waitFor({ state: 'visible' });
  await choose.click();

  const providerFrame = await waitForProviderFrame(context, page);
  const moodlePage = providerFrame.page();
  const search = providerFrame.getByPlaceholder(/buscar disciplina/i).or(providerFrame.getByPlaceholder(/buscar.*materia/i)).first();
  await search.waitFor({ state: 'visible' });
  await search.fill(courseName);
  await search.press('Enter').catch(() => {});
  await providerFrame.waitForTimeout(900);

  let result = providerFrame.getByText(courseName, { exact: true }).first();
  if (await result.count() === 0) {
    const simplified = courseName.replace(/\s+/g, ' ').trim();
    result = providerFrame.getByText(simplified, { exact: false }).first();
  }
  await result.waitFor({ state: 'visible' });
  await result.click();
  await providerFrame.getByText(/materiais complementares|selecionar todas/i).first().waitFor({ state: 'visible', timeout: 30000 });

  const review = providerFrame.getByRole('button', { name: /revisar e confirmar/i }).first();
  const lessonResources = providerFrame.getByRole('button', { name: /^(material da aula|material interativo da aula)$/i });
  const lessonResourceCount = await lessonResources.count();
  if (lessonResourceCount < 1) {
    throw new Error('O fornecedor abriu a disciplina sem disponibilizar aulas selecionáveis.');
  }
  const selectAll = providerFrame.getByRole('button', { name: /selecionar todas/i }).first();
  await selectAll.waitFor({ state: 'visible' });
  await selectAll.click();
  await waitForSelectionCount(review, lessonResourceCount, 'as aulas da disciplina');

  // Complementary books are independent from the lesson-level "Selecionar
  // todas" command. Select each standalone material and confirm through the
  // provider counter instead of relying on theme-specific active classes.
  const standalone = providerFrame.getByRole('button', { name: /^item avulso$/i });
  const standaloneCount = await standalone.count();
  for (let index = 0; index < standaloneCount; index += 1) {
    const button = standalone.nth(index);
    const before = await selectionCount(review);
    await button.click();
    let after = await waitForSelectionChange(review, before);
    if (after < before) {
      // The material was already selected. Restore it deterministically.
      await button.click();
      after = await waitForSelectionCount(review, before, 'o material complementar');
    }
    if (after <= before) throw new Error('O fornecedor não confirmou a inclusão do material complementar.');
  }

  const createAssessment = providerFrame.getByRole('button', { name: /criar avalia[cç][aã]o/i }).first();
  await createAssessment.waitFor({ state: 'visible' });
  const beforeAssessment = await selectionCount(review);
  await createAssessment.click();
  const assessmentName = providerFrame.getByLabel(/nome da avalia[cç][aã]o/i).or(providerFrame.getByPlaceholder(/avalia[cç][aã]o/i)).first();
  if (await assessmentName.count()) await assessmentName.fill(`Avaliação final - ${courseName}`);
  const quantity = providerFrame.getByText(/^10$/).last();
  if (await quantity.count()) await quantity.click().catch(() => {});
  const shuffleText = providerFrame.getByText(/embaralhar alternativas/i).first();
  if (await shuffleText.count()) {
    const row = shuffleText.locator('xpath=..');
    const checkbox = row.locator('input[type="checkbox"]');
    if (await checkbox.count() && !(await checkbox.isChecked())) await checkbox.check({ force: true });
  }
  await providerFrame.getByRole('button', { name: /^salvar$/i }).last().click();
  const expectedSelectionCount = await waitForSelectionCount(review, beforeAssessment + 1, 'a avaliação oficial');

  await review.waitFor({ state: 'visible' });
  await review.click();
  const link = providerFrame.getByRole('button', { name: /vincular ao ava cursos/i }).first();
  await link.waitFor({ state: 'visible', timeout: 30000 });
  await link.click();

  // The Moodle save control already exists behind the provider modal. Do not
  // click that stale form while Deep Linking is still returning the selected
  // resources. Wait until Moodle renders its own multi-item confirmation and
  // verify that every provider item reached the form.
  const linkedItemsAlert = moodlePage.getByRole('alert').filter({
    hasText: /os seguintes itens ser[aã]o adicionados ao seu curso/i,
  }).first();
  await linkedItemsAlert.waitFor({ state: 'visible', timeout: 45000 });
  const linkedItemCount = await linkedItemsAlert.locator('li strong').count();
  if (linkedItemCount < expectedSelectionCount) {
    throw new Error(`O Moodle recebeu somente ${linkedItemCount} de ${expectedSelectionCount} itens confirmados pelo fornecedor.`);
  }

  // Deep Linking only fills the Moodle activity form. The URL activity does
  // not exist until Moodle receives an explicit final submit, so never close
  // the browser after the provider confirmation alone.
  // Depending on the Moodle theme and viewport, the final submit can remain
  // attached but outside the visible area after the Deep Linking modal closes.
  // Native requestSubmit preserves the clicked button value and Moodle form
  // validation without depending on screen position or overlay animation.
  const activityName = moodlePage.locator('#id_name, input[name="name"]').first();
  if (await activityName.count() && await activityName.isVisible()) await activityName.fill(courseName);
  const saveAndReturn = moodlePage.locator([
    '#id_submitbutton2',
    'input[type="submit"][name="submitbutton2"]',
    'button[type="submit"][name="submitbutton2"]',
  ].join(', ')).first();
  await saveAndReturn.waitFor({ state: 'attached', timeout: 45000 });
  await moodlePage.waitForTimeout(600);
  const finalNavigation = moodlePage.waitForNavigation({
    timeout: 45000,
    waitUntil: 'domcontentloaded',
  });
  await saveAndReturn.evaluate(button => {
    if (!(button instanceof HTMLElement)) throw new Error('Botão final do Moodle inválido.');
    // Moodle and the LTI module attach their own activation behaviour to the
    // submit control. A DOM click runs that behaviour even when the theme has
    // left the control outside the viewport; requestSubmit alone skips it.
    button.click();
  });
  await finalNavigation;
  await moodlePage.waitForLoadState('domcontentloaded', { timeout: 30000 }).catch(() => {});
  const finalSubmitStillPresent = await moodlePage.locator('#id_submitbutton2').count();
  if (finalSubmitStillPresent > 0) {
    const validationMessages = await moodlePage.locator([
      '.alert-danger',
      '.invalid-feedback',
      '.form-control-feedback',
      '[data-fieldtype] .error',
    ].join(', ')).allTextContents();
    const invalidFields = await moodlePage.locator('input:invalid, select:invalid, textarea:invalid, [aria-invalid="true"]').evaluateAll(elements => elements.map(element => {
      const id = element instanceof HTMLElement ? element.id : '';
      const labelled = id ? document.querySelector(`label[for="${CSS.escape(id)}"]`) : null;
      const group = element instanceof HTMLElement ? element.closest('.form-group, [data-fieldtype]') : null;
      const groupLabel = group?.querySelector('label, .col-form-label, legend');
      const label = (labelled?.textContent || groupLabel?.textContent || element.getAttribute('name') || id || 'campo obrigatório')
        .replace(/\s+/g, ' ').trim();
      return label;
    }).filter(Boolean).slice(0, 8));
    const serverErrorFields = await moodlePage.locator('.invalid-feedback, .form-control-feedback, [id^="id_error_"]').evaluateAll(elements => elements.map(error => {
      const errorId = error instanceof HTMLElement ? error.id : '';
      const fieldId = errorId.replace(/^id_error_/, 'id_');
      const field = fieldId ? document.getElementById(fieldId) : null;
      const labelled = fieldId ? document.querySelector(`label[for="${CSS.escape(fieldId)}"]`) : null;
      const group = error instanceof HTMLElement ? error.closest('.form-group, [data-fieldtype]') : null;
      const groupLabel = group?.querySelector('label, .col-form-label, legend');
      const fieldName = (labelled?.textContent || groupLabel?.textContent || field?.getAttribute('name') || fieldId || '')
        .replace(/\s+/g, ' ').trim();
      const message = (error.textContent || '').replace(/\s+/g, ' ').trim();
      return fieldName ? `${fieldName}${message ? ` (${message})` : ''}` : '';
    }).filter(Boolean).slice(0, 8));
    const detail = validationMessages.map(value => value.replace(/\s+/g, ' ').trim()).filter(Boolean).slice(0, 3).join(' ');
    const fields = [...new Set([...invalidFields, ...serverErrorFields])].join(', ');
    throw new Error(`O Moodle manteve o formulário aberto após o envio${fields ? `. Campos pendentes: ${fields}` : detail ? `: ${detail}` : '.'}`);
  }
  process.stdout.write(JSON.stringify({ ok: true }));
} catch (error) {
  const message = error instanceof Error ? error.message : String(error);
  process.stdout.write(JSON.stringify({ ok: false, error: message.replace(/https?:\/\/\S+/g, '[endereço protegido]') }));
  process.exitCode = 1;
} finally {
  if (browser) await browser.close().catch(() => {});
}

async function waitForProviderFrame(context, originPage) {
  const deadline = Date.now() + 45000;
  while (Date.now() < deadline) {
    for (const candidatePage of context.pages()) {
      for (const frame of candidatePage.frames()) {
        const url = frame.url();
        if (/fornecimento\.iesde|api-fornecimento/i.test(url)) return frame;
      }
    }
    await originPage.waitForTimeout(250);
  }
  const routes = [];
  for (const candidatePage of context.pages()) {
    for (const frame of candidatePage.frames()) {
      try {
        const current = new URL(frame.url());
        routes.push(`${current.hostname}${current.pathname}`);
      } catch {}
    }
  }
  const observed = [...new Set(routes.filter(Boolean))].slice(0, 8).join(', ');
  throw new Error(`A janela de seleção do fornecedor não abriu${observed ? `. Rotas observadas: ${observed}` : ''}.`);
}

async function selectionCount(reviewButton) {
  const text = (await reviewButton.textContent() || '').replace(/\s+/g, ' ').trim();
  const values = text.match(/\d+/g) || [];
  return values.length ? Number(values.at(-1)) : 0;
}

async function waitForSelectionCount(reviewButton, minimum, label, timeout = 8000) {
  const deadline = Date.now() + timeout;
  let count = 0;
  while (Date.now() < deadline) {
    count = await selectionCount(reviewButton);
    if (count >= minimum) return count;
    await new Promise(resolve => setTimeout(resolve, 120));
  }
  throw new Error(`O fornecedor não confirmou ${label}: esperados ao menos ${minimum}, recebidos ${count}.`);
}

async function waitForSelectionChange(reviewButton, previous, timeout = 4000) {
  const deadline = Date.now() + timeout;
  let count = previous;
  while (Date.now() < deadline) {
    count = await selectionCount(reviewButton);
    if (count !== previous) return count;
    await new Promise(resolve => setTimeout(resolve, 120));
  }
  return count;
}
