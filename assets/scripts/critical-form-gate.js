/**
 * Preserve one native GF body while awaiting its selected complete styles.
 *
 * The placeholder receives trusted provider JavaScript, never eval. Block
 * comments survive GF's newline stripping. This isolated recovery also works
 * when the shared head helper is missing; ordinary activation is centralized.
 *
 * @since 2026-11-02
 */
(function (skyeContext, skyeArguments, event) {
	var skyeCalled = false;
	var skyeBody = function () { /*__SKYE_NATIVE_FORM_SCRIPT__*/ };

	/**
	 * Release this invocation once with its original bindings.
	 * @return {*} Native initializer result.
	 * @since 2026-11-02
	 */
	function skyeInvoke() {
		if (!skyeCalled) {
			skyeCalled = true;
			return skyeBody.apply(skyeContext, skyeArguments);
		}
	}

	if (window.skyeCriticalStyles && typeof window.skyeCriticalStyles.run === 'function') {
		try {
			window.skyeCriticalStyles.run(skyeInvoke, skyeContext, skyeArguments);
			return;
		} catch (skyeError) {
			if (skyeCalled) { throw skyeError; }
		}
	}
	var skyeLinks = document.querySelectorAll('link[data-skye-after-paint="1"]');
	var skyeWaiting = [];

	/**
	 * Recover one original link without depending on the failed shared helper.
	 * @param {HTMLLinkElement} skyeLink Original opted-in native link.
	 * @return {Promise|undefined} Bounded matching-style readiness only.
	 * @since 2026-11-02
	 */
	function skyeWatch(skyeLink) {
		var skyeMatching = !skyeLink.media || !window.matchMedia || window.matchMedia(skyeLink.media).matches;
		if (!skyeMatching) {
			skyeLink.rel = 'stylesheet';
			skyeLink.removeAttribute('as');
			skyeLink.removeAttribute('data-skye-after-paint');
			return;
		}
		return new Promise(function (resolve) {
			var skyeDone = false;
			var skyeTimer;
			/**
			 * Release one fallback stylesheet after load, error or timeout.
			 * @return {void}
			 * @since 2026-11-02
			 */
			function skyeFinish() {
				if (!skyeDone) {
					skyeDone = true;
					window.clearTimeout(skyeTimer);
					skyeLink.removeEventListener('load', skyeFinish);
					skyeLink.removeEventListener('error', skyeFinish);
					skyeLink.removeAttribute('data-skye-after-paint');
					resolve();
				}
			}
			skyeLink.addEventListener('load', skyeFinish, { once: true });
			skyeLink.addEventListener('error', skyeFinish, { once: true });
			skyeTimer = window.setTimeout(skyeFinish, 4000);
			skyeLink.rel = 'stylesheet';
			skyeLink.removeAttribute('as');
			if (skyeLink.sheet) { skyeFinish(); }
		});
	}

	for (var skyeIndex = 0; skyeIndex < skyeLinks.length; skyeIndex++) {
		var skyeReady = skyeWatch(skyeLinks[skyeIndex]);
		if (skyeReady) { skyeWaiting.push(skyeReady); }
	}
	Promise.all(skyeWaiting).then(skyeInvoke, skyeInvoke);
}(this, arguments, typeof event === 'undefined' ? undefined : event));
