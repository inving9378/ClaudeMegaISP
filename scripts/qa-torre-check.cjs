// Verificación de render de /releases (Torre) — blindaje headless (#334).
// Loguea con el usuario de PRUEBA dedicado (qa_torre), carga /releases y captura:
//   (a) errores exactos de consola + pageerror (Uncaught + stack con archivo:línea)
//   (b) un screenshot.
// Uso: node scripts/qa-torre-check.cjs
const { chromium } = require('playwright');

// Credenciales del USUARIO DE PRUEBA dedicado por env (nunca hardcodear el password en git).
//   QA_USER=qa_torre QA_PASS='<pass del QA>' node scripts/qa-torre-check.cjs
const BASE = process.env.QA_BASE || 'http://192.168.105.11';
const USER = process.env.QA_USER || 'qa_torre';
const PASS = process.env.QA_PASS || '';
const SHOT = process.env.QA_SHOT || '/tmp/claude-1000/-/659aff4a-ce32-4f89-b9ec-59826e9457fb/scratchpad/torre.png';

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const ctx = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await ctx.newPage();

  const consoleErrors = [];
  const pageErrors = [];
  const failedReqs = [];
  page.on('console', (m) => { if (m.type() === 'error') consoleErrors.push(m.text()); });
  page.on('pageerror', (e) => { pageErrors.push({ msg: e.message, stack: e.stack }); });
  page.on('requestfailed', (r) => failedReqs.push(`${r.method()} ${r.url()} — ${r.failure()?.errorText}`));
  page.on('response', (r) => { if (r.status() >= 500) failedReqs.push(`HTTP ${r.status()} ${r.url()}`); });

  try {
    // 1) Login — form POST estándar (input name=email acepta login_user; submit "Entrar")
    await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded', timeout: 20000 });
    await page.waitForSelector('input[name="email"]', { timeout: 15000 });
    await page.fill('input[name="email"]', USER);
    await page.fill('input[name="password"]', PASS);
    await page.click('button[type="submit"]');
    await page.waitForURL((u) => !u.toString().includes('/login'), { timeout: 20000 }).catch(() => {});
    await page.waitForTimeout(1000);
    console.log('URL tras login:', page.url());
    if (page.url().includes('/login')) console.log('⚠️ login NO autenticó (sigue en /login)');
    // limpiar errores del login para que solo capturemos los de /releases
    consoleErrors.length = 0; pageErrors.length = 0; failedReqs.length = 0;

    // 2) Cargar /releases
    await page.goto(BASE + '/releases', { waitUntil: 'networkidle', timeout: 25000 }).catch((e) => console.log('goto /releases err:', e.message));
    await page.waitForTimeout(3000); // dar tiempo a que monte Vue y truene si va a tronar

    // 3) ¿Hay algo renderizado dentro del contenedor Vue?
    const bodyLen = (await page.evaluate(() => document.querySelector('#init-vue')?.innerHTML?.length || 0));
    const hasTorre = await page.evaluate(() => !!document.querySelector('.tc-wrap, [class*="tc-"], .tt-wrap'));

    await page.screenshot({ path: SHOT, fullPage: true }).catch(() => {});

    console.log('\n===== RESULTADO =====');
    console.log('URL final:', page.url());
    console.log('#init-vue innerHTML length:', bodyLen, '| Torre montada:', hasTorre);
    console.log('\n--- pageerror (Uncaught) ---');
    if (!pageErrors.length) console.log('(ninguno)');
    pageErrors.forEach((e, i) => { console.log(`[${i}] ${e.msg}\n${e.stack}\n`); });
    console.log('--- console.error ---');
    if (!consoleErrors.length) console.log('(ninguno)');
    consoleErrors.slice(0, 12).forEach((t, i) => console.log(`[${i}] ${t}`));
    console.log('--- requests fallidas / 5xx ---');
    if (!failedReqs.length) console.log('(ninguna)');
    failedReqs.slice(0, 12).forEach((t) => console.log('  ' + t));
    console.log('\nscreenshot:', SHOT);
  } catch (err) {
    console.log('ERROR script:', err.message);
  } finally {
    await browser.close();
  }
})();
