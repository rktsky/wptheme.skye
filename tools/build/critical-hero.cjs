/**
 * Derive optional first-phase header styles from the complete prefixed CSS.
 *
 * This build-only asset supplements the unchanged full/mobile stylesheets. It is
 * suitable only for the explicitly supported full-height image header; runtime
 * code must check its expanded byte budget and retain the complete CSS fallback.
 *
 * @since 2026-11-02
 */
'use strict';

var path = require('path');
var postcss = require('postcss');
var selectorParser = require('postcss-selector-parser');
var valueParser = require('postcss-value-parser');
var defaultConfig = require('../../assets/manifest.json').performance.critical;
var sourceClasses = require('./critical-sources.cjs');

/**
 * Validate the explicit build contract before deriving an optional stylesheet.
 *
 * @param {Object} configuration Complete configuration, never modified here.
 * @return {void}
 * @since 2026-11-02
 */
function validateConfig(configuration) {
	if (!configuration || configuration.version !== 1 ||
		!Array.isArray(configuration.classes) || !Array.isArray(configuration.prefixes) ||
		configuration.filename !== 'main-critical.css' ||
		configuration.placeholder !== '__SKYE_THEME_DIST__') {
		throw new TypeError('Critical CSS requires the complete version-1 build configuration.');
	}
	/**
	 * Reject ambiguous class/prefix values instead of silently widening the scope.
	 *
	 * @param {*} value Configured native class or prefix.
	 * @return {boolean} Whether the value is an explicit nonempty string.
	 * @since 2026-11-02
	 */
	function isName(value) {
		return typeof value === 'string' && value.length > 0 && !/\s/.test(value);
	}
	if (!configuration.classes.every(isName) || !configuration.prefixes.every(isName)) {
		throw new TypeError('Critical CSS classes and prefixes must be explicit class names.');
	}
}

/**
 * Keep original selector branches whose positive class context can occur here.
 *
 * Negated classes do not require a matching element. Functional pseudo-class
 * internals remain conservative: an unknown OR/relative selector must not be
 * mistaken for a required positive class. A missing class outside that function
 * can still prove the complete branch inactive. Parse uncertainty keeps the
 * original selector; declarations and branch order are never rewritten.
 *
 * @param {string} selector Original comma-separated native selector list.
 * @param {Object} configuration Validated explicit header configuration.
 * @return {string} Retained branches, or an empty string when none can match.
 * @since 2026-11-02
 */
function headerSelectors(selector, configuration) {
	var kept = [];
	var classes = new Set(configuration.classes);
	/**
	 * Inspect native selector segments without splitting functional commas.
	 *
	 * @param {Object} selectors Native selector-parser root.
	 * @return {void}
	 * @since 2026-11-02
	 */
	function inspectSelectors(selectors) {
		/**
		 * Preserve one branch only when every known positive class is supported.
		 *
		 * @param {Object} segment Native selector branch.
		 * @return {void}
		 * @since 2026-11-02
		 */
		function inspectSegment(segment) {
			var supported = true;
			/**
			 * Evaluate class nodes outside functional pseudo-class contexts.
			 *
			 * @param {Object} node Native class selector node.
			 * @return {void}
			 * @since 2026-11-02
			 */
			function inspectClass(node) {
				// Parser 2 preserves CSS escapes in value; do not guess a decoded class.
				if (/\\/.test(node.value)) {
					return;
				}
				var parent = node.parent;
				while (parent && parent !== segment) {
					if (parent.type === 'pseudo' && parent.nodes && parent.nodes.length) {
						return;
					}
					parent = parent.parent;
				}
				/**
				 * Match only the explicitly configured native class families.
				 *
				 * @param {string} prefix Supported class-family prefix.
				 * @return {boolean} Whether this positive class belongs to the family.
				 * @since 2026-11-02
				 */
				function matchesPrefix(prefix) {
					return node.value.indexOf(prefix) === 0;
				}
				if (!classes.has(node.value) && !configuration.prefixes.some(matchesPrefix)) {
					supported = false;
				}
			}
			segment.walkClasses(inspectClass);
			if (supported) {
				kept.push(segment.toString());
			}
		}
		selectors.each(inspectSegment);
	}
	try {
		selectorParser(inspectSelectors).processSync(selector);
	} catch (error) {
		return selector;
	}
	return kept.join(',');
}

