/** Exercise real clean-revision builds and release boundaries in disposable Git repositories. */
import test, {before, after} from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import path from 'node:path';
import os from 'node:os';
import {execFile} from 'node:child_process';
import {promisify} from 'node:util';
import {fileURLToPath} from 'node:url';
import {readReleaseAssets} from '../tools/release.mjs';

const execute = promisify(execFile);
const source = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const compilerNode = process.env.SKYE_BUILD_NODE || process.execPath;
const reportPrefix = '__SKYE_RELEASE_TEST__';
const gitEnvironment = {...process.env, GIT_CONFIG_NOSYSTEM: '1', GIT_CONFIG_GLOBAL: '/dev/null', GIT_AUTHOR_NAME: 'Skye Fixture', GIT_AUTHOR_EMAIL: 'fixture@example.invalid', GIT_COMMITTER_NAME: 'Skye Fixture', GIT_COMMITTER_EMAIL: 'fixture@example.invalid'};
let temporary;
let root;
let revision;
let compilerVersion;
let releaseVersion;

/** Execute the actual fixture release compiler separately from the modern harness. */
async function release(options, environment = {}) {
	const program = `
		import {pathToFileURL} from 'url';
		const report = value => console.log(${JSON.stringify(reportPrefix)} + JSON.stringify({node:process.version,executable:process.execPath,...value}));
		try {
			const {release} = await import(pathToFileURL(process.argv[2]).href);
			report({ok:true,result:await release(JSON.parse(process.argv[1]))});
		} catch (error) {
			report({ok:false,error:{name:error.name,message:error.message,code:error.code}});
			process.exitCode = 1;
		}
	`;
	let execution;
	try {
		execution = await execute(compilerNode, ['--input-type=module', '--eval', program, JSON.stringify(options), path.join(root, 'tools/release.mjs')], {cwd:source, env:{...gitEnvironment,...environment}, timeout:60000, killSignal:'SIGKILL', maxBuffer:16*1024*1024});
	} catch (error) {
		if (!error.stdout?.split('\n').some(value => value.startsWith(reportPrefix))) throw error;
		execution = error;
	}
	const line = execution.stdout.split('\n').find(value => value.startsWith(reportPrefix));
	assert.ok(line, 'The actual release child must return a structured result.');
	const report = JSON.parse(line.slice(reportPrefix.length));
	assert.match(report.node, /^v[0-9]+\.[0-9]+\.[0-9]+$/);
	if (path.isAbsolute(compilerNode)) assert.equal(await fs.realpath(report.executable), await fs.realpath(compilerNode));
	if (compilerVersion) assert.equal(report.node, compilerVersion);
	else {
		compilerVersion = report.node;
		console.log(`Release compiler ${report.node}; node:test harness ${process.version}.`);
	}
	if (!report.ok) {
		assert.notEqual(execution.code, undefined, 'A release failure must exit unsuccessfully.');
		const error = new Error(report.error.message);
		error.name = report.error.name;
		if (report.error.code !== undefined) error.code = report.error.code;
		throw error;
	}
	assert.equal(execution.code, undefined, 'A successful release result must also exit successfully.');
	return report.result;
}

/** Run Git only in the disposable fixture without user hooks or signing configuration. */
async function git(...args) {
	return (await execute('git', ['-c', 'core.hooksPath=/dev/null', ...args], {cwd: root, env: gitEnvironment})).stdout.trim();
}

before(async () => {
	temporary = await fs.mkdtemp(path.join(os.tmpdir(), 'skye-release-test-'));
	root = path.join(temporary, 'source');
	await fs.mkdir(root);
	for (const item of ['assets', 'components', 'lib', 'views', 'templates', 'tools/build', 'node_modules']) {
		await fs.cp(path.join(source, item), path.join(root, item), {recursive: true});
	}
	for (const item of ['base.php', 'build.mjs', 'tools/release.mjs', 'package.json', 'package-lock.json', '.gitattributes', 'LICENSE']) {
		await fs.copyFile(path.join(source, item), path.join(root, item));
	}
	await fs.writeFile(path.join(root, '.gitignore'), 'node_modules/\ndist/\n.env\n');
	releaseVersion = JSON.parse(await fs.readFile(path.join(root, 'package.json'), 'utf8')).version;
	await fs.writeFile(path.join(root, 'style.css'), `/*\nTheme Name: Skye release fixture\nVersion: ${releaseVersion}\n*/\n`);
	await fs.writeFile(path.join(root, 'functions.php'), '<?php /** Source export fixture. */\n');
	await fs.writeFile(path.join(root, 'README.md'), 'Committed release fixture.\n');
	await fs.mkdir(path.join(root, 'docs'));
	await fs.writeFile(path.join(root, 'docs', 'security.md'), 'Synthetic internal source audit, never a public runtime asset.\n');
	await fs.mkdir(path.join(root, 'tests'));
	await fs.writeFile(path.join(root, 'tests', 'never-run.php'), '<?php throw new RuntimeException("Development code leaked");\n');
	await git('init', '--quiet');
	await git('add', '--all');
	await git('commit', '--quiet', '-m', 'Create isolated release fixture');
	revision = await git('rev-parse', 'HEAD');
});

