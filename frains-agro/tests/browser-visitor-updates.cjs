const assert = require('node:assert/strict');
const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({
        executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
        headless: true,
    });
    try {
        const page = await browser.newPage();
        await page.addInitScript(() => {
            const original = window.setInterval;
            window.setInterval = (callback, delay, ...args) => {
                if (delay === 10000) { window.checkVisitorUpdates = callback; return 0; }
                return original(callback, delay, ...args);
            };
        });
        let changed = false;
        let navigations = 0;
        page.on('framenavigated', frame => { if (frame === page.mainFrame()) navigations++; });
        await page.route('**/produits*', async route => {
            if (route.request().headers()['x-visitor-update'] !== '1') return route.continue();
            const response = await route.fetch();
            const html = await response.text();
            await route.fulfill({ response, body: changed ? html.replace('Nos produits agricoles', 'Catalogue actualisé') : html });
        });
        await page.goto('http://localhost/FRAINS/frains-agro/public/produits');
        const initial = navigations;
        await page.evaluate(() => window.checkVisitorUpdates());
        assert.equal(navigations, initial, 'Unchanged content should not reload');
        changed = true;
        await Promise.all([
            page.waitForEvent('framenavigated', frame => frame === page.mainFrame()),
            page.evaluate(() => window.checkVisitorUpdates()).catch(error => {
                if (!error.message.includes('Execution context was destroyed')) throw error;
            }),
        ]);
        await page.waitForLoadState('load');
        assert.equal(navigations, initial + 1, 'Changed content should reload automatically');
        await page.locator('[name="q"]').fill('tomate');
        await page.locator('h1').click();
        const beforeEditing = navigations;
        await page.evaluate(() => window.checkVisitorUpdates());
        assert.equal(navigations, beforeEditing, 'Pending search must not be lost');
        assert.equal(await page.locator('[name="q"]').inputValue(), 'tomate');
        console.log('Automatic refresh, unchanged page and unsent form preservation verified.');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
