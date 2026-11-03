/**
 * Discover literal header classes from the own-theme PHP sources at build time.
 *
 * Sources are read as text, never executed. Runtime data, WordPress, ACF and
 * customer content are not involved. Dynamic states remain explicit config.
 *
 * @since 2026-11-02
 */
'use strict';

var fs = require('fs');
var path = require('path');
var DYNAMIC = '__SKYE_DYNAMIC_CLASS__';

/**
 * Collect literal HTML attributes and native PHP image-attribute class values.
 *
 * The masked pass preserves static tokens around PHP expressions. The raw pass
 * additionally finds literal attributes in PHP echo strings, including escaped
 * quote delimiters. Partial dynamic tokens are never invented as class names.
 * This is a literal inventory, not a PHP/HTML interpreter or a page-content scan.
 *
 * @param {string} source Own-theme PHP source text.
 * @return {string[]} Unique literal class names in deterministic order.
 * @since 2026-11-02
 */
function classesFromSource(source) {
	if (typeof source !== 'string') {
		throw new TypeError('Critical class discovery requires source text.');
	}
	var classes = new Set();
	/**
	 * Preserve complete literal whitespace-separated tokens only.
	 *
	 * @param {string} value Captured literal attribute value.
	 * @return {void}
	 * @since 2026-11-02
	 */
	function addValue(value) {
		if (value.indexOf('<?') !== -1) {
			return;
		}
		/**
		 * Exclude PHP placeholders and malformed source fragments.
		 *
		 * @param {string} name Literal class token.
		 * @return {void}
		 * @since 2026-11-02
		 */
		function addClass(name) {
			if (name && name.indexOf(DYNAMIC) === -1 && !/["'<>`=]/.test(name)) {
				classes.add(name);
			}
		}
		value.split(/\s+/).forEach(addClass);
	}
	/**
	 * Read quoted literal attributes without splitting their class names.
	 *
	 * @param {string} text Raw PHP source or its masked inline-HTML representation.
	 * @return {void}
	 * @since 2026-11-02
	 */
	function attributes(text) {
		var expression = /(?:^|[\s<])class\s*=\s*(\\?["'])([\s\S]*?)\1/g;
		var match;
		while ((match = expression.exec(text)) !== null) {
			addValue(match[2]);
		}
		var unquoted = /(?:^|[\s<])class\s*=\s*([^\s"'`=<>\\]+)/g;
		while ((match = unquoted.exec(text)) !== null) {
			addValue(match[1]);
		}
	}
	attributes(source);
	attributes(source.replace(/<\?(?:php\b|=)[\s\S]*?\?>/gi, DYNAMIC));
	var imageAttributes = /(["'])class\1\s*=>\s*(["'])([\s\S]*?)\2/g;
	var match;
	while ((match = imageAttributes.exec(source)) !== null) {
		addValue(match[3]);
	}
	return Array.from(classes).sort();
}

/**
 * Add automatically discovered literals without mutating the explicit config.
 *
 * @param {Object} configuration Explicit states, families and asset contract.
 * @param {string[]} sources Own-theme source texts, never evaluated.
 * @return {Object} Independent configuration containing their class union.
 * @since 2026-11-02
 */
function configurationFromSources(configuration, sources) {
	if (!configuration || !Array.isArray(configuration.classes) || !Array.isArray(sources)) {
		throw new TypeError('Critical source discovery requires a class list and source texts.');
	}
	var classes = new Set(configuration.classes);
	/**
	 * Merge each source's literal names into the deterministic build inventory.
	 *
	 * @param {string} source Own-theme PHP source text.
	 * @return {void}
	 * @since 2026-11-02
	 */
	function mergeSource(source) {
		/**
		 * Retain every literal name independently of the manual interaction list.
		 *
		 * @param {string} name Literal source class name.
		 * @return {void}
		 * @since 2026-11-02
		 */
		function mergeName(name) {
			classes.add(name);
		}
		classesFromSource(source).forEach(mergeName);
	}
	sources.forEach(mergeSource);
	var result = Object.assign({}, configuration);
	result.classes = Array.from(classes).sort();
	result.prefixes = configuration.prefixes.slice();
	if (configuration.source_paths) {
		result.source_paths = configuration.source_paths.slice();
	}
	return result;
}

/**
 * Read only configured own-theme PHP files and directories for this build.
 *
 * Missing sources fail the build instead of publishing an incomplete critical
 * sheet. Directory traversal is sorted, includes new PHP files automatically,
 * and rejects symbolic links escaping the theme checkout. No runtime IO results.
 *
 * @param {Object} configuration Explicit source paths relative to the theme.
 * @param {string} root Absolute theme source root.
 * @return {Object} Build configuration augmented with literal source classes.
 * @since 2026-11-02
 */
function readConfiguration(configuration, root) {
	if (!Array.isArray(configuration.source_paths)) {
		return configuration;
	}
	var theme = fs.realpathSync(root);
	var visited = new Set();
	var sources = [];
	/**
	 * Read a single source or recursively inspect a configured PHP directory.
	 *
	 * @param {string} filename Candidate path inside the theme source tree.
	 * @return {void}
	 * @since 2026-11-02
	 */
	function readSource(filename) {
		var resolved = fs.realpathSync(filename);
		/**
		 * Inspect children in deterministic order, including newly added files.
		 *
		 * @param {string} name Source directory entry.
		 * @return {void}
		 * @since 2026-11-02
		 */
		function readChild(name) {
			readSource(path.join(resolved, name));
		}
		if (resolved.indexOf(theme + path.sep) !== 0) {
			throw new Error('Critical source discovery must remain inside the theme.');
		}
		if (visited.has(resolved)) {
			return;
		}
		visited.add(resolved);
		if (fs.statSync(resolved).isDirectory()) {
			fs.readdirSync(resolved).sort().forEach(readChild);
		} else if (/\.php$/i.test(resolved)) {
			sources.push(fs.readFileSync(resolved, 'utf8'));
		}
	}
	/**
	 * Resolve each explicit file/directory without permitting absolute paths.
	 *
	 * @param {string} relative Source file or directory relative to the checkout.
	 * @return {void}
	 * @since 2026-11-02
	 */
	function readRelative(relative) {
		if (typeof relative !== 'string' || !relative || path.isAbsolute(relative)) {
			throw new TypeError('Critical source paths must be relative theme paths.');
		}
		readSource(path.resolve(theme, relative));
	}
	configuration.source_paths.forEach(readRelative);
	return configurationFromSources(configuration, sources);
}

/**
 * Rebuild critical styles when a normal header template is added or edited.
 *
 * @param {Object} configuration Explicit relative file/directory paths.
 * @return {string[]} Native Gulp watch globs relative to the theme checkout.
 * @since 2026-11-02
 */
function sourceGlobs(configuration) {
	/**
	 * Turn directory entries into recursive PHP globs; files remain explicit.
	 *
	 * @param {string} source Explicit relative source path.
	 * @return {string} Native file or recursive directory glob.
	 * @since 2026-11-02
	 */
	function sourceGlob(source) {
		return /\/$/.test(source) ? source + '**/*.php' : source;
	}
	return (configuration.source_paths || []).map(sourceGlob);
}

module.exports.classesFromSource = classesFromSource;
module.exports.configurationFromSources = configurationFromSources;
module.exports.readConfiguration = readConfiguration;
module.exports.sourceGlobs = sourceGlobs;
