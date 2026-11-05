/** Compile immutable assets and atomically publish one verified manifest. @since 3.0.0 */
import fs from 'node:fs/promises';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';
import * as sass from 'sass';
import { rollup } from 'rollup';
import nodeResolve from '@rollup/plugin-node-resolve';
import { minify } from 'terser';
import { watch } from 'chokidar';

const require = createRequire(import.meta.url);
const { deriveMobile } = require('./tools/build/mobile-css.cjs');
const { deriveCritical } = require('./tools/build/critical-hero.cjs');
const { readConfiguration, classesFromSource } = require('./tools/build/critical-sources.cjs');
const { extractBranding } = require('./tools/build/branding.cjs');
const defaultRoot = path.dirname(fileURLToPath(import.meta.url));
const hash = value => crypto.createHash('sha256').update(value).digest('hex');

/**
 * Bundle the actual browser imports without introducing an HTTP development server.
 *
 * The original Bootstrap/Floating UI modules provide physical ES module paths.
 * Unexpected warnings fail publication instead of producing external globals.
 * Output retains the syntax supported by Bootstrap 6's documented browser floor;
 * Terser is a minifier and does not promise an ES2020 downlevel transformation.
 *
 * @param {string} source Absolute owned entry point inside the checked theme.
 * @return {Promise<string>} One minified standalone browser program with legal comments.
 * @since 3.0.0
 */
async function bundleScript(source) {
	const bundle = await rollup({
		input: source,
		plugins: [nodeResolve({browser: true, mainFields: ['module', 'main']})],
		onwarn: warning => { throw new Error(warning.message); },
	});
	try {
		const {output} = await bundle.generate({format: 'iife'});
		if (output.length !== 1 || output[0].type !== 'chunk' || output[0].imports.length || output[0].dynamicImports.length) {
			throw new Error('Browser entries must produce one complete standalone program.');
		}
		const result = await minify(output[0].code, {ecma: 2022, compress: true, mangle: true, format: {comments: 'some'}});
		if (typeof result.code !== 'string') throw new Error('The browser minifier produced no program.');
		return result.code + '\n';
	} finally {
		await bundle.close();
	}
}

/** Resolve owned input files and reject symlink escapes before reading bytes. */
async function inside(root, relative) {
	if (typeof relative !== 'string' || !relative || path.isAbsolute(relative) || relative.split(/[\\/]/).includes('..')) throw new Error('Asset paths must be relative.');
	const base = await fs.realpath(root);
	const resolved = await fs.realpath(path.resolve(base, relative));
	if (!resolved.startsWith(base + path.sep)) throw new Error('Asset input escapes its root.');
	return resolved;
}

/** Visit real files deterministically; external or cyclic symlinks are refused. */
async function files(root, relative, visited = new Set()) {
	const resolved = await inside(root, relative);
	if (visited.has(resolved)) return [];
	visited.add(resolved);
	if (!(await fs.stat(resolved)).isDirectory()) return [resolved];
	const result = [];
	for (const entry of (await fs.readdir(resolved)).sort()) {
		if (entry.startsWith('.')) continue;
		result.push(...await files(root, path.relative(root, path.join(resolved, entry)), visited));
	}
	return result;
}

/** Create output directories one component at a time without following a symlink. */
async function outputDirectory(root, relative) {
	let directory = root;
	for (const part of relative.split(path.sep).filter(Boolean)) {
		if (part === '..' || part === '.') throw new Error('Invalid output directory.');
		directory = path.join(directory, part);
		try { await fs.mkdir(directory); } catch (error) { if (error.code !== 'EEXIST') throw error; }
		const info = await fs.lstat(directory);
		if (!info.isDirectory() || info.isSymbolicLink()) throw new Error('Build output directories must not be symlinks.');
	}
	return directory;
}

/** Derive trusted free-provider SVG records; never copy Pro glyphs or user SVG. */
async function iconRecords(root, configuration) {
	const names = new Set(configuration.icons);
	const critical = readConfiguration(configuration.critical, root);
	for (const token of critical.classes) {
		if (/^fa-[a-z0-9-]+$/.test(token) && !['fa-solid', 'fa-regular', 'fa-brands'].includes(token)) names.add('solid/' + token.slice(3));
	}
	const records = {};
	for (const name of [...names].sort()) {
		if (!/^(solid|regular|brands)\/[a-z0-9]+(?:-[a-z0-9]+)*$/.test(name)) throw new Error('Invalid icon subset key.');
		let xml;
		try { xml = await fs.readFile(await inside(root, `node_modules/@fortawesome/fontawesome-free/svgs/${name}.svg`), 'utf8'); }
		catch (error) { if (error.code === 'ENOENT') continue; throw error; }
		const box = /viewBox="(0 0 [1-9][0-9]* [1-9][0-9]*)"/.exec(xml);
		const glyphs = [...xml.matchAll(/<path\b[^>]*\bd="([0-9a-zA-Z.,\s-]+)"[^>]*\/?\s*>/g)];
		if (!box || !glyphs.length || glyphs.length > 8) throw new Error('Unsupported provider SVG geometry.');
		records[name] = { view_box: box[1], paths: glyphs.map(glyph => ({ d: glyph[1] })) };
	}
	return records;
}

