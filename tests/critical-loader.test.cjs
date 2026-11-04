/**
 * Verify progressive CSS readiness and native per-invocation form gating.
 *
 * Run with the project Node runtime. Native Promise scheduling and the actual
 * own asset sources run unchanged; DOM/link events are explicit fixtures.
 * No browser, network, vendor change, form render or submission is involved.
 *
 * @since 2026-11-02
 */
'use strict';

var assert = require('assert');
var fs = require('fs');
var path = require('path');
var vm = require('vm');
var source = fs.readFileSync(path.join(__dirname, '../assets/scripts/critical-styles.js'), 'utf8');
var gate = fs.readFileSync(path.join(__dirname, '../assets/scripts/critical-form-gate.js'), 'utf8');
var checks = 0;

/**
 * Supply listener identity and removal rather than canned load replies.
 * @param {Object} target Synthetic native event target.
 * @return {Object} Same target with observable event methods.
 * @since 2026-11-02
 */
function eventTarget(target) {
	var events = {};
	target.addEventListener = function (name, callback) {
		events[name] = events[name] || [];
		events[name].push(callback);
	};
	target.removeEventListener = function (name, callback) {
		events[name] = (events[name] || []).filter(function (listener) { return listener !== callback; });
	};
	target.emit = function (name) {
		(events[name] || []).slice().forEach(function (callback) { callback(); });
	};
	return target;
}

/**
 * Create one original native style link and its observable resource events.
 * @param {string} name Public fixture basename.
 * @param {string} media Unchanged native media condition.
 * @param {boolean} cached Whether the stylesheet is already parsed.
 * @return {Object} Observable native-link boundary.
 * @since 2026-11-02
 */
function link(name, media, cached) {
	var item = eventTarget({ rel: 'preload', media: media, href: 'https://static.example.org/' + name + '.css', as: 'style', 'data-skye-after-paint': '1', sheet: cached ? {} : null });
	item.removeAttribute = function (name) { delete this[name]; };
	return item;
}

/**
 * Execute the real helper against request/media/timeout boundaries.
 * @param {Object} options Fixture request and cache conditions.
 * @return {Object} Actual VM context and observable native boundaries.
 * @since 2026-11-02
 */
function fixture(options) {
	options = options || {};
	var timers = [];
	var activity = { queries: 0, media: 0, scrollReads: 0, loadingScrollReads: 0 };
	var links = [link('main', '(min-width: 576px)', options.cached), link('mobile', 'not all and (min-width: 576px)', options.cached)];
	if (options.forms) {
		['basic', 'components', 'theme'].forEach(function (name) { links.push(link(name, 'all', options.cached)); });
	}
	var document = eventTarget({ readyState: options.readyState || (options.complete ? 'complete' : 'loading'), activeElement: { id: 'native-focused-control' } });
	document.querySelectorAll = function () { activity.queries++; return links.filter(function (item) { return item['data-skye-after-paint'] === '1'; }); };
	var window = eventTarget({
		location: { hash: options.hash || '' },
		performance: { getEntriesByType: function () { return [{ type: options.navigation || 'navigate' }]; } },
		setTimeout: function (callback, delay) { timers.push({ callback: callback, delay: delay }); return timers.length; },
		clearTimeout: function (id) { if (timers[id - 1]) { timers[id - 1].cleared = true; } },
		requestAnimationFrame: function () { throw new Error('CSS activation must not wait for a throttled animation frame.'); },
		matchMedia: function (media) { activity.media++; return { matches: media === 'all' || media === '' || (media.indexOf('not all') === 0 ? (options.width || 390) < 576 : (options.width || 390) >= 576) }; }
	});
	Object.defineProperty(window, 'scrollY', {
		/**
		 * Observe layout-sensitive reads at the real document-state boundary.
		 * @return {number} Native scroll position supplied by the fixture.
		 * @since 2026-11-03
		 */
		get: function () {
			activity.scrollReads++;
			if (document.readyState === 'loading') {
				activity.loadingScrollReads++;
				if (options.forbidLoadingScrollRead) {
					throw new Error('Head bootstrap must not request incomplete scroll geometry.');
				}
			}
			return options.scrollY || 0;
		}
	});
	if (options.noNavigationTiming) { window.performance = undefined; }
	var context = vm.createContext({ window: window, document: document, Promise: Promise, event: { native: true } });
	if (options.install !== false) { vm.runInContext(source, context); }
	return { window: window, document: document, links: links, timers: timers, context: context, activity: activity };
}

