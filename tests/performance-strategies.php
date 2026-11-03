<?php
/**
 * Verify the real WordPress dependency/inline guards for GF loading strategies.
 *
 * Backported from completed Volksgarage Website 6fecb117; only fixture names and paths generalized.
 * Run: SKYE_WORDPRESS_CORE=/absolute/wordpress php tools/tests/asset-strategies.php
 * Core prints the actual script tags. Only the synthetic registration graph and
 * admin screen are fixtures; no site, database, HTTP request or plugin is loaded.
 * The GF 3.2.1 graph was checked against GFForms::register_scripts() and
 * GF_Theme_Script_Dependencies on the installed staging plugin, 2026-11-02.
 *
 * @since 3.0.0
 */

error_reporting( E_ALL );

/**
 * Fail on diagnostics rather than accepting an invalid registration silently.
 *
 * @param int    $severity Native diagnostic severity.
 * @param string $message Native diagnostic text.
 * @param string $file Native source path.
 * @param int    $line Native source line.
 * @return bool Whether the diagnostic was handled.
 * @throws ErrorException For an enabled diagnostic.
 * @since 3.0.0
 */
function skye_perf_asset_test_error( $severity, $message, $file, $line ) {
	if ( error_reporting() & $severity ) {
		throw new ErrorException( $message, 0, $severity, $file, $line );
	}
	return false;
}

set_error_handler( 'skye_perf_asset_test_error' );
$core = getenv( 'SKYE_WORDPRESS_CORE' );
if ( ! $core || ! is_file( $core . '/wp-includes/class-wp-scripts.php' ) ) {
	throw new RuntimeException( 'Set SKYE_WORDPRESS_CORE to a real local WordPress source checkout.' );
}
define( 'ABSPATH', rtrim( $core, '/' ) . '/' );
define( 'WPINC', 'wp-includes' );
define( 'WP_DEBUG', false );
foreach ( array(
	'version.php', 'compat.php', 'plugin.php', 'load.php', 'class-wp-error.php', 'functions.php', 'theme.php',
	'compat-utf8.php', 'utf8.php', 'formatting.php', 'kses.php',
	'script-loader.php', 'class-wp-token-map.php',
	'html-api/html5-named-character-references.php', 'html-api/class-wp-html-attribute-token.php',
	'html-api/class-wp-html-span.php', 'html-api/class-wp-html-text-replacement.php',
	'html-api/class-wp-html-decoder.php', 'html-api/class-wp-html-tag-processor.php',
) as $file ) {
	if ( in_array( $file, array( 'compat-utf8.php', 'utf8.php', 'class-wp-token-map.php' ), true ) && ! is_file( ABSPATH . WPINC . '/' . $file ) ) {
		continue;
	}
	require_once ABSPATH . WPINC . '/' . $file;
}
/**
 * Supply the public charset needed by older Core serializers without a site database.
 *
 * @since 3.0.0
 * @param mixed $previous Existing native option short circuit.
 * @param string $name Requested option.
 * @return mixed UTF-8 charset or the prior value.
 */
function skye_perf_asset_option( $previous, $name ) {
	return $name === 'blog_charset' ? 'UTF-8' : $previous;
}
add_filter( 'pre_option', 'skye_perf_asset_option', 10, 2 );
add_theme_support( 'html5', array( 'script', 'style' ) );
require dirname( __DIR__ ) . '/lib/package/Performance.php';

use Rocketsky\Skye\Packages\Performance;

/**
 * Supply only the native is_admin() screen boundary without bootstrapping a site.
 *
 * @since 3.0.0
 */
class Skye_Asset_Test_Screen {
	/** @var bool Whether this isolated request represents the admin. */
	public $admin = false;

	/**
	 * Return the environment flag consulted by the actual Core is_admin().
	 *
	 * @return bool Whether the synthetic screen is administrative.
	 * @since 3.0.0
	 */
	public function in_admin() {
		return $this->admin;
	}
}

$current_screen = new Skye_Asset_Test_Screen();
$checks = 0;
$performance = new Performance();
do_action( 'init' );

/**
 * Assert observable behavior of real Core or the actual current theme class.
 *
 * @param bool   $condition Observed result.
 * @param string $message Expected behavior.
 * @return void
 * @throws RuntimeException When the expectation is not met.
 * @since 3.0.0
 */
