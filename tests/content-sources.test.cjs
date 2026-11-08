/** Test owned visibility and native editor callbacks with DOM/API doubles; this is not a browser or FormData acceptance test. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../assets/scripts/content-sources.js'), 'utf8');
const nativeState = new WeakMap();

/**
 * Model only native node identity, listeners and owned presentation attributes.
 * @since 3.5.0
 */
class Node {
	/**
	 * Create a controlled element without giving the production program mutating DOM APIs.
	 * @since 3.5.0
	 * @param {string} tag Native uppercase tag name.
	 * @param {object} properties Public native node properties.
	 * @returns {void}
	 */
	constructor(tag, properties = {}) {
		this.tagName = tag;
		this.attributes = new Map();
		this.children = [];
		this.listeners = new Map();
		this.selectors = new Map();
		Object.assign(this, properties);
		this.classes = new Set(properties.classes || []);
		this.classList = {contains: name => this.classes.has(name)};
		this.style = Object.freeze({});
		nativeState.set(this, properties);
		Object.defineProperty(this, 'disabled', {get: () => Boolean(nativeState.get(this).disabled), set: () => { throw new Error('Controller must not change successful-control state.'); }});
		if (['INPUT', 'BUTTON'].includes(tag) && properties.type === 'submit') {
			Object.defineProperty(this, 'formNoValidate', {get: () => this.attributes.has('formnovalidate'), set: value => {
				assert.equal(typeof value, 'boolean');
				if (value) this.attributes.set('formnovalidate', '');
				else this.attributes.delete('formnovalidate');
			}});
		}
	}
	/**
	 * Read one prepared native selector result.
	 * @since 3.5.0
	 * @param {string} selector Expected owned query.
	 * @returns {Node[]} Existing nodes.
	 */
	querySelectorAll(selector) { return this.selectors.get(selector) || []; }
	/**
	 * Read the first prepared native selector result.
	 * @since 3.5.0
	 * @param {string} selector Expected owned query.
	 * @returns {Node|null} Existing node.
	 */
	querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
	/**
	 * Identify fixture clone templates through their actual parent chain.
	 * @since 3.5.0
	 * @param {string} selector Requested native ancestor.
	 * @returns {Node|null} Existing clone ancestor only.
	 */
	closest(selector) {
		if (selector === '.acf-clone' && this.clone || selector === '.acf-postbox' && this.classes.has('acf-postbox')) return this;
		if (selector === 'button, input' && ['INPUT', 'BUTTON'].includes(this.tagName)) return this;
		if (selector === 'fieldset:disabled' && this.tagName === 'FIELDSET' && this.matches(':disabled')) return this;
		return this.parent?.closest(selector) || null;
	}
	/**
	 * Preserve the actual fixture parent identity used by native group discovery.
	 * @since 3.5.0
	 * @returns {Node|null} Current unchanged element parent.
	 */
	get parentElement() { return this.parent || null; }
	/**
	 * Represent the browser's inherited disabled-control classification.
	 * @since 3.5.0
	 * @param {string} selector Native pseudo-class under test.
	 * @returns {boolean} Direct or fieldset-inherited disabled state.
	 */
	matches(selector) { return selector === ':disabled' && (this.disabled || Boolean(this.fieldsetDisabled)); }
	/**
	 * Observe existing containment without changing parents.
	 * @since 3.5.0
	 * @param {Node} node Candidate current focus or control.
	 * @returns {boolean} Whether the candidate is within this native subtree.
	 */
	contains(node) { return node === this || this.children.some(child => child.contains(node)); }
	/**
	 * Accept only the controller's presentation metadata.
	 * @since 3.5.0
	 * @param {string} name Owned readiness/state/pane attribute.
	 * @param {string} value Current presentation state.
	 * @returns {void}
	 */
	setAttribute(name, value) {
		assert.ok(['data-skye-content-sources-ready', 'data-skye-content-source', 'data-skye-content-source-panel', 'data-skye-content-source-group'].includes(name) || name === 'formnovalidate' && this.type === 'submit');
		this.attributes.set(name, value);
	}
	/**
	 * Observe exact raw submitter-attribute presence independently of its reflected boolean.
	 * @since 3.5.0
	 * @param {string} name Native attribute name.
	 * @returns {boolean} Whether the native attribute is present.
	 */
	hasAttribute(name) { return this.attributes.has(name); }
	/**
	 * Read native fixture or controller metadata.
	 * @since 3.5.0
	 * @param {string} name Attribute name.
	 * @returns {string|null} Current value.
	 */
	getAttribute(name) { return this.attributes.get(name) ?? null; }
	/**
	 * Remove only an owned presentation attribute.
	 * @since 3.5.0
	 * @param {string} name Owned metadata name.
	 * @returns {void}
	 */
	removeAttribute(name) { this.attributes.delete(name); }
	/**
	 * Observe the native event registration used by the real controller.
	 * @since 3.5.0
	 * @param {string} name Event name.
	 * @param {Function} callback Actual production listener.
	 * @returns {void}
	 */
	addEventListener(name, callback) {
		if (!this.listeners.has(name)) this.listeners.set(name, []);
		this.listeners.get(name).push(callback);
	}
	/**
	 * Trigger one native event against actual production listeners.
	 * @since 3.5.0
	 * @param {object} event Native event-shaped observation.
	 * @returns {boolean} Native success marker.
	 */
	dispatchEvent(event) { for (const callback of this.listeners.get(event.type) || []) callback(event); return true; }
	/**
	 * Move the fixture's observed focus through the same native element method.
	 * @since 3.5.0
	 * @returns {void}
	 */
	focus() { this.document.activeElement = this; }
}

