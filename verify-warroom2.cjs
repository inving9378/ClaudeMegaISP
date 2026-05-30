const { chromium } = require('playwright');
const fs = require('fs');
const SCREENSHOTS_DIR = '/tmp/warroom-screenshots';
fs.mkdirSync(SCREENSHOTS_DIR, { recursive: true });

(async () => {
    const browser = await chromium.launch({
        headless: true,
        executablePath: '/home/meganet/.cache/ms-playwright/chromium-1223/chrome-linux64/chrome',
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-gpu'],
    });

    const page = await browser.newPage();
    await page.setViewportSize({ width: 1440, height: 900 });

    const consoleErrors = [];
    const consoleAll = [];
    page.on('console', msg => {
        const line = '[' + msg.type() + '] ' + msg.text();
        consoleAll.push(line);
        if (msg.type() === 'error') consoleErrors.push(line);
    });
    page.on('pageerror', err => consoleErrors.push('[pageerror] ' + err.message));

    // Set the session cookie directly
    await page.context().addCookies([{
        name: 'laravel_session',
        value: 'eyJpdiI6IjhEUHJ2MC9KRDk0UkY4Zmg5Y1d1T2c9PSIsInZhbHVlIjoidzBBbll5bHJpbTY5c09XM2FnMVBDdGJ0cDNZWlZ6Qm9tN0hhTjFBaXhKS0NUM20xVnNqMWxocEJhU05EMGZhYSIsIm1hYyI6IjhmNTVjNzc2N2M2ZjM4NGFjNDI3MDY1NzcxMDg0MjBlNjZmM2NkNTdkMjBjZDM1N2MyNGMwMDRjOGM5MmM1YTMiLCJ0YWciOiIifQ==',
        domain: '192.168.105.11',
        path: '/',
        httpOnly: true,
        secure: false,
    }]);

    console.log('→ Navigating to /warroom with session cookie...');
    await page.goto('http://192.168.105.11/warroom', { waitUntil: 'domcontentloaded', timeout: 20000 });
    await page.waitForTimeout(5000);
    console.log('  URL: ' + page.url());

    await page.screenshot({ path: SCREENSHOTS_DIR + '/10-warroom-main.png', fullPage: true });

    const hasContainer = (await page.) !== null;
    const tabCount     = (await page.1245681('.q-tab')).length;
    const kpiCount     = (await page.1245681('.wr-kpi-card')).length;
    const hasStartBtn  = (await page.) !== null;
    const hasPeriod    = (await page.) !== null;
    const skeletons    = (await page.1245681('.q-skeleton')).length;
    const kpiValues    = await page.1245681eval('.wr-kpi-value', els => els.map(e => e.textContent.trim()));

    console.log('\n  STRUCTURE:');
    console.log('    .warroom-container: ' + hasContainer);
    console.log('    q-tab count: ' + tabCount);
    console.log('    .wr-kpi-card count: ' + kpiCount);
    console.log('    .wr-btn-start: ' + hasStartBtn);
    console.log('    .wr-period-label: ' + hasPeriod);
    console.log('    Skeletons remaining: ' + skeletons);
    console.log('    KPI values: ' + JSON.stringify(kpiValues));

    if (hasPeriod) {
        const pt = await page.('.wr-period-label', e => e.textContent.trim());
        console.log('    Period text: "' + pt + '"');
    }

    // Navigate tabs
    const allTabs = await page.1245681('.q-tab');
    console.log('\n→ Tabs found: ' + allTabs.length);

    for (let i = 1; i < Math.min(allTabs.length, 6); i++) {
        await allTabs[i].click();
        await page.waitForTimeout(1800);
        const kpis = (await page.1245681('.wr-kpi-card')).length;
        const txt = (await allTabs[i].textContent()).trim().replace(/\s+/g,' ').substring(0,12);
        console.log('    Tab ' + (i+1) + ' "' + txt + '": ' + kpis + ' KPI cards');
    }

    // Screenshot Finanzas
    if (allTabs[1]) { await allTabs[1].click(); await page.waitForTimeout(2500); }
    await page.screenshot({ path: SCREENSHOTS_DIR + '/11-finanzas.png', fullPage: true });

    // Screenshot Operaciones
    if (allTabs[2]) { await allTabs[2].click(); await page.waitForTimeout(2500); }
    await page.screenshot({ path: SCREENSHOTS_DIR + '/12-operaciones.png', fullPage: true });

    // Back to Resumen
    if (allTabs[0]) { await allTabs[0].click(); await page.waitForTimeout(1500); }
    await page.screenshot({ path: SCREENSHOTS_DIR + '/13-resumen-final.png', fullPage: true });

    // PeriodSelector
    if (hasPeriod) {
        const before = await page.('.wr-period-label', e => e.textContent.trim());
        const btns = await page.1245681('.wr-period-btn');
        if (btns.length > 0) {
            await btns[0].click();
            await page.waitForTimeout(2500);
            const after = await page.('.wr-period-label', e => e.textContent.trim());
            console.log('\n  Period: "' + before + '" → "' + after + '" (changed: ' + (before !== after) + ')');
        }
    }

    const sidebarLink = await page.;
    console.log('  Sidebar link: ' + (sidebarLink !== null));

    console.log('\n═══ CONSOLE ERRORS ═══');
    if (consoleErrors.length === 0) console.log('  ✓ Ninguno');
    else consoleErrors.slice(0,10).forEach(e => console.log(' ', e));

    const relevant = consoleAll.filter(m => m.includes('WarRoom') || m.includes('[error]') || m.includes('[warn]'));
    if (relevant.length) { console.log('\n═══ RELEVANT ═══'); relevant.slice(0,10).forEach(m => console.log(' ',m)); }

    fs.writeFileSync(SCREENSHOTS_DIR + '/console.log', consoleAll.join('\n'));
    console.log('\n✓ Files:', fs.readdirSync(SCREENSHOTS_DIR).filter(f=>f.endsWith('.png')).join(', '));

    await browser.close();
})().catch(err => { console.error('FATAL:', err.message); process.exit(1); });
