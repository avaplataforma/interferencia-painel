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

  const standalone = providerFrame.getByRole('button', { name: /item avulso/i });
  const standaloneCount = await standalone.count();
  for (let index = 0; index < standaloneCount; index += 1) {
    const button = standalone.nth(index);
    const pressed = await button.getAttribute('aria-pressed');
    const classes = await button.getAttribute('class') || '';
    if (pressed !== 'true' && !/active|selected/i.test(classes)) await button.click();
  }

  const selectAll = providerFrame.getByRole('button', { name: /selecionar todas/i }).first();
  await selectAll.waitFor({ state: 'visible' });
  await selectAll.click();

  const createAssessment = providerFrame.getByRole('button', { name: /criar avalia[cç][aã]o/i }).first();
  await createAssessment.waitFor({ state: 'visible' });
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

  const review = providerFrame.getByRole('button', { name: /revisar e confirmar/i }).first();
  await review.waitFor({ state: 'visible' });
  await review.click();
  const link = providerFrame.getByRole('button', { name: /vincular ao ava cursos/i }).first();
  await link.waitFor({ state: 'visible', timeout: 30000 });
  await link.click();

  // Deep Linking only fills the Moodle activity form. The URL activity does
  // not exist until Moodle receives an explicit final submit, so never close
  // the browser after the provider confirmation alone.
  // Depending on the Moodle theme and viewport, the final submit can remain
  // attached but outside the visible area after the Deep Linking modal closes.
  // Native requestSubmit preserves the clicked button value and Moodle form
  // validation without depending on screen position or overlay animation.
  const saveAndReturn = page.locator([
    '#id_submitbutton2',
    'input[type="submit"][name="submitbutton2"]',
    'input[type="submit"][name="submitbutton"]',
    'button[type="submit"]',
  ].join(', ')).filter({ hasText: /salvar e voltar ao curso/i }).or(page.locator('#id_submitbutton2')).first();
  await saveAndReturn.waitFor({ state: 'attached', timeout: 45000 });
  await page.waitForTimeout(600);
  const finalNavigation = page.waitForNavigation({
    timeout: 45000,
    waitUntil: 'domcontentloaded',
  });
  await saveAndReturn.evaluate(button => {
    if (!(button instanceof HTMLElement)) throw new Error('Botão final do Moodle inválido.');
    const form = button.closest('form');
    if (!(form instanceof HTMLFormElement)) throw new Error('Formulário final do Moodle não encontrado.');
    if (button instanceof HTMLButtonElement || button instanceof HTMLInputElement) {
      form.requestSubmit(button);
      return;
    }
    form.requestSubmit();
  });
  await finalNavigation;
  await page.waitForLoadState('domcontentloaded', { timeout: 30000 }).catch(() => {});
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