after(async () => {
	if (temporary) await fs.rm(temporary, {recursive: true, force: true});
});

test('clean HEAD export ships only verified current assets and licenses', {timeout: 180000}, async () => {
	await fs.mkdir(path.join(root, 'dist'), {recursive: true});
	await fs.writeFile(path.join(root, 'dist', 'stale-not-in-manifest.css'), 'Never publish a stale previous output.');
	const output = path.join(temporary, 'release-' + releaseVersion);
	const result = await release({root, output});
	assert.equal(result.revision, revision);
	assert.equal(result.bootstrap, '6.0.0-alpha.1');
	assert.equal(await fs.readFile(path.join(output, 'README.md'), 'utf8'), 'Committed release fixture.\n');
	for (const item of ['tests', 'tools', 'docs', 'assets', 'node_modules', '.git', 'package.json', 'package-lock.json', '.gitattributes']) {
		await assert.rejects(fs.lstat(path.join(output, item)), {code: 'ENOENT'});
	}
	await assert.rejects(fs.stat(path.join(output, 'dist', 'stale-not-in-manifest.css')), {code: 'ENOENT'});
	const {files} = await readReleaseAssets(path.join(root, 'dist'));
	assert.ok([...files.keys()].filter(file => file.startsWith('licenses/')).length >= 2);
	for (const [file, bytes] of files) assert.deepEqual(await fs.readFile(path.join(output, 'dist', file)), bytes);
	const metadata = JSON.parse(await fs.readFile(path.join(output, 'release.json'), 'utf8'));
	assert.equal(metadata.revision, revision);
	assert.equal(metadata.version, releaseVersion);
	assert.equal(metadata.node, compilerVersion, 'The release records the actual compiler runtime.');
	assert.equal(Object.keys(metadata.files).length, result.files);
	assert.ok(metadata.files['dist/assets.json']);
	assert.ok(Object.keys(metadata.files).some(file => file.startsWith('dist/licenses/bootstrap-')));
	const manifest = JSON.parse(await fs.readFile(path.join(root, 'dist/assets.json'), 'utf8'));
	assert.ok(Array.isArray(manifest._licenses) && manifest._licenses.length >= 2);
	for (const license of manifest._licenses) assert.ok(metadata.files['dist/' + license], 'Every current provider license belongs to the release inventory.');
});

test('dirty, ignored, local-only export rules and unsafe output are rejected before publication', async () => {
	const output = path.join(temporary, 'rejected');
	await fs.writeFile(path.join(root, 'README.md'), 'Uncommitted work.\n');
	await assert.rejects(release({root, output}), /clean committed/);
	await git('checkout', '--', 'README.md');
	const secret = path.join(root, 'assets', 'images', '.env');
	await fs.writeFile(secret, 'Synthetic secret that must never become a built asset.');
	await assert.rejects(release({root, output}), /Ignored build inputs/);
	await fs.rm(secret);
	await fs.writeFile(path.join(root, '.git', 'info', 'attributes'), '*.php export-ignore\n');
	await assert.rejects(release({root, output}), /export rules must be committed/);
	await fs.rm(path.join(root, '.git', 'info', 'attributes'));
	await assert.rejects(release({root, output: path.join(root, 'release')}), /outside the source/);
	await assert.rejects(release({root, output: 'relative-output'}), /absolute path/);
	await fs.mkdir(output);
	await fs.writeFile(path.join(output, 'keep.txt'), 'Keep existing user data.');
	await assert.rejects(release({root, output}), /already exists/);
	assert.equal(await fs.readFile(path.join(output, 'keep.txt'), 'utf8'), 'Keep existing user data.');
});