/**
 * Preserve original wrappers/order while excluding proven inactive branches.
 *
 * Font faces, keyframes and unknown at-rules remain intact at this stage. Only
 * media/supports wrappers recurse, and only genuinely empty wrappers disappear.
 *
 * @param {Object} container Native PostCSS root or media/supports container.
 * @param {Object} configuration Validated explicit header configuration.
 * @return {void}
 * @since 2026-11-02
 */
function filterRules(container, configuration) {
	/**
	 * Update the current native node in place without rearranging its siblings.
	 *
	 * @param {Object} node Current rule, at-rule, declaration or comment.
	 * @return {void}
	 * @since 2026-11-02
	 */
	function filterNode(node) {
		if (node.type === 'rule') {
			var selector = headerSelectors(node.selector, configuration);
			if (selector) {
				node.selector = selector;
			} else {
				node.remove();
			}
		} else if (node.type === 'atrule' && /^(media|supports|layer|container)$/i.test(node.name)) {
			filterRules(node, configuration);
			if (!node.nodes || !node.nodes.length) {
				node.remove();
			}
		}
	}
	container.each(filterNode);
}

/**
 * Read a single unescaped animation identifier without inventing CSS syntax.
 *
 * @param {string} value Native keyframe at-rule parameter.
 * @return {string|false} Exact case-sensitive name, or false when uncertain.
 * @since 2026-11-02
 */
function keyframeName(value) {
	var nodes = valueParser(value).nodes;
	/**
	 * Ignore insignificant CSS whitespace and comments.
	 *
	 * @param {Object} node Native value-parser node.
	 * @return {boolean} Whether the node contributes a value token.
	 * @since 2026-11-02
	 */
	function significant(node) {
		return node.type !== 'space' && node.type !== 'comment';
	}
	nodes = nodes.filter(significant);
	return nodes.length === 1 && /^(word|string)$/.test(nodes[0].type) &&
		!/\\/.test(nodes[0].value) ? nodes[0].value : false;
}

/**
 * Keep referenced animations verbatim, including transitive/vendor references.
 *
 * A variable, unfamiliar function, identifier or syntax conservatively retains
 * every keyframe. This optional first phase must not guess a future animation.
 * The complete full/mobile assets retain every original animation regardless.
 *
 * @param {Object} root Filtered native PostCSS root.
 * @return {void}
 * @since 2026-11-02
 */
