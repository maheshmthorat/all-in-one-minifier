// Browser regression test (Playwright/Chromium).
// For each page: render the original and the optimised version, compare full-page
// screenshots pixel by pixel, collect JavaScript errors and failed requests, then
// exercise interactive features (inline script, contact form, add to cart, search,
// details toggle) on the optimised site.
//
// Env: BASE (site URL), TOKEN (report token for original/optimised captures), OUT (screenshot dir), LABEL.
// Run: see tests/README.md.
'use strict';
const { chromium } = require('playwright');
const { PNG } = require('pngjs');
const pixelmatch = require('pixelmatch');
const fs = require('fs');
const path = require('path');

const BASE = process.env.BASE || 'http://localhost:8090';
const TOKEN = process.env.TOKEN || '';
const OUT = process.env.OUT || '/tmp/aiom-shots';
const LABEL = process.env.LABEL || 'default';
const PAGES = (process.env.PAGES || '/,/code-samples/,/blocks/,/blog/,/sample-post-1/,/shop/,/product/sample-product-1/,/contact/,/sample-page/,/?s=sample,/does-not-exist/').split(',');
fs.mkdirSync(OUT, { recursive: true });

function withMode(p, mode) {
	return BASE + p + (p.includes('?') ? '&' : '?') + `aiom_report=${TOKEN}&aiom_mode=${mode}`;
}

async function capture(browser, url, file) {
	const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, reducedMotion: 'reduce' });
	const page = await ctx.newPage();
	const problems = [];
	page.on('pageerror', (e) => problems.push('JS error: ' + e.message));
	page.on('console', (m) => { if (m.type() === 'error') problems.push('console: ' + m.text().slice(0, 160)); });
	page.on('response', (r) => { if (r.status() >= 400 && !r.url().includes('does-not-exist')) problems.push(`HTTP ${r.status()} ${r.url()}`); });
	const response = await page.goto(url, { waitUntil: 'networkidle' });
	const status = response ? response.status() : 0;
	await page.mouse.move(20, 20); // let interaction-delayed scripts run
	await page.waitForTimeout(500);
	const inline = await page.locator('#inline-check').textContent({ timeout: 500 }).catch(() => null);
	// WooCommerce shuffles related products on every request; mask them so only real differences count.
	await page.addStyleTag({ content: 'section.related.products ul.products, .wp-block-woocommerce-related-products .wc-block-product-template { visibility: hidden !important; }' });
	await page.screenshot({ path: file, fullPage: true, animations: 'disabled' });
	await ctx.close();
	return { problems, inline, status };
}

function compare(a, b, out) {
	const A = PNG.sync.read(fs.readFileSync(a));
	const B = PNG.sync.read(fs.readFileSync(b));
	if (A.width !== B.width || A.height !== B.height) return `size differs ${A.width}x${A.height} vs ${B.width}x${B.height}`;
	const D = new PNG({ width: A.width, height: A.height });
	const n = pixelmatch(A.data, B.data, D.data, A.width, A.height, { threshold: 0.1 });
	if (n) fs.writeFileSync(out, PNG.sync.write(D));
	return n;
}

async function functional(browser) {
	const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
	const page = await ctx.newPage();
	const errors = [];
	page.on('pageerror', (e) => errors.push(e.message));
	const results = {};

	await page.goto(BASE + '/contact/', { waitUntil: 'networkidle' });
	await page.mouse.move(10, 10);
	if (await page.locator('form.wpcf7-form').count()) {
		await page.fill('input[name="your-name"]', 'Test User');
		await page.fill('input[name="your-email"]', 'test@example.com');
		await page.fill('input[name="your-subject"]', 'Hello').catch(() => {});
		await page.fill('textarea[name="your-message"]', 'Message body');
		await page.click('form.wpcf7-form [type="submit"]');
		results.contactForm = await page.locator('.wpcf7-response-output').filter({ hasText: /./ }).first().textContent({ timeout: 15000 }).catch(() => 'NO RESPONSE (JavaScript did not handle the form)');
	} else {
		results.contactForm = 'skipped: Contact Form 7 not active';
	}

	await page.goto(BASE + '/blocks/', { waitUntil: 'networkidle' });
	await page.click('details.wp-block-details summary');
	results.detailsToggle = await page.locator('details.wp-block-details').evaluate((d) => d.open);

	await page.goto(BASE + '/blocks/', { waitUntil: 'networkidle' });
	await page.fill('.wp-block-search input[type="search"]', 'sample');
	await Promise.all([page.waitForNavigation(), page.press('.wp-block-search input[type="search"]', 'Enter')]);
	results.search = (await page.title()).slice(0, 60);

	await page.goto(BASE + '/shop/', { waitUntil: 'networkidle' });
	const add = page.locator('.add_to_cart_button, .wc-block-components-product-button__button').first();
	await add.click();
	await page.waitForTimeout(2500);
	await page.goto(BASE + '/cart/', { waitUntil: 'networkidle' });
	results.cartItems = await page.locator('.wc-block-cart-items__row, .cart_item').count();

	results.jsErrors = errors;
	await ctx.close();
	return results;
}

(async () => {
	const browser = await chromium.launch();
	let failures = 0;
	console.log(`\n== ${LABEL}: original vs optimised ==`);
	for (const p of PAGES) {
		const slug = p.replace(/[^a-z0-9]+/gi, '_') || 'home';
		const a = path.join(OUT, `${LABEL}-${slug}-original.png`);
		const b = path.join(OUT, `${LABEL}-${slug}-optimised.png`);
		const ro = await capture(browser, withMode(p, 'off'), a);
		const rn = await capture(browser, withMode(p, 'on'), b);
		const pixels = compare(a, b, path.join(OUT, `${LABEL}-${slug}-diff.png`));
		const newProblems = rn.problems.filter((x) => !ro.problems.includes(x));
		const expected = p.includes('does-not-exist') ? 404 : 200;
		if (ro.status !== expected || rn.status !== expected) newProblems.push(`HTTP status original ${ro.status} / optimised ${rn.status} (expected ${expected})`);
		const bad = pixels !== 0 || newProblems.length || ro.inline !== rn.inline;
		if (bad) failures++;
		console.log(`${bad ? 'FAIL' : 'ok  '} ${p.padEnd(28)} pixels differing: ${pixels}  new errors: ${newProblems.length}${ro.inline ? `  inline script: ${rn.inline}` : ''}`);
		newProblems.forEach((x) => console.log('       ' + x));
	}
	const f = await functional(browser);
	console.log(`\n== ${LABEL}: interactions on the optimised site ==`);
	console.log(JSON.stringify(f, null, 2));
	if (f.jsErrors.length || !f.detailsToggle || !f.cartItems || /NO RESPONSE/.test(f.contactForm)) failures++;
	await browser.close();
	console.log(failures ? `\n${failures} problem(s)` : '\nNo problems found.');
	process.exit(failures ? 1 : 0);
})();
