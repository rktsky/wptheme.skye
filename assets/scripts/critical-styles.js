/**
 * Activate complete native styles and expose their bounded readiness contract.
 *
 * Keep source order/media intact. DOM readiness activates links immediately;
 * matching stylesheet load/error controls form initialization independently.
 * Nonmatching media never delays forms or waits for a preload load event.
 *
 * @since 2026-11-02
 */
(function () {
	'use strict';

	if (window.skyeCriticalStyles && typeof window.skyeCriticalStyles.run === 'function') {
		return;
	}
	var api = {};
	var activated = false;
	var domReady = document.readyState !== 'loading';
	var scanning = false;
	var pending = 0;
	var settled = false;
	var expired = false;
	var resolveReady;
	var loadTimer;
	var records = [];
	var activate;

	/**
	 * Start a readiness cycle for newly discovered styles.
	 * @return {void}
	 * @since 2026-11-02
	 */
	function cycle() {
		settled = false;
		expired = false;
		api.ready = new Promise(function (resolve) { resolveReady = resolve; });
	}

	/**
	 * Resolve after all matching styles and native DOM discovery settle.
	 * @return {void}
	 * @since 2026-11-02
	 */
	function complete() {
		if (!settled && activated && domReady && !scanning && pending === 0) {
			settled = true;
			window.clearTimeout(loadTimer);
			loadTimer = undefined;
			resolveReady();
		}
	}

	/**
	 * Fail open after stalled CSS without permanently blocking a form.
	 * @return {void}
	 * @since 2026-11-02
	 */
	function timeout() {
		if (settled) {
			return;
		}
		expired = true;
		activate();
		for (var index = 0; index < records.length; index++) {
			records[index].finish();
		}
		complete();
	}

	/**
	 * Activate a link and observe only its applicable stylesheet state.
	 * @param {HTMLLinkElement} link Original native link, never moved or copied.
	 * @return {void}
	 * @since 2026-11-02
	 */
	function watch(link) {
		var matching = !link.media || !window.matchMedia || window.matchMedia(link.media).matches;
		for (var index = 0; index < records.length; index++) {
			if (records[index].link === link) {
				if (!records[index].done && !matching) {
					records[index].finish();
					records[index].matching = false;
				}
				if (!records[index].done || records[index].matching || !matching || link.sheet) {
					return;
				}
				records.splice(index, 1);
				break;
			}
		}
		var record = { link: link, done: false, matching: matching };
		if (matching) {
			if (settled) {
				cycle();
			}
			pending++;
		}

		/**
		 * Accept success, failure or the bounded fallback exactly once.
		 * @return {void}
		 * @since 2026-11-02
		 */
		record.finish = function () {
			if (record.done) {
				return;
			}
			record.done = true;
			link.removeEventListener('load', record.finish);
			link.removeEventListener('error', record.finish);
			link.removeAttribute('data-skye-after-paint');
			if (matching) {
				pending--;
			}
			complete();
		};
		records.push(record);
		if (matching) {
			link.addEventListener('load', record.finish, { once: true });
			link.addEventListener('error', record.finish, { once: true });
		}
		link.rel = 'stylesheet';
		link.removeAttribute('as');
		if (!matching || link.sheet || expired) {
			record.finish();
		}
	}

	/**
	 * Promote all discovered links in place, including later footer links.
	 * @return {void}
	 * @since 2026-11-02
	 */
	activate = function () {
		activated = true;
		scanning = true;
		var known = records.slice();
		for (var previous = 0; previous < known.length; previous++) {
			watch(known[previous].link);
		}
		var links = document.querySelectorAll('link[data-skye-after-paint="1"]');
		for (var index = 0; index < links.length; index++) {
			watch(links[index]);
		}
		scanning = false;
		if (domReady && pending > 0 && !loadTimer && !expired) {
			loadTimer = window.setTimeout(timeout, 4000);
		}
		complete();
	};

	/**
	 * Run one native invocation after applicable CSS settles.
	 *
	 * Rejecting initializers must not rerun through a chained catch handler.
	 * Later native AJAX renders receive a new invocation, not a form-ID lock.
	 *
	 * @param {Function} callback Unmodified native provider body.
	 * @param {*} context Original native callback receiver.
	 * @param {IArguments|Array} args Original native callback arguments.
	 * @return {Promise} Result of this one provider invocation.
	 * @since 2026-11-02
	 */
	function run(callback, context, args) {
		if (activated) {
			activate();
		}
		var called = false;
		/**
		 * Keep load/error recovery distinct from an initializer exception.
		 * @return {*} Native initializer result.
		 * @since 2026-11-02
		 */
		function invoke() {
			if (!called) {
				called = true;
				return callback.apply(context, args);
			}
		}
		return api.ready.then(invoke, invoke);
	}

	/**
	 * Activate immediately when native DOM discovery is complete.
	 * @return {void}
	 * @since 2026-11-02
	 */
	function ready() {
		domReady = true;
		activate();
	}

	cycle();
	api.activate = activate;
	api.run = run;
	window.skyeCriticalStyles = api;
	window.addEventListener('beforeprint', activate);
	window.addEventListener('hashchange', activate);
	window.addEventListener('resize', activate);
	window.addEventListener('scroll', function () {
		if (!activated && document.readyState !== 'loading') {
			activate();
		}
	}, { passive: true });
	var initialNavigation = window.performance && window.performance.getEntriesByType('navigation')[0];
	// Scroll offsets can force layout while the head is still parsing. Hash and
	// history identify early restoration without reading incomplete geometry.
	if (window.location.hash || (initialNavigation && initialNavigation.type !== 'navigate') || (domReady && window.scrollY)) {
		activate();
	}
	if (!domReady) {
		document.addEventListener('DOMContentLoaded', ready, { once: true });
	} else {
		ready();
	}
}());
