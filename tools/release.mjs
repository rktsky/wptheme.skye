/** Export a clean committed source revision with exactly its verified built assets. */
import fs from 'node:fs/promises';
import path from 'node:path';
import crypto from 'node:crypto';
import { execFile, spawn } from 'node:child_process';
import { promisify } from 'node:util';
import { fileURLToPath } from 'node:url';
import { build } from '../build.mjs';

const execute = promisify(execFile);
const defaultRoot = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const digest = bytes => crypto.createHash('sha256').update(bytes).digest('hex');
const isObject = value => value !== null && typeof value === 'object' && !Array.isArray(value);

/** Accept one unambiguous local artifact path, never a wrapper or traversal. */
function relativeFile(value) {
	return typeof value === 'string' && /^[a-zA-Z0-9][a-zA-Z0-9._/-]*$/.test(value) &&
		!value.split('/').some(part => part === '' || part === '.' || part === '..');
}

/** Refuse symlinks at every component before reading a declared generated file. */
async function regularFile(root, relative) {
	if (!relativeFile(relative)) throw new Error('Invalid generated asset path.');
	let current = root;
	const parts = relative.split('/');
	for (const [index, part] of parts.entries()) {
		current = path.join(current, part);
		const info = await fs.lstat(current);
		if (info.isSymbolicLink() || (index < parts.length - 1 ? !info.isDirectory() : !info.isFile())) {
			throw new Error('Release assets must be regular local files.');
		}
	}
	return fs.readFile(current);
}

