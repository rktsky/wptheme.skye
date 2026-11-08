/** Exercise the actual owned bridge against Yoast's ordered public API, without a browser or persistence claim. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const test = require('node:test');
const source = fs.readFileSync(path.join(__dirname, '../assets/scripts/yoast-analysis.js'), 'utf8');

/**
 * Run the production bridge with the original collector's documented append position.
 * @since 3.5.0
 * @param {object} options Initial API availability and injected native failure.
 * @returns {object} Observable callbacks, native analysis pipeline and unsaved source fixture.
 */
function fixture(options = {}) {
	const events = new Map();
	const timers = new Map();
	let nextTimer = 0;
	let reloads = 0;
	const modifications = [];
	const plugins = [];
	const app = {
		registerPlugin(name, config) {
			if (options.throwPlugin) throw new Error('Unknown native registration');
			plugins.push({name, config});
		},
		registerModification(type, callback, name, priority) {
			if (options.throwModification && priority === 20) throw new Error('Unknown native modification');
			modifications.push({type, callback, name, priority});
		},
		pluginReloaded() { reloads++; },
	};
	const window = {
		SkyeYoastAnalysisConfig: options.config === undefined ? {id: 'fixture'} : options.config,
		setTimeout(callback) { timers.set(++nextTimer, callback); return nextTimer; },
		clearTimeout(id) { timers.delete(id); },
	};
	if (!options.lateYoast) window.YoastSEO = {app, analysis: {worker: {}}};
	if (options.foreignGlobal) window.SkyeYoastAnalysis = options.foreignGlobal;
	const context = vm.createContext({window, jQuery: () => ({on: (name, callback) => events.set(name, callback)}), Promise});
	vm.runInContext(source, context);
	return {
		window, app, modifications, plugins, timers,
		get reloads() { return reloads; },
		ready() { window.YoastSEO = {app, analysis: {worker: {}}}; events.get('YoastSEO:ready')?.(); },
		flush() { const callbacks = [...timers.values()]; timers.clear(); callbacks.forEach(callback => callback()); },
		analyze(original, extra = '<p>Native ACF collector.</p>') {
			const ordered = [...modifications, {priority: 10, callback: input => input + extra}].sort((a, b) => a.priority - b.priority);
			return ordered.reduce((input, record) => record.callback(input), original);
		},
	};
}

test('no project callback keeps the complete original collector and registers nothing', () => {
	const f = fixture();
	assert.equal(f.analyze('<p>Native editor.</p>'), '<p>Native editor.</p><p>Native ACF collector.</p>');
	assert.equal(f.plugins.length, 0);
	assert.equal(f.modifications.length, 0);
	assert.equal(f.window.SkyeYoastAnalysis.refresh('fixture'), false);
	assert.equal(f.window.SkyeYoastAnalysis.register('another', () => ''), false);
	assert.equal(f.window.SkyeYoastAnalysis.register('fixture', null), false);
});

test('a complete ready projection replaces the collector once and observes unsaved source changes', () => {
	const f = fixture();
	const state = {mode: 'builder', text: 'First unsaved value'};
	const project = (native, combined) => {
		assert.ok(combined.startsWith(native));
		return state.mode === 'editor' ? native : '<h1>' + state.text + '</h1>';
	};
	assert.equal(f.window.SkyeYoastAnalysis.register('fixture', project), true);
	assert.equal(f.plugins.length, 1);
	assert.equal(f.modifications.map(row => row.priority).join(','), '5,20');
	assert.equal(f.analyze('<p>Inactive editor.</p>'), '<h1>First unsaved value</h1>');
	state.text = 'New unsaved value';
	assert.equal(f.window.SkyeYoastAnalysis.refresh('fixture'), true);
	assert.equal(f.window.SkyeYoastAnalysis.refresh('fixture'), true);
	assert.equal(f.timers.size, 1);
	f.flush();
	assert.equal(f.reloads, 1);
	assert.equal(f.analyze('<p>Inactive editor.</p>'), '<h1>New unsaved value</h1>');
	state.mode = 'editor';
	assert.equal(f.analyze('<p>Current native editor.</p>'), '<p>Current native editor.</p>');
	assert.equal(f.window.SkyeYoastAnalysis.register('fixture', project), true);
	f.ready();
	assert.equal(f.plugins.length, 1);
	assert.equal(f.window.SkyeYoastAnalysis.register('fixture', () => ''), false);
});

