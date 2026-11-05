/** Exercise real compilation, conservative transforms, publication and watcher recovery. */
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import path from 'node:path';
import os from 'node:os';
import crypto from 'node:crypto';
import { execFile, spawn } from 'node:child_process';
import { promisify } from 'node:util';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
const require = createRequire(import.meta.url);
const { deriveMobile } = require('../tools/build/mobile-css.cjs');
const { deriveCritical } = require('../tools/build/critical-hero.cjs');
const { classesFromSource } = require('../tools/build/critical-sources.cjs');
const { extractBranding } = require('../tools/build/branding.cjs');
const postcss = require('postcss');
const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const compilerNode = process.env.SKYE_BUILD_NODE || process.execPath;
const execute = promisify(execFile);
const reportPrefix = '__SKYE_BUILD_TEST__';
let compilerVersion;

/** Keep the modern test runner distinct from the executable that actually compiles. */
async function verifyCompiler(report) {
	assert.match(report.node, /^v[0-9]+\.[0-9]+\.[0-9]+$/);
	if (path.isAbsolute(compilerNode)) assert.equal(await fs.realpath(report.executable), await fs.realpath(compilerNode));
	if (compilerVersion) assert.equal(report.node, compilerVersion);
	else {
		compilerVersion = report.node;
		console.log(`Build compiler ${report.node}; node:test harness ${process.version}.`);
	}
}

/** Execute the real fixture compiler with structured arguments and its own error exit. */
async function build(fixture) {
	const program = `
		import {pathToFileURL} from 'url';
		try {
			const {build} = await import(pathToFileURL(process.argv[2]).href);
			const manifest = await build(process.argv[1]);
			console.log(${JSON.stringify(reportPrefix)} + JSON.stringify({node:process.version,executable:process.execPath,manifest}));
		} catch (error) { console.error(error.stack || error.message); process.exitCode = 1; }
	`;
	const {stdout} = await execute(compilerNode, ['--input-type=module', '--eval', program, fixture, path.join(fixture, 'build.mjs')], {cwd:root, timeout:60000, killSignal:'SIGKILL', maxBuffer:16*1024*1024});
	const line = stdout.split('\n').find(value => value.startsWith(reportPrefix));
	assert.ok(line, 'The actual compiler must report successful publication.');
	const report = JSON.parse(line.slice(reportPrefix.length));
	await verifyCompiler(report);
	return report.manifest;
}

/** Bound child handshakes and shutdown without leaving a timer alive on success. */
async function bounded(promise, milliseconds, message) {
	let timer;
	try {
		return await Promise.race([promise, new Promise((resolve, reject) => { timer = setTimeout(() => reject(new Error(message)), milliseconds); })]);
	} finally { clearTimeout(timer); }
}

/** Exercise the real watcher in the selected compiler and acknowledge actual readiness. */
async function watchBuild(fixture) {
	const program = `
		import {pathToFileURL} from 'url';
		let watcher;
		let closing = false;
		const send = value => { if (process.connected) process.send(value, () => {}); };
		const fail = error => { send({type:'error',message:error.stack || error.message}); process.exitCode = 1; if (process.connected) process.disconnect(); };
		process.on('message', async message => {
			if (!message || message.type !== 'close' || closing) return;
			closing = true;
			try { await watcher.close(); send({type:'closed'}); if (process.connected) process.disconnect(); }
			catch (error) { fail(error); }
		});
		try {
			const {watchBuild} = await import(pathToFileURL(process.argv[2]).href);
			watcher = await watchBuild(process.argv[1]);
			await new Promise(resolve => watcher.once('ready', resolve));
			send({type:'ready',node:process.version,executable:process.execPath});
		} catch (error) { fail(error); }
	`;
	const child = spawn(compilerNode, ['--input-type=module', '--eval', program, fixture, path.join(fixture, 'build.mjs')], {cwd:root, stdio:['ignore','pipe','pipe','ipc']});
	let ended = false;
	let acknowledgedClose = false;
	let diagnostic = '';
	for (const stream of [child.stdout, child.stderr]) stream.on('data', bytes => { diagnostic = (diagnostic + bytes.toString()).slice(-65536); });
	const exit = new Promise(resolve => {
		child.once('error', error => { ended = true; resolve({error}); });
		child.once('close', (code, signal) => { ended = true; resolve({code,signal}); });
	});
	const close = async (force = false) => {
		if (!ended) {
			if (!force && child.connected) child.send({type:'close'}, () => {});
			else child.kill('SIGTERM');
		}
		let result;
		try { result = await bounded(exit, 10000, 'Compiler watcher did not close.'); }
		catch (error) {
			child.kill('SIGKILL');
			await bounded(exit, 5000, 'Compiler watcher did not exit after SIGKILL.');
			throw error;
		}
		if (!force && (result.error || result.code !== 0 || !acknowledgedClose)) throw new Error('Compiler watcher failed to confirm a clean close: ' + (result.error?.message || result.signal || result.code) + '\n' + diagnostic);
	};
	try {
		const ready = new Promise((resolve, reject) => {
			child.on('message', message => {
				if (message?.type === 'ready') resolve(message);
				else if (message?.type === 'error') {
					diagnostic = (diagnostic + '\n' + message.message).slice(-65536);
					reject(new Error(message.message));
				}
				else if (message?.type === 'closed') acknowledgedClose = true;
			});
			exit.then(result => reject(new Error('Compiler watcher exited before ready: ' + (result.error?.message || result.signal || result.code) + '\n' + diagnostic)));
		});
		await verifyCompiler(await bounded(ready, 30000, 'Compiler watcher did not become ready.'));
		return {close};
	} catch (error) {
		await close(true);
		throw error;
	}
}

