/** Verify actual local WordPress pages in a real browser; all external requests are blocked. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {annotateGitHubError} = require('../tools/testing/github-errors.cjs');
const playwright = require(process.env.SKYE_PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');
const origin = 'http://127.0.0.1:8973';
const data = JSON.parse(fs.readFileSync(path.join(root, '.git/runtime/site-fixture.json'), 'utf8'));
const engine = process.env.SKYE_BROWSER || 'chromium';
const output = path.join(root, '.git/runtime/browser-' + engine);
fs.mkdirSync(output, {recursive: true});

async function run() {
	const options = {headless: true};
	if (process.env.SKYE_BROWSER_EXECUTABLE) options.executablePath = process.env.SKYE_BROWSER_EXECUTABLE;
	const browser = await playwright[engine].launch(options);
	const result = {engine, version: browser.version(), platform: process.platform, wordpress: data.wordpress, emulation: [], cases: [], checks: 0};
	try {
		for (const width of [320, 390, 768, 1440]) {
			const context = await browser.newContext({viewport: {width, height: 900}});
			await context.route('**/*', route => route.request().url().startsWith(origin) ? route.continue() : route.abort());
			const page = await context.newPage();
			const errors = [];
			page.on('pageerror', error => errors.push(error.message));
			for (const [name, url, status] of [
				['home', '/', 200], ['page', '/?page_id=' + data.about, 200],
				['child', '/?page_id=' + data.child, 200], ['single', '/?p=' + data.article, 200],
				['long-content', '/?page_id=' + data.longtext, 200], ['blocks', '/?p=' + data.blocks, 200], ['protected', '/?page_id=' + data.protected, 200],
				['search', '/?s=native', 200], ['empty-search', '/?s=zzzNoFixtureMatch', 200],
				['not-found', '/?p=99999999', 404],
			]) {
				const response = await page.goto(origin + url, {waitUntil: 'networkidle'});
				assert.equal(response.status(), status, name + ' native response');
				await page.locator('main#main-content').waitFor();
				assert.equal(await page.locator('h1').count(), 1, name + ' has one main heading');
				const dimensions = await page.evaluate(() => ({width: innerWidth, scroll: document.documentElement.scrollWidth, height: document.querySelector('main').getBoundingClientRect().height}));
				assert.ok(dimensions.scroll <= dimensions.width + 1, name + ' has no horizontal overflow at ' + width);
				assert.ok(dimensions.height > 0, name + ' content remains visible');
				if (name === 'protected') assert.ok(!(await page.locator('main').innerText()).includes('PRIVATE_FIXTURE_MARKER'), 'password-protected content is not exposed');
				if (name === 'blocks') assert.ok((await page.locator('main').innerText()).includes('Previously saved block content still renders.'), 'saved blocks survive classic editor policy');
				result.cases.push({name, width, status: response.status()});
				result.checks += 4;
			}
			await page.goto(origin + '/', {waitUntil: 'networkidle'});
			await page.screenshot({path: path.join(output, 'home-' + width + '.png'), fullPage: true});
			await page.keyboard.press('Tab');
			assert.equal(await page.locator(':focus').getAttribute('href'), '#main-content', 'skip link is first keyboard action');
			await page.keyboard.press('Enter');
			assert.equal(await page.locator(':focus').getAttribute('id'), 'main-content', 'skip link moves focus to main content');
			if (await page.locator('button[data-bs-toggle="drawer"]').isVisible()) {
				const toggle = page.locator('button[data-bs-toggle="drawer"]');
				await page.evaluate(() => {
						window.skyeDrawerShown = false;
						document.querySelector('#skye-navigation').addEventListener('shown.bs.drawer', () => { window.skyeDrawerShown = true; }, {once: true});
					});
					await toggle.click();
				await page.waitForFunction(() => window.skyeDrawerShown);
				await page.keyboard.press('Escape');
				await page.waitForFunction(() => !document.querySelector('#skye-navigation').open);
					await page.locator('#skye-navigation').waitFor({state: 'hidden'});
				assert.equal(await page.locator(':focus').getAttribute('data-bs-toggle'), 'drawer', 'native drawer restores opener focus');
				result.checks += 3;
			} else {
				const tops = await page.locator('nav .navbar-nav > li').evaluateAll(items => items.map(item => item.getBoundingClientRect().top));
					assert.ok(Math.max(...tops) - Math.min(...tops) < 2, 'desktop top-level navigation shares a row');
					const menu = page.locator('button[data-bs-toggle="menu"]').first();
				await menu.click();
				await page.waitForFunction(() => document.querySelector('button[data-bs-toggle="menu"]').getAttribute('aria-expanded') === 'true');
				await page.keyboard.press('Escape');
				await page.waitForFunction(() => document.querySelector('button[data-bs-toggle="menu"]').getAttribute('aria-expanded') === 'false');
				result.checks += 2;
			}
			assert.deepEqual(errors, [], 'no browser JavaScript errors');
			await page.screenshot({path: path.join(output, 'after-keyboard-' + width + '.png'), fullPage: true});
			const resources = await page.evaluate(() => performance.getEntriesByType('resource').filter(item => item.name.includes('/themes/skye/dist/')).map(item => ({url: item.name, bytes: item.decodedBodySize, transfer: item.transferSize})));
			assert.equal(new Set(resources.map(item => item.url)).size, resources.length, 'owned resources do not duplicate a URL');
			result.cases.push({name: 'resources', width, resources});
			result.checks += 4;
			await context.close();
		}
		const nojs = await browser.newContext({javaScriptEnabled: false, viewport: {width: 390, height: 844}});
		await nojs.route('**/*', route => route.request().url().startsWith(origin) ? route.continue() : route.abort());
		const page = await nojs.newPage();
		await page.goto(origin + '/', {waitUntil: 'networkidle'});
		assert.ok(await page.locator('.skye-no-script-navigation a').count() >= 3, 'no-JS navigation keeps native destinations');
		await page.locator('.skye-no-script-navigation a').filter({hasText: 'About the fixture'}).first().click();
		assert.ok(page.url().includes('page_id=' + data.about), 'no-JS parent navigation works');
		await page.screenshot({path: path.join(output, 'no-js-390.png'), fullPage: true});
		result.checks += 2;
		await nojs.close();
		if (engine !== 'firefox') {
			const mobile = await browser.newContext({viewport: {width: 390, height: 844}, isMobile: true, hasTouch: true, deviceScaleFactor: 3});
			await mobile.route('**/*', route => route.request().url().startsWith(origin) ? route.continue() : route.abort());
			const touch = await mobile.newPage();
			await touch.goto(origin + '/', {waitUntil: 'networkidle'});
			await touch.evaluate(() => {
				window.skyeTouchShown = false;
				document.querySelector('#skye-navigation').addEventListener('shown.bs.drawer', () => { window.skyeTouchShown = true; }, {once: true});
			});
			await touch.tap('button[data-bs-toggle="drawer"]');
			await touch.waitForFunction(() => window.skyeTouchShown);
			await touch.tap('button[data-bs-dismiss="drawer"]');
			await touch.locator('#skye-navigation').waitFor({state: 'hidden'});
			await touch.setViewportSize({width: 844, height: 390});
			assert.ok(await touch.locator('.navbar-nav').isVisible(), 'landscape emulation retains native navigation');
			await touch.screenshot({path: path.join(output, 'touch-landscape.png'), fullPage: true});
			result.emulation.push({kind: 'browser touch emulation, no physical device or Safari claim', portrait: '390x844', landscape: '844x390'});
			result.checks += 3;
			await mobile.close();
		}
		for (const url of ['/wp-content/themes/skye/.git/config', '/wp-content/themes/skye/tests/security.php', '/wp-content/themes/skye/tools/coding-standards/check.php', '/wp-config.php']) {
			const response = await fetch(origin + url);
			assert.equal(response.status, 404, 'development path is blocked by test server: ' + url);
			result.checks++;
		}
		fs.writeFileSync(path.join(output, 'result.json'), JSON.stringify(result, null, 2) + '\n');
		console.log(JSON.stringify({status: 'pass', ...result}, null, 2));
	} finally {
		await browser.close();
	}
}
run().catch(error => {
	console.error(error);
	annotateGitHubError(error);
	process.exitCode = 1;
});