/** Provide only recognized old-Git command responses; all other calls use real Git. */
async function legacyGit(mode) {
	const directory = path.join(temporary, 'git-shim');
	await fs.mkdir(directory, {recursive:true});
	const realGit = (await execute('which', ['git'], {env:gitEnvironment})).stdout.trim();
	const filename = path.join(directory, 'git');
	const legacyError = "fatal: ambiguous argument 'info/attributes': unknown revision or path not in the working tree.\nUse '--' to separate paths from revisions, like this:\n'git <command> [<revision>...] -- [<file>...]'\n";
	const program = `#!${compilerNode}
const fs = require('fs');
const {spawnSync} = require('child_process');
const args = process.argv.slice(2);
fs.appendFileSync(process.env.SKYE_FIXTURE_GIT_LOG, JSON.stringify(args) + '\\n');
if (args.join(' ') === 'rev-parse --git-path info/attributes') {
	const mode = process.env.SKYE_FIXTURE_GIT_MODE;
	if (mode === 'unknown') { process.stderr.write('Synthetic Git I/O failure.\\n'); process.exit(74); }
	if (mode === 'malformed') { process.stdout.write('--unrecognized\\n'); process.exit(0); }
	process.stdout.write('--git-path\\ninfo/attributes\\n');
	if (mode === 'zero') process.exit(0);
	if (mode === 'broken-ref') process.stderr.write('warning: ignoring broken ref info/attributes.\\n'.repeat(2));
	process.stderr.write(${JSON.stringify(legacyError)});
	if (mode === 'unexpected-diagnostic') process.stderr.write('An additional unrecognized diagnostic.\\n');
	process.exit(128);
}
if (process.env.SKYE_FIXTURE_GIT_MODE === 'bad-directory' && args.join(' ') === 'rev-parse --git-dir') {
	process.stdout.write('--unexpected\\n'); process.exit(0);
}
const result = spawnSync(${JSON.stringify(realGit)}, args, {stdio:'inherit'});
if (result.error) { process.stderr.write(result.error.name + '\\n'); process.exit(1); }
process.exit(result.status === null ? 1 : result.status);
`;
	await fs.writeFile(filename, program, {mode:0o700});
	const log = path.join(temporary, 'git-' + mode + '.jsonl');
	await fs.writeFile(log, '');
	return {PATH:directory + path.delimiter + process.env.PATH, SKYE_FIXTURE_GIT_MODE:mode, SKYE_FIXTURE_GIT_LOG:log};
}

test('recognized Git 1.8 replies retain full clean exports and attribute guards', {timeout:180000}, async () => {
	for (const mode of ['zero', 'failure', 'broken-ref']) {
		const environment = await legacyGit(mode);
		const output = path.join(temporary, 'legacy-' + mode);
		const result = await release({root, output}, environment);
		assert.equal(result.revision, revision);
		await assert.rejects(fs.lstat(path.join(output, 'docs')), {code:'ENOENT'});
		const calls = (await fs.readFile(environment.SKYE_FIXTURE_GIT_LOG, 'utf8')).trim().split('\n').map(line => JSON.parse(line));
		assert.ok(calls.some(args => args.join(' ') === 'rev-parse --git-dir'));
		assert.ok(calls.some(args => args.join(' ') === 'status --porcelain --untracked-files=all'));
		assert.ok(!calls.some(args => args.includes('--porcelain=v1')));
	}
	const environment = await legacyGit('failure');
	try {
		await fs.writeFile(path.join(root, '.git/info/attributes'), '*.php export-ignore\n');
		await assert.rejects(release({root, output:path.join(temporary, 'legacy-override')}, environment), /export rules must be committed/);
	} finally { await fs.rm(path.join(root, '.git/info/attributes'), {force:true}); }
});

test('unknown Git errors and malformed path replies never open the legacy fallback', async () => {
	for (const mode of ['unknown', 'malformed', 'unexpected-diagnostic', 'bad-directory']) {
		const environment = await legacyGit(mode);
		const output = path.join(temporary, 'git-rejected-' + mode);
		await assert.rejects(release({root, output}, environment), mode === 'unknown' ? /Synthetic Git I\/O failure/ : mode === 'unexpected-diagnostic' ? /unrecognized diagnostic/ : /Unrecognized Git/);
		await assert.rejects(fs.lstat(output), {code:'ENOENT'});
		const calls = (await fs.readFile(environment.SKYE_FIXTURE_GIT_LOG, 'utf8')).trim().split('\n').map(line => JSON.parse(line));
		assert.equal(calls.some(args => args.join(' ') === 'rev-parse --git-dir'), mode === 'bad-directory');
	}
});

test('modern linked worktrees still refuse their actual shared local attributes', async () => {
	const worktree = path.join(temporary, 'linked');
	await git('worktree', 'add', '--quiet', '--detach', worktree, revision);
	const response = await execute('git', ['rev-parse', '--git-path', 'info/attributes'], {cwd:worktree, env:gitEnvironment});
	const attributes = path.resolve(worktree, response.stdout.trim());
	try {
		await fs.mkdir(path.dirname(attributes), {recursive:true});
		await fs.writeFile(attributes, '*.php export-ignore\n');
		await assert.rejects(release({root:worktree, output:path.join(temporary, 'worktree-override')}), /export rules must be committed/);
	} finally { await fs.rm(attributes, {force:true}); }
});

