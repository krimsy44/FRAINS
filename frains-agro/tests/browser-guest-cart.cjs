const { chromium } = require('playwright');
(async () => {
    const browser = await chromium.launch({ executablePath: 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe', headless: true });
    try {
        const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
        const base = 'http://localhost/FRAINS/frains-agro/public';
        await page.goto(base + '/produits/tomate-fraiche-tom-001');
        await page.getByRole('button', { name: 'Ajouter au panier' }).click();
        await page.waitForURL('**/panier');
        for (const [name, value] of Object.entries({ first_name: 'Awa', last_name: 'Diop', phone: '771234567' })) await page.locator(`[name=${name}]`).fill(value);
        await page.locator('[name=delivery_zone_id]').selectOption({ label: 'Dakar — 2 000 FCFA de livraison' });
        if (!(await page.locator('#cart-final-total').innerText()).includes('FCFA')) throw new Error('Total absent');
        if (await page.locator('input[type=password], input[type=email]').count()) throw new Error('Compte demandé');
        if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth)) throw new Error('Débordement mobile');
        await page.screenshot({ path: 'docs/screenshots/guest-cart-mobile.png', fullPage: true });
        console.log('Panier sans compte : coordonnées, zone, total et affichage mobile vérifiés. Aucune commande réelle envoyée.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
