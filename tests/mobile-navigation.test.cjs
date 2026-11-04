/** Exercise the actual navigation controller state against a bounded DOM fixture, without a browser claim. */
'use strict';
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const test = require('node:test');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/scripts/mobile-navigation.js'), 'utf8');

function fixture() {
	let document;
	const queued = [];
	const timers = new Map();
	let sequence = 0;
	class Event {
		constructor(type, options = {}) { Object.assign(this, {type, defaultPrevented: false, button: 0}, options); }
		preventDefault() { this.defaultPrevented = true; }
	}
	class Element {
		constructor(tag, attributes = {}) {
			this.tagName = tag.toUpperCase(); this.attributes = {...attributes}; this.children = []; this.parentNode = null; this.listeners = new Map();
			this.dataset = new Proxy({}, {get: (_, key) => this.getAttribute('data-' + key.replace(/[A-Z]/g, letter => '-' + letter.toLowerCase())), set: (_, key, value) => { this.setAttribute('data-' + key.replace(/[A-Z]/g, letter => '-' + letter.toLowerCase()), value); return true; }});
		}
		get id() { return this.getAttribute('id') || ''; }
		get hidden() { return this.hasAttribute('hidden'); }
		set hidden(value) { value ? this.setAttribute('hidden', '') : this.removeAttribute('hidden'); }
		get href() { return this.getAttribute('href'); }
		get target() { return this.getAttribute('target') || ''; }
		get tabIndex() { return this.hasAttribute('tabindex') ? Number(this.getAttribute('tabindex')) : ['A', 'BUTTON', 'INPUT'].includes(this.tagName) ? 0 : -1; }
		get isConnected() { let node = this; while (node.parentNode) node = node.parentNode; return node === document.body; }
		getAttribute(name) { return Object.hasOwn(this.attributes, name) ? this.attributes[name] : null; }
		setAttribute(name, value) { this.attributes[name] = String(value); }
		removeAttribute(name) { delete this.attributes[name]; }
		hasAttribute(name) { return Object.hasOwn(this.attributes, name); }
		remove() { if (this.parentNode) { this.parentNode.children.splice(this.parentNode.children.indexOf(this), 1); this.parentNode = null; } }
		append(node) { node.remove(); node.parentNode = this; this.children.push(node); }
		before(node) { const parent = this.parentNode; node.remove(); parent.children.splice(parent.children.indexOf(this), 0, node); node.parentNode = parent; }
		replaceWith(node) { const parent = this.parentNode; node.remove(); parent.children[parent.children.indexOf(this)] = node; node.parentNode = parent; this.parentNode = null; }
		contains(node) { return this === node || this.children.some(child => child.contains(node)); }
		matches(selector) {
			if (selector === 'header') return this.tagName === 'HEADER';
			if (selector === 'a[href]') return this.tagName === 'A' && this.hasAttribute('href');
			if (selector.startsWith('.')) return (this.getAttribute('class') || '').split(/\s+/).includes(selector.slice(1));
			const attribute = /^\[([^=\]]+)(?:="([^"]*)")?\]$/.exec(selector);
			return attribute ? this.hasAttribute(attribute[1]) && (attribute[2] === undefined || this.getAttribute(attribute[1]) === attribute[2]) : false;
		}
		closest(selector) { for (let node = this; node; node = node.parentNode) if (node.matches(selector)) return node; return null; }
		querySelectorAll(selector) {
			const descendants = []; const visit = node => { for (const child of node.children) { descendants.push(child); visit(child); } }; visit(this);
			if (selector.includes('button:not')) return descendants.filter(node => ['A', 'BUTTON', 'INPUT', 'SELECT', 'TEXTAREA'].includes(node.tagName) || node.hasAttribute('tabindex'));
			if (/^\.[a-z-]+ a\[href\]$/.test(selector)) return descendants.filter(node => node.matches('a[href]') && node.closest(selector.split(' ')[0]));
			return descendants.filter(node => node.matches(selector));
		}
		querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
		getClientRects() { for (let node = this; node; node = node.parentNode) if (node.hidden || (node.tagName === 'DIALOG' && !node.open)) return []; return [{}]; }
		addEventListener(type, callback, options = {}) { const list = this.listeners.get(type) || []; list.push({callback, options}); this.listeners.set(type, list); }
		dispatchEvent(event) {
			if (!event.target) event.target = this;
			for (const item of [...(this.listeners.get(event.type) || [])]) {
				if (item.options.once) this.listeners.set(event.type, this.listeners.get(event.type).filter(other => other !== item));
				item.callback(event);
			}
			if (event.bubbles && this.parentNode) this.parentNode.dispatchEvent(event);
			return !event.defaultPrevented;
		}
		focus() { const previous = document.activeElement; document.activeElement = this; if (previous && previous !== this) previous.dispatchEvent(new Event('blur')); }
		scrollIntoView(options) { this.scrolled = options; }
	}
	class Dialog extends Element {
		constructor(attributes) { super('dialog', attributes); this.open = false; this.animations = []; }
		showModal() { this.open = true; }
		close() { this.open = false; queued.push(() => this.dispatchEvent(new Event('close'))); }
		getAnimations() { return this.animations; }
	}
	const body = new Element('body'); const header = new Element('header'); body.append(header);
	const brand = new Element('a', {'data-skye-navigation-brand-for': 'fixture-navigation', href: '/'}); header.append(brand);
	const root = new Element('div', {'data-skye-navigation': '', 'data-skye-navigation-breakpoint': '1200'}); header.append(root);
	const desktopNav = new Element('nav', {class: 'skye-navigation-desktop'}); root.append(desktopNav);
	const desktopLink = new Element('a', {href: 'https://fixture.test/#destination'}); desktopNav.append(desktopLink);
	const fallback = new Element('details', {'data-skye-navigation-fallback': ''}); root.append(fallback);
	const opener = new Element('button', {'data-skye-navigation-open': '', 'aria-controls': 'fixture-navigation', 'aria-expanded': 'false', hidden: ''}); root.append(opener);
	const dialog = new Dialog({id: 'fixture-navigation', 'data-skye-navigation-dialog': '', tabindex: '-1'}); root.append(dialog);
	const slot = new Element('div', {'data-skye-navigation-brand-slot': ''}); dialog.append(slot);
	const close = new Element('button', {'data-skye-navigation-close': ''}); dialog.append(close);
	const links = new Element('nav', {class: 'skye-navigation-dialog-links'}); dialog.append(links);
	const first = new Element('a', {href: 'https://fixture.test/#destination'}); links.append(first);
	const last = new Element('a', {href: 'https://fixture.test/other/'}); links.append(last);
	const destination = new Element('h2', {id: 'destination'}); body.append(destination);
	document = {body, activeElement: opener, querySelectorAll: () => [], getElementById: id => id === 'destination' ? destination : null, createComment: () => new Element('comment')};
	const media = initial => ({matches: initial, listeners: [], addEventListener(type, callback) { this.listeners.push(callback); }, change(value) { this.matches = value; this.listeners.forEach(callback => callback()); }});
	const desktop = media(false); const reduced = media(true);
	const location = {href: 'https://fixture.test/', origin: 'https://fixture.test', pathname: '/', search: '', hash: ''};
	const context = vm.createContext({document, window: {location, matchMedia: query => query.includes('min-width') ? desktop : reduced}, HTMLDialogElement: Dialog, CustomEvent: Event, URL, Promise, getComputedStyle: () => ({visibility: 'visible'}), setTimeout: callback => { timers.set(++sequence, callback); return sequence; }, clearTimeout: id => timers.delete(id)});
	vm.runInContext(source, context);
	return {context, document, header, root, fallback, opener, dialog, close, first, last, links, brand, slot, destination, desktop, desktopLink, reduced, location, timers, initialize: () => context.initializeNavigation(root), event: (node, type, options) => { const event = new Event(type, options); node.dispatchEvent(event); return event; }, flush: () => { while (queued.length) queued.shift()(); }};
}

