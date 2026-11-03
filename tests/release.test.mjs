/** Exercise real clean-revision builds and release boundaries in disposable Git repositories. */
import test, {before, after} from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import path from 'node:path';
import os from 'node:os';
import {execFile} from 'node:child_process';
import {promisify} from 'node:util';
import {fileURLToPath} from 'node:url';
import {release, readReleaseAssets} from '../tools/release.mjs';

const execute = promisify(execFile);
const source = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const gitEnvironment = {...process.env, GIT_CONFIG_NOSYSTEM: '1', GIT_CONFIG_GLOBAL: '/dev/null', GIT_AUTHOR_NAME: 'Skye Fixture', GIT_AUTHOR_EMAIL: 'fixture@example.invalid', GIT_COMMITTER_NAME: 'Skye Fixture', GIT_COMMITTER_EMAIL: 'fixture@example.invalid'};
let temporary;
let root;
let revision;

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
	for (const item of ['base.php', 'package.json', 'package-lock.json', '.gitattributes', 'LICENSE']) {
		await fs.copyFile(path.join(source, item), path.join(root, item));
	}
	await fs.writeFile(path.join(root, '.gitignore'), 'node_modules/\ndist/\n.env\n');
	await fs.writeFile(path.join(root, 'style.css'), '/*\nTheme Name: Skye release fixture\nVersion: 3.0.0\n*/\n');
	await fs.writeFile(path.join(root, 'functions.php'), '<?php /** Source export fixture. */\n');
	await fs.writeFile(path.join(root, 'README.md'), 'Committed release fixture.\n');
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

test('clean HEAD export ships only verified current assets and licenses', {timeout: 90000}, async () => {
	await fs.mkdir(path.join(root, 'dist'), {recursive: true});
	await fs.writeFile(path.join(root, 'dist', 'stale-not-in-manifest.css'), 'Never publish a stale previous output.');
	const output = path.join(temporary, 'release-3.0.0');
	const result = await release({root, output});
	assert.equal(result.revision, revision);
	assert.equal(result.bootstrap, '6.0.0-alpha.1');
	assert.equal(await fs.readFile(path.join(output, 'README.md'), 'utf8'), 'Committed release fixture.\n');
	for (const item of ['tests', 'tools', 'assets', 'node_modules', '.git', 'package.json', 'package-lock.json', '.gitattributes']) {
		await assert.rejects(fs.lstat(path.join(output, item)), {code: 'ENOENT'});
	}
	await assert.rejects(fs.stat(path.join(output, 'dist', 'stale-not-in-manifest.css')), {code: 'ENOENT'});
	const {files} = await readReleaseAssets(path.join(root, 'dist'));
	assert.ok([...files.keys()].filter(file => file.startsWith('licenses/')).length >= 2);
	for (const [file, bytes] of files) assert.deepEqual(await fs.readFile(path.join(output, 'dist', file)), bytes);
	const metadata = JSON.parse(await fs.readFile(path.join(output, 'release.json'), 'utf8'));
	assert.equal(metadata.revision, revision);
	assert.equal(Object.keys(metadata.files).length, result.files);
	assert.ok(metadata.files['dist/assets.json']);
	assert.ok(Object.keys(metadata.files).some(file => file.startsWith('dist/licenses/bootstrap-')));
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

test('committed archive substitutions cannot change the critical sources used by the build', {timeout: 90000}, async () => {
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
	try {
		await fs.writeFile(file, '{invalid');
		await assert.rejects(readReleaseAssets(dist), /manifest JSON/);
		await fs.writeFile(file, JSON.stringify({...manifest, _licenses: []}));
		await assert.rejects(readReleaseAssets(dist), /dependency licenses/);
		await fs.writeFile(file, JSON.stringify({...manifest, _licenses: manifest._licenses.slice(0, 1)}));
		await assert.rejects(readReleaseAssets(dist), /dependency licenses/);
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