/** Read the actual build manifest and verify every referenced payload and license. */
export async function readReleaseAssets(dist) {
	const info = await fs.lstat(dist);
	if (!info.isDirectory() || info.isSymbolicLink()) throw new Error('Release dist must be a real directory.');
	const bytes = await regularFile(dist, 'assets.json');
	let manifest;
	try { manifest = JSON.parse(bytes.toString('utf8')); }
	catch { throw new Error('Invalid asset manifest JSON.'); }
	if (!isObject(manifest)) throw new Error('Invalid asset manifest object.');
	for (const key of ['main.css', 'main-mobile.css', 'main-critical.css', 'login.css', 'backend.css', 'fontawesome.css', 'main.js', 'critical-styles.js', 'critical-form-gate.js', 'mobile-navigation.js', 'mobile-navigation.css']) {
		if (typeof manifest[key] !== 'string') throw new Error('Missing required built asset: ' + key);
	}
	const performance = manifest._performance;
	const branding = manifest._branding;
	if (!isObject(branding) || branding.version !== 1 || !isObject(branding.colors) ||
		Object.keys(branding.colors).length !== 4 || ['primary', 'secondary', 'background', 'text'].some(role => !/^#[a-f0-9]{6}$/.test(branding.colors[role]))) throw new Error('Invalid branding manifest contract.');
	if (!isObject(performance) || performance.version !== 1 || !Number.isInteger(performance.mobileBreakpoint) || performance.mobileBreakpoint < 1 ||
		!isObject(performance.critical) || !/^[a-z0-9-]+$/.test(performance.critical.id) ||
		!/^[a-f0-9]{64}$/.test(performance.critical.build_hash) || performance.critical.css !== manifest['main-critical.css'] ||
		!Array.isArray(performance.critical.sources) || performance.critical.sources.length === 0 ||
		performance.critical.sources.some(source => !isObject(source) || !relativeFile(source.path) || !source.path.endsWith('.php') || !/^[a-f0-9]{64}$/.test(source.sha256)) ||
		new Set(performance.critical.sources.map(source => source.path)).size !== performance.critical.sources.length ||
		!Array.isArray(performance.critical.classes) || performance.critical.classes.some(value => typeof value !== 'string' || value === '' || /\s/.test(value)) ||
		!isObject(performance.icons) || !Array.isArray(performance.fonts)) throw new Error('Invalid performance manifest contract.');
	if (!Array.isArray(manifest._licenses) || manifest._licenses.length === 0 ||
		manifest._licenses.some(value => typeof value !== 'string' || !value.startsWith('licenses/')) ||
		['bootstrap', 'fontawesome'].some(provider => !manifest._licenses.some(value => new RegExp('^licenses/' + provider + '-[a-f0-9]{16}\\.txt$').test(value)))) throw new Error('Missing current dependency licenses.');
	for (const [name, record] of Object.entries(performance.icons)) {
		if (!/^(solid|regular|brands)\/[a-z0-9]+(?:-[a-z0-9]+)*$/.test(name) || !isObject(record) || !/^0 0 [1-9][0-9]* [1-9][0-9]*$/.test(record.view_box) ||
			!Array.isArray(record.paths) || record.paths.length === 0 || record.paths.length > 8 || record.paths.some(glyph => !isObject(glyph) || typeof glyph.d !== 'string' || !/^[0-9a-zA-Z.,\s-]+$/.test(glyph.d))) throw new Error('Invalid generated icon geometry.');
	}
	const requested = new Set(manifest._licenses);
	for (const [key, value] of Object.entries(manifest)) {
		if (key.startsWith('_')) {
			if (!['_performance', '_licenses', '_branding'].includes(key)) throw new Error('Unknown reserved manifest entry.');
			continue;
		}
		if (!relativeFile(key) || typeof value !== 'string') throw new Error('Invalid asset mapping.');
		requested.add(value);
	}
	for (const font of performance.fonts) {
		if (!isObject(font) || typeof font.file !== 'string' || !/^fonts\/[a-zA-Z0-9_./-]+\.woff2$/.test(font.file) || !relativeFile(font.file) || typeof manifest[font.file] !== 'string') throw new Error('Invalid preload font binding.');
	}
	const files = new Map([['assets.json', bytes]]);
	for (const relative of [...requested].sort()) {
		const match = typeof relative === 'string' && /-([a-f0-9]{16})\.[a-z0-9]+$/.exec(relative);
		if (!match) throw new Error('Generated assets must have content fingerprints.');
		const contents = await regularFile(dist, relative);
		if (digest(contents).slice(0, 16) !== match[1]) throw new Error('Generated asset fingerprint mismatch: ' + relative);
		if (relative.startsWith('licenses/') && contents.length === 0) throw new Error('Empty dependency license.');
		files.set(relative, contents);
	}
	return { manifest, files };
}

/** Check tracked and ignored build inputs before and after compilation. */
async function cleanRevision(root) {
	const top = (await execute('git', ['rev-parse', '--show-toplevel'], {cwd: root})).stdout.trim();
	if (await fs.realpath(top) !== root) throw new Error('Release root must be the Git checkout root.');
	const status = (await execute('git', ['status', '--porcelain=v1', '--untracked-files=all'], {cwd: root})).stdout;
	if (status !== '') throw new Error('Release requires a clean committed worktree.');
	const ignored = (await execute('git', ['ls-files', '--others', '--ignored', '--exclude-standard', '-z', '--', 'assets', 'components', 'views', 'templates', 'lib', 'tools/build'], {cwd: root})).stdout;
	if (ignored !== '') throw new Error('Ignored build inputs must be removed before release.');
	const localAttributes = (await execute('git', ['rev-parse', '--git-path', 'info/attributes'], {cwd: root})).stdout.trim();
	try {
		if ((await fs.readFile(path.resolve(root, localAttributes), 'utf8')).trim() !== '') throw new Error('Release export rules must be committed, not set in Git info/attributes.');
	} catch (error) { if (error.code !== 'ENOENT') throw error; }
	return (await execute('git', ['rev-parse', 'HEAD'], {cwd: root})).stdout.trim();
}

/** Extract only Git's committed tree, without writing a user archive file. */
async function extractRevision(root, revision, directory) {
	const tree = (await execute('git', ['ls-tree', '-rz', revision], {cwd: root, maxBuffer: 16 * 1024 * 1024})).stdout;
	for (const entry of tree.split('\0').filter(Boolean)) {
		if (!/^(?:100644|100755) blob [a-f0-9]+\t/.test(entry)) throw new Error('Release source may not contain symlinks or submodules.');
	}
	const {stdout: archive} = await execute('git', ['-c', 'core.attributesFile=/dev/null', 'archive', '--format=tar', revision], {cwd: root, env: {...process.env, GIT_ATTR_NOSYSTEM: '1'}, encoding: 'buffer', maxBuffer: 64 * 1024 * 1024});
	await new Promise((resolve, reject) => {
		const tar = spawn('tar', ['-xf', '-', '-C', directory, '--no-same-owner', '--no-same-permissions'], {stdio: ['pipe', 'ignore', 'pipe']});
		let diagnostic = '';
		tar.stderr.on('data', value => { diagnostic += value.toString(); });
		tar.on('error', reject);
		tar.stdin.on('error', reject);
		tar.on('close', code => code === 0 ? resolve() : reject(new Error('Git archive extraction failed: ' + diagnostic)));
		tar.stdin.end(archive);
	});
}

/** Inventory regular exported files and reject sensitive or executable dev paths. */
async function inventory(directory, relative = '') {
	const result = {};
	for (const name of (await fs.readdir(path.join(directory, relative))).sort()) {
		if (name === '.git' || name === 'auth.json' || (name.startsWith('.env') && name !== '.env.example')) throw new Error('Sensitive configuration must not appear in a release.');
		const item = relative ? relative + '/' + name : name;
		const info = await fs.lstat(path.join(directory, item));
		if (info.isSymbolicLink()) throw new Error('Release payload may not contain symlinks.');
		if (info.isDirectory()) Object.assign(result, await inventory(directory, item));
		else if (info.isFile()) result[item] = digest(await fs.readFile(path.join(directory, item)));
		else throw new Error('Release payload contains an unsupported filesystem entry.');
	}
	return result;
}

/** Build and export one clean revision into a new external folder. */
export async function release({root = defaultRoot, output} = {}) {
	root = await fs.realpath(root);
	if (typeof output !== 'string' || !path.isAbsolute(output) || !/^[a-zA-Z0-9][a-zA-Z0-9._-]*$/.test(path.basename(output))) throw new Error('Supply an absolute path to a new release folder.');
	const parent = await fs.realpath(path.dirname(output));
	const target = path.join(parent, path.basename(output));
	if (target === root || target.startsWith(root + path.sep) || root.startsWith(target + path.sep)) throw new Error('Release output must be outside the source checkout.');
	try { await fs.lstat(target); throw new Error('Release output already exists.'); }
	catch (error) { if (error.code !== 'ENOENT') throw error; }
	const revision = await cleanRevision(root);
	const packageData = JSON.parse(await fs.readFile(path.join(root, 'package.json'), 'utf8'));
	if (typeof packageData.version !== 'string' || typeof packageData.devDependencies?.bootstrap !== 'string') throw new Error('Missing release package versions.');
	await build(root);
	if (await cleanRevision(root) !== revision) throw new Error('Source revision changed during release build.');
	const {manifest, files} = await readReleaseAssets(path.join(root, 'dist'));
	let staging = await fs.mkdtemp(path.join(parent, '.skye-release-'));
	try {
		await extractRevision(root, revision, staging);
		for (const name of ['.git', 'tests', 'tools', '.github', 'node_modules', 'assets', 'package.json', 'package-lock.json', 'build.mjs', 'AGENTS.md', '.php-cs-fixer.dist.php', '.editorconfig', '.nvmrc', '.npmrc', 'README.tpl.md']) {
			try { await fs.lstat(path.join(staging, name)); throw new Error('Development path leaked through git archive: ' + name); }
			catch (error) { if (error.code !== 'ENOENT') throw error; }
		}
		const style = await fs.readFile(path.join(staging, 'style.css'), 'utf8');
		if (!new RegExp('^Version:\\s*' + packageData.version.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\s*$', 'm').test(style)) throw new Error('Theme and package versions disagree.');
		if ((await fs.readFile(path.join(staging, 'LICENSE'))).length === 0) throw new Error('Missing theme license.');
		for (const source of manifest._performance.critical.sources) {
			if (digest(await regularFile(staging, source.path)) !== source.sha256) throw new Error('Exported critical source differs from build: ' + source.path);
		}
		for (const [relative, contents] of files) {
			const destination = path.join(staging, 'dist', relative);
			await fs.mkdir(path.dirname(destination), {recursive: true});
			await fs.writeFile(destination, contents, {flag: 'wx'});
		}
		const metadata = {format: 1, revision, version: packageData.version, bootstrap: packageData.devDependencies.bootstrap, node: process.version, files: await inventory(staging)};
		await fs.writeFile(path.join(staging, 'release.json'), JSON.stringify(metadata, null, 2) + '\n', {flag: 'wx'});
		if (await cleanRevision(root) !== revision) throw new Error('Source revision changed before release publication.');
		try { await fs.lstat(target); throw new Error('Release output was created while building.'); }
		catch (error) { if (error.code !== 'ENOENT') throw error; }
		await fs.rename(staging, target);
		staging = null;
		return {output: target, revision, version: metadata.version, bootstrap: metadata.bootstrap, files: Object.keys(metadata.files).length};
	} finally {
		if (staging) await fs.rm(staging, {recursive: true, force: true});
	}
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
	try {
		if (process.argv.length !== 3) throw new Error('Usage: node tools/release.mjs /absolute/new-release-folder');
		console.log(JSON.stringify(await release({output: process.argv[2]}), null, 2));
	} catch (error) {
		console.error(error.message);
		process.exitCode = 1;
	}
}
