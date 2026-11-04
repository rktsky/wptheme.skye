/** Check the actual positive critical path in a disposable loopback WordPress/browser fixture. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {annotateGitHubError} = require('../tools/testing/github-errors.cjs');
const playwright = require(process.env.SKYE_PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');
const origin = process.env.SKYE_TEST_ORIGIN || 'http://127.0.0.1:8973';
const site = fs.realpathSync(process.env.SKYE_SITE_ROOT || path.join(root, '.git/runtime/site/wordpress'));
const target = path.join(site, 'wp-content/mu-plugins/skye-critical-browser.php');
const source = fs.readFileSync(path.join(__dirname, 'critical-site-fixture.php'));
const engine = process.env.SKYE_BROWSER || 'chromium';
assert.ok(['chromium', 'firefox', 'webkit'].includes(engine), 'Use a known Playwright browser engine.');
const output = path.join(root, '.git/runtime/browser-critical-' + engine);
const manifest = JSON.parse(fs.readFileSync(path.join(root, 'dist/assets.json')));
const fixture = JSON.parse(fs.readFileSync(path.join(root, '.git/runtime/site-fixture.json')));
const fullSuffix = '/dist/' + manifest['main.css'];
const mobileSuffix = '/dist/' + manifest['main-mobile.css'];
const originURL = new URL(origin);
assert.ok(['127.0.0.1', 'localhost', '[::1]'].includes(originURL.hostname), 'Only loopback browser fixtures are allowed.');
assert.ok(site.startsWith(path.join(root, '.git/runtime') + path.sep), 'Only the disposable runtime site may receive this fixture.');
assert.ok(!fs.existsSync(target), 'Never overwrite an existing MU plugin.');
fs.mkdirSync(output, {recursive: true});

/**
 * Capture the admitted system-font viewport while its complete CSS is held.
 *
 * Firefox's document.fonts.ready also waits for the pending stylesheet. That
 * cannot settle until the caller releases that response after this capture.
 * Disable only Playwright's auxiliary font wait for this one capture; the
 * actual page, CSS readiness, typography and geometry assertions stay intact.
 * Restore any caller setting even if screenshot transport fails. The fixture
 * requires no custom font faces/imports and makes no webfont readiness claim.
 *
 * @param {Object} page Actual Playwright page under the held CSS request.
 * @param {string} filename Screenshot output inside the disposable runtime.
 * @return {Promise<void>} Completion of the actual browser capture.
 * @since 3.0.0
 */
async function capturePendingSystemFontViewport(page, filename) {
	const full = fs.readFileSync(path.join(root, 'dist', manifest['main.css']), 'utf8');
	assert.doesNotMatch(full, /@(?:font-face|import)\b/i, 'The pending screenshot contract requires the current system-font stylesheet.');
	const previous = process.env.PW_TEST_SCREENSHOT_NO_FONTS_READY;
	try {
		process.env.PW_TEST_SCREENSHOT_NO_FONTS_READY = '1';
		await page.screenshot({path: filename});
	} finally {
		if (previous === undefined) delete process.env.PW_TEST_SCREENSHOT_NO_FONTS_READY;
		else process.env.PW_TEST_SCREENSHOT_NO_FONTS_READY = previous;
	}
}

