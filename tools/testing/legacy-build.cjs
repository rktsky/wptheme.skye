/** Exercise the real build and watcher in an unchanged CentOS 7 / Node 14 userland. */
'use strict';
const fs = require('fs');
const path = require('path');
const os = require('os');
const crypto = require('crypto');
const assert = require('assert').strict;
const {spawn, spawnSync} = require('child_process');
const {pathToFileURL} = require('url');
const {annotateGitHubError} = require('./github-errors.cjs');

/** Wait for an observed child/output state instead of assuming a build duration. */
async function until(predicate, label, timeout = 60000) {
	const deadline = Date.now() + timeout;
	while (!predicate()) {
		if (Date.now() > deadline) throw new Error('Timed out: ' + label);
		await new Promise(resolve => setTimeout(resolve, 20));
	}
}

/** Copy only owned build inputs; installed modules and live data are never copied. */
function copy(source, target) {
	const info = fs.lstatSync(source);
	if (info.isSymbolicLink()) throw new Error('Legacy fixture inputs must not be symlinks: ' + source);
	if (info.isDirectory()) {
		fs.mkdirSync(target, {recursive: true});
		for (const name of fs.readdirSync(source)) copy(path.join(source, name), path.join(target, name));
	} else if (info.isFile()) {
		fs.mkdirSync(path.dirname(target), {recursive: true});
		fs.copyFileSync(source, target);
	} else throw new Error('Unsupported fixture input: ' + source);
}

/** Run the production watch API in its own signalable Node 14 process. */
async function watchWorker(site) {
	const {watchBuild} = await import(pathToFileURL(path.join(site, 'build.mjs')).href);
	const watcher = await watchBuild(site);
	watcher.once('ready', () => process.stdout.write('SKYE_LEGACY_WATCH_READY\n'));
}

/** Verify every published reference against its real bytes and content fingerprint. */
function verifyManifest(site) {
	const manifest = JSON.parse(fs.readFileSync(path.join(site, 'dist/assets.json'), 'utf8'));
	const references = Object.keys(manifest).filter(name => typeof manifest[name] === 'string').map(name => manifest[name]).concat(manifest._licenses);
	for (const relative of references) {
		assert.ok(!path.isAbsolute(relative) && !relative.split('/').includes('..'));
		const data = fs.readFileSync(path.join(site, 'dist', relative));
		const fingerprint = crypto.createHash('sha256').update(data).digest('hex').slice(0, 16);
		assert.ok(data.length > 0 && path.basename(relative).includes(fingerprint), 'Published bytes match ' + relative);
	}
	return manifest;
}