test('brand colors follow existing unconditional CSS tokens without parsing frontend markup', () => {
	const mapping = {primary:'--brand-main', secondary:'--brand-second', background:'--brand-paper', text:'--brand-ink'};
	const source = '/*! original source */@layer colors;@layer colors{:root{--brand-main:#abc;--brand-second:#123456;--brand-paper:#fff;--brand-ink:#171819}.content{content:";{}\\\""}}';
	assert.deepEqual(extractBranding(source, mapping), {version:1,colors:{primary:'#aabbcc',secondary:'#123456',background:'#ffffff',text:'#171819'}});
	assert.equal(extractBranding(source.replace('#abc', '#Dd8686'), mapping).colors.primary, '#dd8686', 'Changing the actual website token changes the derived palette.');
	assert.equal(extractBranding(source + ':root{--brand-main:#aabbcc}', mapping).colors.primary, '#aabbcc', 'Equivalent static definitions stay unambiguous across layers.');
	assert.throws(() => extractBranding(source + ':root{--brand-main:#111111}', mapping), /Ambiguous/);
	assert.throws(() => extractBranding(source + '@layer weaker{:root{--brand-main:#111111}}', mapping), /Ambiguous/);
	assert.throws(() => extractBranding('@layer low,high;@layer high{' + source + '}@layer low{:root{--brand-main:#111111}}', mapping), /Ambiguous/);
	for (const selector of [':root[data-theme=dark]', ':ROOT', ':r\\6fot', '.section']) {
		assert.throws(() => extractBranding(source + selector + '{--brand-main:#111111}', mapping), /literal :root/);
	}
	assert.throws(() => extractBranding(source + ':root{--brand\\2d main:#111111}', mapping), /Escaped custom-property/);
	assert.throws(() => extractBranding(source + ':root{--brand-main/**/:#111111}', mapping), /Ambiguous/);
	assert.throws(() => extractBranding(source + ':root{--brand-main /* name */ : /* value */ #111111}', mapping), /Ambiguous/);
	assert.equal(extractBranding(source.replace('--brand-main:#abc', '--brand-main/**/:/* color */#abc'), mapping).colors.primary, '#aabbcc');
	assert.throws(() => extractBranding(source + '@media(prefers-color-scheme:dark){:root{--brand-main:#000}}', mapping), /Conditional/);
	assert.throws(() => extractBranding(source.replace('#abc', 'var(--other)'), mapping), /static hex/);
	assert.throws(() => extractBranding(source.replace('--brand-ink', '--different'), mapping), /Missing static root/);
	assert.throws(() => extractBranding(source, {...mapping,text:'--brand-ink;evil'}), /explicit custom-property/);
	assert.throws(() => extractBranding(source + '/*', mapping), /Incomplete/);
	assert.throws(() => extractBranding(' '.repeat(2*1024*1024+1), mapping), /bounded CSS/);
	assert.equal(source.includes('--brand-main:#abc'), true, 'Reading the palette does not rewrite the website CSS.');
});