async function run() {
	fs.writeFileSync(target, source, {mode: 0o600, flag: 'wx'});
	let browser;
	const result = {engine, platform: process.platform, wordpress: fixture.wordpress, checks: 0, cases: []};
	try {
		const options = {headless: true};
		if (process.env.SKYE_BROWSER_EXECUTABLE) options.executablePath = process.env.SKYE_BROWSER_EXECUTABLE;
		browser = await playwright[engine].launch(options);
		result.version = browser.version();
		for (const width of [390, 1440]) {
			const context = await browser.newContext({viewport: {width, height: 844}});
			let release;
			const held = new Promise(resolve => { release = resolve; });
			const requests = [];
			const stylesheetRequests = [];
			const stylesheetRecords = new Map();
			// Routing disables HTTP cache. Retain actual completion/failure evidence
			// so a cancelled preload cannot be confused with two completed transfers.
			context.on('response', response => {
				const record = stylesheetRecords.get(response.request());
				if (record) {
					record.status = response.status();
					record.cacheControl = response.headers()['cache-control'] || null;
				}
			});
			context.on('requestfinished', request => {
				const record = stylesheetRecords.get(request);
				if (record) record.finished = true;
			});
			context.on('requestfailed', request => {
				const record = stylesheetRecords.get(request);
				if (record) {
					record.failure = request.failure()?.errorText || 'unknown failure';
					record.failedBeforeContinue = !record.continued;
				}
			});
			await context.route('**/*', async route => {
				const request = route.request();
				const url = request.url();
				if (!url.startsWith(origin + '/')) return route.abort();
				requests.push(url);
				if (url.endsWith(fullSuffix)) {
					if (stylesheetRequests.length < 10) {
						const record = {resourceType:request.resourceType(),continued:false,status:null,finished:false,failure:null,failedBeforeContinue:false,cacheControl:null};
						stylesheetRequests.push(record);
						stylesheetRecords.set(request, record);
					}
					await held;
					const record = stylesheetRecords.get(request);
					if (record) record.continued = true;
				}
				return route.continue();
			});
			const page = await context.newPage();
			const errors = [];
			page.on('pageerror', error => errors.push(error.message));
			try {
				await page.goto(origin + '/?page_id=' + fixture.about + '&skye_critical_browser=positive', {waitUntil: 'domcontentloaded'});
				assert.ok(await page.locator('[id="skye/css-critical-inline-css"]').count(), 'The positive page uses the actual generated critical bytes.');
				assert.equal(await page.locator('link[data-skye-after-paint="1"]').count(), 1, 'Exactly one complete native style is pending.');
				assert.ok(requests.some(url => url.endsWith(fullSuffix)), 'The complete native stylesheet request is in flight.');
				const before = await page.evaluate(() => {
					const heading = document.querySelector('h1');
					const box = heading.getBoundingClientRect();
					return {x:box.x, y:box.y, width:box.width, height:box.height, font:getComputedStyle(heading).fontSize, footer:document.querySelector('footer').getBoundingClientRect().top, viewport:innerHeight};
				});
				assert.ok(before.footer >= before.viewport, 'The controlled viewport excludes arbitrary footer content.');
				if (width === 1440) {
					const positions = await page.locator('.navbar-nav > li').evaluateAll(items => items.map(item => item.getBoundingClientRect().y));
					assert.ok(positions.every(y => Math.abs(y - positions[0]) < 1), 'Visible desktop siblings occupy one horizontal row, not merely a flex container.');
				}
				await capturePendingSystemFontViewport(page, path.join(output, 'pending-' + width + '.png'));
				release();
				await page.evaluate(() => window.skyeCriticalStyles.ready);
				await page.waitForLoadState('networkidle');
				const after = await page.locator('h1').boundingBox();
				for (const key of ['x','y','width','height']) assert.ok(Math.abs(before[key] - after[key]) < 1, 'Critical/full heading geometry stays stable: ' + key);
				assert.equal(await page.locator('h1').evaluate(node => getComputedStyle(node).fontSize), before.font, 'Typography stays stable.');
				assert.equal(await page.locator('link[rel="stylesheet"]').evaluateAll((links, suffix) => links.filter(link => link.href.endsWith(suffix)).length, fullSuffix), 1, 'The native complete stylesheet exists exactly once after promotion.');
				const attemptCount = requests.filter(url => url.endsWith(fullSuffix)).length;
				const outcomes = JSON.stringify({engine,version:result.version,width,attemptCount,requests:stylesheetRequests});
				assert.equal(stylesheetRequests.length, attemptCount, 'Every CSS attempt has a tracked outcome, including beyond the diagnostic cap. ' + outcomes);
				const completed = stylesheetRequests.filter(record => record.resourceType === 'stylesheet' && record.continued && record.status === 200 && record.finished && record.failure === null && !record.failedBeforeContinue);
				assert.equal(completed.length, 1, 'Exactly one complete HTTP 200 CSS transfer succeeds. ' + outcomes);
				assert.ok(stylesheetRequests.every(record => completed.includes(record) || (record.failedBeforeContinue && record.status === null && !record.finished && typeof record.failure === 'string' && record.failure.length > 0)), 'Any other attempt was cancelled before network release without a response or completed transfer. ' + outcomes);
				assert.ok(await page.locator('link[rel="stylesheet"]').evaluateAll((links, suffix) => {
					const link = links.find(item => item.href.endsWith(suffix));
					return Boolean(link && link.sheet && link.sheet.ownerNode === link && link.sheet.cssRules.length > 0);
				}, fullSuffix), 'The original native link owns a loaded, parsed CSS stylesheet.');
				assert.equal(requests.filter(url => url.endsWith(mobileSuffix)).length, 0, 'The promoted mobile alias does not trigger a second complete download.');
				assert.deepEqual(errors, [], 'The positive path has no JavaScript exception.');
				await page.screenshot({path:path.join(output, 'complete-' + width + '.png')});
				result.checks += width === 1440 ? 17 : 16;
				result.cases.push({name:'delayed-complete-css',width,requests:requests.filter(url=>url.includes('/dist/')),stylesheetRequests,before,after});
			} finally { release(); await context.close(); }
		}
		const normal = await browser.newContext();
		await normal.route('**/*', route => route.request().url().startsWith(origin + '/') ? route.continue() : route.abort());
		const page = await normal.newPage();
		await page.goto(origin + '/?page_id=' + fixture.about + '&skye_critical_browser=late-optout', {waitUntil:'networkidle'});
		assert.equal(await page.locator('link[data-skye-after-paint]').count(), 0, 'A late refusal keeps native blocking link output.');
		assert.equal(await page.locator('[id="skye/css-critical-inline-css"]').count(), 0, 'A refused request prints no critical alias.');
		result.checks += 2;
		await normal.close();
		const nojs = await browser.newContext({javaScriptEnabled:false,viewport:{width:390,height:844}});
		await nojs.route('**/*', route => route.request().url().startsWith(origin + '/') ? route.continue() : route.abort());
		const nojsPage = await nojs.newPage();
		await nojsPage.goto(origin + '/?page_id=' + fixture.about + '&skye_critical_browser=positive', {waitUntil:'networkidle'});
		assert.ok(await nojsPage.locator('link[rel="stylesheet"]').count(), 'No-JavaScript receives the real native stylesheet fallback.');
		assert.ok(await nojsPage.locator('.skye-no-script-navigation a').first().isVisible(), 'No-JavaScript navigation remains usable on the admitted test viewport.');
		result.checks += 2;
		await nojs.close();
		fs.writeFileSync(path.join(output,'result.json'),JSON.stringify(result,null,2)+'\n');
		console.log(JSON.stringify({status:'pass',...result},null,2));
	} finally {
		try {
			if (browser) await browser.close();
		} finally {
			if (fs.existsSync(target) && fs.readFileSync(target).equals(source)) fs.unlinkSync(target);
		}
	}
}
run().catch(error => {
	console.error(error);
	annotateGitHubError(error);
	process.exitCode = 1;
});
