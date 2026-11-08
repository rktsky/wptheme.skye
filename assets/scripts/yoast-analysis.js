/* Project one complete active editorial source through Yoast's supported content API. */
( function ( $, window ) {
	'use strict';
	const id = window.SkyeYoastAnalysisConfig?.id;
	if ( typeof id !== 'string' || ! /^[a-zA-Z][a-zA-Z0-9_-]{0,63}$/.test( id ) || window.SkyeYoastAnalysis ) return;
	const plugin = 'SkyeAnalysis-' + id;
	let projection = null;
	let registeredApp = null;
	let failed = false;
	let pending = null;
	let timer;

	/**
	 * Remember this synchronous pipeline's input before the original ACF append.
	 * @since 3.5.0
	 * @param {mixed} content Current native contribution, returned without mutation.
	 * @returns {mixed} The exact incoming value; interrupted captures expire at the microtask boundary.
	 */
	function capture( content ) {
		const current = typeof content === 'string' ? { content } : null;
		pending = current;
		Promise.resolve().then( () => { if ( pending === current ) pending = null; } );
		return content;
	}

	/**
	 * Replace the combined source only when this cycle has a ready project projection.
	 * @since 3.5.0
	 * @param {mixed} combined Native input including the unchanged original ACF collector.
	 * @returns {mixed} Complete project HTML, or the complete native input on refusal or failure.
	 */
	function content( combined ) {
		const current = pending;
		pending = null;
		if ( ! current || typeof combined !== 'string' || typeof projection !== 'function' ) return combined;
		try {
			const result = projection( current.content, combined );
			return typeof result === 'string' ? result : combined;
		} catch { return combined; }
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
	 * Register once after both the project callback and native worker become available.
	 * @since 3.5.0
	 * @returns {void} Unsupported or partially failing APIs leave the original collector untouched.
	 */
	function ready() {
		const app = window.YoastSEO?.app;
		if ( failed || registeredApp || ! projection || ! app || ! window.YoastSEO.analysis?.worker ||
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
		return ! failed;
	}

	window.SkyeYoastAnalysis = Object.freeze( { register, refresh } );
	$( window ).on( 'YoastSEO:ready', ready );
} )( jQuery, window );