/**
 * Supply existing native form/field/editor nodes to the production controller.
 * @since 3.5.0
 * @param {object} options Specific unavailable or mismatching native contract.
 * @returns {object} Observed controls, callbacks and native editor instances.
 */
function fixture(options = {}) {
	const form = new Node('FORM', {id: 'post'});
	const chooser = new Node('FIELDSET', {form, inert: Boolean(options.inert), willValidate: false, validity: {valid: true}});
	const builder = new Node('DIV', {classes: ['acf-field']});
	const editor = new Node('DIV', {id: 'postdivrich'});
	const nativeId = new Node('INPUT', {form, value: options.postId || '123', willValidate: false, validity: {valid: true}});
	const identity = new Node('INPUT', {form, value: '123', willValidate: false, validity: {valid: true}});
	const core = new Node('TEXTAREA', {id: 'content', name: 'content', value: 'Saved native.', form, willValidate: true, validity: {valid: true}});
	const rich = new Node('TEXTAREA', {id: 'acf-editor', name: 'acf[field_fixture][row-0][field_body]', value: 'Saved builder.', form, willValidate: true, validity: {valid: true}});
	const clone = new Node('TEXTAREA', {id: 'acf-clone', name: 'acf[field_fixture][acfcloneindex][field_body]', value: 'Clone.', form, clone: true, willValidate: false, validity: {valid: true}});
	const radios = ['builder', 'editor'].map(value => new Node('INPUT', {value, checked: value === (options.selected || 'builder'), form, willValidate: true, validity: {valid: true}}));
	const publish = new Node('INPUT', {name: 'publish', value: 'Publish', type: 'submit', form, willValidate: true, validity: {valid: true}});
	const submissions = [];
	const enabledSubmitters = [];
	const timers = new Map();
	let timerId = 0;
	form.requestSubmit = submitter => { submissions.push({submitter, noValidate: submitter.formNoValidate}); options.requestSubmit?.(submitter); form.dispatchEvent({type: 'submit', submitter}); };
	if (options.noRequestSubmit) delete form.requestSubmit;
	if (options.noInert) delete chooser.inert;
	form.elements = [chooser, nativeId, identity, core, rich, clone, ...radios, publish];
	builder.attributes.set('data-type', options.fieldType || 'flexible_content');
	chooser.selectors.set('input[name="skye_content_source_post"]', [identity]);
	chooser.selectors.set('input[name="skye_content_source"][type="radio"]', radios);
	form.selectors.set('.acf-field[data-key="field_fixture"]', options.duplicate ? [builder, new Node('DIV')] : [builder]);
	editor.selectors.set('textarea#content', options.noEditor ? [] : [core]);
	builder.selectors.set('textarea[id]', [rich, clone]);
	chooser.children = [identity, ...radios];
	builder.children = [rich, clone];
	editor.children = [core];
	form.children = [chooser, builder, editor, nativeId, publish];
	for (const panel of [chooser, builder, editor]) for (const node of panel.children) node.parent = panel;
	let group = null;
	let groupFields = null;
	if (options.group) {
		group = new Node('DIV', {classes: ['postbox', 'acf-postbox']});
		groupFields = new Node('DIV', {classes: ['acf-fields']});
		builder.parent = groupFields;
		groupFields.children = [builder];
		groupFields.parent = group;
		group.children = [groupFields];
		group.parent = form;
		group.selectors.set('.acf-field', [builder]);
		form.children = [chooser, group, editor, nativeId, publish];
		if (options.sharedGroup) {
			const other = new Node('DIV', {classes: ['acf-field']});
			other.parent = groupFields;
			groupFields.children.push(other);
			group.selectors.get('.acf-field').push(other);
		}
	}
	const ids = new Map([['post', form], ['postdivrich', editor], ['post_ID', nativeId]]);
	const notifications = [];
	const document = {activeElement: null, getElementById: id => ids.get(id) || null, querySelector: selector => selector === '#skye-content-source [data-skye-content-sources]' ? chooser : null,
		dispatchEvent(event) { notifications.push({event, ready: form.getAttribute('data-skye-content-sources-ready')}); return true; }};
	for (const node of [form, chooser, builder, editor, core, rich, identity, nativeId, ...radios]) node.document = document;
	const actions = new Map();
	const events = new Map();
	const editors = new Map();
	const field = {get: name => name === 'type' ? 'flexible_content' : undefined, $el: [options.wrongModel ? new Node('DIV') : builder]};
	const acf = {
		getField: () => field,
		addAction: (name, callback) => { if (!actions.has(name)) actions.set(name, []); actions.get(name).push(callback); },
		enableSubmit(control) {
			assert.equal(control[0], publish, 'Only the original native submitter may use the public handoff.');
			enabledSubmitters.push(control[0]);
			nativeState.get(control[0]).disabled = false;
			control[0].attributes.delete('disabled');
			return control;
		},
	};
	const window = {
		SkyeContentSourcesConfig: options.config || {fieldKey: 'field_fixture', source: 'builder', postId: 123},
		acf: options.noAcf ? undefined : acf,
		tinymce: {get: id => editors.get(id) || null},
		FormData,
		FormDataEvent: class {},
		CustomEvent: class { constructor(type, init) { this.type = type; Object.assign(this, init); } },
		setTimeout(callback, delay) { assert.equal(delay, 0); timers.set(++timerId, callback); return timerId; },
		clearTimeout(id) { timers.delete(id); },
	};
	const jq = argument => {
		if (typeof argument === 'function') { argument(); return; }
		return {0: argument, on: (name, callback) => events.set(name, callback)};
	};
	if (options.noFormData) delete window.FormData;
	if (options.foreignGlobal) window.SkyeContentSources = options.foreignGlobal;
	/**
	 * Add one already existing visual editor through its native public API double.
	 * @since 3.5.0
	 * @param {Node} textarea Original native textarea.
	 * @param {string} html Current unsaved visual content.
	 * @returns {object} Observable native instance and its normal saved textarea.
	 */
	function addEditor(textarea, html) {
		const listeners = new Map();
		const instance = {
			html, hidden: false, saves: 0, dirty: true,
			getElement: () => textarea,
			isHidden() { return this.hidden; },
			save(options = {}) { this.saves++; textarea.value = this.html; if (options.set_dirty !== false) this.dirty = false; },
			isDirty() { return this.dirty; },
			setDirty(value) { this.dirty = value; },
			on(names, callback) { for (const name of names.split(' ')) listeners.set(name, callback); },
			emit(name) { listeners.get(name)?.(); },
		};
		editors.set(textarea.id, instance);
		return instance;
	}
	const coreEditor = addEditor(core, '<p>Unsaved native.</p>');
	const richEditor = addEditor(rich, '<p>Unsaved builder.</p>');
	const cloneEditor = addEditor(clone, '<p>Must not enter the source.</p>');
	if (options.badEditor) coreEditor.getElement = () => rich;
	if (options.unreadyEditor) coreEditor.save = () => { throw new Error('Native editor not initialized.'); };
	vm.runInNewContext(source, {window, document, jQuery: jq, WeakSet, Set, Error});
	return {
		form, chooser, builder, editor, core, rich, clone, radios, document, actions, editors, coreEditor, richEditor, cloneEditor, field, window, addEditor, notifications, group, groupFields, publish, submissions, timers, enabledSubmitters,
		choose(value) { for (const radio of radios) radio.checked = radio.value === value; chooser.dispatchEvent({type: 'change', target: radios.find(radio => radio.value === value)}); },
		data(entries) { const data = new FormData(); for (const [name, value] of entries) data.append(name, value); form.dispatchEvent({type: 'formdata', formData: data}); return data; },
		action(name, ...args) { for (const callback of actions.get(name) || []) callback(...args); },
		click(submitter = publish, trusted = true) {
			const event = {type: 'click', target: submitter, isTrusted: trusted, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }};
			form.dispatchEvent(event);
			return event;
		},
		invalid(control = rich) {
			const event = {type: 'invalid', target: control, prevented: false, stopped: false, preventDefault() { this.prevented = true; }, stopImmediatePropagation() { this.stopped = true; }};
			form.dispatchEvent(event);
			return event;
		},
		drain() { const pending = [...timers]; timers.clear(); for (const [, callback] of pending) callback(); },
		lockSubmitter() { nativeState.get(publish).disabled = true; publish.attributes.set('disabled', 'disabled'); },
		editorReady() { events.get('tinymce-editor-init.skyeContentSources')?.(); },
	};
}