test('mobile proof recognizes Bootstrap 6 ranges inside unconditional layers only', () => {
	const css = '@layer a{.base{display:block}@media(width>=576px){.wide{display:flex}}}@supports(display:grid){@media(width>=576px){.unknown{display:grid}}}';
	const mobile = deriveMobile(css, 'fixture.css', 576);
	assert.ok(mobile.includes('.base'));
	assert.ok(!mobile.includes('.wide'));
	assert.ok(mobile.includes('.unknown'));
	assert.ok(deriveMobile('@media (width>500px){.keep{display:block}}').includes('.keep'));
	assert.throws(() => deriveMobile('.bad{'), /Unclosed/);
});

test('critical extraction filters nested layer rules but preserves uncertain containers', () => {
	const config = { version: 1, id: 'fixture', classes: ['nav'], prefixes: [], filename: 'main-critical.css', placeholder: '__SKYE_THEME_DIST__' };
	const css = '@layer components{.nav{display:block}.unrelated{display:none}}@container(width>1px){.nav{width:1px}}';
	const critical = deriveCritical(css, 'fixture.css', config);
	assert.ok(critical.includes('.nav'));
	assert.ok(!critical.includes('.unrelated'));
	assert.ok(critical.includes('@container'));
	const ordered = deriveCritical('@layer utilities,components;@layer components{.nav{display:block}}', 'fixture.css', config);
	assert.ok(ordered.startsWith('@layer utilities,components;'), 'Global cascade ordering statements must survive extraction.');
	assert.deepEqual(classesFromSource('<header class="nav <?php echo $state; ?> fixed">'), ['fixed','nav']);
	assert.deepEqual(classesFromSource('<nav class="navbar md:navbar-expand">'), ['md:navbar-expand','navbar']);
	const responsive = deriveCritical('.md\\:navbar-expand{display:flex}', 'fixture.css', {...config, classes:['md:navbar-expand']});
	assert.ok(responsive.includes('display:flex'), 'Bootstrap 6 responsive class syntax survives discovery and selector filtering.');
});

/** Wait for an observed output contract, with a bounded failure rather than a timing assumption. */
async function until(predicate, timeout = 15000) {
	const start = Date.now();
	while (!(await predicate())) {
		if (Date.now() - start > timeout) throw new Error('Timed out waiting for actual build publication.');
		await new Promise(resolve => setTimeout(resolve, 25));
	}
}

