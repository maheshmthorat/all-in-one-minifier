// Admin UI check (Playwright): every screen loads without JS errors, the report runner and
// cache preloader work through their buttons, settings save, and screenshots are written.
// Env: BASE, OUT.
'use strict';
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = process.env.BASE || 'http://localhost:8090';
const OUT = process.env.OUT || '/tmp/aiom-admin';
fs.mkdirSync(OUT, { recursive: true });

(async () => {
	const browser = await chromium.launch();
	const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
	const page = await ctx.newPage();
	const errors = [];
	page.on('pageerror', (e) => errors.push(e.message));
	page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
	let failed = 0;
	const check = (label, cond) => { console.log(`${cond ? 'ok  ' : 'FAIL'} ${label}`); if (!cond) failed++; };

	await page.goto(BASE + '/wp-login.php');
	await page.fill('#user_login', 'admin');
	await page.fill('#user_pass', 'admin');
	await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);

	for (const slug of ['aiom', 'aiom-settings', 'aiom-cache', 'aiom-reports', 'aiom-tools', 'aiom-about']) {
		await page.goto(`${BASE}/wp-admin/admin.php?page=${slug}`, { waitUntil: 'networkidle' });
		check(`${slug} renders`, (await page.locator('.aiom-wrap').count()) === 1);
		await page.screenshot({ path: path.join(OUT, `${slug}.png`), fullPage: true });
	}

	// Report runner.
	await page.goto(`${BASE}/wp-admin/admin.php?page=aiom-reports`, { waitUntil: 'networkidle' });
	await page.click('.aiom-report-form button[type="submit"]');
	await page.waitForSelector('.aiom-report-output .aiom-report', { timeout: 120000 });
	check('report runs from the button', (await page.locator('.aiom-report-output .aiom-metric').count()) >= 4);
	await page.screenshot({ path: path.join(OUT, 'report-result.png'), fullPage: true });
	const detail = await page.locator('.aiom-wrap table a').first().getAttribute('href').catch(() => null);
	if (detail) {
		await page.goto(detail.startsWith('http') ? detail : BASE + detail, { waitUntil: 'networkidle' });
		check('report detail view', (await page.locator('.aiom-assets').count()) >= 1);
		await page.screenshot({ path: path.join(OUT, 'report-detail.png'), fullPage: true });
	}

	// Preload.
	await page.goto(`${BASE}/wp-admin/admin.php?page=aiom-cache`, { waitUntil: 'networkidle' });
	await page.click('.aiom-preload-start');
	await page.waitForFunction(() => /Preloaded/.test(document.querySelector('.aiom-preload-status').textContent), null, { timeout: 180000 });
	const status = await page.locator('.aiom-preload-status').textContent();
	check(`preload finishes (${status.trim()})`, /\(0 errors\)/.test(status));

	// Settings save round-trip.
	await page.goto(`${BASE}/wp-admin/admin.php?page=aiom-settings`, { waitUntil: 'networkidle' });
	await page.fill('#aiom-css-exclude', 'my-handle\n/plugins/demo/');
	await Promise.all([page.waitForNavigation(), page.click('.aiom-savebar button')]);
	check('settings saved notice', (await page.locator('.notice-success').count()) >= 1);
	check('saved value persists', (await page.inputValue('#aiom-css-exclude')) === 'my-handle\n/plugins/demo/');
	await page.fill('#aiom-css-exclude', '');
	await Promise.all([page.waitForNavigation(), page.click('.aiom-savebar button')]);

	// Mobile layout.
	await page.setViewportSize({ width: 390, height: 900 });
	await page.goto(`${BASE}/wp-admin/admin.php?page=aiom-settings`, { waitUntil: 'networkidle' });
	const overflow = await page.evaluate(() => document.querySelector('.aiom-wrap').scrollWidth - document.querySelector('.aiom-wrap').clientWidth);
	check(`no horizontal overflow on a phone (${overflow}px)`, overflow <= 1);
	await page.screenshot({ path: path.join(OUT, 'settings-mobile.png'), fullPage: false });

	check(`no JavaScript errors (${errors.length})`, errors.length === 0);
	errors.forEach((e) => console.log('   ' + e));
	await browser.close();
	process.exit(failed ? 1 : 0);
})();
