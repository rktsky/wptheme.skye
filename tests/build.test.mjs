/** Exercise real compilation, conservative transforms, publication and watcher recovery. */
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import path from 'node:path';
import os from 'node:os';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import { build, watchBuild } from '../build.mjs';
const require = createRequire(import.meta.url);
const { deriveMobile } = require('../tools/build/mobile-css.cjs');
const { deriveCritical } = require('../tools/build/critical-hero.cjs');
const { classesFromSource } = require('../tools/build/critical-sources.cjs');
const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));

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
	assert.deepEqual(classesFromSource('<header class="nav <?php echo $state; ?> fixed">'), ['fixed','nav']);
});

/** Wait for an observed output contract, with a bounded failure rather than a timing assumption. */
async function until(predicate, timeout = 15000) {
	const start = Date.now();
	while (!(await predicate())) {
		if (Date.now() - start > timeout) throw new Error('Timed out waiting for actual build publication.');
		await new Promise(resolve => setTimeout(resolve, 25));
	}
}

test('clean locked assets, failed source and source-watch recovery preserve immutable publication', { timeout: 90000 }, async () => {
	const fixture = await fs.mkdtemp(path.join(os.tmpdir(), 'skye-build-test-'));
	let watcher;
	try {
		for (const name of ['assets', 'components', 'lib', 'tools/build', 'node_modules']) {
			await fs.cp(path.join(root, name), path.join(fixture, name), { recursive: true });
		}
		await fs.copyFile(path.join(root, 'base.php'), path.join(fixture, 'base.php'));
		const initialConfigFile = path.join(fixture, 'assets/manifest.json');
		const initialConfig = JSON.parse(await fs.readFile(initialConfigFile, 'utf8'));
		initialConfig.performance.icons = ['solid/arrow-right'];
		await fs.writeFile(initialConfigFile, JSON.stringify(initialConfig));
		const manifest = await build(fixture);
		assert.equal(manifest._performance.version, 1);
		assert.equal(manifest._licenses.length, 2);
		assert.ok(manifest._performance.icons['solid/arrow-right'].paths.length > 0);
		for (const license of manifest._licenses) assert.ok((await fs.stat(path.join(fixture, 'dist', license))).size > 0);
		assert.ok(!Object.keys(manifest).some(name => name.includes('.gitkeep') || name.includes('.DS_Store')));
		assert.ok(manifest._performance.critical.sources.every(source => /^[a-f0-9]{64}$/.test(source.sha256)));
		assert.match(manifest._performance.critical.build_hash, /^[a-f0-9]{64}$/);
		assert.ok((await fs.stat(path.join(fixture,'dist',manifest['main.js']))).size > 0);
		assert.ok((await fs.stat(path.join(fixture,'dist',manifest['main-mobile.css']))).size < (await fs.stat(path.join(fixture,'dist',manifest['main.css']))).size);
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
		watcher = await watchBuild(fixture);
		await new Promise(resolve => watcher.once('ready', resolve));
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
