/** Check owned source syntax and whitespace without rewriting generated or vendor assets. */
import fs from 'node:fs/promises';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {spawnSync} from 'node:child_process';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const mode = process.argv[2];
if (!['js', 'style'].includes(mode)) throw new Error('Expected js or style source check.');
const directories = mode === 'style' ? ['assets/styles'] : ['assets/scripts', 'tools', 'tests'];
const files = [];
async function walk(directory) {
	for (const item of await fs.readdir(path.join(root, directory), {withFileTypes: true})) {
		if (['vendor', 'node_modules', 'fixtures'].includes(item.name)) continue;
		const relative = path.join(directory, item.name);
		if (item.isDirectory()) await walk(relative);
		else if ((mode === 'style' ? /\.(scss|css)$/ : /\.(js|cjs|mjs)$/).test(item.name)) files.push(relative);
	}
}
for (const directory of directories) await walk(directory);
if (mode === 'js') files.push('build.mjs');
for (const file of files) {
	const content = await fs.readFile(path.join(root, file), 'utf8');
	if (content.includes('\r') || (content && !content.endsWith('\n'))) throw new Error(file + ': use LF and a final newline.');
	if (mode === 'js') {
		const type = file.endsWith('.cjs') || file.startsWith('tools/build/') ? 'commonjs' : 'module';
		const result = spawnSync(process.execPath, ['--check', '--input-type=' + type], {input: content, encoding: 'utf8'});
		if (result.status !== 0) throw new Error(file + ': ' + result.stderr);
	}
}
console.log(mode === 'js' ? `JavaScript sources: ${files.length} syntax/LF checks passed.` : `Sass/CSS sources: ${files.length} LF checks passed; Sass grammar is checked by the production build.`);