test('real controller keeps native fallback, brand identity, modal focus and fragment semantics', () => {
	const f = fixture(); const lifecycle = [];
	f.root.addEventListener('skye:navigation:opened', event => lifecycle.push(event.detail));
	f.root.addEventListener('skye:navigation:closed', event => lifecycle.push(event.detail));
	const controller = f.initialize();
	assert.ok(controller); assert.equal(f.fallback.hidden, true); assert.equal(f.opener.hidden, false); assert.equal(f.initialize(), null);
	f.dialog.scrollTop = 200; f.links.scrollTop = 400; f.event(f.opener, 'click');
	assert.equal(f.dialog.scrollTop, 0); assert.equal(f.links.scrollTop, 0);
	assert.equal(f.dialog.open, true); assert.equal(f.opener.getAttribute('aria-expanded'), 'true'); assert.equal(f.document.activeElement, f.first);
	assert.equal(f.slot.children[0], f.brand); assert.equal(lifecycle[0].id, 'fixture-navigation');
	f.last.focus(); const forward = f.event(f.dialog, 'keydown', {key: 'Tab'});
	assert.equal(forward.defaultPrevented, true); assert.equal(f.document.activeElement, f.brand);
	const backward = f.event(f.dialog, 'keydown', {key: 'Tab', shiftKey: true});
	assert.equal(backward.defaultPrevented, true); assert.equal(f.document.activeElement, f.last);
	f.event(f.dialog, 'click', {target: f.first, metaKey: true}); assert.equal(f.dialog.open, true);
	f.event(f.dialog, 'cancel'); f.flush();
	assert.equal(f.dialog.open, false); assert.equal(f.document.activeElement, f.opener); assert.equal(f.header.children[0], f.brand); assert.equal(f.opener.getAttribute('aria-expanded'), 'false');
	assert.equal(lifecycle[1].reason, 'escape');
	controller.open(); const fragment = f.event(f.dialog, 'click', {target: f.first}); f.flush();
	assert.equal(fragment.defaultPrevented, true); assert.equal(f.dialog.open, false); assert.equal(f.document.activeElement, f.destination); assert.equal(f.location.hash, '#destination'); assert.equal(f.destination.getAttribute('tabindex'), '-1');
	assert.equal(f.destination.scrolled.block, 'start'); assert.equal(f.destination.scrolled.behavior, 'instant'); f.opener.focus(); assert.equal(f.destination.hasAttribute('tabindex'), false);
	f.desktop.change(true); controller.open(); assert.equal(f.dialog.open, false);
});