test('missing committed documentation exclusion is rejected instead of publishing internal evidence', {timeout:180000}, async () => {
	const output = path.join(temporary, 'leaked-docs');
	try {
		const attributes = path.join(root, '.gitattributes');
		await fs.writeFile(attributes, (await fs.readFile(attributes, 'utf8')).replace(/^\/docs export-ignore\n/m, ''));
		await git('add', '.gitattributes');
		await git('commit', '--quiet', '-m', 'Exercise missing documentation exclusion');
		await assert.rejects(release({root, output}), /Development path leaked through git archive: docs/);
		await assert.rejects(fs.lstat(output), {code:'ENOENT'});
	} finally { await git('reset', '--hard', revision); }
});

test('committed archive substitutions cannot change the critical sources used by the build', {timeout: 180000}, async () => {
	const output = path.join(temporary, 'substituted');
	try {
		await fs.appendFile(path.join(root, 'base.php'), '\n<?php /* $Format:%H$ */ ?>\n');
		await fs.appendFile(path.join(root, '.gitattributes'), '\n/base.php export-subst\n');
		await git('add', 'base.php', '.gitattributes');
		await git('commit', '--quiet', '-m', 'Exercise exported critical source substitution');
		await assert.rejects(release({root, output}), /Exported critical source differs/);
		await assert.rejects(fs.lstat(output), {code: 'ENOENT'});
	} finally {
		await git('reset', '--hard', revision);
	}
});

test('malformed manifests, missing files, symlinks and changed fingerprints fail closed', async () => {
	const dist = path.join(root, 'dist');
	const file = path.join(dist, 'assets.json');
	const original = await fs.readFile(file);
	const manifest = JSON.parse(original.toString());
	const withoutAnalysis = {...manifest};
	delete withoutAnalysis['yoast-analysis.js'];
	try {
		await fs.writeFile(file, JSON.stringify(withoutAnalysis));
		await assert.rejects(readReleaseAssets(dist), /Missing required built asset: yoast-analysis\.js/);
		await fs.writeFile(file, '{invalid');
		await assert.rejects(readReleaseAssets(dist), /manifest JSON/);
		await fs.writeFile(file, JSON.stringify({...manifest, _licenses: []}));
		await assert.rejects(readReleaseAssets(dist), /dependency licenses/);
		await fs.writeFile(file, JSON.stringify({...manifest, _licenses: manifest._licenses.slice(0, 1)}));
		await assert.rejects(readReleaseAssets(dist), /dependency licenses/);
		for (const branding of [null, {...manifest._branding, version:2}, {version:1,colors:{...manifest._branding.colors,primary:'red;display:none'}}, {version:1,colors:{primary:'#ffffff'}}]) {
			await fs.writeFile(file, JSON.stringify({...manifest, _branding:branding}));
			await assert.rejects(readReleaseAssets(dist), /branding manifest/);
		}
		for (const critical of [{...manifest._performance.critical, sources: []}, {...manifest._performance.critical, sources: [{path: '../escape.php', sha256: '0'.repeat(64)}]}, {...manifest._performance.critical, classes: [{}]}]) {
			await fs.writeFile(file, JSON.stringify({...manifest, _performance: {...manifest._performance, critical}}));
			await assert.rejects(readReleaseAssets(dist), /performance manifest/);
		}
		await fs.writeFile(file, JSON.stringify({...manifest, _performance: {...manifest._performance, icons: {'solid/x': {view_box: '0 0 10 10', paths: [{d: '<script>'}]}}}}));
		await assert.rejects(readReleaseAssets(dist), /icon geometry/);
		await fs.writeFile(file, JSON.stringify({...manifest, _performance: {...manifest._performance, fonts: [{file: 'fonts/../secret.woff2'}]}}));
		await assert.rejects(readReleaseAssets(dist), /font binding/);
		await fs.writeFile(file, JSON.stringify({...manifest, 'main.css': '../escape.css'}));
		await assert.rejects(readReleaseAssets(dist), /path|fingerprint/);
		await fs.writeFile(file, original);
		const target = path.join(dist, manifest['main.js']);
		const bytes = await fs.readFile(target);
		await fs.writeFile(target, 'Changed after build.');
		await assert.rejects(readReleaseAssets(dist), /fingerprint mismatch/);
		await fs.writeFile(target, bytes);
		const backup = target + '.test-backup';
		await fs.rename(target, backup);
		await assert.rejects(readReleaseAssets(dist), {code: 'ENOENT'});
		await fs.symlink(backup, target);
		await assert.rejects(readReleaseAssets(dist), /regular local files/);
		await fs.rm(target);
		await fs.rename(backup, target);
	} finally {
		await fs.writeFile(file, original);
	}
});