function skye_perf_asset_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	$GLOBALS['checks']++;
}

/**
 * Register a fresh native graph with the actual installed GF frontend edges.
 *
 * Fixture URLs are inert and never fetched. Footer positions and dependencies
 * mirror the installed GF declarations; Core itself resolves and prints them.
 *
 * @param bool $enqueue Whether a form/theme is actually requested.
 * @return WP_Scripts Real new Core script registry.
 * @since 3.0.0
 */
function skye_perf_asset_graph( $enqueue = true ) {
	$GLOBALS['current_screen']->admin = false;
	$scripts = new WP_Scripts();
	$scripts->base_url = 'https://example.test';
	$scripts->default_version = 'fixture';
	$GLOBALS['wp_scripts'] = $scripts;
	wp_register_script( 'jquery-core', '/jquery.js', array(), null, array( 'strategy' => 'defer' ) );
	wp_register_script( 'jquery-migrate', '/migrate.js', array( 'jquery-core' ), null, array( 'strategy' => 'defer' ) );
	wp_register_script( 'jquery', false, array( 'jquery-core', 'jquery-migrate' ) );
	wp_register_script( 'gform_json', '/jquery.json.js', array( 'jquery' ), null, true );
	wp_register_script( 'gform_gravityforms', '/gravityforms.js', array( 'jquery', 'gform_json' ), null, false );
	wp_register_script( 'gform_gravityforms_utils', '/utils.js', array(), null, false );
	wp_register_script( 'gform_gravityforms_theme_vendors', '/vendor-theme.js', array( 'gform_gravityforms_utils', 'gform_gravityforms' ), null, true );
	wp_register_script( 'gform_gravityforms_theme', '/scripts-theme.js', array( 'gform_gravityforms_theme_vendors' ), null, true );
	wp_register_script( 'gform_conditional_logic', '/conditional.js', array( 'jquery', 'gform_gravityforms' ), null, false );
	wp_register_script( 'gform_page_conditional_logic', '/page-conditional.js', array( 'jquery', 'gform_gravityforms' ), null, false );
	wp_register_script( 'gform_chosen', '/chosen.js', array( 'jquery' ), null, false );
	wp_register_script( 'gform_unused', '/unused.js', array( 'jquery' ), null, false );
	wp_register_script( 'skye/js', '/main.js', array( 'jquery' ), null, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	wp_localize_script( 'gform_gravityforms', 'gf_global', array( 'fixture' => 'unchanged config' ) );
	wp_add_inline_script( 'gform_gravityforms', 'window.gfFixtureBefore = true;', 'before' );
	if ( $enqueue ) {
		wp_enqueue_script( 'gform_gravityforms' );
		wp_enqueue_script( 'gform_gravityforms_theme' );
		wp_enqueue_script( 'skye/js' );
	}
	return $scripts;
}

/**
 * Capture actual Core output and inspect native script attributes by ID.
 *
 * @return array Printed HTML and the parsed external script attributes.
 * @since 3.0.0
 */
function skye_perf_asset_output() {
	ob_start();
	wp_scripts()->do_items();
	$html = ob_get_clean();
	$processor = new WP_HTML_Tag_Processor( $html );
	$tags = array();
	while ( $processor->next_tag( 'SCRIPT' ) ) {
		$tags[$processor->get_attribute( 'id' )] = array(
			'defer' => $processor->get_attribute( 'defer' ),
			'src' => $processor->get_attribute( 'src' ),
		);
	}
	return array( $html, $tags );
}

skye_perf_asset_check( has_action( 'gform_enqueue_scripts', array( $performance, 'defer_form_scripts' ) ) === 20, 'The bridge runs after GF priority-ten theme enqueues.' );
$scripts = skye_perf_asset_graph();
list( $html, $tags ) = skye_perf_asset_output();
skye_perf_asset_check( $tags['jquery-core-js']['defer'] !== true && $tags['jquery-migrate-js']['defer'] !== true, 'Unbridged GF dependents reproduce Core downgrading intended deferred jQuery.' );

$scripts = skye_perf_asset_graph();
do_action( 'gform_enqueue_scripts', array( 'id' => 6 ), true );
skye_perf_asset_check( ! is_callable( array( 'GFFormDisplay', 'add_script_defer' ) ) && $scripts->get_data( 'gform_gravityforms', 'strategy' ) === false, 'A missing GF provider never opts scripts into native defer.' );

if ( ! class_exists( 'GFFormDisplay', false ) ) {
	/**
	 * Model provider intent without copying the plugin's tag/dependency algorithm.
	 *
	 * Core's native filter and HTML parser remain real. Tests can withdraw the
	 * provider or its per-handle intent independently of the theme's allowlist.
	 *
	 * @since 3.0.0
	 */
	class GFFormDisplay {
		/** @var bool Whether the synthetic provider currently wants deferred scripts. */
		public static $enabled = true;
		/** @var array Handles whose provider intent differs from other GF scripts. */
		public static $declined = array();

		/**
		 * Express selected provider intent through the actual native HTML parser.
		 *
		 * @param string $html Existing native script tag.
		 * @param string $handle Handle whose intent is queried.
		 * @return string Provider output without any queue, dependency or inline edit.
		 * @since 3.0.0
		 */
		public static function add_script_defer( $html, $handle ) {
			if ( ! self::$enabled || in_array( $handle, self::$declined, true ) || strpos( $handle, 'gform_' ) !== 0 ) {
				return $html;
			}
			$tag = new WP_HTML_Tag_Processor( $html );
			if ( $tag->next_tag( 'SCRIPT' ) && null !== $tag->get_attribute( 'src' ) ) {
				$tag->set_attribute( 'defer', true );
			}
			return $tag->get_updated_html();
		}
	}
}

$provider = array( 'GFFormDisplay', 'add_script_defer' );
$scripts = skye_perf_asset_graph();
do_action( 'gform_enqueue_scripts', array( 'id' => 6 ), true );
skye_perf_asset_check( is_callable( $provider ) && has_filter( 'script_loader_tag', $provider ) === false && $scripts->get_data( 'gform_gravityforms', 'strategy' ) === false, 'A callable but opted-out GF provider does not enable native defer.' );
add_filter( 'script_loader_tag', $provider, 10, 2 );
GFFormDisplay::$enabled = false;
$scripts = skye_perf_asset_graph();
do_action( 'gform_enqueue_scripts', array( 'id' => 6 ), true );
list( $html, $tags ) = skye_perf_asset_output();
skye_perf_asset_check( $scripts->get_data( 'gform_gravityforms', 'strategy' ) === false && $tags['jquery-core-js']['defer'] !== true, 'An active provider that no longer defers its own scripts is respected.' );
GFFormDisplay::$enabled = true;
GFFormDisplay::$declined = array( 'gform_json' );
$scripts = skye_perf_asset_graph();
do_action( 'gform_enqueue_scripts', array( 'id' => 6 ), true );
list( $html, $tags ) = skye_perf_asset_output();
skye_perf_asset_check( $scripts->get_data( 'gform_json', 'strategy' ) === false && $scripts->get_data( 'gform_gravityforms', 'strategy' ) === 'defer' && $tags['jquery-core-js']['defer'] !== true, 'Provider intent is inspected separately for each verified source handle.' );
GFFormDisplay::$declined = array();

$scripts = skye_perf_asset_graph();
$queue = $scripts->queue;
$before = $scripts->get_data( 'gform_gravityforms', 'before' );
$settings = $scripts->get_data( 'gform_gravityforms', 'data' );
$dependencies = array();
foreach ( $scripts->registered as $handle => $registered ) {
	$dependencies[$handle] = $registered->deps;
}
do_action( 'gform_enqueue_scripts', array( 'id' => 6 ), true );
do_action( 'gform_enqueue_scripts', array( 'id' => 3 ), false );
skye_perf_asset_check( $scripts->queue === $queue, 'Repeated form hooks do not enqueue, dequeue, reorder or duplicate scripts.' );
foreach ( $dependencies as $handle => $expected ) {
	skye_perf_asset_check( $scripts->registered[$handle]->deps === $expected, 'Every registered dependency list stays exact.' );
}
skye_perf_asset_check( $scripts->get_data( 'gform_gravityforms', 'before' ) === $before && $scripts->get_data( 'gform_gravityforms', 'data' ) === $settings, 'GF inline-before and localized configuration remain byte-exact.' );
skye_perf_asset_check( $scripts->get_data( 'jquery', 'strategy' ) === false && $scripts->get_data( 'gform_unused', 'strategy' ) === false, 'The jQuery alias and unrequested scripts acquire no strategy.' );
list( $html, $tags ) = skye_perf_asset_output();
foreach ( array( 'jquery-core', 'jquery-migrate', 'gform_json', 'gform_gravityforms', 'gform_gravityforms_utils', 'gform_gravityforms_theme_vendors', 'gform_gravityforms_theme', 'skye/js' ) as $handle ) {
	skye_perf_asset_check( $tags[$handle . '-js']['defer'] === true, 'Core itself emits defer throughout the known form/theme chain.' );
}
// Inspect raw final output: normal HTML parsing alone would hide duplicate attributes.
preg_match_all( '/<script\\b[^>]*>/i', $html, $opening_tags );
$gf_tags = 0;
$single_defer = true;
foreach ( $opening_tags[0] as $opening_tag ) {
	$printed = new WP_HTML_Tag_Processor( $opening_tag . '</script>' );
	$printed->next_tag( 'SCRIPT' );
	if ( strpos( (string) $printed->get_attribute( 'id' ), 'gform_' ) !== 0 || null === $printed->get_attribute( 'src' ) ) {
		continue;
	}
	$gf_tags++;
	$single_defer = $single_defer && preg_match_all( '/\\sdefer(?=[\\s=>])/i', $opening_tag ) === 1;
}
skye_perf_asset_check( $gf_tags === 5 && $single_defer, 'Final Core tags passed through the active provider contain exactly one defer attribute each.' );
skye_perf_asset_check( strpos( $html, 'id="jquery-core-js"' ) < strpos( $html, 'id="gform_json-js"' ) && strpos( $html, 'id="gform_json-js"' ) < strpos( $html, 'id="gform_gravityforms-js"' ), 'Native dependency output preserves jQuery, JSON helper and GF execution order.' );

$scripts = skye_perf_asset_graph();
foreach ( array( 'gform_conditional_logic', 'gform_page_conditional_logic', 'gform_chosen' ) as $handle ) {
	wp_enqueue_script( $handle );
}
do_action( 'gform_enqueue_scripts', array( 'id' => 1 ), true );
list( $html, $tags ) = skye_perf_asset_output();
skye_perf_asset_check( $tags['jquery-core-js']['defer'] === true && $tags['gform_conditional_logic-js']['defer'] === true && $tags['gform_page_conditional_logic-js']['defer'] === true && $tags['gform_chosen-js']['defer'] === true, 'Known conditional/page/enhanced-choice scripts remain natively ordered and deferred.' );

$scripts = skye_perf_asset_graph();
$after = 'window.gfFixtureAfter = jQuery.noConflict();';
wp_add_inline_script( 'gform_gravityforms', $after, 'after' );
$after_data = $scripts->get_data( 'gform_gravityforms', 'after' );
do_action( 'gform_enqueue_scripts', array( 'id' => 6 ), true );
list( $html, $tags ) = skye_perf_asset_output();
skye_perf_asset_check( $tags['jquery-core-js']['defer'] !== true && $tags['jquery-migrate-js']['defer'] !== true, 'Core still keeps jQuery and Migrate blocking for immediate after-inline code; the provider owns its GF tag policy.' );
skye_perf_asset_check( strpos( $html, $after ) !== false && $scripts->get_data( 'gform_gravityforms', 'after' ) === $after_data, 'The blocking inline code is neither dropped, wrapped nor relocated.' );

$scripts = skye_perf_asset_graph();
wp_enqueue_script( 'gform_external_addon', '/external-addon.js', array( 'jquery' ), null );
do_action( 'gform_enqueue_scripts', array( 'id' => 6 ), true );
list( $html, $tags ) = skye_perf_asset_output();
skye_perf_asset_check( $tags['jquery-core-js']['defer'] !== true && $scripts->get_data( 'gform_external_addon', 'strategy' ) === false, 'Unknown GF-prefixed add-ons remain outside the bridge and keep native jQuery conservative.' );

$scripts = skye_perf_asset_graph( false );
do_action( 'gform_enqueue_scripts', array(), false );
skye_perf_asset_check( $scripts->queue === array() && $scripts->get_data( 'gform_gravityforms', 'strategy' ) === false, 'A request without queued forms gains neither scripts nor form strategy work.' );
$scripts = skye_perf_asset_graph();
$current_screen->admin = true;
do_action( 'gform_enqueue_scripts', array( 'id' => 6 ), true );
skye_perf_asset_check( $scripts->get_data( 'gform_gravityforms', 'strategy' ) === false, 'The admin keeps GF registration behavior unchanged.' );
$current_screen->admin = false;
$scripts = skye_perf_asset_graph();
wp_script_add_data( 'gform_gravityforms', 'strategy', 'async' );
do_action( 'gform_enqueue_scripts', array( 'id' => 6 ), true );
skye_perf_asset_check( $scripts->get_data( 'gform_gravityforms', 'strategy' ) === 'async', 'An explicit existing native strategy is not overwritten.' );

$scripts = skye_perf_asset_graph();
wp_deregister_script( 'gform_json' );
wp_register_script( 'gform_json', false, array( 'jquery' ) );
do_action( 'gform_enqueue_scripts', array( 'id' => 6 ), true );
list( $html, $tags ) = skye_perf_asset_output();
skye_perf_asset_check( $scripts->get_data( 'gform_json', 'strategy' ) === false && $tags['jquery-core-js']['defer'] === true, 'A source-less GF alias remains a native alias without invalid strategy metadata.' );
$scripts = skye_perf_asset_graph();
ob_start();
$scripts->do_items( array( 'gform_gravityforms_utils' ) );
ob_end_clean();
do_action( 'gform_enqueue_scripts', array( 'id' => 6 ), true );
skye_perf_asset_check( $scripts->get_data( 'gform_gravityforms_utils', 'strategy' ) === false, 'Already printed source scripts are not retroactively changed.' );

$scripts = skye_perf_asset_graph();
wp_register_script( 'wp-dom-ready', '/dom-ready.js', array(), null, true );
wp_register_script( 'wp-hooks', '/hooks.js', array(), null, true );
wp_register_script( 'wp-i18n', '/i18n.js', array( 'wp-hooks' ), null, true );
wp_register_script( 'wp-a11y', '/a11y.js', array( 'wp-dom-ready', 'wp-i18n' ), null, true );
$scripts->registered['gform_gravityforms_theme_vendors']->deps[] = 'wp-a11y';
$locale = "wp.i18n.setLocaleData( { 'direction': [ 'ltr' ] } );";
wp_add_inline_script( 'wp-i18n', $locale, 'after' );
wp_add_inline_script( 'wp-a11y', 'wp.i18n.setLocaleData( {} );', 'before' );
$locale_data = $scripts->get_data( 'wp-i18n', 'after' );
$performance->defer_form_dependencies();
list( $html, $tags ) = skye_perf_asset_output();
skye_perf_asset_check( $tags['wp-dom-ready-js']['defer'] === true && $tags['wp-a11y-js']['defer'] === true, 'Native non-blocking accessibility dependencies keep their resolved source order.' );
skye_perf_asset_check( $tags['wp-i18n-js']['defer'] !== true && $tags['wp-hooks-js']['defer'] !== true, 'Core preserves the immediate locale bootstrap and its blocking dependency.' );
skye_perf_asset_check( strpos( $html, $locale ) !== false && $scripts->get_data( 'wp-i18n', 'after' ) === $locale_data, 'Native locale inline code is neither removed, relocated nor wrapped.' );
skye_perf_asset_check( $scripts->get_data( 'gform_gravityforms_theme', 'strategy' ) === 'defer', 'The final pass observes late theme provider enqueues through the same checked bridge.' );
$scripts = skye_perf_asset_graph( false );
wp_register_script( 'wp-a11y', '/a11y.js', array(), null, true );
$performance->defer_form_dependencies();
skye_perf_asset_check( $scripts->get_data( 'wp-a11y', 'strategy' ) === false && $scripts->queue === array(), 'Pages without forms neither enqueue nor retag Core dependencies.' );

echo json_encode( array( 'status' => 'pass', 'assertions' => $checks, 'php' => PHP_VERSION, 'wordpress' => $wp_version, 'network' => 'not bootstrapped; fixture URLs never fetched' ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
