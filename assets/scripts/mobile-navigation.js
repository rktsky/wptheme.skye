/** Enhance native details navigation with one bounded modal; keep Core links and brand nodes intact. @since 3.2.0 */

const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/** Return only rendered sequential keyboard destinations inside the actual dialog. @since 3.2.0 */
function focusable(dialog) {
	return [...dialog.querySelectorAll(focusableSelector)].filter(node => node.tabIndex >= 0 && !node.closest('[inert]') && node.getClientRects().length && getComputedStyle(node).visibility !== 'hidden');
}

/** Resolve only an existing same-document fragment; modifiers and foreign navigation stay native. @since 3.2.0 */
function fragmentTarget(link) {
	try {
		const url = new URL(link.href, window.location.href);
		if (!url.hash || url.origin !== window.location.origin || url.pathname !== window.location.pathname || url.search !== window.location.search) return null;
		const target = document.getElementById(decodeURIComponent(url.hash.slice(1)));
		return target ? {target, hash: url.hash} : null;
	} catch {
		return null;
	}
}

/** Initialize one explicit component; missing native modal support retains its complete details fallback. @since 3.2.0 */
function initializeNavigation(root) {
	if (root.dataset.skyeNavigationReady || typeof HTMLDialogElement === 'undefined' || typeof HTMLDialogElement.prototype.showModal !== 'function') return null;
	const opener = root.querySelector('[data-skye-navigation-open]');
	const dialog = root.querySelector('[data-skye-navigation-dialog]');
	const closeButton = root.querySelector('[data-skye-navigation-close]');
	const fallback = root.querySelector('[data-skye-navigation-fallback]');
	const slot = root.querySelector('[data-skye-navigation-brand-slot]');
	const linksPanel = root.querySelector('.skye-navigation-dialog-links');
	const breakpoint = Number(root.dataset.skyeNavigationBreakpoint);
	if (!opener || !dialog || !closeButton || !fallback || ![768, 992, 1200].includes(breakpoint) || opener.getAttribute('aria-controls') !== dialog.id) return null;
	const desktop = window.matchMedia('(min-width: ' + breakpoint + 'px)');
	const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
	let state = 'closed';
	let brand = null;
	let placeholder = null;
	let afterClose = null;
	let closeTimer = null;
	let closeReason = 'close';
	let lifecycle = 0;

	/** Publish lifecycle only; consumers may adapt branding without receiving menu data. @since 3.2.0 */
	function announce(name, reason) {
		root.dispatchEvent(new CustomEvent('skye:navigation:' + name, {bubbles: true, detail: {id: dialog.id, reason}}));
	}

	/** Move the same owned brand node into the top layer; listeners and SVG IDs are preserved. @since 3.2.0 */
	function moveBrand() {
		if (!slot) return;
		const header = root.closest('header');
		const candidate = header?.querySelector('[data-skye-navigation-brand-for="' + dialog.id + '"]');
		if (!candidate || dialog.contains(candidate)) return;
		brand = candidate;
		placeholder = document.createComment('skye-navigation-brand');
		brand.before(placeholder);
		slot.append(brand);
	}

	/** Restore the exact original position instead of rendering a second animated logo. @since 3.2.0 */
	function restoreBrand() {
		if (brand && placeholder?.isConnected) placeholder.replaceWith(brand);
		brand = null;
		placeholder = null;
	}

	/** Complete one close exactly once, including native/foreign close and breakpoint cleanup. @since 3.2.0 */
	function finishedClose() {
		if (state === 'closed') return;
		const focusedHref = dialog.contains(document.activeElement) ? document.activeElement.closest('a[href]')?.href : null;
		state = 'closed';
		lifecycle++;
		clearTimeout(closeTimer);
		dialog.removeAttribute('data-skye-navigation-closing');
		if (dialog.open) dialog.close();
		opener.setAttribute('aria-expanded', 'false');
		restoreBrand();
		const destination = afterClose;
		afterClose = null;
		if (destination?.target.isConnected) {
			const {target, hash} = destination;
			const temporary = !target.hasAttribute('tabindex') && target.tabIndex < 0;
			if (temporary) {
				target.setAttribute('tabindex', '-1');
				target.addEventListener('blur', () => {
					if (target.getAttribute('tabindex') === '-1') target.removeAttribute('tabindex');
				}, {once: true});
			}
			if (window.location.hash !== hash) window.location.hash = hash;
			target.focus({preventScroll: true});
			target.scrollIntoView({behavior: reducedMotion.matches ? 'instant' : 'auto', block: 'start'});
		} else if (desktop.matches && closeReason === 'breakpoint') {
			const links = [...root.querySelectorAll('.skye-navigation-desktop a[href]')].filter(link => link.getClientRects().length);
			(links.find(link => link.href === focusedHref) || links[0])?.focus({preventScroll: true});
		} else if (!desktop.matches && opener.isConnected) {
			opener.focus({preventScroll: true});
		}
		announce('closed', closeReason);
	}

	/** Bound closing animation while Escape and rapid close requests remain effective. @since 3.2.0 */
	function close(reason = 'close', destination = null) {
		if (state === 'closed') return;
		if (destination) afterClose = destination;
		closeReason = reason;
		if (state === 'closing') {
			if (desktop.matches || reducedMotion.matches) finishedClose();
			return;
		}
		state = 'closing';
		const ticket = ++lifecycle;
		if (reducedMotion.matches || desktop.matches) {
			finishedClose();
			return;
		}
		dialog.setAttribute('data-skye-navigation-closing', '');
		const animations = typeof dialog.getAnimations === 'function' ? dialog.getAnimations() : [];
		if (!animations.length) {
			finishedClose();
			return;
		}
		// A removed stylesheet or interrupted animation must not leave an inert page.
		const finish = () => { if (ticket === lifecycle) finishedClose(); };
		closeTimer = setTimeout(finish, 300);
		Promise.all(animations.map(animation => animation.finished.catch(() => {}))).then(finish);
	}

	/** Open only in the mobile layout and focus the first destination after native modal activation. @since 3.2.0 */
	function open() {
		if (state !== 'closed' || desktop.matches) return;
		moveBrand();
		try {
			dialog.showModal();
			dialog.scrollTop = 0;
			if (linksPanel) linksPanel.scrollTop = 0;
		} catch {
			restoreBrand();
			opener.hidden = true;
			fallback.hidden = false;
			root.removeAttribute('data-skye-navigation-ready');
			return;
		}
		state = 'open';
		lifecycle++;
		opener.setAttribute('aria-expanded', 'true');
		const firstLink = [...dialog.querySelectorAll('.skye-navigation-dialog-links a[href]')].find(link => link.getClientRects().length);
		(firstLink || closeButton).focus({preventScroll: true});
		announce('opened', 'toggle');
	}

	opener.addEventListener('click', open);
	closeButton.addEventListener('click', () => close('close'));
	dialog.addEventListener('cancel', event => {
		event.preventDefault();
		close('escape');
	});
	dialog.addEventListener('close', () => { if (!dialog.open) finishedClose(); });
	dialog.addEventListener('keydown', event => {
		if (event.defaultPrevented || !dialog.open) return;
		if (event.key === 'Escape') {
			event.preventDefault();
			close('escape');
		} else if (event.key === 'Tab') {
			const elements = focusable(dialog);
			const first = elements[0];
			const last = elements[elements.length - 1];
			if (!first) {
				event.preventDefault();
				dialog.focus();
			} else if (event.shiftKey && (document.activeElement === first || !dialog.contains(document.activeElement))) {
				event.preventDefault();
				last.focus();
			} else if (!event.shiftKey && (document.activeElement === last || !dialog.contains(document.activeElement))) {
				event.preventDefault();
				first.focus();
			}
		}
	});
	dialog.addEventListener('click', event => {
		if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
		const link = event.target.closest('a[href]');
		if (!link || !dialog.contains(link) || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;
		const destination = fragmentTarget(link);
		if (destination) {
			event.preventDefault();
			close('fragment', destination);
		} else {
			close('link');
		}
	});
	desktop.addEventListener('change', () => {
		if (desktop.matches) {
			afterClose = null;
			close('breakpoint');
			fallback.open = false;
		}
	});
	root.dataset.skyeNavigationReady = 'true';
	fallback.open = false;
	fallback.hidden = true;
	opener.hidden = false;
	return {open, close};
}

for (const navigation of document.querySelectorAll('[data-skye-navigation]')) initializeNavigation(navigation);
