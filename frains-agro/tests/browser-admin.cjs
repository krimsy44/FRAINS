const { chromium } = require('playwright');
const path = require('node:path');
const fs = require('node:fs');

(async () => {
    const browser = await chromium.launch({ executablePath: 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe', headless: true });
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const base = 'http://localhost/FRAINS/frains-agro/public';
    await page.goto(base + '/admin');
    await page.locator('[name=email]').fill('admin@frains-agro.sn');
    await page.locator('[name=password]').fill(process.env.FRAINS_TEST_ADMIN_PASSWORD || 'password');
    await Promise.all([page.waitForURL('**/admin'), page.getByRole('button', { name: 'Se connecter', exact: true }).click()]);
    await page.getByRole('heading', { name: 'Tableau de bord', exact: true }).waitFor();
    if (await page.locator('.site-header, footer').count() || await page.locator('.admin-header').count() !== 1) throw new Error('Administration mélangée au site public');
    const output = path.resolve('docs/screenshots');
    fs.mkdirSync(output, { recursive: true });
    await page.screenshot({ path: path.join(output, 'admin-desktop.png'), fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({ path: path.join(output, 'admin-mobile.png'), fullPage: true });
    const mobileOverflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    const statuses = {};
    for (const route of ['/admin/gestion/zones', '/admin/stocks', '/admin/agriculture', '/admin/livraisons', '/admin/rapports', '/admin/parametres', '/devis']) {
        const response = await page.goto(base + route);
        statuses[route] = response.status();
        if (await page.locator('.admin-header').count() !== 1 || await page.locator('.site-header').count()) throw new Error('Mauvaise mise en page : ' + route);
    }
    for (const route of ['/', '/produits']) {
        const response = await page.goto(base + route);
        statuses[route] = response.status();
        if (await page.locator('.workspace-nav, .admin-header').count() || await page.locator('.site-header').count() !== 1) throw new Error('Menu admin visible sur le site public');
    }
    await browser.close();
    console.log(JSON.stringify({ statuses, mobileOverflow, errors, screenshots: output }, null, 2));
    if (mobileOverflow || errors.length || Object.values(statuses).some(status => status !== 200)) process.exitCode = 1;
})().catch(error => { console.error(error.message); process.exitCode = 1; });