test('initialization hides through owned metadata only and preserves both native source nodes', () => {
	const f = fixture();
	assert.equal(f.form.getAttribute('data-skye-content-sources-ready'), 'true');
	assert.equal(f.form.getAttribute('data-skye-content-source'), 'builder');
	assert.equal(f.builder.getAttribute('data-skye-content-source-panel'), 'builder');
	assert.equal(f.editor.getAttribute('data-skye-content-source-panel'), 'editor');
	assert.equal(f.core.value, '<p>Unsaved native.</p>');
	assert.equal(f.rich.value, '<p>Unsaved builder.</p>');
	assert.equal(f.core.disabled, false);
	assert.equal(f.rich.disabled, false);
	assert.equal(f.editor.contains(f.core), true);
	assert.equal(f.builder.contains(f.rich), true);
	assert.equal(f.cloneEditor.saves, 0);
	assert.equal(f.notifications[0].event.type, 'skye:content-source:change');
	assert.equal(f.notifications[0].event.detail.postId, 123);
	assert.equal(f.notifications[0].event.detail.source, 'builder');
	assert.equal(f.notifications[0].ready, 'true', 'Subscribers observe initial readiness before the source event.');
	assert.equal(f.window.SkyeContentSources.getSource(), 'builder');
});

test('switching and submitting flush both visual sources and retain inactive unsaved values', () => {
	const f = fixture();
	f.coreEditor.html = '<p>New native value.</p>';
	f.richEditor.html = '<p>New builder value.</p>';
	f.choose('editor');
	assert.equal(f.form.getAttribute('data-skye-content-source'), 'editor');
	assert.equal(f.core.value, '<p>New native value.</p>');
	assert.equal(f.rich.value, '<p>New builder value.</p>');
	f.richEditor.html = '<p>Latest hidden builder value.</p>';
	f.form.dispatchEvent({type: 'submit'});
	assert.equal(f.rich.value, '<p>Latest hidden builder value.</p>');
	f.choose('builder');
	assert.equal(f.form.getAttribute('data-skye-content-source'), 'builder');
	assert.equal(f.core.value, '<p>New native value.</p>');
});