/**
 * Assert an observed contract with useful native diagnostics.
 * @param {*} actual Production result.
 * @param {*} expected Required result.
 * @param {string} message Contract description.
 * @return {void}
 * @since 2026-11-02
 */
function check(actual, expected, message) {
	assert.deepStrictEqual(actual, expected, message);
	checks++;
}

/**
 * Finish actual matching resource events while leaving nonmatching links silent.
 * @param {Object} scenario Actual helper fixture.
 * @param {string} event Native completion event.
 * @return {void}
 * @since 2026-11-02
 */
function finishStyles(scenario, event) {
	scenario.links.forEach(function (item) {
		if (scenario.window.matchMedia(item.media).matches) {
			item.sheet = event === 'error' ? null : {};
			item.emit(event || 'load');
		}
	});
}

/**
 * Complete HTML discovery through the helper's real event handler.
 * @param {Object} scenario Actual fixture.
 * @return {void}
 * @since 2026-11-02
 */
function domReady(scenario) {
	scenario.document.readyState = 'interactive';
	scenario.document.emit('DOMContentLoaded');
}

/**
 * Execute the gate after the same newline flattening performed by GF itself.
 * @param {Object} scenario Actual helper or missing-helper fixture.
 * @return {void}
 * @since 2026-11-02
 */
function nativeInvocation(scenario) {
	var body = 'window.invocations.push({receiver:this,argument:arguments[0],page:current_page,event:event});';
	var wrapped = gate.replace('/*__SKYE_NATIVE_FORM_SCRIPT__*/', body).replace(/[\t\n\r]/g, '');
	scenario.context.nativeReceiver = { identity: 'native callback' };
	vm.runInContext('(function(){var current_page=3;' + wrapped + '}).call(nativeReceiver,"original argument");', scenario.context);
}

/**
 * Exercise load timing, fallback and repeated native callbacks without stubs.
 * @return {Promise<void>} Completed isolated regression suite.
 * @since 2026-11-02
 */
