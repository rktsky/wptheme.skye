/** Read a small static brand palette from the same compiled CSS as the website. @since 3.4.0 */
'use strict';

const roles = ['primary', 'secondary', 'background', 'text'];

/** Remove CSS comments as whitespace while preserving quoted text and original caller bytes. */
function withoutComments(css) {
	const parts = [];
	let start = 0;
	let quote = '';
	let escaped = false;
	for (let index = 0; index < css.length; index++) {
		const character = css[index];
		if (escaped) { escaped = false; continue; }
		if (character === '\\') { escaped = true; continue; }
		if (quote) { if (character === quote) quote = ''; continue; }
		if (character === '"' || character === "'") { quote = character; continue; }
		if (character === '/' && css[index + 1] === '*') {
			const end = css.indexOf('*/', index + 2);
			if (end < 0) throw new Error('Incomplete branding CSS.');
			parts.push(css.slice(start, index), ' ');
			index = end + 1;
			start = end + 2;
		}
	}
	parts.push(css.slice(start));
	return parts.join('');
}

/**
 * Extract unconditional root colors without evaluating CSS, Sass or browser state.
 *
 * This deliberately accepts only literal hex declarations. Conditional, inherited
 * or computed brand values need an explicit project producer rather than a guess.
 * The bounded scanner keeps customer build tools free of extra dependencies.
 *
 * @param {string} css Complete compiled stylesheet, unchanged by this reader.
 * @param {Object} mapping Four semantic roles mapped to existing custom properties.
 * @return {Object} Versioned palette with canonical six-digit hex values.
 * @since 3.4.0
 */
function extractBranding(css, mapping) {
	if (typeof css !== 'string' || Buffer.byteLength(css) > 2 * 1024 * 1024 || !mapping ||
		Object.keys(mapping).length !== roles.length || roles.some(role => !/^--[a-z][a-z0-9-]{0,79}$/.test(mapping[role]))) {
		throw new Error('Branding requires bounded CSS and four explicit custom-property names.');
	}
	css = withoutComments(css);
	const requested = new Set(roles.map(role => mapping[role]));
	const values = new Map();
	const stack = [];
	let start = 0;
	let quote = '';
	let parentheses = 0;
	let escaped = false;
	const declaration = end => {
		if (!stack.length) return;
		const text = css.slice(start, end);
		if (/^\s*--[^:]*\\/.test(text)) throw new Error('Escaped custom-property names require an explicit brand producer.');
		const match = /^\s*(--[a-z][a-z0-9-]*)\s*:\s*([^]*)$/.exec(text);
		if (!match || !requested.has(match[1])) return;
		const current = stack[stack.length - 1];
		if (current !== ':root') {
			throw new Error('Brand declarations require one unconditional literal :root selector.');
		}
		if (stack.slice(0, -1).some(rule => !/^@layer(?:\s+[a-zA-Z0-9_.-]+)?$/.test(rule))) {
			throw new Error('Conditional brand colors cannot describe one automatic backend palette.');
		}
		const value = match[2].trim();
		if (!/^#[a-fA-F0-9]{3}(?:[a-fA-F0-9]{3})?$/.test(value)) throw new Error('Brand colors must be static hex values.');
		const normalized = (value.length === 4 ? '#' + value.slice(1).split('').map(digit => digit + digit).join('') : value).toLowerCase();
		if (values.has(match[1]) && values.get(match[1]) !== normalized) throw new Error('Ambiguous root brand colors require an explicit producer.');
		values.set(match[1], normalized);
	};
	for (let index = 0; index < css.length; index++) {
		const character = css[index];
		if (escaped) { escaped = false; continue; }
		if (character === '\\') { escaped = true; continue; }
		if (quote) { if (character === quote) quote = ''; continue; }
		if (character === '"' || character === "'") { quote = character; continue; }
		if (character === '(') { parentheses++; continue; }
		if (character === ')') { parentheses--; if (parentheses < 0) throw new Error('Unbalanced branding CSS.'); continue; }
		if (parentheses) continue;
		if (character === '{') { stack.push(css.slice(start, index).trim()); start = index + 1; }
		else if (character === ';') { declaration(index); start = index + 1; }
		else if (character === '}') {
			declaration(index);
			if (!stack.length) throw new Error('Unbalanced branding CSS.');
			stack.pop(); start = index + 1;
		}
	}
	if (stack.length || quote || parentheses || escaped) throw new Error('Incomplete branding CSS.');
	const colors = {};
	for (const role of roles) {
		if (!values.has(mapping[role])) throw new Error('Missing static root brand color: ' + mapping[role]);
		colors[role] = values.get(mapping[role]);
	}
	return {version: 1, colors};
}

module.exports = {extractBranding};