test('interrupted animation cannot close a reopened modal; breakpoint cleanup is immediate', async () => {
	const f = fixture(); f.reduced.matches = false;
	const controller = f.initialize(); controller.open();
	let resolveOld; f.dialog.animations = [{finished: new Promise(resolve => { resolveOld = resolve; })}];
	controller.close('close'); assert.equal(f.dialog.open, true);
	for (const callback of [...f.timers.values()]) callback();
	assert.equal(f.dialog.open, false); controller.open(); assert.equal(f.dialog.open, true);
	resolveOld(); await Promise.resolve(); await Promise.resolve(); f.flush();
	assert.equal(f.dialog.open, true, 'old animation and queued native close events do not affect the new lifecycle');
	f.dialog.animations = [{finished: new Promise(() => {})}]; controller.close('close');
	f.desktop.change(true); assert.equal(f.dialog.open, false); assert.equal(f.header.children[0], f.brand); assert.equal(f.opener.getAttribute('aria-expanded'), 'false'); assert.equal(f.document.activeElement, f.desktopLink);
});

test('unsupported modal APIs and malformed control identity leave native details unmodified', () => {
	const unsupported = fixture(); unsupported.context.HTMLDialogElement = undefined;
	assert.equal(unsupported.initialize(), null); assert.equal(unsupported.fallback.hidden, false); assert.equal(unsupported.opener.hidden, true);
	const mismatch = fixture(); mismatch.opener.setAttribute('aria-controls', 'another-dialog');
	assert.equal(mismatch.initialize(), null); assert.equal(mismatch.fallback.hidden, false); assert.equal(mismatch.opener.hidden, true);
});

test('late enhancement preserves a menu the user already explicitly expanded', () => {
	const f = fixture(); f.fallback.open = true;
	const controller = f.initialize();
	assert.ok(controller); assert.equal(f.dialog.open, true); assert.equal(f.opener.getAttribute('aria-expanded'), 'true'); assert.equal(f.document.activeElement, f.first);
	controller.close(); f.flush(); assert.equal(f.header.children[0], f.brand);
});
