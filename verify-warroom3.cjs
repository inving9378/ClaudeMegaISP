const { chromium } = require('playwright');
const fs = require('fs');
const SCREENSHOTS_DIR = '/tmp/warroom-screenshots';
fs.mkdirSync(SCREENSHOTS_DIR, { recursive: true });

const SESSION_COOKIE = 'eyJpdiI6ImdDaW0ybng5TTArZ1h3Y09nZlVvWFE9PSIsInZhbHVlIjoiVHMvNm9UNzByL1k4MW83NU1USUhMa0JJejYzQUJlQ1o3ZXVpQXI1dkhXWWNNUHkwUWdpdTRKWENhWWFZTklvbiIsIm1hYyI6IjdhOGYzMDdkMGVlYjliZDcwN2UxNWExMzg0NzJmZWIwMjM0MDZkZTVjMGRlYmRlYzY0NmU2YTM4YTQ3YjBkM2MiLCJ0YWciOiIifQ==';

(async () => {
    const browser = await chromium.launch({
        headless: true,
        executablePath: '/home/meganet/.cache/ms-playwright/chromium-1223/chrome-linux64/chrome',
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-gpu'],
    });

    const ctx = await browser.newContext();
    await ctx.addCookies([{
        name: 'laravel_session',
        value: SESSION_COOKIE,
        domain: '192.168.105.11',
        path: '/',
        httpOnly: true,
        secure: false,
    }]);

    const page = await ctx.newPage();
    await page.setViewportSize({ width: 1440, height: 900 });

    const consoleErrors = [];
    const consoleAll = [];
    page.on('console', msg => {
        const line = '[' + msg.type() + '] ' + msg.text();
        consoleAll.push(line);
        if (msg.type() === 'error') consoleErrors.push(line);
    });
    page.on('pageerror', err => consoleErrors.push('[pageerror] ' + err.message));

    console.log('Navigating to /warroom with session cookie...');
    await page.goto('http://192.168.105.11/warroom', { waitUntil: 'domcontentloaded', timeout: 20000 });
    await page.waitForTimeout(5000);
    console.log('URL: ' + page.url());
    await page.screenshot({ path: SCREENSHOTS_DIR + '/10-warroom-main.png', fullPage: true });

    const hasContainer = (await page.$('.warroom-container')) !== null;
    const tabCount = (await page.$$('.q-tab')).length;
    const kpiCount = (await page.$$('.wr-kpi-card')).length;
    const hasStartBtn = (await page.$('.wr-btn-start')) !== null;
    const hasPeriod = (await page.$('.wr-period-label')) !== null;
    const skeletons = (await page.$$('.q-skeleton')).length;
    const kpiValues = await page.$$eval('.wr-kpi-value', els => els.map(e => e.textContent.trim()));

    console.log('STRUCTURE:');
    console.log('  .warroom-container: ' + hasContainer);
    console.log('  q-tab count: ' + tabCount);
    console.log('  .wr-kpi-card count: ' + kpiCount);
    console.log('  .wr-btn-start: ' + hasStartBtn);
    console.log('  .wr-period-label: ' + hasPeriod);
    console.log('  Skeletons: ' + skeletons);
    console.log('  KPI values: ' + JSON.stringify(kpiValues));

    if (hasPeriod) {
        const pt = await page.$eval('.wr-period-label', e => e.textContent.trim());
        console.log('  Period text: ' + pt);
    }

    // Navigate all tabs
    const allTabs = await page.$$('.q-tab');
    console.log('Tabs found: ' + allTabs.length);

    for (let i = 1; i < Math.min(allTabs.length, 6); i++) {
        await allTabs[i].click();
        await page.waitForTimeout(1800);
        const kpis = (await page.$$('.wr-kpi-card')).length;
        const txt = (await allTabs[i].textContent()).trim().replace(/\s+/g, ' ').substring(0, 15);
        console.log('  Tab ' + (i + 1) + ' [' + txt + ']: ' + kpis + ' KPI cards');
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
        const before = await page.$eval('.wr-period-label', e => e.textContent.trim());
        const btns = await page.$$('.wr-period-btn');
        if (btns.length > 0) {
            await btns[0].click();
            await page.waitForTimeout(2500);
            const after = await page.$eval('.wr-period-label', e => e.textContent.trim());
            console.log('PeriodSelector: "' + before + '" -> "' + after + '" (changed: ' + (before !== after) + ')');
            await page.screenshot({ path: SCREENSHOTS_DIR + '/14-period-changed.png', fullPage: false });
        }
    }

    const sidebarLink = await page.$('a[href*="warroom"]');
    console.log('Sidebar link: ' + (sidebarLink !== null));

    console.log('');
    console.log('=== CONSOLE ERRORS ===');
    if (consoleErrors.length === 0) {
        console.log('  OK - ninguno');
    } else {
        consoleErrors.slice(0, 15).forEach(e => console.log('  ' + e));
    }

    // Relevant non-error messages
    const relevant = consoleAll.filter(m =>
        (m.includes('[warn]') || m.includes('[error]')) &&
        !m.includes('__VUE_PROD') &&
        !m.includes('side effect') &&
        !m.includes('compile-time')
    );
    if (relevant.length > 0) {
        console.log('');
        console.log('=== RELEVANT MESSAGES ===');
        relevant.slice(0, 10).forEach(m => console.log('  ' + m));
    }

    fs.writeFileSync(SCREENSHOTS_DIR + '/console.log', consoleAll.join('\n'));
    console.log('');
    console.log('Screenshots: ' + fs.readdirSync(SCREENSHOTS_DIR).filter(f => f.endsWith('.png')).join(', '));

    await browser.close();
})().catch(err => {
    console.error('FATAL: ' + err.message);
    process.exit(1);
});