test('clean locked assets, failed source and source-watch recovery preserve immutable publication', { timeout: 180000 }, async () => {
	const fixture = await fs.mkdtemp(path.join(os.tmpdir(), 'skye-build-test-'));
	let watcher;
	try {
		for (const name of ['assets', 'components', 'lib', 'tools/build', 'node_modules']) {
			await fs.cp(path.join(root, name), path.join(fixture, name), { recursive: true });
		}
		await fs.copyFile(path.join(root, 'base.php'), path.join(fixture, 'base.php'));
		await fs.copyFile(path.join(root, 'build.mjs'), path.join(fixture, 'build.mjs'));
		const initialConfigFile = path.join(fixture, 'assets/manifest.json');
		const initialConfig = JSON.parse(await fs.readFile(initialConfigFile, 'utf8'));
		initialConfig.performance.icons = ['solid/arrow-right'];
		await fs.writeFile(initialConfigFile, JSON.stringify(initialConfig));
		const manifest = await build(fixture);
		assert.equal(manifest._performance.version, 1);
		assert.deepEqual(manifest._branding, {version:1,colors:{primary:'#3b9280',secondary:'#bc974e',background:'#ffffff',text:'#24231d'}});
		assert.match(manifest['backend.css'], /^styles\/backend-[a-f0-9]{16}\.css$/);
		assert.ok(Array.isArray(manifest._licenses) && manifest._licenses.length >= 2);
		assert.equal(new Set(manifest._licenses).size, manifest._licenses.length);
		assert.ok(manifest._performance.icons['solid/arrow-right'].paths.length > 0);
		for (const license of manifest._licenses) {
			const fingerprint = /^licenses\/[a-z0-9-]+-([a-f0-9]{16})\.txt$/.exec(license);
			assert.ok(fingerprint, 'Every current provider license has a declared local fingerprint.');
			const bytes = await fs.readFile(path.join(fixture, 'dist', license));
			assert.ok(bytes.length > 0);
			assert.equal(crypto.createHash('sha256').update(bytes).digest('hex').slice(0, 16), fingerprint[1]);
		}
		assert.ok(!Object.keys(manifest).some(name => name.includes('.gitkeep') || name.includes('.DS_Store')));
		assert.ok(manifest._performance.critical.sources.every(source => /^[a-f0-9]{64}$/.test(source.sha256)));
		for (const model of ['lib/presentation.php', 'lib/package/MobileNavigation.php', 'lib/rocketsky/helpers.php', 'lib/titles.php', 'lib/routing.php']) {
			assert.ok(manifest._performance.critical.sources.some(source => source.path === model), 'Bind the actual first-viewport renderer: ' + model);
		}
		assert.match(manifest._performance.critical.build_hash, /^[a-f0-9]{64}$/);
		assert.ok((await fs.stat(path.join(fixture,'dist',manifest['main.js']))).size > 0);
		assert.ok((await fs.stat(path.join(fixture,'dist',manifest['main-mobile.css']))).size < (await fs.stat(path.join(fixture,'dist',manifest['main.css']))).size);
		const expectedLayers = ['colors','config','root','reboot','layout','content','forms','components','custom','helpers','utilities'];
		for (const name of ['main.css', 'main-mobile.css', 'main-critical.css']) {
			const layers = new Set();
			postcss.parse(await fs.readFile(path.join(fixture, 'dist', manifest[name]), 'utf8')).walkAtRules('layer', rule => {
				if (rule.parent.type !== 'root') return;
				for (const layer of rule.params.split(',').map(value => value.trim()).filter(Boolean)) layers.add(layer);
			});
			assert.deepEqual([...layers], expectedLayers, 'Final CSS preserves effective first-definition layer order: ' + name);
		}
		const before = await fs.readFile(path.join(fixture,'dist/assets.json'), 'utf8');
		const source = path.join(fixture, 'assets/styles/main.scss');
		const original = await fs.readFile(source, 'utf8');
		await fs.writeFile(source, original + '\n.invalid {');
		await assert.rejects(build(fixture));
		assert.equal(await fs.readFile(path.join(fixture,'dist/assets.json'), 'utf8'), before);
		await fs.writeFile(source, original);
		await fs.symlink(path.join(fixture, 'dist'), path.join(fixture, 'assets/escape'));
		const configFile = path.join(fixture, 'assets/manifest.json');
		const config = JSON.parse(await fs.readFile(configFile,'utf8'));
		config.styles['escape.css'] = 'escape/../styles/main.scss';
		await fs.writeFile(configFile, JSON.stringify(config));
		await assert.rejects(build(fixture));
		delete config.styles['escape.css'];
		await fs.writeFile(configFile, JSON.stringify(config));
		await fs.unlink(path.join(fixture, 'assets/escape')); // Restore valid source inputs before observing source changes.
		watcher = await watchBuild(fixture);
		const header = path.join(fixture,'components/header/build-test.php');
		await fs.writeFile(header, '<?php /** Build discovery fixture. */ ?><div class="skye-new-header-state"></div>');
		await until(async () => JSON.parse(await fs.readFile(path.join(fixture,'dist/assets.json'),'utf8'))._performance.critical.classes.includes('skye-new-header-state'));
		const firstHash = JSON.parse(await fs.readFile(path.join(fixture,'dist/assets.json'),'utf8'))._performance.critical.build_hash;
		await fs.appendFile(header, '\n<?php /* A behavior-relevant source change need not introduce a new class. */ ?>');
		await until(async () => JSON.parse(await fs.readFile(path.join(fixture,'dist/assets.json'),'utf8'))._performance.critical.build_hash !== firstHash);
		await watcher.close();
		watcher = null;
		await assert.rejects(fs.stat(path.join(fixture,'.skye-build.lock')), { code:'ENOENT' });
	} finally {
		if (watcher) await watcher.close();
		await fs.rm(fixture, { recursive:true, force:true });
	}
});
