/* Project one complete active editorial source through Yoast's supported content API. */
( function ( $, window ) {
	'use strict';
	const id = window.SkyeYoastAnalysisConfig?.id;
	if ( typeof id !== 'string' || ! /^[a-zA-Z][a-zA-Z0-9_-]{0,63}$/.test( id ) || window.SkyeYoastAnalysis ) return;
	const plugin = 'SkyeAnalysis-' + id;
	let projection = null;
	let sourcePolicy = null;
	let registeredApp = null;
	let failed = false;
	let pending = null;
	let timer;

	/**
	 * Capture one current source decision before the original ACF append.
	 * @since 3.5.0
	 * @param {mixed} content Current native contribution, returned without mutation.
	 * @returns {mixed} Active collector input, or untouched native content when source readiness is unknown.
	 */
	function capture( content ) {
		if ( failed || ! registeredApp || window.YoastSEO?.app !== registeredApp ) { pending = null; return content; }
		const current = typeof content === 'string' ? { content, policy: typeof sourcePolicy === 'function', source: null } : null;
		if ( current?.policy ) {
			try {
				const selected = sourcePolicy();
				if ( selected === 'builder' || selected === 'editor' ) current.source = selected;
			} catch { /* An unavailable controller keeps the full native collector path. */ }
		}
		pending = current;
		Promise.resolve().then( () => { if ( pending === current ) pending = null; } );
		return current?.source === 'builder' ? '' : content;
	}

	/**
	 * Replace the combined source only when this cycle has a ready project projection.
	 * @since 3.5.0
	 * @param {mixed} combined Native input including the unchanged original ACF collector.
	 * @returns {mixed} Complete semantic HTML or the native contribution for this cycle's verified active source.
	 */
	function content( combined ) {
		const current = pending;
		pending = null;
		if ( ! current || typeof combined !== 'string' || ( current.policy && ! current.source ) ) return combined;
		const active = current.source === 'editor' ? current.content : combined;
		if ( typeof projection !== 'function' ) return active;
		try {
			const result = projection( current.content, combined );
			return typeof result === 'string' ? result : active;
		} catch { return active; }
	}

	/**
	 * Debounce actual unsaved project changes through Yoast's public refresh API.
	 * @since 3.5.0
	 * @param {string} name Exact identity supplied by the authorized PHP editor contract.
	 * @returns {boolean} Whether an active native registration accepted the refresh request.
	 */
	function refresh( name ) {
		if ( name !== id || ! registeredApp || window.YoastSEO?.app !== registeredApp || typeof registeredApp.pluginReloaded !== 'function' ) return false;
		window.clearTimeout( timer );
		timer = window.setTimeout( () => {
			if ( window.YoastSEO?.app !== registeredApp ) return;
			try { registeredApp.pluginReloaded( plugin ); } catch { /* Keep native provider recovery in control. */ }
		}, 100 );
		return true;
	}

	/**
	 * Register once when a source policy or semantic callback and the native worker are ready.
	 * @since 3.5.0
	 * @returns {void} Unsupported or partially failing APIs leave the original collector untouched.
	 */
	function ready() {
		const app = window.YoastSEO?.app;
		if ( failed || registeredApp || ( ! projection && ! sourcePolicy ) || ! app || ! window.YoastSEO.analysis?.worker ||
			typeof app.registerPlugin !== 'function' || typeof app.registerModification !== 'function' ) return;
		try {
			app.registerPlugin( plugin, { status: 'ready' } );
			app.registerModification( 'content', capture, plugin, 5 );
			app.registerModification( 'content', content, plugin, 20 );
			registeredApp = app;
			refresh( id );
		} catch { failed = true; pending = null; }
	}

	/**
	 * Accept one owned complete-source callback, including asynchronously loaded adapters.
	 * @since 3.5.0
	 * @param {string} name Exact current project identity.
	 * @param {Function} callback Pure synchronous projection of pre-ACF and combined content.
	 * @returns {boolean} Whether the callback was accepted; native initialization may still be pending.
	 */
	function register( name, callback ) {
		if ( name !== id || typeof callback !== 'function' || ( projection && projection !== callback ) ) return false;
		projection = callback;
		ready();
		if ( registeredApp ) refresh( id );
		return ! failed;
	}

	/**
	 * Select one verified live source without replacing the native ACF field collector.
	 *
	 * The getter runs once per synchronous analysis, before the collector. Builder
	 * excludes the inactive editor from its input; editor discards the append.
	 * Unknown, asynchronous or throwing decisions retain the whole native path.
	 * @since 3.5.0
	 * @param {string} name Exact current project identity.
	 * @param {Function} getter Current verified controller source, builder or editor.
	 * @returns {boolean} Whether this one source-policy owner was accepted.
	 */
	function setSource( name, getter ) {
		if ( name !== id || typeof getter !== 'function' || ( sourcePolicy && sourcePolicy !== getter ) ) return false;
		sourcePolicy = getter;
		ready();
		if ( registeredApp ) refresh( id );
		return ! failed;
	}

	window.SkyeYoastAnalysis = Object.freeze( { register, refresh, setSource } );
	$( window ).on( 'YoastSEO:ready', ready );
} )( jQuery, window );