test('serialization flushes current values without per-keystroke saves or native dirty-state loss', () => {
	const f = fixture();
	const saves = f.richEditor.saves;
	f.richEditor.html = '<p>Unsaved visual input.</p>';
	f.richEditor.emit('input');
	assert.equal(f.richEditor.saves, saves, 'Typing does not trigger expensive serialization.');
	const data = f.data([['content', 'Previous native.'], [f.rich.name, 'Previous builder.'], ['_wpnonce', 'native-nonce']]);
	assert.equal(data.get(f.rich.name), '<p>Unsaved visual input.</p>');
	assert.equal(data.get('content'), '<p>Unsaved native.</p>');
	assert.equal(data.get('_wpnonce'), 'native-nonce');
	assert.equal(f.coreEditor.dirty, true);
	assert.equal(f.richEditor.dirty, true);
	f.coreEditor.hidden = true;
	f.core.value = '<p>Current code-mode text.</p>';
	f.coreEditor.html = '<p>Stale visual model.</p>';
	f.choose('editor');
	assert.equal(f.core.value, '<p>Current code-mode text.</p>');
	assert.equal(f.cloneEditor.saves, 0);
});

test('serialization never creates absent controls or collapses repeated native names', () => {
	const f = fixture();
	f.richEditor.html = '<p>Inactive retained draft.</p>';
	const absent = f.data([['_wpnonce', 'original']]);
	assert.deepEqual([...absent.keys()], ['_wpnonce']);
	const repeated = f.data([[f.rich.name, 'first-native-entry'], [f.rich.name, 'second-native-entry']]);
	assert.deepEqual(repeated.getAll(f.rich.name), ['first-native-entry', 'second-native-entry']);
	assert.equal(repeated.has(f.clone.name), false);
	assert.equal(f.core.disabled, false);
	assert.equal(f.rich.disabled, false);
	f.rich.fieldsetDisabled = true;
	const disabledFieldset = f.data([[f.rich.name, 'A same-name native entry outside the disabled fieldset.']]);
	assert.equal(disabledFieldset.get(f.rich.name), 'A same-name native entry outside the disabled fieldset.');
});

test('dirty-state preservation also covers a native save implementation that clears it', () => {
	const f = fixture();
	f.coreEditor.save = function () { this.saves++; f.core.value = this.html; this.dirty = false; };
	f.choose('editor');
	assert.equal(f.coreEditor.dirty, true);
	f.coreEditor.dirty = false;
	f.choose('builder');
	assert.equal(f.coreEditor.dirty, false, 'A clean editor is not fabricated as dirty.');
});

