/* Bind Yoast's native collector to the verified current content-source controller. */
( function ( window, document ) {
	'use strict';
	const id = window.SkyeYoastAnalysisConfig?.id;
	if ( typeof id !== 'string' || ! /^[a-zA-Z][a-zA-Z0-9_-]{0,63}$/.test( id ) ) return;
	let controller = null;
	let connected = false;

	/**
	 * Read the ready controller directly rather than trusting an event payload.
	 * @since 3.5.0
	 * @returns {string|null} Current builder/editor source, or an unknown native fallback.
	 */
	function source() {
		if ( ! controller || window.SkyeContentSources !== controller || typeof controller.getSource !== 'function' ) return null;
		const selected = controller.getSource();
		return selected === 'builder' || selected === 'editor' ? selected : null;
	}

	/**
	 * Connect either initialization order and refresh after genuine source changes.
	 *
	 * An unavailable controller never starts preprocessing. Once connected, an
	 * unknown current source makes the existing policy retain native analysis.
	 * @since 3.5.0
	 * @returns {void} No content, input, storage or provider registration is changed.
	 */
	function connect() {
		const analysis = window.SkyeYoastAnalysis;
		const current = window.SkyeContentSources;
		if ( ! analysis || typeof analysis.setSource !== 'function' || typeof analysis.refresh !== 'function' ) return;
		if ( ! controller && current && typeof current.getSource === 'function' ) controller = current;
		let selected = null;
		try { selected = source(); } catch { /* Retain the native path for unavailable controllers. */ }
		if ( ! connected && selected ) connected = analysis.setSource( id, source );
		if ( connected ) analysis.refresh( id );
	}

	document.addEventListener( 'skye:content-source:change', connect );
	connect();
} )( window, document );
