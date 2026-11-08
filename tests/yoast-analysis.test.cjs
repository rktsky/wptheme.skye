/** Exercise the actual owned bridge against Yoast's ordered public API, without a browser or persistence claim. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const test = require('node:test');
const source = fs.readFileSync(path.join(__dirname, '../assets/scripts/yoast-analysis.js'), 'utf8');
const coupling = fs.readFileSync(path.join(__dirname, '../assets/scripts/content-sources-yoast.js'), 'utf8');

/**
 * Run the production bridge with the original collector's documented append position.
 * @since 3.5.0
 * @param {object} options Initial API availability and injected native failure.
 * @returns {object} Observable callbacks, native analysis pipeline and unsaved source fixture.
 */
function fixture(options = {}) {
	const events = new Map();
	const sourceEvents = new Map();
	const document = {addEventListener: (name, callback) => sourceEvents.set(name, callback)};
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
	const context = vm.createContext({window, document, jQuery: () => ({on: (name, callback) => events.set(name, callback)}), Promise});
	vm.runInContext(source, context);
	return {
		window, app, modifications, plugins, timers,
		couple() { vm.runInContext(coupling, context); },
		sourceChanged() { sourceEvents.get('skye:content-source:change')?.({detail: {source:'untrusted event payload'}}); },
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


test('source policy alone keeps the native collector while excluding the inactive source', () => {
	const f = fixture();
	let mode = 'builder';
	let reads = 0;
	let collectorInput;
	const policy = () => { reads++; return mode; };
	f.modifications.push({priority: 9, callback: input => { collectorInput = input; return input; }});
	assert.equal(f.window.SkyeYoastAnalysis.setSource('fixture', policy), true);
	assert.equal(f.plugins.length, 1);
	assert.equal(f.analyze('<p>Inactive editor.</p>'), '<p>Native ACF collector.</p>');
	assert.equal(collectorInput, '', 'The real append receives no inactive editor text.');
	assert.equal(reads, 1, 'A pipeline uses one source decision.');
	mode = 'editor';
	assert.equal(f.analyze('<p>Current editor.</p>'), '<p>Current editor.</p>');
	assert.equal(collectorInput, '<p>Current editor.</p>');
	assert.equal(reads, 2);
	assert.equal(f.window.SkyeYoastAnalysis.setSource('other', policy), false);
	assert.equal(f.window.SkyeYoastAnalysis.setSource('fixture', null), false);
	assert.equal(f.window.SkyeYoastAnalysis.setSource('fixture', () => mode), false);
	assert.equal(f.window.SkyeYoastAnalysis.setSource('fixture', policy), true);
});

test('a valid source keeps its native active fallback when optional semantics are absent or fail', () => {
	const f = fixture();
	let mode = 'builder';
	let result = null;
	let fail = false;
	f.window.SkyeYoastAnalysis.setSource('fixture', () => mode);
	assert.equal(f.window.SkyeYoastAnalysis.register('fixture', (before, combined) => {
		assert.ok(before.includes('editor'));
		if (fail) throw new Error('Unavailable semantic mapping');
		return result;
	}), true, 'Semantic adapters can register after the source policy.');
	for (result of [null, false, 0, {}, Promise.resolve('Unknown asynchronous value')]) {
		assert.equal(f.analyze('Inactive editor.'), '<p>Native ACF collector.</p>');
		mode = 'editor';
		assert.equal(f.analyze('Current editor.'), 'Current editor.');
		mode = 'builder';
	}
	fail = true;
	assert.equal(f.analyze('Inactive editor.'), '<p>Native ACF collector.</p>');
	mode = 'editor';
	assert.equal(f.analyze('Current editor.'), 'Current editor.');
	fail = false;
	result = '<h1>Actual mapped active source.</h1>';
	assert.equal(f.analyze('Current editor.'), result);
	assert.equal(f.plugins.length, 1);
});

test('unavailable source decisions preserve full native input and never invoke a semantic replacement', () => {
	const f = fixture();
	let selected;
	let fail = false;
	let semanticCalls = 0;
	f.window.SkyeYoastAnalysis.setSource('fixture', () => {
		if (fail) throw new Error('Controller not ready');
		return selected;
	});
	f.window.SkyeYoastAnalysis.register('fixture', () => { semanticCalls++; return 'Must not run.'; });
	for (selected of [undefined, null, false, 'unknown', 0, {}, Promise.resolve('builder')]) {
		assert.equal(f.analyze('Native editor.'), 'Native editor.<p>Native ACF collector.</p>');
	}
	fail = true;
	assert.equal(f.analyze('Native editor.'), 'Native editor.<p>Native ACF collector.</p>');
	assert.equal(semanticCalls, 0);
});

test('source-only initialization, changed apps and partial registration failures retain native boundaries', () => {
	const early = fixture({lateYoast: true});
	early.window.SkyeYoastAnalysis.setSource('fixture', () => 'builder');
	assert.equal(early.plugins.length, 0);
	early.ready();
	assert.equal(early.plugins.length, 1);
	assert.equal(early.analyze('Inactive editor.'), '<p>Native ACF collector.</p>');
	const late = fixture({lateYoast: true});
	late.ready();
	late.window.SkyeYoastAnalysis.setSource('fixture', () => 'editor');
	assert.equal(late.analyze('Current editor.'), 'Current editor.');
	late.window.YoastSEO.app = {};
	assert.equal(late.analyze('Native editor.'), 'Native editor.<p>Native ACF collector.</p>');
	for (const options of [{throwPlugin:true}, {throwModification:true}]) {
		const broken = fixture(options);
		assert.equal(broken.window.SkyeYoastAnalysis.setSource('fixture', () => 'builder'), false);
		assert.equal(broken.analyze('Native editor.'), 'Native editor.<p>Native ACF collector.</p>');
	}
});

test('the coupler observes ready state and genuine source changes without trusting event payloads', () => {
	const f = fixture();
	let mode = null;
	f.couple();
	assert.equal(f.plugins.length, 0);
	f.window.SkyeContentSources = {getSource: () => mode};
	f.sourceChanged();
	assert.equal(f.plugins.length, 0, 'An existing but unready controller remains native.');
	mode = 'builder';
	f.sourceChanged();
	assert.equal(f.analyze('Inactive editor.'), '<p>Native ACF collector.</p>');
	mode = 'editor';
	f.sourceChanged();
	assert.equal(f.analyze('Current editor.'), 'Current editor.');
	mode = null;
	f.sourceChanged();
	assert.equal(f.analyze('Native editor.'), 'Native editor.<p>Native ACF collector.</p>');
	mode = 'builder';
	f.window.SkyeContentSources = {getSource: () => 'builder'};
	f.sourceChanged();
	assert.equal(f.analyze('Native editor.'), 'Native editor.<p>Native ACF collector.</p>', 'A replacement controller is not silently adopted.');
});

test('a controller ready before the coupler works with late Yoast and unknown configurations stay native', () => {
	const f = fixture({lateYoast:true});
	f.window.SkyeContentSources = {getSource: () => 'editor'};
	f.couple();
	assert.equal(f.plugins.length, 0);
	f.ready();
	assert.equal(f.analyze('Current editor.'), 'Current editor.');
	const unknown = fixture({config:{id:'<invalid>'}});
	unknown.window.SkyeContentSources = {getSource: () => 'builder'};
	unknown.couple();
	assert.equal(unknown.analyze('Native.'), 'Native.<p>Native ACF collector.</p>');
	const throwing = fixture();
	throwing.window.SkyeContentSources = {getSource() { throw new Error('Unavailable controller'); }};
	throwing.couple();
	assert.equal(throwing.plugins.length, 0);
});
