/**
 * Derive a complete narrow-viewport stylesheet without changing its cascade.
 *
 * This build-only transform keeps the original file and creates its mobile peer
 * from the same prefixed CSS. It never selects rules from page content.
 *
 * @since 2026-11-02
 */
'use strict';

var postcss = require('postcss');
var BREAKPOINT = 576;

/**
 * Recognize only a top-level media condition false at every width below 576px.
 *
 * Unrecognized syntax stays in the output. In particular print, mixed/range
 * expressions, non-pixel units and nested conditions are never simplified.
 *
 * @param {Object} node Native PostCSS node with its parent relationship.
 * @return {boolean} Whether the complete media block is inactive on mobile.
 * @since 2026-11-02
 */
function isInactiveMedia(node, breakpoint = BREAKPOINT) {
	if (!node || node.type !== 'atrule' || String(node.name).toLowerCase() !== 'media' ||
		!node.parent || typeof node.params !== 'string') {
		return false;
	}
	var parent = node.parent;
	while (parent && parent.type !== 'root') {
		if (parent.type !== 'atrule' || String(parent.name).toLowerCase() !== 'layer') return false;
		parent = parent.parent;
	}
	// PostCSS may expose a comment-free value; validate the actual raw condition.
	var params = node.params;
	if (node.raws && node.raws.params && node.raws.params.value === params) {
		params = node.raws.params.raw;
	}
	var match = /^(?:screen\s+and\s+)?\(\s*min-width\s*:\s*(\d+(?:\.\d+)?|\.\d+)px\s*\)$/i.exec(params.trim()) || /^(?:screen\s+and\s+)?\(\s*width\s*>=\s*(\d+(?:\.\d+)?|\.\d+)px\s*\)$/i.exec(params.trim());
	return !!match && Number(match[1]) >= breakpoint;
}

/**
 * Remove an entire condition only when the shared policy proves it inactive.
 *
 * @param {Object} node Direct child of the parsed stylesheet root.
 * @return {void}
 * @since 2026-11-02
 */
function removeInactiveMedia(node, breakpoint) {
	if (isInactiveMedia(node, breakpoint)) {
		node.remove();
	}
}

/**
 * Keep every remaining native AST node in its original declaration/source order.
 *
 * PostCSS parses actual CSS syntax; media-looking text inside declarations or
 * comments is never considered a condition. Parse errors fail the build rather
 * than emitting a partial variant. Stringification keeps existing raw syntax.
 *
 * @param {string} css Complete compiled and prefixed stylesheet.
 * @param {string} [from] Original file path used only in parser diagnostics.
 * @return {string} Complete mobile stylesheet with inactive blocks removed.
 * @since 2026-11-02
 */
function deriveMobile(css, from, breakpoint = BREAKPOINT) {
	if (typeof css !== 'string') {
		throw new TypeError('Mobile CSS input must be a complete CSS string.');
	}
	var root = postcss.parse(css, {from: from});
	root.walkAtRules('media', function(node) { removeInactiveMedia(node, breakpoint); });
	return root.toString();
}

module.exports = { deriveMobile, isInactiveMedia, BREAKPOINT };