/** Prepare a private checkout and exercise native npm, Sass, publication and watcher recovery. */
async function run(source, work, evidence) {
	fs.mkdirSync(evidence, {recursive: true});
	const result = {status: 'running', platform: process.platform, arch: process.arch, node: process.version, kernel: os.release(), kernel_shared_with_host: true, image: process.env.SKYE_LEGACY_IMAGE, cases: []};
	let watcher;
	let ended = false;
	let exitCode;
	let exitSignal;
	const site = path.join(work, 'site');
	const log = path.join(evidence, 'commands.log');
	const record = name => result.cases.push(name);
	const execute = (label, arguments_, expected = 0) => {
		const child = spawnSync(process.execPath, arguments_, {cwd: site, encoding: 'utf8', timeout: 120000, maxBuffer: 32 * 1024 * 1024});
		fs.appendFileSync(log, '\n--- ' + label + ' ---\n' + (child.stdout || '') + (child.stderr || ''));
		if (child.error) throw child.error;
		assert.equal(child.status, expected, label + ': ' + child.stdout + child.stderr);
		return child.stdout.trim();
	};
	try {
		assert.equal(process.platform, 'linux');
		assert.equal(process.arch, 'x64');
		assert.equal(process.version, 'v14.21.3');
		assert.equal(process.execPath, process.env.SKYE_BUILD_NODE);
		result.centos = fs.readFileSync('/etc/centos-release', 'utf8').trim();
		assert.match(result.centos, /^CentOS Linux release 7\.9\.2009\b/);
		const libc = spawnSync('/usr/bin/getconf', ['GNU_LIBC_VERSION'], {encoding: 'utf8'});
		assert.equal(libc.status, 0);
		result.libc = libc.stdout.trim();
		assert.equal(result.libc, 'glibc 2.17');
		record('Original CentOS 7.9 / glibc 2.17, shared host kernel, official Node 14');
		assert.ok(!fs.existsSync(site), 'Never reuse an existing fixture directory.');
		fs.mkdirSync(site, {recursive: true});
		for (const name of ['assets', 'components', 'lib', 'tools/build', 'build.mjs', 'base.php', 'package.json', 'package-lock.json', '.npmrc', '.nvmrc']) copy(path.join(source, name), path.join(site, name));
		const lock = JSON.parse(fs.readFileSync(path.join(site, 'package-lock.json'), 'utf8'));
		assert.equal(lock.lockfileVersion, 1);
		const npm = path.resolve(path.dirname(process.execPath), '../lib/node_modules/npm/bin/npm-cli.js');
		result.npm = execute('native npm version', [npm, '--version']);
		assert.equal(result.npm, '6.14.18');
		assert.equal(execute('engine-strict configuration', [npm, 'config', 'get', 'engine-strict']), 'true');
		execute('clean locked installation without lifecycle scripts', [npm, 'ci', '--production=false', '--ignore-scripts', '--no-audit']);
		record('npm 6.14.18 ci, lockfile v1, engine-strict=true, ignore-scripts');
		execute('ordinary production build', [npm, 'run', 'build']);
		const initial = verifyManifest(site);
		const manifestFile = path.join(site, 'dist/assets.json');
		const beforeFailure = fs.readFileSync(manifestFile, 'utf8');
		const stylesheet = path.join(site, 'assets/styles/main.scss');
		const originalStyle = fs.readFileSync(stylesheet, 'utf8');
		fs.appendFileSync(stylesheet, '\n.skye-invalid {\n');
		execute('actual invalid Sass fails', [path.join(site, 'build.mjs')], 1);
		assert.equal(fs.readFileSync(manifestFile, 'utf8'), beforeFailure);
		assert.deepEqual(verifyManifest(site), initial);
		fs.writeFileSync(stylesheet, originalStyle);
		execute('valid production source recovers', [npm, 'run', 'build']);
		verifyManifest(site);
		record('CLI Sass failure preserves all published bytes; valid build recovers');
		let stdout = '';
		let stderr = '';
		watcher = spawn(process.execPath, [__filename, '--watch-worker', site], {cwd: site, stdio: ['ignore', 'pipe', 'pipe']});
		watcher.stdout.on('data', data => { stdout += data; fs.appendFileSync(log, data); });
		watcher.stderr.on('data', data => { stderr += data; fs.appendFileSync(log, data); });
		watcher.on('error', error => { stderr += error.stack; ended = true; });
		watcher.on('exit', (code, signal) => { exitCode = code; exitSignal = signal; ended = true; });
		// Fail promptly when an actual watcher crash prevents the expected publication.
		const watchUntil = (predicate, label) => until(() => { assert.ok(!ended, 'Watcher exited before ' + label + ': ' + stderr); return predicate(); }, label);
		await until(() => stdout.includes('SKYE_LEGACY_WATCH_READY') || ended, 'native watcher ready');
		assert.ok(!ended, stderr);
		const stable = fs.readFileSync(manifestFile, 'utf8');
		const errorsBefore = stderr.length;
		fs.appendFileSync(stylesheet, '\n.skye-watch-invalid {\n');
		await until(() => /expected/i.test(stderr.slice(errorsBefore)) || ended, 'actual watched Sass failure');
		assert.ok(!ended, stderr);
		assert.equal(fs.readFileSync(manifestFile, 'utf8'), stable);
		fs.writeFileSync(stylesheet, originalStyle + '\n.skye-legacy-recovered { color: #123456; }\n');
		await watchUntil(() => {
			const current = JSON.parse(fs.readFileSync(manifestFile, 'utf8'));
			return fs.readFileSync(path.join(site, 'dist', current['main.css']), 'utf8').includes('.skye-legacy-recovered');
		}, 'watch recovery publishes valid CSS');
		record('Watched Sass failure is reported; watcher recovers without restart');
		const header = path.join(site, 'components/header/legacy-watch.php');
		fs.writeFileSync(header, '<?php /** Isolated watch discovery fixture. */ ?><div class="skye-legacy-new-header"></div>');
		await watchUntil(() => JSON.parse(fs.readFileSync(manifestFile, 'utf8'))._performance.critical.classes.includes('skye-legacy-new-header'), 'new PHP component discovery');
		let previousHash = JSON.parse(fs.readFileSync(manifestFile, 'utf8'))._performance.critical.build_hash;
		fs.appendFileSync(header, '\n<?php /* A source change without another class. */ ?>');
		await watchUntil(() => JSON.parse(fs.readFileSync(manifestFile, 'utf8'))._performance.critical.build_hash !== previousHash, 'existing PHP source byte change');
		record('New PHP component and classless PHP source changes rebuild critical fingerprints');
		const image = path.join(site, 'assets/images/legacy/new.svg');
		fs.mkdirSync(path.dirname(image), {recursive: true});
		fs.writeFileSync(image, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 2 2"><path d="M0 0h2v2H0z"/></svg>');
		await watchUntil(() => Boolean(JSON.parse(fs.readFileSync(manifestFile, 'utf8'))['images/legacy/new.svg']), 'new asset directory/file discovery');
		record('New nested asset directory and file are discovered under Linux');
		await until(() => !fs.existsSync(path.join(site, '.skye-build.lock')), 'previous publication settled');
		previousHash = JSON.parse(fs.readFileSync(manifestFile, 'utf8'))._performance.critical.build_hash;
		const triggered = Date.now();
		fs.appendFileSync(header, '\n<?php /* Signal during the next actual publication. */ ?>');
		await until(() => {
			if (ended) return true;
			try {
				const owner = JSON.parse(fs.readFileSync(path.join(site, '.skye-build.lock/owner.json'), 'utf8'));
				return Date.parse(owner.started) >= triggered;
			} catch (error) {
				if (error.code === 'ENOENT' || error instanceof SyntaxError) return false;
				throw error;
			}
		}, 'new active build before SIGTERM');
		assert.ok(!ended, stderr);
		watcher.kill('SIGTERM');
		await until(() => ended, 'SIGTERM drains the active build and closes the watcher');
		assert.equal(exitCode, 0, stderr);
		assert.equal(exitSignal, null);
		assert.ok(!fs.existsSync(path.join(site, '.skye-build.lock')));
		assert.ok(!fs.readdirSync(site).some(name => name.startsWith('.skye-build-')));
		assert.notEqual(verifyManifest(site)._performance.critical.build_hash, previousHash);
		record('SIGTERM during a real build drains publication, cleans staging and exits successfully');
		result.status = 'pass';
	} catch (error) {
		result.status = 'fail';
		result.failure = {message: error.message, stack: error.stack};
		throw error;
	} finally {
		try {
			if (watcher && !ended) {
				watcher.kill('SIGTERM');
				try { await until(() => ended, 'failed fixture watcher cleanup', 10000); }
				catch (error) {
					watcher.kill('SIGKILL');
					await until(() => ended, 'forced fixture watcher cleanup', 10000);
				}
			}
		} finally {
			fs.writeFileSync(path.join(evidence, 'result.json'), JSON.stringify(result, null, 2) + '\n');
		}
	}
	console.log(JSON.stringify(result, null, 2));
}

const task = process.argv[2] === '--watch-worker' ? watchWorker(process.argv[3]) : run(process.argv[2], process.argv[3], process.argv[4]);
task.catch(error => { annotateGitHubError(error); console.error(error); process.exitCode = 1; });
