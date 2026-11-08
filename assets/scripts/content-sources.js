/* Keep both native content sources in the post form while enhancing their visibility. */
( function ( $, window, document ) {
	'use strict';
	const config = window.SkyeContentSourcesConfig;
	if ( ! config || typeof config.fieldKey !== 'string' || ! /^field_[a-zA-Z0-9_-]{1,120}$/.test( config.fieldKey ) ||
		! [ 'builder', 'editor' ].includes( config.source ) || ! /^[1-9][0-9]*$/.test( String( config.postId ) ) ||
		! Number.isSafeInteger( Number( config.postId ) ) || ! window.acf || typeof window.acf.getField !== 'function' ||
		typeof window.acf.addAction !== 'function' || typeof window.FormData !== 'function' ||
		typeof window.FormDataEvent !== 'function' || window.SkyeContentSources ) return;

	let state = null;
	let active = false;
	let initialized = false;
	let synchronizing = false;
	let sourceGeneration = 0;
	let submitIntent = null;
	let invalidRetry = null;
	let validationLock = null;
	const nativeControlTags = new Set( [ 'INPUT', 'SELECT', 'TEXTAREA', 'BUTTON', 'FIELDSET', 'OUTPUT', 'OBJECT' ] );

	/**
	 * Cancel only this controller's deferred invalid-cycle retry.
	 * @since 3.5.0
	 * @returns {void} Native ACF requests, timers and error collectors remain untouched.
	 */
	function cancelRetry() {
		if ( invalidRetry !== null ) window.clearTimeout( invalidRetry.timer );
		invalidRetry = null;
	}

	/**
	 * Forget only the own user intent and its pending one-task expiry.
	 * @since 3.5.0
	 * @returns {void} Native submission, validation and provider timers remain untouched.
	 */
	function forgetIntent() {
		if ( submitIntent && submitIntent.timer !== null ) window.clearTimeout( submitIntent.timer );
		submitIntent = null;
	}

	/**
	 * Restore only the inert state introduced on the owned chooser for one validation.
	 * @since 3.5.0
	 * @returns {void} A chooser that was already inert is never made interactive.
	 */
	function releaseValidation() {
		const lock = validationLock;
		validationLock = null;
		if ( lock?.changed ) lock.chooser.inert = lock.previous;
	}

	/**
	 * Remove only owned presentation markers so a failed enhancement hides no source.
	 * @since 3.5.0
	 * @returns {void} Native controls, conditional states, editor instances and values stay intact.
	 */
	function fallback() {
		const wasActive = active;
		active = false;
		cancelRetry();
		forgetIntent();
		releaseValidation();
		if ( ! state ) return;
		state.form.removeAttribute( 'data-skye-content-sources-ready' );
		state.form.removeAttribute( 'data-skye-content-source' );
		state.builder.removeAttribute( 'data-skye-content-source-panel' );
		state.editor.removeAttribute( 'data-skye-content-source-panel' );
		state.group?.removeAttribute( 'data-skye-content-source-group' );
		if ( wasActive ) announce( null );
	}

	/**
	 * Publish only the current source identity after its presentation state is settled.
	 * @since 3.5.0
	 * @param {string|null} source Active builder/editor, or null for an unavailable own controller.
	 * @returns {void} No content, field value, nonce or analysis result is exposed.
	 */
	function announce( source ) {
		document.dispatchEvent( new window.CustomEvent( 'skye:content-source:change', { detail: { source, postId: Number( config.postId ) } } ) );
	}

	/**
	 * Expose only a fully initialized current source to independent analysis adapters.
	 * @since 3.5.0
	 * @returns {string|null} Active builder/editor, or null for native/unknown presentation.
	 */
	function getSource() {
		if ( ! active || ! initialized || ! state.form.contains( state.builder ) || ! state.form.contains( state.editor ) ||
			state.form.getAttribute( 'data-skye-content-sources-ready' ) !== 'true' ) return null;
		const selected = state.radios.filter( radio => radio.checked );
		const source = state.form.getAttribute( 'data-skye-content-source' );
		return selected.length === 1 && selected[ 0 ].value === source && [ 'builder', 'editor' ].includes( source ) ? source : null;
	}

	/**
	 * Admit a native ACF postbox only when every field belongs to the one builder root.
	 *
	 * A direct native fields container must hold exactly the configured top-level
	 * field. Any additional field elsewhere in the postbox keeps the shared group
	 * visible; only the builder field itself may then be hidden.
	 * @since 3.5.0
	 * @param {HTMLElement} builder Exact native flexible-content field wrapper.
	 * @param {HTMLFormElement} form Current native post form.
	 * @returns {HTMLElement|null} Exclusive native postbox, or no group-level hiding.
	 */
	function exclusiveGroup( builder, form ) {
		const container = builder.parentElement;
		if ( ! container?.classList.contains( 'acf-fields' ) ) return null;
		const fields = [ ...container.children ].filter( node => node.classList.contains( 'acf-field' ) );
		if ( fields.length !== 1 || fields[ 0 ] !== builder ) return null;
		const group = container.closest( '.acf-postbox' );
		return group && group.classList.contains( 'postbox' ) && form.contains( group ) &&
			[ ...group.querySelectorAll( '.acf-field' ) ].every( field => builder.contains( field ) ) ? group : null;
	}

	/**
	 * Admit only native form-associated controls with a readable constraint-validation contract.
	 *
	 * This shape check runs at existing source/lifecycle/submit boundaries, before
	 * any inactive invalid event may be cancelled. Unknown custom controls revoke
	 * hiding; known invalid controls remain available to their native handlers.
	 * @since 3.5.0
	 * @param {HTMLFormElement} form Exact current native post form.
	 * @returns {boolean} Whether every listed control has the supported public native API.
	 */
	function knownControls( form ) {
		for ( const control of form.elements ) {
			if ( ! nativeControlTags.has( control.tagName ) || control.form !== form || typeof control.willValidate !== 'boolean' ||
				typeof control.matches !== 'function' || ! control.validity || typeof control.validity.valid !== 'boolean' ) return false;
		}
		return true;
	}

	/**
	 * Resolve one exact native post form and ACF field without global same-name fallbacks.
	 * @since 3.5.0
	 * @returns {object|null} Matching form, chooser, field and editor nodes, or an untouched native fallback.
	 */
	function contract() {
		const form = document.getElementById( 'post' );
		const chooser = document.querySelector( '#skye-content-source [data-skye-content-sources]' );
		const editor = document.getElementById( 'postdivrich' );
		const nativeId = document.getElementById( 'post_ID' );
		const content = editor?.querySelector( 'textarea#content' );
		if ( ! form || form.tagName !== 'FORM' || typeof form.requestSubmit !== 'function' ||
			! chooser || typeof chooser.inert !== 'boolean' || ! editor || ! nativeId ||
			! content || content.form !== form || content.name !== 'content' || content.disabled ||
			nativeId.form !== form || nativeId.value !== String( config.postId ) || ! form.contains( chooser ) || ! form.contains( editor ) || ! knownControls( form ) ) return null;
		const identity = chooser.querySelector( 'input[name="skye_content_source_post"]' );
		const radios = [ ...chooser.querySelectorAll( 'input[name="skye_content_source"][type="radio"]' ) ];
		const builders = [ ...form.querySelectorAll( '.acf-field[data-key="' + config.fieldKey + '"]' ) ].filter( node => ! node.closest( '.acf-clone' ) );
		if ( ! identity || identity.form !== form || identity.value !== String( config.postId ) || radios.length !== 2 ||
			radios.some( radio => radio.form !== form || radio.disabled ) || new Set( radios.map( radio => radio.value ) ).size !== 2 ||
			! radios.every( radio => [ 'builder', 'editor' ].includes( radio.value ) ) || radios.filter( radio => radio.checked ).length !== 1 ||
			builders.length !== 1 || builders[ 0 ].getAttribute( 'data-type' ) !== 'flexible_content' ) return null;
		const field = window.acf.getField( config.fieldKey );
		if ( ! field || typeof field.get !== 'function' || field.get( 'type' ) !== 'flexible_content' || field.$el?.[ 0 ] !== builders[ 0 ] ) return null;
		return { form, chooser, editor, builder: builders[ 0 ], group: exclusiveGroup( builders[ 0 ], form ), radios };
	}

	/**
	 * List only real source textareas; ACF clone templates never enter synchronization.
	 * @since 3.5.0
	 * @returns {HTMLTextAreaElement[]} Existing controlled source nodes, without moving or creating controls.
	 */
	function textareas() {
		const native = state.editor.querySelector( 'textarea#content' );
		const builder = [ ...state.builder.querySelectorAll( 'textarea[id]' ) ].filter( node => node.form === state.form && node.name && ! node.closest( '.acf-clone' ) );
		return [ ...new Set( native ? [ native, ...builder ] : builder ) ];
	}

	/**
	 * Read an existing native TinyMCE instance without creating or reinitializing an editor.
	 * @since 3.5.0
	 * @param {HTMLTextAreaElement} textarea Actual successful-control candidate in a source.
	 * @returns {object|null} Matching current native editor, or no initialized visual editor.
	 */
	function nativeEditor( textarea ) {
		if ( ! window.tinymce ) return null;
		if ( typeof window.tinymce.get !== 'function' ) throw new Error( 'Unsupported native editor manager.' );
		const editor = window.tinymce.get( textarea.id );
		if ( ! editor ) return null;
		if ( typeof editor.getElement !== 'function' || editor.getElement() !== textarea ||
			typeof editor.isHidden !== 'function' || typeof editor.save !== 'function' ||
			typeof editor.isDirty !== 'function' || typeof editor.setDirty !== 'function' ) {
			throw new Error( 'Unsupported native editor instance.' );
		}
		return editor;
	}

	/**
	 * Flush only controlled visual editors through their native save contract.
	 * @since 3.5.0
	 * @returns {boolean} Whether both source values remain serializable; code mode and native dirty state stay authoritative.
	 */
	function synchronize() {
		if ( ! state || synchronizing ) return true;
		synchronizing = true;
		try {
			for ( const textarea of textareas() ) {
				const editor = nativeEditor( textarea );
				if ( editor && ! editor.isHidden() ) {
					const dirty = editor.isDirty();
					if ( typeof dirty !== 'boolean' ) throw new Error( 'Unsupported native dirty state.' );
					editor.save( { set_dirty: false } );
					if ( editor.isDirty() !== dirty ) editor.setDirty( dirty );
				}
			}
			return true;
		} catch {
			fallback();
			return false;
		} finally {
			synchronizing = false;
		}
	}

	/**
	 * Check late native editor instances without adding per-keystroke serialization.
	 * @since 3.5.0
	 * @returns {boolean} Whether all present controlled editor instances support the known native API.
	 */
	function observeEditors() {
		try {
			for ( const textarea of textareas() ) nativeEditor( textarea );
			return true;
		} catch {
			fallback();
			return false;
		}
	}

	/**
	 * Refresh existing successful textarea entries after native FormData construction.
	 *
	 * The formdata event occurs after controls were serialized. Only one existing
	 * entry with the exact native textarea name is replaced; absent, disabled,
	 * clone, foreign-form and repeated-name controls are never added or restored.
	 * @since 3.5.0
	 * @param {FormDataEvent} event Browser-owned completed form data.
	 * @returns {void} Native entry membership, names, other controls and nonces stay intact.
	 */
	function formData( event ) {
		if ( ! active ) return;
		try {
			if ( ! ( event.formData instanceof window.FormData ) ) {
				fallback();
				return;
			}
			update( false );
			if ( ! active ) return;
			for ( const textarea of textareas() ) {
				if ( ! textarea.matches( ':disabled' ) && ! textarea.closest( 'datalist' ) && textarea.name &&
					textarea.form === state.form && event.formData.getAll( textarea.name ).length === 1 ) {
					event.formData.set( textarea.name, textarea.value );
				}
			}
		} catch {
			fallback();
		}
	}

	/**
	 * Match one actual native submit control without inferring an action or changing its payload.
	 * @since 3.5.0
	 * @param {HTMLElement} submitter Existing clicked control.
	 * @returns {boolean} Whether this same native submitter still belongs to the post form.
	 */
	function sameSubmitter( submitter ) {
		return !!submitter && [ 'INPUT', 'BUTTON' ].includes( submitter.tagName ) && submitter.type === 'submit' &&
			submitter.form === state.form && state.form.contains( submitter ) &&
			typeof submitter.formNoValidate === 'boolean';
	}

	/**
	 * Keep directly and fieldset-disabled submitters outside ordinary native retry admission.
	 * @since 3.5.0
	 * @param {HTMLElement} submitter Existing clicked control.
	 * @returns {boolean} Whether the same current submitter is natively enabled.
	 */
	function validSubmitter( submitter ) {
		return sameSubmitter( submitter ) && ! submitter.matches( ':disabled' );
	}

	/**
	 * Remember the real user submitter, including the browser's implicit keyboard click.
	 * @since 3.5.0
	 * @param {MouseEvent} event Captured native form click.
	 * @returns {void} ACF's synchronous synthetic handoff retains the original bound intent.
	 */
	function rememberSubmitter( event ) {
		if ( ! active || getSource() !== 'editor' || validationLock ) return;
		const submitter = event.target?.closest( 'button, input' );
		if ( ! validSubmitter( submitter ) ) return;
		if ( event.isTrusted !== true ) {
			if ( submitIntent && submitter !== submitIntent.submitter ) {
				cancelRetry();
				forgetIntent();
			}
			return;
		}
		update( false );
		if ( ! active || getSource() !== 'editor' ) return;
		cancelRetry();
		forgetIntent();
		const intent = { submitter, click: event, form: state.form, builder: state.builder, generation: sourceGeneration, timer: null };
		submitIntent = intent;
		intent.timer = window.setTimeout( () => {
			intent.timer = null;
			if ( submitIntent === intent && invalidRetry?.intent !== intent && validationLock?.intent !== intent ) forgetIntent();
		}, 0 );
	}

	/**
	 * Check every current form-associated control without firing more invalid events.
	 *
	 * Unknown custom controls decline the retry. Outside controls, including ones
	 * linked through form= outside the DOM subtree, keep their native constraints.
	 * @since 3.5.0
	 * @returns {boolean} Whether only the known inactive root may contain invalid native controls.
	 */
	function outsideControlsValid() {
		for ( const control of state.form.elements ) {
			if ( ! nativeControlTags.has( control.tagName ) || control.form !== state.form || typeof control.willValidate !== 'boolean' ) return false;
			if ( control.matches( ':disabled' ) || ! control.willValidate ) continue;
			if ( ! control.validity || typeof control.validity.valid !== 'boolean' ) return false;
			if ( state.builder.contains( control ) && ! control.closest( '.acf-clone' ) ) continue;
			if ( ! control.validity.valid ) return false;
		}
		return true;
	}

	/**
	 * Repeat only ACF's public original-submitter handoff after its success completion relocks buttons.
	 *
	 * A matching public success receipt is granted while the original submitter is
	 * still enabled. It authorizes one public enableSubmit call for that exact
	 * control; fieldset-disabled controls, replacement APIs and other intents never qualify.
	 * @since 3.5.0
	 * @param {object} intent Same real user intent bound before native validation began.
	 * @returns {boolean} Whether the original submitter is enabled through its proven native handoff.
	 */
	function validatedSubmitter( intent ) {
		const submitter = intent.submitter;
		if ( validSubmitter( submitter ) ) return true;
		const receipt = intent.receipt;
		if ( ! sameSubmitter( submitter ) || submitter.disabled !== true || submitter.closest( 'fieldset:disabled' ) ||
			! receipt || receipt.intent !== intent || receipt.form !== state.form || receipt.builder !== state.builder ||
			receipt.generation !== sourceGeneration || receipt.source !== getSource() || receipt.submitter !== submitter ||
			typeof receipt.enableSubmit !== 'function' || window.acf.enableSubmit !== receipt.enableSubmit ) return false;
		intent.receipt = null;
		receipt.enableSubmit( $( submitter ) );
		return validSubmitter( submitter );
	}

	/**
	 * Resume one bound inactive-root invalid cycle through the browser's real submitter path.
	 *
	 * Only this submitter's formnovalidate is set for the synchronous requestSubmit
	 * call, after checking every other control. Exact native attribute spelling is
	 * restored immediately; fields, form constraints and submitter values never change.
	 * @since 3.5.0
	 * @param {object} job Exact queued intent, form, root and generation.
	 * @returns {void} No provider error, validation success or post status is manufactured.
	 */
	function retryInvalid( job ) {
		if ( invalidRetry !== job ) return;
		invalidRetry = null;
		try {
			update( false );
			if ( ! active || getSource() !== 'editor' || validationLock || submitIntent !== job.intent || job.intent.click.defaultPrevented ||
				job.intent.form !== state.form || job.intent.builder !== state.builder || job.intent.generation !== sourceGeneration ||
				! outsideControlsValid() || ! validatedSubmitter( job.intent ) ) {
				if ( submitIntent === job.intent ) forgetIntent();
				return;
			}
			update( false );
			if ( ! active || getSource() !== 'editor' || validationLock || submitIntent !== job.intent || job.intent.click.defaultPrevented ||
				job.intent.form !== state.form || job.intent.builder !== state.builder || job.intent.generation !== sourceGeneration ||
				! validSubmitter( job.intent.submitter ) || ! outsideControlsValid() ) {
				if ( submitIntent === job.intent ) forgetIntent();
				return;
			}
			const submitter = job.intent.submitter;
			const hadAttribute = submitter.hasAttribute( 'formnovalidate' );
			const attribute = submitter.getAttribute( 'formnovalidate' );
			try {
				submitter.formNoValidate = true;
				state.form.requestSubmit( submitter );
			} finally {
				if ( hadAttribute ) submitter.setAttribute( 'formnovalidate', attribute );
				else submitter.removeAttribute( 'formnovalidate' );
				if ( ! validationLock && submitIntent === job.intent && invalidRetry === null ) forgetIntent();
			}
		} catch {
			fallback();
		}
	}

	/**
	 * Suppress only new native HTML5 errors for this ready, deliberately inactive root.
	 * @since 3.5.0
	 * @param {Event} event Captured native invalid event before ACF's direct target listener.
	 * @returns {void} Active-builder, foreign and unknown controls retain their original invalid handlers.
	 */
	function inactiveInvalid( event ) {
		if ( ! active || getSource() !== 'editor' || validationLock || ! submitIntent ) return;
		try {
			update( false );
			const control = event.target;
			if ( ! active || getSource() !== 'editor' || submitIntent.click.defaultPrevented || submitIntent.form !== state.form || submitIntent.builder !== state.builder ||
				submitIntent.generation !== sourceGeneration || ! validSubmitter( submitIntent.submitter ) ||
				! nativeControlTags.has( control?.tagName ) || control.form !== state.form || ! state.builder.contains( control ) ||
				control.closest( '.acf-clone' ) || ! control.name || control.matches( ':disabled' ) || control.willValidate !== true ||
				control.validity?.valid !== false ) return;
			event.preventDefault();
			event.stopImmediatePropagation();
			if ( invalidRetry !== null ) return;
			const job = { intent: submitIntent, timer: null };
			invalidRetry = job;
			job.timer = window.setTimeout( () => retryInvalid( job ), 0 );
		} catch {
			fallback();
		}
	}

	/**
	 * Freeze only the chooser during this exact form's native asynchronous validation.
	 * @since 3.5.0
	 * @param {object} form Original ACF jQuery-wrapped form.
	 * @returns {void} Inert preserves all native successful controls and their values.
	 */
	function beginValidation( form ) {
		if ( ! active || form?.[ 0 ] !== state.form || validationLock ) return;
		update( false );
		const source = getSource();
		if ( ! active || ! source ) return;
		cancelRetry();
		validationLock = { form: state.form, chooser: state.chooser, source, intent: submitIntent, previous: state.chooser.inert, changed: ! state.chooser.inert };
		state.chooser.inert = true;
	}

	/**
	 * Release the owned lease when native validation fails, without clearing its errors.
	 * @since 3.5.0
	 * @param {object} form Original ACF jQuery-wrapped form.
	 * @returns {void} Other forms cannot release this form's existing chooser state.
	 */
	function failedValidation( form ) {
		if ( ! validationLock || form?.[ 0 ] !== validationLock.form ) return;
		releaseValidation();
		cancelRetry();
		forgetIntent();
	}

	/**
	 * Restore the chooser before ACF's synchronous native handoff; retain only a queued original intent.
	 * @since 3.5.0
	 * @param {object} form Original ACF jQuery-wrapped form.
	 * @returns {void} No validation success, source change or native save is initiated here.
	 */
	function successfulValidation( form ) {
		if ( ! validationLock || form?.[ 0 ] !== validationLock.form ) return;
		const lock = validationLock;
		const intent = submitIntent;
		update( false );
		if ( ! active || validationLock !== lock ) return;
		releaseValidation();
		if ( intent && intent === lock.intent && intent.form === state.form && intent.builder === state.builder &&
			intent.generation === sourceGeneration && lock.source === 'editor' && getSource() === 'editor' &&
			validSubmitter( intent.submitter ) && ! intent.submitter.closest( 'fieldset:disabled' ) && typeof window.acf.enableSubmit === 'function' ) {
			intent.receipt = { intent, form: state.form, builder: state.builder, generation: sourceGeneration, source: 'editor', submitter: intent.submitter, enableSubmit: window.acf.enableSubmit };
		}
		Promise.resolve().then( () => { if ( submitIntent === intent && invalidRetry === null ) forgetIntent(); } );
	}

	/**
	 * Change only source visibility after saving both native in-memory values.
	 * @since 3.5.0
	 * @param {boolean|Event} notify False only during initialization before readiness publication.
	 * @returns {void} Unknown or replaced controls revoke the enhancement instead of disabling fields.
	 */
	function update( notify = true ) {
		if ( ! active ) return;
		try {
			const current = contract();
			if ( ! current || current.form !== state.form || current.builder !== state.builder || current.editor !== state.editor ||
				current.chooser !== state.chooser || current.radios.some( ( radio, index ) => radio !== state.radios[ index ] ) ) {
				fallback();
				return;
			}
			if ( current.group !== state.group ) {
				state.group?.removeAttribute( 'data-skye-content-source-group' );
				state.group = current.group;
				state.group?.setAttribute( 'data-skye-content-source-group', 'builder' );
			}
			if ( ! observeEditors() || ! synchronize() ) return;
			const selected = state.radios.find( radio => radio.checked );
			if ( validationLock && selected.value !== validationLock.source ) {
				fallback();
				return;
			}
			if ( selected.value !== state.form.getAttribute( 'data-skye-content-source' ) ) {
				cancelRetry();
				forgetIntent();
				sourceGeneration++;
			}
			const hidden = selected.value === 'builder' ? state.editor : state.builder;
			if ( hidden.contains( document.activeElement ) ) selected.focus( { preventScroll: true } );
			state.form.setAttribute( 'data-skye-content-source', selected.value );
			if ( notify !== false ) announce( selected.value );
		} catch {
			fallback();
		}
	}

	/**
	 * Initialize once after native ACF fields exist; absent providers retain both native interfaces.
	 * @since 3.5.0
	 * @returns {void} The readiness marker is published only after listeners and synchronization succeed.
	 */
	function initialize() {
		if ( initialized ) return;
		try {
			state = contract();
			if ( ! state ) return;
			if ( ! observeEditors() || ! synchronize() ) return;
			state.chooser.addEventListener( 'change', update );
			state.form.addEventListener( 'submit', update, true );
			state.form.addEventListener( 'formdata', formData );
			state.form.addEventListener( 'click', rememberSubmitter, true );
			state.form.addEventListener( 'invalid', inactiveInvalid, true );
			state.builder.setAttribute( 'data-skye-content-source-panel', 'builder' );
			state.editor.setAttribute( 'data-skye-content-source-panel', 'editor' );
			state.group?.setAttribute( 'data-skye-content-source-group', 'builder' );
			active = true;
			initialized = true;
			update( false );
			if ( active ) {
				state.form.setAttribute( 'data-skye-content-sources-ready', 'true' );
				announce( state.form.getAttribute( 'data-skye-content-source' ) );
			}
		} catch {
			fallback();
		}
	}

	/**
	 * Observe late native editor/row initialization without replacing its lifecycle.
	 * @since 3.5.0
	 * @returns {void} Failed or unknown active contracts remain visible until a normal reload.
	 */
	function providerReady() {
		if ( ! initialized ) initialize();
		else if ( active ) update();
	}

	window.SkyeContentSources = Object.freeze( { getSource } );
	window.acf.addAction( 'ready', providerReady );
	window.acf.addAction( 'append', providerReady );
	window.acf.addAction( 'remove', () => { Promise.resolve().then( providerReady ); } );
	window.acf.addAction( 'wysiwyg_tinymce_init', providerReady );
	window.acf.addAction( 'validation_begin', beginValidation );
	window.acf.addAction( 'validation_failure', failedValidation );
	window.acf.addAction( 'validation_success', successfulValidation );
	$( document ).on( 'tinymce-editor-init.skyeContentSources', providerReady );
	$( initialize );
} )( jQuery, window, document );