test('project adapters can arrive before or after native Yoast readiness', () => {
	const early = fixture({lateYoast: true});
	assert.equal(early.window.SkyeYoastAnalysis.register('fixture', () => '<h1>Early adapter.</h1>'), true);
	assert.equal(early.plugins.length, 0);
	assert.equal(early.window.SkyeYoastAnalysis.refresh('fixture'), false);
	early.ready();
	assert.equal(early.plugins.length, 1);
	assert.equal(early.analyze('Native.'), '<h1>Early adapter.</h1>');
	const late = fixture({lateYoast: true});
	late.ready();
	assert.equal(late.plugins.length, 0);
	assert.equal(late.window.SkyeYoastAnalysis.register('fixture', () => '<h1>Late adapter.</h1>'), true);
	assert.equal(late.plugins.length, 1);
	assert.equal(late.analyze('Native.'), '<h1>Late adapter.</h1>');
});

test('null, thrown and unknown projection values preserve the complete native contribution', () => {
	const f = fixture();
	let result = null;
	let fail = false;
	f.window.SkyeYoastAnalysis.register('fixture', () => {
		if (fail) throw new Error('Unknown project source');
		return result;
	});
	for (result of [null, false, 0, {}, Promise.resolve('Not synchronous')]) {
		assert.equal(f.analyze('Native.'), 'Native.<p>Native ACF collector.</p>');
	}
	fail = true;
	assert.equal(f.analyze('Native.'), 'Native.<p>Native ACF collector.</p>');
	fail = false;
	result = '';
	assert.equal(f.analyze('Native.'), '', 'A ready complete empty source is distinct from an unavailable source.');
});

test('a capture belongs to its current synchronous pipeline and is consumed once', async () => {
	const f = fixture();
	f.window.SkyeYoastAnalysis.register('fixture', native => native + 'Projected.');
	const capture = f.modifications.find(row => row.priority === 5).callback;
	const project = f.modifications.find(row => row.priority === 20).callback;
	assert.equal(project('Combined without capture.'), 'Combined without capture.');
	assert.equal(f.analyze('First.'), 'First.Projected.');
	assert.equal(project('Unrelated combined.'), 'Unrelated combined.');
	capture('Interrupted prior cycle.');
	await Promise.resolve();
	assert.equal(project('Later combined.'), 'Later combined.');
	assert.equal(f.analyze('New cycle.'), 'New cycle.Projected.');
	const opaque = {};
	assert.equal(capture(opaque), opaque);
	assert.equal(project(opaque), opaque);
});

test('unknown worker/API shapes, partial failures and foreign globals remain native', () => {
	const unsupported = fixture();
	delete unsupported.window.YoastSEO.app.registerModification;
	assert.equal(unsupported.window.SkyeYoastAnalysis.register('fixture', () => 'Project.'), true);
	assert.equal(unsupported.modifications.length, 0);
	assert.equal(unsupported.analyze('Native.'), 'Native.<p>Native ACF collector.</p>');
	const noWorker = fixture();
	delete noWorker.window.YoastSEO.analysis.worker;
	noWorker.window.SkyeYoastAnalysis.register('fixture', () => 'Project.');
	assert.equal(noWorker.modifications.length, 0);
	for (const options of [{throwPlugin: true}, {throwModification: true}]) {
		const broken = fixture(options);
		assert.equal(broken.window.SkyeYoastAnalysis.register('fixture', () => 'Project.'), false);
		assert.equal(broken.analyze('Native.'), 'Native.<p>Native ACF collector.</p>');
		broken.ready();
		assert.ok(broken.plugins.length <= 1);
	}
	const foreign = {ownedElsewhere: true};
	assert.equal(fixture({foreignGlobal: foreign}).window.SkyeYoastAnalysis, foreign);
	assert.equal(fixture({config: {id: '<unsafe>'}}).window.SkyeYoastAnalysis, undefined);
});

test('later foreign content modifiers retain their native position after the explicit projection', () => {
	const f = fixture();
	f.window.SkyeYoastAnalysis.register('fixture', () => '<h1>Project.</h1>');
	f.modifications.push({priority: 30, callback: input => input + '<p>Later native integration.</p>'});
	assert.equal(f.analyze('Native.'), '<h1>Project.</h1><p>Later native integration.</p>');
	f.window.YoastSEO.app = {};
	assert.equal(f.window.SkyeYoastAnalysis.refresh('fixture'), false);
	f.flush();
	assert.equal(f.reloads, 0);
});