test('late native WYSIWYG instances and appended rows are observed without reinitialization', () => {
	const f = fixture();
	const textarea = new Node('TEXTAREA', {id: 'acf-new', name: 'acf[field_fixture][row-1][field_body]', value: 'New row.', form: f.form});
	textarea.parent = f.builder;
	f.builder.children.push(textarea);
	f.builder.selectors.get('textarea[id]').push(textarea);
	const late = f.addEditor(textarea, '<p>Late native instance.</p>');
	f.action('append');
	late.html = '<p>Edited late instance.</p>';
	f.choose('editor');
	assert.equal(textarea.value, '<p>Edited late instance.</p>');
	assert.equal(f.form.getAttribute('data-skye-content-sources-ready'), 'true');
});

test('focus leaves a pane before it becomes inactive and other focus stays put', () => {
	const f = fixture();
	f.document.activeElement = f.rich;
	f.choose('editor');
	assert.equal(f.document.activeElement, f.radios[1]);
	const outside = new Node('INPUT');
	f.document.activeElement = outside;
	f.choose('builder');
	assert.equal(f.document.activeElement, outside);
});

test('missing or unknown contracts leave both native sources without owned hiding markers', () => {
	for (const options of [{noAcf: true}, {noFormData: true}, {noRequestSubmit: true}, {noInert: true}, {postId: '124'}, {duplicate: true}, {fieldType: 'text'}, {wrongModel: true}, {noEditor: true}, {badEditor: true}, {config: {fieldKey: 'invalid selector', source: 'builder', postId: 123}}]) {
		const f = fixture(options);
		assert.equal(f.form.getAttribute('data-skye-content-sources-ready'), null);
		assert.equal(f.builder.getAttribute('data-skye-content-source-panel'), null);
		assert.equal(f.editor.getAttribute('data-skye-content-source-panel'), null);
		assert.equal(f.core.disabled, false);
		assert.equal(f.rich.disabled, false);
	}
	const foreign = {ownedElsewhere: true};
	const f = fixture({foreignGlobal: foreign});
	assert.equal(f.window.SkyeContentSources, foreign);
	assert.equal(f.form.getAttribute('data-skye-content-sources-ready'), null);
});

test('provider readiness can recover before initialization and later failures revoke only presentation', () => {
	const early = fixture({unreadyEditor: true});
	assert.equal(early.form.getAttribute('data-skye-content-sources-ready'), null);
	early.coreEditor.save = function () { this.saves++; early.core.value = this.html; };
	early.editorReady();
	assert.equal(early.form.getAttribute('data-skye-content-sources-ready'), 'true');
	const active = fixture();
	active.field.$el[0] = new Node('DIV');
	active.choose('editor');
	assert.equal(active.form.getAttribute('data-skye-content-sources-ready'), null);
	assert.equal(active.builder.getAttribute('data-skye-content-source-panel'), null);
	assert.equal(active.core.disabled, false);
	assert.equal(active.rich.disabled, false);
	assert.equal(active.notifications.at(-1).event.detail.source, null);
	assert.equal(active.notifications.at(-1).ready, null);
	assert.equal(active.window.SkyeContentSources.getSource(), null);
});

test('native append and remove lifecycles revoke a replaced or removed whole builder', async () => {
	const appended = fixture();
	appended.form.selectors.set('.acf-field[data-key="field_fixture"]', []);
	appended.action('append');
	assert.equal(appended.form.getAttribute('data-skye-content-sources-ready'), null);
	const removed = fixture();
	removed.action('remove');
	removed.form.selectors.set('.acf-field[data-key="field_fixture"]', []);
	await Promise.resolve();
	assert.equal(removed.form.getAttribute('data-skye-content-sources-ready'), null);
	assert.equal(removed.window.SkyeContentSources.getSource(), null);
});

test('an exclusive native ACF group receives the optional group marker and fallback removes it', () => {
	const f = fixture({group: true});
	assert.equal(f.group.getAttribute('data-skye-content-source-group'), 'builder');
	f.choose('editor');
	assert.equal(f.form.getAttribute('data-skye-content-source'), 'editor');
	assert.equal(f.builder.parentElement, f.groupFields, 'Native builder and editor parents do not move.');
	f.field.$el[0] = new Node('DIV');
	f.choose('builder');
	assert.equal(f.group.getAttribute('data-skye-content-source-group'), null);
});

test('a shared or newly shared native ACF group remains visible while only its builder is marked', () => {
	const shared = fixture({group: true, sharedGroup: true});
	shared.choose('editor');
	assert.equal(shared.group.getAttribute('data-skye-content-source-group'), null);
	assert.equal(shared.builder.getAttribute('data-skye-content-source-panel'), 'builder');
	const changed = fixture({group: true});
	changed.choose('editor');
	const other = new Node('DIV', {classes: ['acf-field']});
	other.parent = changed.groupFields;
	changed.groupFields.children.push(other);
	changed.group.selectors.get('.acf-field').push(other);
	changed.action('append');
	assert.equal(changed.group.getAttribute('data-skye-content-source-group'), null);
	assert.equal(changed.form.getAttribute('data-skye-content-sources-ready'), 'true');
	assert.equal(changed.builder.getAttribute('data-skye-content-source-panel'), 'builder');
});