/** Validate the owned configuration once; malformed input never reaches publication. */
function validate(config) {
	if (!config.styles || !config.scripts || !config.performance ||
		!Number.isInteger(config.performance.mobileBreakpoint) || config.performance.mobileBreakpoint < 1 ||
		!Array.isArray(config.performance.icons) || !Array.isArray(config.performance.fonts)) throw new Error('Invalid asset manifest configuration.');
	if (!/^[a-z0-9-]+$/.test(config.performance.critical.id)) throw new Error('Invalid critical layout contract ID.');
	for (const [name, entry] of [...Object.entries(config.styles), ...Object.entries(config.scripts)]) {
		if (!/^[a-z0-9-]+\.(css|js)$/.test(name) || typeof entry !== 'string') throw new Error('Invalid asset entry.');
	}
	if (config.branding && (!config.branding.source || !Object.prototype.hasOwnProperty.call(config.styles, config.branding.source))) throw new Error('Branding must refer to a declared stylesheet.');
}

/** Build without touching live references until every input/transform succeeds. */
export async function build(root = defaultRoot) {
	root = await fs.realpath(root);
	const lock = path.join(root, '.skye-build.lock');
	try { await fs.mkdir(lock); }
	catch (error) { if (error.code === 'EEXIST') throw new Error('Another build owns .skye-build.lock; do not run concurrent builds.'); throw error; }
	const staging = path.join(root, `.skye-build-${process.pid}-${crypto.randomBytes(5).toString('hex')}`);
	try {
		await fs.writeFile(path.join(lock, 'owner.json'), JSON.stringify({ pid: process.pid, started: new Date().toISOString() }));
		await fs.mkdir(staging);
		const config = JSON.parse(await fs.readFile(await inside(root, 'assets/manifest.json'), 'utf8'));
		validate(config);
		const outputs = new Map();
		const manifest = {};
		const critical = readConfiguration(config.performance.critical, root);
		// Load the unchanged native compiler only for an actual build, keeping pure
		// manifest consumers independent of a different host's native architecture.
		const {transform} = require('lightningcss');
		const targets = {chrome: 130 << 16, edge: 130 << 16, firefox: 132 << 16, safari: 18 << 16, ios_saf: 18 << 16};
		const optimize = css => {
			const result = transform({filename: 'skye.css', code: Buffer.from(css), minify: true, errorRecovery: false, targets});
			if (result.warnings.length) throw new Error(result.warnings.map(warning => warning.message).join('\n'));
			return Buffer.from(result.code).toString();
		};
		const add = (name, directory, contents) => {
			const parsed = path.parse(name);
			const filename = `${parsed.name}-${hash(contents).slice(0, 16)}${parsed.ext}`;
			const relative = `${directory}/${filename}`;
			outputs.set(relative, contents);
			manifest[name] = relative;
		};
		for (const directory of ['fonts', 'images']) {
			for (const filename of await files(root, `assets/${directory}`)) {
				const relative = path.relative(path.join(root, 'assets'), filename).split(path.sep).join('/');
				const contents = await fs.readFile(filename);
				const info = path.parse(relative);
				const target = `${info.dir}/${info.name}-${hash(contents).slice(0, 16)}${info.ext}`;
				outputs.set(target, contents); manifest[relative] = target;
			}
		}
		for (const filename of await files(root, 'node_modules/@fortawesome/fontawesome-free/webfonts')) {
			const contents = await fs.readFile(filename);
			const info = path.parse(filename);
			const relative = `fonts/${info.base}`;
			const target = `fonts/${info.name}-${hash(contents).slice(0, 16)}${info.ext}`;
			outputs.set(target, contents); manifest[relative] = target;
		}
		for (const [name, entry] of Object.entries(config.styles)) {
			const source = await inside(root, `assets/${entry}`);
			const compiled = sass.compile(source, { loadPaths: [path.join(root, 'node_modules')], quietDeps: true, style: 'compressed' });
			const css = compiled.css.replace(/url\((["']?)(\.\.\/(?:fonts|images)\/[^)'"?]+)\1\)/g, (original, quote, url) => manifest[url.slice(3)] ? `url(${quote}../${manifest[url.slice(3)]}${quote})` : original);
			if (config.branding && name === config.branding.source) manifest._branding = extractBranding(css, config.branding.colors);
			add(name, 'styles', await optimize(css));
			if (name === 'main.css') {
				add('main-mobile.css', 'styles', await optimize(deriveMobile(css, source, config.performance.mobileBreakpoint)));
				add('main-critical.css', 'styles', await optimize(deriveCritical(css, source, critical)));
			}
		}
		for (const [name, entry] of Object.entries(config.scripts)) {
			const source = await inside(root, `assets/${entry}`);
			let contents;
			if (name === 'critical-form-gate.js') {
				contents = await fs.readFile(source, 'utf8');
				// GF strips newlines: only block comments may precede its native body.
				if ((contents.match(/\/\*__SKYE_NATIVE_FORM_SCRIPT__\*\//g) || []).length !== 1 || contents.includes('//')) throw new Error('Invalid native form gate template.');
			} else {
				contents = await bundleScript(source);
			}
			add(name, 'scripts', contents);
		}
		manifest._licenses = [];
		for (const [provider, filename] of [
			['bootstrap', 'node_modules/bootstrap/LICENSE'],
			['fontawesome', 'node_modules/@fortawesome/fontawesome-free/LICENSE.txt'],
			['floating-ui-core', 'node_modules/@floating-ui/core/LICENSE'],
			['floating-ui-dom', 'node_modules/@floating-ui/dom/LICENSE'],
			['floating-ui-utils', 'node_modules/@floating-ui/utils/LICENSE'],
		]) {
			const license = await fs.readFile(await inside(root, filename));
			const target = `licenses/${provider}-${hash(license).slice(0, 16)}.txt`;
			outputs.set(target, license); manifest._licenses.push(target);
		}
		for (const font of config.performance.fonts) {
			if (!font || typeof font.file !== 'string' || !/^fonts\/[a-zA-Z0-9_./-]+\.woff2$/.test(font.file) || !manifest[font.file]) throw new Error('Configured preload font is not a published own WOFF2 file.');
		}
		manifest._performance = { version: 1, mobileBreakpoint: config.performance.mobileBreakpoint,
			critical: { id: critical.id, build_hash: hash(JSON.stringify(critical) + manifest['main.css']), sources: critical.source_fingerprints, classes: critical.classes, css: manifest['main-critical.css'] },
			icons: await iconRecords(root, config.performance), fonts: config.performance.fonts };
		for (const [relative, contents] of outputs) {
			const target = path.join(staging, relative);
			await fs.mkdir(path.dirname(target), { recursive: true });
			await fs.writeFile(target, contents);
		}
		const dist = path.join(root, 'dist');
		await outputDirectory(root, 'dist');
		for (const [relative, contents] of outputs) {
			const target = path.join(dist, relative);
			await outputDirectory(dist, path.dirname(relative));
			try { await fs.link(path.join(staging, relative), target); }
			catch (error) {
				if (error.code !== 'EEXIST') throw error;
				if ((await fs.lstat(target)).isSymbolicLink()) throw new Error('Build output files must not be symlinks.');
				if (hash(await fs.readFile(target)) !== hash(contents)) {
					throw new Error('Immutable asset collision.');
				}
			}
		}
		await fs.writeFile(path.join(dist, `assets-${process.pid}.tmp`), JSON.stringify(manifest, null, '\t') + '\n');
		await fs.rename(path.join(dist, `assets-${process.pid}.tmp`), path.join(dist, 'assets.json'));
		return manifest;
	} finally {
		await fs.rm(staging, { recursive: true, force: true });
		await fs.rm(lock, { recursive: true, force: true });
	}
}

/** Serialize source events; a failed rebuild leaves the last complete manifest live. */
export async function watchBuild(root = defaultRoot) {
	let running = false;
	let queued = false;
	let closing = false;
	const run = async () => {
		if (closing) return;
		queued = true;
		if (running) return;
		running = true;
		while (queued) {
			queued = false;
			try { const manifest = await build(root); console.log(`Built ${Object.values(manifest).filter(value => typeof value === 'string').length + (manifest._licenses || []).length} assets.`); }
			catch (error) { console.error(error.message); }
		}
		running = false;
	};
	await run();
	const config = JSON.parse(await fs.readFile(path.join(root, 'assets/manifest.json'), 'utf8'));
	const sources = config.performance.critical.source_paths.map(value => path.join(root, value));
	const watcher = watch([path.join(root, 'assets'), ...sources, path.join(root, 'tools/build')], { ignoreInitial: true, awaitWriteFinish: { stabilityThreshold: 80, pollInterval: 20 } });
	watcher.on('all', run);
	const close = watcher.close.bind(watcher);
	watcher.close = async () => { closing = true; await close(); while (running) await new Promise(resolve => setTimeout(resolve, 10)); };
	process.once('SIGTERM', async () => { await watcher.close(); });
	return watcher;
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
	try {
		if (process.argv.includes('--watch')) await watchBuild();
		else { const manifest = await build(); console.log(`Built ${Object.values(manifest).filter(value => typeof value === 'string').length + (manifest._licenses || []).length} assets atomically.`); }
	} catch (error) { console.error(error.message); process.exitCode = 1; }
}
