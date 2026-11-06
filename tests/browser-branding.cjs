/** Exercise the compiled branding cascade against unchanged native Core styles and genuine pointer/focus states. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {spawnSync} = require('node:child_process');
const playwright = require(process.env.SKYE_PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');
const runtime = path.join(root, '.git/runtime');
const site = fs.realpathSync(process.env.SKYE_SITE_ROOT || path.join(runtime, 'site/wordpress'));
assert.ok(site.startsWith(runtime + path.sep), 'Use only this checkout’s disposable native WordPress site.');
const engine = process.env.SKYE_BROWSER || 'chromium';
assert.ok(['chromium', 'firefox', 'webkit'].includes(engine), 'Use a declared CI browser engine.');
const output = path.join(runtime, 'browser-branding-' + engine);
fs.mkdirSync(output, {recursive: true});
const manifest = JSON.parse(fs.readFileSync(path.join(root, 'dist/assets.json'), 'utf8'));
const backend = fs.readFileSync(path.join(root, 'dist', manifest['backend.css']), 'utf8');

/** Read the real PHP package’s validated variables and dependency order without saved palette/user fixtures. */
function nativePalette(primary, secondary) {
	const program = `
		$_SERVER['REQUEST_METHOD']='GET'; $_SERVER['HTTP_HOST']='127.0.0.1:8973'; $_SERVER['REQUEST_URI']='/';
		require $argv[1].'/wp-load.php';
		if(wp_get_environment_type()!=='local'||realpath(get_template_directory())!==realpath($argv[2])){throw new RuntimeException('Wrong native fixture/theme.');}
		add_filter('query',static function($sql){if(preg_match('/^\\s*(?:INSERT|REPLACE|UPDATE|DELETE|CREATE|DROP|ALTER|TRUNCATE)\\b/i',$sql)){throw new RuntimeException('No SQL writes.');}return $sql;});
		add_filter('pre_http_request',static function(){throw new RuntimeException('No HTTP.');},PHP_INT_MAX);
		require_once ABSPATH.'wp-admin/includes/class-wp-screen.php'; require_once ABSPATH.'wp-admin/includes/screen.php'; set_current_screen('dashboard');
		add_filter('skye_branding_colors',static function()use($argv){return array('primary'=>$argv[3],'secondary'=>$argv[4],'background'=>'#ffffff','text'=>'#181a30');});
		do_action('admin_enqueue_scripts','index.php');
		$variables=implode('',wp_styles()->get_data('skye/backend','after')?:array());
		ob_start();wp_styles()->do_items(array('skye/backend'));ob_end_clean();
		echo wp_json_encode(array('variables'=>$variables,'order'=>wp_styles()->done,'wordpress'=>get_bloginfo('version')));
	`;
	const result = spawnSync(process.env.SKYE_PHP_BINARY || 'php', ['-r', program, site, root, primary, secondary], {encoding: 'utf8'});
	assert.equal(result.status, 0, result.stderr || result.stdout);
	const model = JSON.parse(result.stdout);
	assert.ok(model.order.indexOf('colors') >= 0 && model.order.indexOf('colors') < model.order.indexOf('skye/backend'), 'Native color-scheme output precedes the original package stylesheet.');
	return model;
}

const markup = `<!doctype html><html class="wp-toolbar"><head><meta charset="utf-8"></head><body class="wp-admin wp-core-ui js">
<div id="wpadminbar"><div id="wp-toolbar" class="quicklinks"><ul class="ab-top-menu"><li id="toolbar-parent" class="menupop"><a class="ab-item" href="#toolbar"><span class="ab-icon"></span><span class="ab-label">Site</span></a><div class="ab-sub-wrapper"><ul class="ab-submenu"><li><a id="toolbar-child" class="ab-item" href="#toolbar-child"><strong>Visit site</strong></a></li></ul></div></li></ul></div></div>
<div id="adminmenumain"><div id="adminmenuback"></div><div id="adminmenuwrap"><ul id="adminmenu"><li id="menu-parent" class="menu-top wp-has-submenu wp-not-current-submenu"><a class="menu-top" href="#menu"><div class="wp-menu-image dashicons-before dashicons-admin-tools"></div><div class="wp-menu-name">Tools</div></a><ul class="wp-submenu"><li class="wp-submenu-head">Tools</li><li><a id="menu-child" href="#menu-child">Available tools</a></li><li class="current"><a id="menu-current-child" href="#menu-current-child">Current child</a></li></ul></li></ul></div></div>
<div id="wpcontent"><div class="wrap"><h1>Native UI fixture</h1><p><a id="plain" href="#plain">Ordinary link</a> <a class="row-title" id="row-title" href="#row">Native row title</a></p><div class="row-actions"><span class="trash"><a id="trash" href="#trash">Trash</a></span></div><div class="submitbox"><a id="delete" class="submitdelete" href="#delete">Delete permanently</a></div><button type="button" id="delete-button" class="button-link button-link-delete">Delete</button><button type="button" class="button-link" id="button-link">Native link button</button><a id="tab" class="nav-tab nav-tab-active" href="#tab">Active tab</a><div class="notice notice-error" id="notice"><p>Native error</p></div><a id="skip" class="screen-reader-shortcut" href="#plain">Skip</a></div></div></body></html>`;