test('an inactive own invalid cycle retries once with the exact native submitter and restores its attribute', () => {
	const f = fixture({selected: 'editor'});
	f.rich.validity.valid = false;
	const before = f.data([['content', 'old'], [f.rich.name, 'old'], ['_wpnonce', 'wp'], ['_acf_nonce', 'acf'], ['skye_content_source_nonce', 'source']]);
	f.click();
	const first = f.invalid();
	const second = f.invalid();
	assert.equal(first.prevented, true);
	assert.equal(first.stopped, true, 'The new inactive HTML5 error does not reach ACF’s direct listener.');
	assert.equal(second.stopped, true);
	assert.equal(f.timers.size, 2, 'One click-expiry task and one deduplicated invalid-cycle retry are pending.');
	assert.equal(f.submissions.length, 0, 'The original invalid-event stack is never reentered.');
	f.coreEditor.html = '<p>Newest native.</p>';
	f.richEditor.html = '<p>Newest inactive draft.</p>';
	f.drain();
	assert.equal(f.submissions.length, 1);
	assert.equal(f.submissions[0].submitter, f.publish);
	assert.equal(f.submissions[0].noValidate, true);
	assert.equal(f.publish.hasAttribute('formnovalidate'), false);
	assert.equal(f.publish.formNoValidate, false);
	assert.equal(f.rich.validity.valid, false, 'No field constraint or validity is repaired.');
	assert.equal(f.core.value, '<p>Newest native.</p>');
	assert.equal(f.rich.value, '<p>Newest inactive draft.</p>');
	assert.equal(f.coreEditor.dirty, true);
	assert.equal(f.richEditor.dirty, true);
	const after = f.data([...before]);
	assert.deepEqual([...after.keys()], [...before.keys()], 'No successful control is inserted or removed.');
	for (const name of ['_wpnonce', '_acf_nonce', 'skye_content_source_nonce']) assert.equal(after.get(name), before.get(name));
	assert.equal(f.invalid().prevented, false, 'A finished intent cannot replay a later unrelated invalid event.');
});

test('active builder, missing trusted submitter and foreign or clone invalid controls remain native', () => {
	const builder = fixture();
	builder.rich.validity.valid = false;
	builder.click();
	assert.equal(builder.invalid().stopped, false);
	assert.equal(builder.timers.size, 0);
	const f = fixture({selected: 'editor'});
	f.rich.validity.valid = false;
	assert.equal(f.invalid().stopped, false);
	f.click(f.publish, false);
	assert.equal(f.invalid().stopped, false, 'A script-generated click cannot create an otherwise missing user intent.');
	f.click();
	f.core.validity.valid = false;
	assert.equal(f.invalid(f.core).stopped, false);
	f.clone.willValidate = true;
	f.clone.validity = {valid: false};
	assert.equal(f.invalid(f.clone).stopped, false);
	assert.equal(f.timers.size, 1, 'Only the real click intent expiry remains; no invalid retry was added.');
	f.drain();
	assert.equal(f.submissions.length, 0);
});

test('an interrupted trusted click expires before later standalone native constraint checks', () => {
	for (const outsideInvalid of [false, true]) {
		const f = fixture({selected: 'editor'});
		f.rich.validity.valid = false;
		let outside;
		if (outsideInvalid) {
			outside = new Node('INPUT', {form: f.form, name: 'other-native', willValidate: true, validity: {valid: false}});
			f.form.elements.push(outside);
		}
		f.click();
		if (outside) assert.equal(f.invalid(outside).prevented, false, 'An outside HTML5 error can interrupt native submission.');
		assert.equal(f.timers.size, 1, 'A cancelled click without an own invalid cycle only has an expiry.');
		f.drain();
		if (outside) outside.validity.valid = true;
		const laterCheck = f.invalid();
		assert.equal(laterCheck.prevented, false, 'A later checkValidity/reportValidity cycle has no current user submission intent.');
		assert.equal(laterCheck.stopped, false);
		assert.equal(f.timers.size, 0);
		assert.equal(f.submissions.length, 0);
	}
});

test('final cancellation of the same trusted click cannot be overridden by a same-task invalid cycle', () => {
	for (const cancelBeforeInvalid of [false, true]) {
		const f = fixture({selected: 'editor'});
		f.rich.validity.valid = false;
		const click = f.click();
		if (cancelBeforeInvalid) click.preventDefault();
		const invalid = f.invalid();
		assert.equal(invalid.prevented, !cancelBeforeInvalid);
		if (!cancelBeforeInvalid) click.preventDefault();
		f.drain();
		assert.equal(f.submissions.length, 0, 'A later click handler retains its native cancellation.');
		assert.equal(f.enabledSubmitters.length, 0);
		assert.equal(f.timers.size, 0);
		assert.equal(f.invalid().prevented, false, 'The cancelled click leaves no stale intent.');
	}
});

