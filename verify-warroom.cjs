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
        const line = `[${msg.type()}] ${msg.text()}`;
        consoleAll.push(line);
        if (msg.type() === 'error') consoleErrors.push(line);
    });
    page.on('pageerror', err => consoleErrors.push(`[pageerror] ${err.message}`));

    console.log('→ Login...');
    await page.goto('http://192.168.105.11/login', { waitUntil: 'networkidle', timeout: 15000 });
    await page.locator('#username').fill('admin');
    await page.locator('#password').fill('bWVnYW5ldA==');
    await page.screenshot({ path: `${SCREENSHOTS_DIR}/00-login-filled.png` });
    await page.locator('button[type="submit"]').first().click();
    await page.waitForTimeout(3000);
    console.log(`  URL after login: ${page.url()}`);

    if (page.url().includes('/login')) {
        await page.locator('#username').fill('admin');
        await page.locator('#password').fill('YWRtaW4=');
        await page.locator('button[type="submit"]').first().click();
        await page.waitForTimeout(2000);
        console.log(`  Retry URL: ${page.url()}`);
    }

    console.log('\n→ War Room...');
    await page.goto('http://192.168.105.11/warroom', { waitUntil: 'domcontentloaded', timeout: 20000 });
    await page.waitForTimeout(4500);
    console.log(`  URL: ${page.url()}`);

    const hasContainer = (await page.$('.warroom-container')) !== null;
    const tabCount     = (await page.$$('.q-tab')).length;
    const kpiCount     = (await page.$$('.wr-kpi-card')).length;
    const hasStartBtn  = (await page.$('.wr-btn-start')) !== null;
    const hasPeriod    = (await page.$('.wr-period-label')) !== null;
    const skeletons    = (await page.$$('.q-skeleton')).length;
    const kpiValues    = await page.$$eval('.wr-kpi-value', els => els.map(e => e.textContent.trim()));

    console.log('\n  STRUCTURE:');
    console.log(`    .warroom-container: ${hasContainer}`);
    console.log(`    q-tab count: ${tabCount}`);
    console.log(`    .wr-kpi-card count: ${kpiCount}`);
    console.log(`    .wr-btn-start: ${hasStartBtn}`);
    console.log(`    .wr-period-label: ${hasPeriod}`);
    console.log(`    Skeletons: ${skeletons}`);
    console.log(`    KPI values: ${JSON.stringify(kpiValues)}`);

    if (hasPeriod) {
        const pt = await page.$eval('.wr-period-label', e => e.textContent.trim());
        console.log(`    Period: "${pt}"`);
    }

    await page.screenshot({ path: `${SCREENSHOTS_DIR}/02-resumen.png`, fullPage: true });

    console.log('\n→ Tabs...');
    const allTabs = await page.$$('.q-tab');
    console.log(`  Found ${allTabs.length} tabs`);

    for (let i = 1; i < Math.min(allTabs.length, 6); i++) {
        await allTabs[i].click();
        await page.waitForTimeout(1500);
        const txt = (await allTabs[i].textContent()).trim().replace(/\s+/g,' ').substring(0,12);
        const kpis = (await page.$$('.wr-kpi-card')).length;
        console.log(`    Tab ${i+1} "${txt}": ${kpis} KPI cards`);
    }

    if (allTabs[1]) { await allTabs[1].click(); await page.waitForTimeout(2000); }
    await page.screenshot({ path: `${SCREENSHOTS_DIR}/03-finanzas.png`, fullPage: true });

    if (allTabs[2]) { await allTabs[2].click(); await page.waitForTimeout(2000); }
    await page.screenshot({ path: `${SCREENSHOTS_DIR}/04-operaciones.png`, fullPage: true });

    if (allTabs[0]) { await allTabs[0].click(); await page.waitForTimeout(1000); }

    console.log('\n→ PeriodSelector...');
    if (hasPeriod) {
        const before = await page.$eval('.wr-period-label', e => e.textContent.trim());
        const btns = await page.$$('.wr-period-btn');
        if (btns.length > 0) {
            await btns[0].click();
            await page.waitForTimeout(2000);
            const after = await page.$eval('.wr-period-label', e => e.textContent.trim());
            console.log(`  "${before}" → "${after}" (changed: ${before !== after})`);
            await page.screenshot({ path: `${SCREENSHOTS_DIR}/05-period-changed.png` });
        }
    }

    const sidebarLink = await page.$('a[href*="warroom"]');
    console.log(`\n  Sidebar link: ${sidebarLink !== null}`);

    console.log('\n═══ CONSOLE ERRORS ═══');
    if (consoleErrors.length === 0) console.log('  ✓ ninguno');
    else consoleErrors.slice(0,15).forEach(e => console.log(' ', e));

    const relevant = consoleAll.filter(m => m.includes('WarRoom') || m.includes('error') || m.includes('warn') || m.includes('[error]'));
    if (relevant.length) { console.log('\n═══ RELEVANT ═══'); relevant.slice(0,15).forEach(m => console.log(' ',m)); }

    fs.writeFileSync(`${SCREENSHOTS_DIR}/console.log`, consoleAll.join('\n'));

    console.log('\n✓ Files:', fs.readdirSync(SCREENSHOTS_DIR).filter(f=>f.endsWith('.png')).join(', '));
    await browser.close();
})().catch(err => { console.error('FATAL:', err.message); process.exit(1); });