function filterKeyframes(root) {
	var available = new Map();
	var referenced = new Set();
	var visited = new Set();
	var uncertain = false;
	/**
	 * Index native names while retaining all possible vendor-prefixed bodies.
	 *
	 * @param {Object} node Native at-rule.
	 * @return {void}
	 * @since 2026-11-02
	 */
	function indexKeyframe(node) {
		if (!/^(?:-[a-z]+-)?keyframes$/i.test(node.name)) {
			return;
		}
		// Unknown wrappers are opaque; their future animation semantics are unknown.
		var parent = node.parent;
		while (parent) {
			if (parent.type === 'atrule' && !/^(media|supports|layer|container)$/i.test(parent.name)) {
				uncertain = true;
			}
			parent = parent.parent;
		}
		var name = keyframeName(node.params);
		if (name === false) {
			uncertain = true;
			return;
		}
		if (!available.has(name)) {
			available.set(name, []);
		}
		available.get(name).push(node);
	}
	/**
	 * Inspect supported animation-name/shorthand tokens without rewriting values.
	 *
	 * @param {Object} declaration Native declaration or equivalent parsed fixture.
	 * @return {void}
	 * @since 2026-11-02
	 */
	function animationReferences(declaration) {
		if (!/^(?:-[a-z]+-)?animation(?:-name)?$/i.test(declaration.prop)) {
			return;
		}
		var isName = /-name$/i.test(declaration.prop);
		var groups = [[]];
		/**
		 * Split only native top-level animation-list comma tokens.
		 *
		 * @param {Object} node Native value-parser token.
		 * @return {void}
		 * @since 2026-11-02
		 */
		function groupToken(node) {
			if (node.type === 'div' && node.value === ',') {
				groups.push([]);
			} else if (node.type !== 'space' && node.type !== 'comment') {
				groups[groups.length - 1].push(node);
			}
		}
		/**
		 * Preserve known references; uncertainty keeps all original animations.
		 *
		 * @param {Object[]} group Parsed tokens of one animation list entry.
		 * @return {void}
		 * @since 2026-11-02
		 */
		function inspectGroup(group) {
			if (!group.length || (isName && group.length !== 1)) {
				uncertain = true;
				return;
			}
			/**
			 * Accept only known names and unambiguous scalar shorthand tokens.
			 *
			 * @param {Object} node Native animation token.
			 * @return {void}
			 * @since 2026-11-02
			 */
			function inspectToken(node) {
				if (!/^(word|string)$/.test(node.type) || /\\/.test(node.value)) {
					uncertain = true;
					return;
				}
				var word = node.value;
				if (available.has(word)) {
					referenced.add(word);
					return;
				}
				if (word.toLowerCase() === 'none') {
					return;
				}
				if (!isName && (/^-?(?:\d+(?:\.\d+)?|\.\d+)(?:ms|s)$/i.test(word) ||
					/^(?:\d+(?:\.\d+)?|\.\d+)$/.test(word) ||
					/^(infinite|ease|ease-in|ease-out|ease-in-out|linear|step-start|step-end|normal|reverse|alternate|alternate-reverse|forwards|backwards|both|running|paused)$/i.test(word))) {
					return;
				}
				uncertain = true;
			}
			group.forEach(inspectToken);
		}
		valueParser(declaration.value).nodes.forEach(groupToken);
		groups.forEach(inspectGroup);
	}
	/**
	 * Read initial references only from rules outside keyframe bodies.
	 *
	 * @param {Object} declaration Native declaration.
	 * @return {void}
	 * @since 2026-11-02
	 */
	function initialReferences(declaration) {
		var parent = declaration.parent;
		while (parent) {
			if (parent.type === 'atrule' && /^(?:-[a-z]+-)?keyframes$/i.test(parent.name)) {
				return;
			}
			parent = parent.parent;
		}
		animationReferences(declaration);
	}
	/**
	 * Include every body belonging to a referenced native/vendor animation name.
	 *
	 * @param {string} name Exact native keyframe name.
	 * @return {void}
	 * @since 2026-11-02
	 */
	function followReference(name) {
		if (visited.has(name)) {
			return;
		}
		visited.add(name);
		/**
		 * Follow animation references nested in this retained keyframe body.
		 *
		 * @param {Object} node Original unmodified native keyframe at-rule.
		 * @return {void}
		 * @since 2026-11-02
		 */
		function followBody(node) {
			node.walkDecls(animationReferences);
		}
		available.get(name).forEach(followBody);
	}
	/**
	 * Remove only complete, unreferenced bodies after a successful proof.
	 *
	 * @param {Object} node Native at-rule.
	 * @return {void}
	 * @since 2026-11-02
	 */
	function removeUnreferenced(node) {
		if (/^(?:-[a-z]+-)?keyframes$/i.test(node.name) && !referenced.has(keyframeName(node.params))) {
			node.remove();
		}
	}
	root.walkAtRules(indexKeyframe);
	root.walkDecls(initialReferences);
	while (!uncertain && visited.size < referenced.size) {
		referenced.forEach(followReference);
	}
	if (!uncertain) {
		root.walkAtRules(removeUnreferenced);
	}
}