test('known outside form-associated invalid controls prevent a retry without touching their native errors', () => {
	for (const make of [form => new Node('INPUT', {form, name: 'outside', willValidate: true, validity: {valid: false}})]) {
		const f = fixture({selected: 'editor'});
		const outside = make(f.form);
		f.form.elements.push(outside);
		assert.equal(f.form.contains(outside), false, 'form= controls are checked through the native elements collection.');
		f.rich.validity.valid = false;
		f.click();
		assert.equal(f.invalid().stopped, true);
		f.drain();
		assert.equal(f.submissions.length, 0);
		assert.equal(f.invalid().prevented, false, 'A declined retry discards only its own old intent.');
		assert.equal(f.core.disabled, false);
		assert.equal(f.rich.disabled, false);
	}
	const disabled = fixture({selected: 'editor'});
	disabled.form.elements.push(new Node('INPUT', {form: disabled.form, fieldsetDisabled: true, willValidate: true, validity: {valid: false}}));
	disabled.rich.validity.valid = false;
	disabled.click();
	disabled.invalid();
	disabled.drain();
	assert.equal(disabled.submissions.length, 1, 'Disabled controls remain outside native constraint validation.');
});

test('unknown control contracts revoke hiding before cancelling any native invalid event', () => {
	for (const make of [
		form => new Node('CUSTOM-CONTROL', {form}),
		form => new Node('CUSTOM-CONTROL', {form, willValidate: false, validity: {valid: true}}),
		form => new Node('INPUT', {form, name: 'unknown-validity', willValidate: true}),
		form => new Node('INPUT', {form, name: 'unknown-candidate', validity: {valid: true}}),
		() => new Node('INPUT', {form: new Node('FORM'), willValidate: true, validity: {valid: true}}),
	]) {
		const f = fixture({selected: 'editor'});
		f.rich.validity.valid = false;
		f.click();
		f.form.elements.push(make(f.form));
		const invalid = f.invalid();
		assert.equal(invalid.prevented, false);
		assert.equal(invalid.stopped, false);
		assert.equal(f.form.getAttribute('data-skye-content-sources-ready'), null);
		assert.equal(f.builder.getAttribute('data-skye-content-source-panel'), null);
		assert.equal(f.editor.getAttribute('data-skye-content-source-panel'), null);
		assert.equal(f.window.SkyeContentSources.getSource(), null);
		assert.equal(f.timers.size, 0);
		f.drain();
		assert.equal(f.submissions.length, 0);
	}
});

test('queued retries recheck source, provider, exact builder and inherited submitter state', () => {
	for (const mutate of [
		f => f.choose('builder'),
		f => { f.field.$el[0] = new Node('DIV'); },
		f => { f.window.acf = null; },
		f => { f.publish.fieldsetDisabled = true; },
		f => { f.form.children = f.form.children.filter(node => node !== f.publish); },
	]) {
		const f = fixture({selected: 'editor'});
		f.rich.validity.valid = false;
		f.click();
		f.invalid();
		mutate(f);
		f.drain();
		assert.equal(f.submissions.length, 0);
		assert.equal(f.publish.formNoValidate, false);
	}
});

test('submitter raw formnovalidate spelling is restored even when requestSubmit fails', () => {
	for (const raw of [null, '', 'formnovalidate']) {
		const f = fixture({selected: 'editor', requestSubmit() { throw new Error('Native submission unavailable.'); }});
		if (raw !== null) f.publish.attributes.set('formnovalidate', raw);
		f.rich.validity.valid = false;
		f.click();
		f.invalid();
		f.drain();
		assert.equal(f.publish.hasAttribute('formnovalidate'), raw !== null);
		assert.equal(f.publish.getAttribute('formnovalidate'), raw);
		assert.equal(f.publish.formNoValidate, raw !== null);
		assert.equal(f.window.SkyeContentSources.getSource(), null);
		assert.equal(f.rich.disabled, false);
	}
});

test('native validation locks only the own chooser and restores the original inert state', () => {
	for (const prior of [false, true]) {
		for (const completion of ['validation_failure', 'validation_success']) {
			const f = fixture({selected: 'editor', inert: prior});
			const entries = [['content', 'old'], [f.rich.name, 'old'], ['_wpnonce', 'native'], ['skye_content_source', 'editor']];
			f.action('validation_begin', [f.form]);
			assert.equal(f.chooser.inert, true);
			assert.equal(f.core.disabled, false);
			assert.equal(f.rich.disabled, false);
			assert.deepEqual([...f.data(entries).keys()], entries.map(([name]) => name));
			f.action('validation_begin', [f.form]);
			f.action(completion, [new Node('FORM')]);
			assert.equal(f.chooser.inert, true, 'Another form cannot release this chooser.');
			f.action(completion, [f.form]);
			assert.equal(f.chooser.inert, prior);
		}
	}
});

