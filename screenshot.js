const { chromium } = require('C:/Users/carlos/AppData/Roaming/npm/node_modules/playwright');
const path = require('path');
const fs   = require('fs');

const BASE = 'http://claude-meganet.localhost';
const ZIP  = path.resolve('dump-2026-05-26-1779801485.zip');

(async () => {
    const browser = await chromium.launch({
        headless: true,
        executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe',
    });
    const page = await browser.newPage();
    await page.setViewportSize({ width: 1400, height: 900 });
    page.setDefaultTimeout(30000);

    // Capturar errores de consola
    page.on('console', msg => {
        if (['error','warn'].includes(msg.type())) {
            console.log(`  [${msg.type()}]`, msg.text().slice(0, 200));
        }
    });
    page.on('response', async resp => {
        if (resp.url().includes('smart-import') && resp.status() >= 400) {
            const body = await resp.text().catch(() => '');
            console.log(`  [HTTP ${resp.status()}] ${resp.url()}\n  ${body.slice(0,300)}`);
        }
    });

    // ── Login ──
    await page.goto(BASE + '/login');
    await page.waitForTimeout(1500);
    await page.fill('input[name="email"]', 'admin');
    await page.fill('input[name="password"]', 'Admin2024');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    console.log('✓ Login —', page.url());

    // ── Paso 1: subir ──
    await page.goto(BASE + '/configuracion/smart-import');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    const sizeMB = Math.round(fs.statSync(ZIP).size / 1024 / 1024);
    console.log(`Subiendo ZIP de ${sizeMB} MB... (el análisis del SQL de 720MB puede tardar 3-5 min)`);

    const fileInput = page.locator('input[type="file"]');
    await fileInput.setInputFiles(ZIP);

    // Esperar respuesta HTTP del upload (el servidor procesa síncronamente)
    console.log('Esperando respuesta del servidor...');
    await page.waitForTimeout(4000);
    await page.screenshot({ path: 'test_upload_inicio.png' });

    // Esperar hasta 8 minutos a que aparezca el paso 2
    let paso2 = false;
    const deadline = Date.now() + 8 * 60 * 1000;
    while (Date.now() < deadline) {
        const url = page.url();
        const stepText = await page.$eval('.q-stepper__header', el => el.textContent).catch(() => '');
        const hasReport = await page.$('.si-report-table, .q-table').catch(() => null);
        const currentStep = await page.$('.q-stepper__step--active').catch(() => null);
        const stepLabel = currentStep ? await currentStep.$eval('.q-stepper__label', el => el.textContent).catch(() => '') : '';

        console.log(`  [${Math.round((Date.now()-Date.now())/1000)}s] step activo: "${stepLabel.trim()}" | tabla visible: ${!!hasReport}`);

        if (stepLabel.includes('Reporte') || hasReport) {
            paso2 = true;
            break;
        }
        await page.waitForTimeout(5000);
    }

    await page.screenshot({ path: 'test_paso2.png', fullPage: true });

    if (paso2) {
        console.log('✓ Paso 2 alcanzado — tomando screenshot completo');

        // Contar filas del reporte
        const filas = await page.$$eval('tbody tr', rows => rows.length).catch(() => 0);
        console.log(`  Tablas en el reporte: ${filas}`);

        // Intentar ejecutar la importación con "Limpiar datos"
        // Activar toggle truncate_before
        const toggle = page.locator('.q-toggle').first();
        if (await toggle.isVisible().catch(() => false)) {
            await toggle.click();
            console.log('✓ Toggle "Limpiar datos" activado');
        }

        // Clic en Ejecutar
        const btnEjecutar = page.locator('button:has-text("Ejecutar importación")');
        if (await btnEjecutar.isVisible().catch(() => false)) {
            await btnEjecutar.click();
            console.log('✓ Importación iniciada — esperando paso 3...');
            await page.waitForTimeout(5000);
            await page.screenshot({ path: 'test_paso3_inicio.png' });

            // Esperar hasta 30 min para que termine
            const deadline3 = Date.now() + 30 * 60 * 1000;
            while (Date.now() < deadline3) {
                const estado = await page.$eval('[class*="log-pre"]', el => el.textContent.slice(-300)).catch(() => '');
                const done = estado.includes('finalizada') || estado.includes('completed') || estado.includes('abortada');
                console.log(`  Log tail: ...${estado.slice(-80).replace(/\n/g,' ')}`);
                if (done) break;
                await page.waitForTimeout(10000);
            }
            await page.screenshot({ path: 'test_paso3_final.png', fullPage: true });
            console.log('✓ Screenshot paso 3 final');
        }
    } else {
        console.log('✗ Timeout: paso 2 no alcanzado en 8 minutos');
    }

    await browser.close();
    console.log('\nDone.');
})().catch(e => {
    console.error('ERROR:', e.message);
    process.exit(1);
});