/**
 * Replace only the theme's two known relative asset roots with a stable token.
 *
 * Runtime expands the token to the final public Dist URL without a trailing
 * slash. Data, external, root-relative and unrelated URLs remain unchanged.
 * Strings containing url-like text are not parsed as resource functions.
 *
 * @param {Object} root Native derived stylesheet root.
 * @param {string} placeholder Validated Dist-base placeholder.
 * @return {void}
 * @since 2026-11-02
 */
function tokenizeAssetUrls(root, placeholder) {
	/**
	 * Rebase genuine URL function nodes while preserving unrelated raw values.
	 *
	 * @param {Object} declaration Native declaration.
	 * @return {void}
	 * @since 2026-11-02
	 */
	function tokenizeDeclaration(declaration) {
		var value = valueParser(declaration.value);
		var changed = false;
		/**
		 * Map only a single literal theme asset URL argument.
		 *
		 * @param {Object} node Native value-parser token.
		 * @return {void}
		 * @since 2026-11-02
		 */
		function tokenizeUrl(node) {
			if (node.type === 'function' && node.value.toLowerCase() === 'url' &&
				node.nodes.length === 1 && /^(word|string)$/.test(node.nodes[0].type) &&
				/^\.\.\/(fonts|images)\//.test(node.nodes[0].value)) {
				node.nodes[0].value = placeholder + '/' + node.nodes[0].value.slice(3);
				changed = true;
			}
		}
		value.walk(tokenizeUrl);
		if (changed) {
			declaration.value = value.toString();
		}
	}
	root.walkDecls(tokenizeDeclaration);
}

/**
 * Compact only the recognized Bootstrap notice while retaining its attribution.
 *
 * @param {Object} root Native derived stylesheet root.
 * @return {void}
 * @since 2026-11-02
 */
function compactBootstrapNotice(root) {
	/**
	 * Preserve version, copyright and the original MIT license URL.
	 *
	 * @param {Object} comment Native CSS comment node.
	 * @return {void}
	 * @since 2026-11-02
	 */
	function compactComment(comment) {
		var version = /Bootstrap\s+v(\d+(?:\.\d+)+)/.exec(comment.text);
		var copyright = /Copyright\s+([\d-]+)\s+The Bootstrap Authors/.exec(comment.text);
		var license = /https:\/\/github\.com\/twbs\/bootstrap\/blob\/[^\s)]+\/LICENSE/.exec(comment.text);
		if (version && copyright && license && /Licensed under MIT/.test(comment.text)) {
			comment.text = '! Bootstrap v' + version[1] + ' | Copyright ' + copyright[1] +
				' The Bootstrap Authors | MIT: ' + license[0] + ' ';
			comment.raws.left = '';
			comment.raws.right = '';
		}
	}
	root.walkComments(compactComment);
}

/**
 * Derive a supplemental critical asset without changing the complete source.
 *
 * @param {string} css Complete compiled and prefixed stylesheet.
 * @param {string} [from] Original path used only for parser diagnostics.
 * @param {Object} [configuration] Complete optional replacement configuration.
 * @return {string} Critical CSS using the stable Dist-base URL placeholder.
 * @since 2026-11-02
 */
function deriveCritical(css, from, configuration) {
	if (typeof css !== 'string') {
		throw new TypeError('Critical CSS input must be a complete CSS string.');
	}
	configuration = configuration || sourceClasses.readConfiguration(defaultConfig, path.resolve(__dirname, '../..'));
	validateConfig(configuration);
	var root = postcss.parse(css, {from: from});
	filterRules(root, configuration);
	filterKeyframes(root);
	tokenizeAssetUrls(root, configuration.placeholder);
	compactBootstrapNotice(root);
	return root.toString();
}

module.exports = { deriveCritical, validateConfig };