test('changed source or provider during validation restores inert and exposes both sources', () => {
	for (const mutate of [
		f => f.choose('builder'),
		f => { f.field.$el[0] = new Node('DIV'); f.action('append'); },
	]) {
		const f = fixture({selected: 'editor'});
		f.action('validation_begin', [f.form]);
		assert.equal(f.chooser.inert, true);
		mutate(f);
		assert.equal(f.chooser.inert, false);
		assert.equal(f.form.getAttribute('data-skye-content-sources-ready'), null);
		assert.equal(f.builder.getAttribute('data-skye-content-source-panel'), null);
		assert.equal(f.editor.getAttribute('data-skye-content-source-panel'), null);
		assert.equal(f.window.SkyeContentSources.getSource(), null);
	}
});

test('ACF asynchronous success and its final lock retain the same native publish submitter across replay', async () => {
	const f = fixture({selected: 'editor'});
	f.rich.validity.valid = false;
	f.form.requestSubmit = submitter => {
		f.submissions.push({submitter, noValidate: submitter.formNoValidate});
		f.form.dispatchEvent({type: 'submit', submitter});
		if (f.submissions.length === 1) f.action('validation_begin', [f.form]);
	};
	f.click();
	f.invalid();
	f.drain();
	assert.equal(f.chooser.inert, true);
	assert.equal(f.submissions.length, 1);
	f.action('validation_success', [f.form]);
	assert.equal(f.chooser.inert, false);
	f.click(f.publish, false);
	assert.equal(f.invalid().prevented, true, 'ACF’s replay uses its original user intent without fabricating a new one.');
	f.lockSubmitter();
	assert.equal(f.publish.disabled, true, 'Original ACF complete relocks all submit buttons after the replay.');
	await Promise.resolve();
	f.drain();
	assert.equal(f.submissions.length, 2);
	for (const submission of f.submissions) {
		assert.equal(submission.submitter, f.publish);
		assert.equal(submission.noValidate, true);
	}
	assert.equal(f.publish.name, 'publish');
	assert.equal(f.publish.value, 'Publish');
	assert.equal(f.publish.formNoValidate, false);
	assert.deepEqual(f.enabledSubmitters, [f.publish], 'Only the bound success receipt permits the original ACF public handoff.');
	assert.equal(f.rich.validity.valid, false);
	assert.equal(f.timers.size, 0);
	assert.equal(f.invalid().prevented, false);
});

test('no success receipt, a replaced API or inherited-disabled original submitter cannot authorize a handoff', async () => {
	for (const scenario of ['no-receipt', 'foreign-success', 'disabled-before-receipt', 'disabled-fieldset-after-receipt', 'replaced-api', 'outside-invalid-after-receipt', 'different-button']) {
		const f = fixture({selected: 'editor'});
		f.rich.validity.valid = false;
		f.form.requestSubmit = submitter => {
			f.submissions.push({submitter});
			f.action('validation_begin', [f.form]);
		};
		f.click();
		f.invalid();
		if (scenario === 'no-receipt') {
			f.lockSubmitter();
			f.drain();
			assert.equal(f.submissions.length, 0);
		} else {
			f.drain();
			if (scenario === 'disabled-before-receipt') f.lockSubmitter();
			f.action('validation_success', [scenario === 'foreign-success' ? new Node('FORM') : f.form]);
			f.click(f.publish, false);
			const invalid = f.invalid();
			if (scenario === 'foreign-success' || scenario === 'disabled-before-receipt') assert.equal(invalid.prevented, false);
			f.lockSubmitter();
			if (scenario === 'disabled-fieldset-after-receipt') {
				const fieldset = new Node('FIELDSET', {form: f.form, disabled: true, willValidate: false});
				fieldset.children = [f.publish];
				fieldset.parent = f.form;
				f.publish.parent = fieldset;
				f.publish.fieldsetDisabled = true;
				f.form.children = f.form.children.map(node => node === f.publish ? fieldset : node);
			} else if (scenario === 'replaced-api') {
				f.window.acf.enableSubmit = () => { throw new Error('A replaced provider API must never be called.'); };
			} else if (scenario === 'outside-invalid-after-receipt') {
				f.core.validity.valid = false;
			} else if (scenario === 'different-button') {
				const other = new Node('BUTTON', {form: f.form, type: 'submit', name: 'other', willValidate: true, validity: {valid: true}});
				f.form.children.push(other);
				f.form.elements.push(other);
				f.click(other, false);
			}
			await Promise.resolve();
			f.drain();
			assert.equal(f.submissions.length, 1, scenario);
		}
		assert.equal(f.enabledSubmitters.length, 0, scenario);
		assert.equal(f.publish.disabled, true, scenario);
	}
});