async function main() {
	var normal = fixture({ forms: true });
	check(normal.links.every(function (item) { return item.rel === 'preload'; }), true, 'Parsing retains preloads before DOM readiness.');
	check(normal.activity.scrollReads, 0, 'Fresh head parsing never requests a layout-sensitive scroll offset.');
	var runs = 0;
	var first = normal.window.skyeCriticalStyles.run(function () { runs++; });
	domReady(normal);
	check(normal.links.every(function (item) { return item.rel === 'stylesheet'; }), true, 'DOMContentLoaded activates every file immediately without a frame or delay.');
	await Promise.resolve();
	check(runs, 0, 'Changing rel alone does not claim that matching CSS has loaded.');
	check(normal.links[0]['data-skye-after-paint'], undefined, 'A nonmatching variant never waits for a load event.');
	check(normal.links[1]['data-skye-after-paint'], '1', 'Pending matching CSS retains a recovery identity until it settles.');
	finishStyles(normal, 'load');
	await first;
	check(runs, 1, 'All matching main/GF resources release the native invocation exactly once.');
	check(normal.links.every(function (item) { return !('as' in item) && !('data-skye-after-paint' in item); }), true, 'Settled resources retain only their original stylesheet behavior.');
	check(normal.links.map(function (item) { return item.media; }), ['(min-width: 576px)', 'not all and (min-width: 576px)', 'all', 'all', 'all'], 'Original media and node order remain intact.');
	check(normal.timers.every(function (timer) { return timer.cleared; }), true, 'Successful readiness cancels its bounded fallback.');
	var readyActivity = [normal.activity.queries, normal.activity.media];
	for (var scroll = 0; scroll < 12; scroll++) { normal.window.emit('scroll'); }
	check([normal.activity.queries, normal.activity.media], readyActivity, 'Ordinary scrolling after DOM activation does not rescan links or media queries.');
	await normal.window.skyeCriticalStyles.run(function () { runs++; });
	check(runs, 2, 'A later AJAX/native invocation is not suppressed by a global form-ID once lock.');
	var throwingRuns = 0;
	await assert.rejects(normal.window.skyeCriticalStyles.run(function () { throwingRuns++; throw new Error('native init failure'); }), /native init failure/);
	check(throwingRuns, 1, 'An initializer exception is not mistaken for CSS failure and rerun.');
	var late = link('late', 'all', false);
	normal.links.push(late);
	var lateCall = normal.window.skyeCriticalStyles.run(function () { runs++; });
	await Promise.resolve();
	check(runs, 2, 'A newly discovered resource starts another real readiness cycle.');
	late.sheet = {};
	late.emit('load');
	await lateCall;
	check(runs, 3, 'A later real load releases only its own waiting invocation.');
	var rotating = fixture();
	domReady(rotating);
	var rotateRuns = 0;
	var rotated = rotating.window.skyeCriticalStyles.run(function () { rotateRuns++; });
	rotating.window.matchMedia = function (media) { return { matches: media.indexOf('not all') !== 0 }; };
	rotating.window.emit('resize');
	await Promise.resolve();
	check(rotateRuns, 0, 'A viewport change starts waiting for the newly matching still-unloaded variant.');
	rotating.links[0].sheet = {};
	rotating.links[0].emit('load');
	await rotated;
	check(rotateRuns, 1, 'The old nonmatching variant no longer holds initialization after resize.');

	for (var options of [{ hash: '#form' }, { navigation: 'back_forward' }, { navigation: 'reload' }]) {
		var immediate = fixture(Object.assign({ forbidLoadingScrollRead: true, scrollY: 900 }, options));
		check(immediate.links[0].rel, 'stylesheet', 'Anchors and restored navigation activate before DOM completion.');
		check(immediate.activity.scrollReads, 0, 'Hash and history restoration do not require a head-time geometry read.');
		var restoredFocus = immediate.document.activeElement;
		var restoredHash = immediate.window.location.hash;
		domReady(immediate);
		finishStyles(immediate, 'load');
		await immediate.window.skyeCriticalStyles.ready;
		check(immediate.document.activeElement === restoredFocus, true, 'Restoration leaves native focus on its original element.');
		check(immediate.window.location.hash, restoredHash, 'Style readiness does not rewrite the native fragment target.');
	}
	for (var position of [0, 900]) {
		var parsing = fixture({ scrollY: position, cached: true, forbidLoadingScrollRead: true });
		var parsingFocus = parsing.document.activeElement;
		parsing.window.emit('scroll');
		check(parsing.activity.scrollReads, 0, 'A scroll event during parsing does not force geometry or prematurely activate links.');
		check(parsing.links[0].rel, 'preload', 'Fresh parsing retains its ordinary DOM-ready activation even with a restored offset.');
		domReady(parsing);
		await parsing.window.skyeCriticalStyles.ready;
		check(parsing.links.every(function (item) { return item.rel === 'stylesheet'; }), true, 'DOM completion activates all native styles at both zero and restored scroll offsets.');
		check(parsing.activity.scrollReads, 0, 'DOMContentLoaded activation also needs no scroll geometry read.');
		check(parsing.document.activeElement === parsingFocus, true, 'Loading-to-ready activation preserves a focused native control.');
	}
	for (var state of ['interactive', 'complete']) {
		var readyDocument = fixture({ readyState: state, scrollY: 900, cached: true });
		await readyDocument.window.skyeCriticalStyles.ready;
		check(readyDocument.activity.scrollReads, 1, 'An already discovered document retains its existing restored-scroll bootstrap.');
		check(readyDocument.activity.loadingScrollReads, 0, 'Already-ready bootstrap never observes a loading document.');
		check(readyDocument.links.every(function (item) { return item.rel === 'stylesheet'; }), true, 'Interactive and complete documents activate immediately without waiting for another DOM event.');
	}
	var noTiming = fixture({ noNavigationTiming: true, forbidLoadingScrollRead: true, cached: true });
	check(noTiming.links[0].rel, 'preload', 'Missing optional Navigation Timing does not request head geometry.');
	domReady(noTiming);
	await noTiming.window.skyeCriticalStyles.ready;
	check(noTiming.links[0].rel, 'stylesheet', 'Native DOM readiness remains sufficient without Navigation Timing.');
	for (var name of ['beforeprint', 'hashchange', 'scroll']) {
		var interrupted = fixture();
		interrupted.document.readyState = 'interactive';
		interrupted.window.emit(name);
		check(interrupted.links[0].rel, 'stylesheet', 'User navigation/printing immediately restores ordinary stylesheets.');
	}
	var failed = fixture({ forms: true });
	domReady(failed);
	finishStyles(failed, 'error');
	var failedRuns = 0;
	await failed.window.skyeCriticalStyles.run(function () { failedRuns++; });
	check(failedRuns, 1, 'CSS errors release initialization once rather than leaving a dead form.');
	var stalled = fixture({ forms: true });
	domReady(stalled);
	var timedRuns = 0;
	var timed = stalled.window.skyeCriticalStyles.run(function () { timedRuns++; });
	stalled.timers.filter(function (timer) { return !timer.cleared; }).forEach(function (timer) { check(timer.delay, 4000, 'The only timer bounds resource failure, not activation.'); timer.callback(); });
	await timed;
	check(timedRuns, 1, 'A stalled stylesheet cannot hold native initialization forever.');
	var cached = fixture({ cached: true, complete: true, forms: true });
	await cached.window.skyeCriticalStyles.ready;
	check(cached.links.every(function (item) { return item.rel === 'stylesheet'; }), true, 'Already parsed styles settle without relying on a missed cache load event.');

	for (var install of [true, false]) {
		var gated = fixture({ forms: true, install: install });
		gated.window.invocations = [];
		var originalEvent = gated.context.event;
		nativeInvocation(gated);
		if (install) { domReady(gated); }
		check(gated.window.invocations.length, 0, 'The flattened native body waits for real stylesheet readiness.');
		gated.context.event = { later: true };
		finishStyles(gated, 'load');
		await new Promise(function (resolve) { setImmediate(resolve); });
		check(gated.window.invocations.length, 1, 'Shared and missing-helper paths each release the native body once.');
		check(gated.window.invocations[0].receiver.identity, 'native callback', 'Original native callback receiver survives.');
		check(gated.window.invocations[0].argument, 'original argument', 'Original native callback arguments survive.');
		check(gated.window.invocations[0].page, 3, 'The iframe current_page lexical variable remains available.');
		check(gated.window.invocations[0].event === originalEvent, true, 'The original ambient event is captured before asynchronous continuation.');
		nativeInvocation(gated);
		await new Promise(function (resolve) { setImmediate(resolve); });
		check(gated.window.invocations.length, 2, 'Another native render remains independently callable after readiness.');
	}
	var broken = fixture({ forms: true, install: false });
	broken.window.invocations = [];
	broken.window.skyeCriticalStyles = { run: function () { throw new Error('broken helper'); } };
	nativeInvocation(broken);
	check(broken.links.every(function (item) { return item.rel === 'stylesheet'; }), true, 'A failed shared helper immediately promotes complete CSS through the independent recovery.');
	finishStyles(broken, 'load');
	await new Promise(function (resolve) { setImmediate(resolve); });
	check(broken.window.invocations.length, 1, 'A failed shared helper still releases the untouched native body once.');
	process.stdout.write(JSON.stringify({ assertions: checks, result: 'pass' }) + '\n');
}

main().catch(function (error) { process.stderr.write(error.stack + '\n'); process.exitCode = 1; });