/** Read actual computed foreground/background/focus geometry rather than matching source selector strings. */
async function colors(page, selector, pseudo) {
	return page.locator(selector).evaluate((node, pseudo) => {
		const css = getComputedStyle(node, pseudo || null);
		return {color: css.color, background: css.backgroundColor, border: css.borderLeftColor, shadow: css.boxShadow, outline: css.outlineStyle, outlineWidth: css.outlineWidth};
	}, pseudo);
}

/** Wait for Core's short color transition to settle instead of disabling native interaction styling. */
async function settled(page, selector, property, expected) {
	await page.waitForFunction(({selector, property, expected}) => getComputedStyle(document.querySelector(selector))[property] === expected, {selector, property, expected});
	await page.evaluate(() => Promise.all(document.getAnimations().map(animation => animation.finished.catch(() => {}))));
}

async function run() {
	const launch = {headless: true};
	if (process.env.SKYE_BROWSER_EXECUTABLE) launch.executablePath = process.env.SKYE_BROWSER_EXECUTABLE;
	const browser = await playwright[engine].launch(launch);
	const report = {engine, version: browser.version(), platform: process.platform, checks: 0, cases: []};
	const check = (value, message) => { assert.ok(value, message); report.checks++; };
	try {
		for (const [primary, secondary] of [['#dd8686', '#181a30'], ['#315f5d', '#d6d0c4']]) {
			const native = nativePalette(primary, secondary);
			for (const scheme of ['fresh', 'modern', 'blue']) {
				const context = await browser.newContext({viewport: {width: 1200, height: 900}, reducedMotion: 'reduce'});
				try {
					const page = await context.newPage();
					await page.route('**/*', route => route.abort());
					await page.setContent(markup);
					await page.evaluate(version => document.body.classList.add('branch-' + version.split('.').slice(0, 2).join('-')), native.wordpress);
					await page.evaluate(scheme => document.body.classList.add('admin-color-' + scheme), scheme);
					for (const filename of ['wp-includes/css/buttons.css', 'wp-includes/css/dashicons.css', 'wp-admin/css/common.css', 'wp-admin/css/admin-menu.css', 'wp-includes/css/admin-bar.css']) {
						await page.addStyleTag({content: fs.readFileSync(path.join(site, filename), 'utf8')});
					}
					if (scheme !== 'fresh') await page.addStyleTag({content: fs.readFileSync(path.join(site, 'wp-admin/css/colors', scheme, 'colors.css'), 'utf8')});
					const baseStyles = path.join(site, 'wp-includes/css/dist/base-styles/admin-schemes.css');
					if (fs.existsSync(baseStyles)) await page.addStyleTag({content: fs.readFileSync(baseStyles, 'utf8')});
					await page.evaluate(() => Promise.all(document.getAnimations().map(animation => animation.finished.catch(() => {}))));
					const semantic = {};
					for (const selector of ['#trash', '#delete', '#delete-button', '#tab', '#notice']) semantic[selector] = await colors(page, selector);
					await page.locator('#button-link').focus();
					await page.evaluate(() => Promise.all(document.getAnimations().map(animation => animation.finished.catch(() => {}))));
					const nativeButtonFocus = await colors(page, '#button-link');
					await page.locator('#plain').focus();
					const nativeFocus = await colors(page, '#plain');
					await page.addStyleTag({content: backend + native.variables});
					await page.evaluate(() => document.body.classList.add('skye-branding'));
					const expected = await page.evaluate(() => {
						const tokens = getComputedStyle(document.body);
						const node = document.createElement('span'); document.body.append(node);
						const result = {};
						for (const key of ['primary', 'on-primary', 'secondary', 'on-secondary', 'admin-link', 'admin-link-hover']) { node.style.color = tokens.getPropertyValue('--skye-brand-' + key); result[key] = getComputedStyle(node).color; }
						node.remove(); return result;
					});
					await settled(page, '#plain', 'color', expected['admin-link-hover']);
					const focus = await colors(page, '#plain');
					check(focus.color === expected['admin-link-hover'] && focus.shadow !== 'none' && focus.outline === nativeFocus.outline && focus.outlineWidth === nativeFocus.outlineWidth, 'Link focus retains native visible geometry with a readable brand tone.');
					await page.locator('#plain').evaluate(node => node.blur());
					for (const selector of ['#plain', '#row-title']) {
						await settled(page, selector, 'color', expected['admin-link']);
						check((await colors(page, selector)).color === expected['admin-link'], 'Ordinary and native row-title links use the readable brand tone.');
						await page.locator(selector).hover();
						await settled(page, selector, 'color', expected['admin-link-hover']);
						check((await colors(page, selector)).color === expected['admin-link-hover'], 'Link hover does not return to the chosen scheme’s blue.');
						await page.mouse.move(1100, 800);
					}
					for (const selector of Object.keys(semantic)) {
						const current = await colors(page, selector);
						check(current.color === semantic[selector].color && current.border === semantic[selector].border, 'Native destructive, status and selected-tab meaning remains unchanged: ' + selector + ' ' + JSON.stringify({before: semantic[selector], after: current}));
					}
					check((await colors(page, '#button-link')).color === expected['admin-link'], 'Native link buttons consume the same public WordPress color variable.');
					await page.locator('#button-link').focus();
					await settled(page, '#button-link', 'color', expected['admin-link-hover']);
					const buttonFocus = await colors(page, '#button-link');
					if (native.wordpress.startsWith('6.8.')) check(buttonFocus.shadow.includes(expected['admin-link']) && buttonFocus.shadow.replace(/rgba?\([^)]*\)/g, 'COLOR') === nativeButtonFocus.shadow.replace(/rgba?\([^)]*\)/g, 'COLOR') && buttonFocus.outlineWidth === nativeButtonFocus.outlineWidth, 'The Core 6.8 link-button focus changes only its color while preserving the exact native 2px geometry.');
					await page.locator('#button-link').evaluate(node => node.blur());
					await page.locator('#menu-parent > a').focus();
					await settled(page, '#menu-parent > a', 'backgroundColor', expected.primary);
					check((await colors(page, '#menu-parent > a')).background === expected.primary, 'Menu keyboard focus uses the branded surface.');
					await page.locator('#menu-parent > a').evaluate(node => node.blur());
					await page.locator('#menu-parent').evaluate(node => node.classList.add('opensub'));
					await page.locator('#menu-child').hover();
					await settled(page, '#menu-parent > a', 'backgroundColor', expected.primary);
					await settled(page, '#menu-child', 'color', expected['on-secondary']);
					check(await page.locator('#menu-parent > a').evaluate(node => !node.matches(':hover') && !node.matches(':focus') && node.parentElement.matches(':hover')), 'The regression exercises the actual open-parent state after the pointer leaves its root anchor.');
					const open = await colors(page, '#menu-parent > a');
					check(open.background === expected.primary && open.color === expected['on-primary'] && (await colors(page, '#menu-parent .wp-menu-image', '::before')).color === expected['on-primary'], 'Open-parent label, icon and background remain coherent instead of reverting to Core blue.');
					check((await colors(page, '#menu-child')).background === expected.secondary && (await colors(page, '#menu-child')).color === expected['on-secondary'], 'Hovered submenu items retain a readable paired brand foreground/surface.');
					await page.locator('#menu-parent').evaluate(node => { node.classList.remove('opensub'); node.classList.add('current'); });
					await page.mouse.move(1100, 800);
					await settled(page, '#menu-parent > a', 'backgroundColor', expected.primary);
					check((await colors(page, '#menu-parent > a')).background === expected.primary && (await colors(page, '#menu-current-child')).color === expected['on-secondary'], 'Current root and child selections retain their branded states.');
					await page.locator('#toolbar-parent > a').focus();
					await settled(page, '#toolbar-parent > a', 'backgroundColor', expected.primary);
					await settled(page, '#toolbar-parent .ab-label', 'color', expected['on-primary']);
					check((await colors(page, '#toolbar-parent > a')).background === expected.primary && (await colors(page, '#toolbar-parent .ab-label')).color === expected['on-primary'] && (await colors(page, '#toolbar-parent .ab-icon', '::before')).color === expected['on-primary'], 'Adminbar root keyboard focus includes its native label and icon.');
					await page.locator('#toolbar-parent > a').evaluate(node => node.blur());
					await page.locator('#toolbar-parent').evaluate(node => node.classList.add('hover'));
					await page.locator('#toolbar-child').hover();
					await settled(page, '#toolbar-child strong', 'color', expected['on-secondary']);
					check((await colors(page, '#toolbar-parent > a')).background === expected.primary && (await colors(page, '#toolbar-child')).background === expected.secondary && (await colors(page, '#toolbar-child strong')).color === expected['on-secondary'], 'Adminbar open-parent and nested hover retain readable, independent native states.');
					await page.screenshot({path: path.join(output, native.wordpress + '-' + scheme + '-' + primary.slice(1) + '.png'), fullPage: true});
					report.cases.push({wordpress: native.wordpress, scheme, primary, nativeStyleOrder: native.order});
				} finally { await context.close(); }
			}
		}
	} finally { await browser.close(); }
	fs.writeFileSync(path.join(output, 'results-' + report.cases[0].wordpress + '.json'), JSON.stringify(report, null, 2) + '\n');
	console.log(JSON.stringify(report));
}
run().catch(error => { console.error(error.stack || error.message); process.exitCode = 1; });
