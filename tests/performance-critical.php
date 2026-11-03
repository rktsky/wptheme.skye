<?php
/** Verify actual progressive/native-style contracts, refusal and fallback with real Core. */

if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	exit;
}

require __DIR__ . '/performance-fixture.php';
use Rocketsky\Skye\Assets;
use Rocketsky\Skye\Packages\CriticalStyles;

/**
 * Describe a verified synthetic first viewport; this does not claim real page coverage.
 *
 * @since 3.0.0
 * @param mixed $previous Existing project contract.
 * @param array $build Actual immutable build descriptor.
 * @return array Exact controlled test contract.
 */
function perf_layout( $previous, $build ) {
	return array( 'id' => $build['id'], 'build_hash' => $build['build_hash'], 'first_viewport' => true );
}

/**
 * Create actual native styles with the same production descriptors and optional metadata.
 *
 * @since 3.0.0
 * @param array $extra Existing foreign metadata to test conservative refusal.
 * @return array Original native registrations indexed by handle.
 */
function perf_critical_graph( $extra = array() ) {
	$GLOBALS['wp_styles'] = new WP_Styles();
	$GLOBALS['wp_scripts'] = new WP_Scripts();
	wp_styles()->base_url = 'https://example.test';
	wp_scripts()->base_url = 'https://example.test';
	$originals = array();
	foreach ( Assets\stylesheet_variants() as $handle => $style ) {
		wp_enqueue_style( $handle, Assets\asset_uri( $style['file'] ), array(), null, $style['media'] );
		foreach ( $extra as $name => $value ) {
			wp_styles()->registered[$handle]->extra[$name] = $value;
		}
		$originals[$handle] = clone wp_styles()->registered[$handle];
	}
	return $originals;
}

/**
 * Capture native dependency output instead of maintaining a test stylesheet serializer.
 *
 * @since 3.0.0
 * @return string Actual Core-generated HTML after production filters.
 */
function perf_style_output() {
	ob_start();
	wp_styles()->do_items();
	return ob_get_clean();
}

/**
 * Detach one tested observer so subsequent request fixtures cannot share instance state.
 *
 * @since 3.0.0
 * @param CriticalStyles $observer Completed fixture observer.
 * @return void
 */
function perf_detach( $observer ) {
	foreach ( array(
		array( 'gform_enqueue_scripts', 'observe_form', PHP_INT_MAX ), array( 'gform_post_render_script', 'form_script', 20 ),
		array( 'wp_enqueue_scripts', 'enqueue', 999 ), array( 'wp_print_styles', 'validate_delivery', 0 ),
		array( 'wp_preload_resources', 'defer_font_preloads', PHP_INT_MAX ), array( 'wp_body_open', 'render_font_preloads', 0 ),
		array( 'style_loader_tag', 'style_tag', 100 ),
	) as $hook ) {
		remove_filter( $hook[0], array( $observer, $hook[1] ), $hook[2] );
	}
}

$originals = perf_critical_graph();
$critical = new CriticalStyles();
$critical->enqueue();
perf_check( ! wp_script_is( 'skye/css-loader', 'enqueued' ), 'A generic first viewport never silently opts into partial critical coverage.' );
perf_check( wp_styles()->registered['skye/css']->src === $originals['skye/css']->src, 'Ordinary full stylesheet retains its source.' );
perf_detach( $critical );
add_filter( 'skye_icon_subset_complete', '__return_true' );
add_filter( 'skye_critical_layout', 'perf_layout', 10, 2 );

$originals = perf_critical_graph();
$critical = new CriticalStyles();
$critical->enqueue();
perf_check( wp_script_is( 'skye/css-loader', 'enqueued' ), 'A matching explicit native contract installs the bounded real loader.' );
perf_check( wp_styles()->registered['skye/css-mobile']->src === false, 'The mobile handle aliases the complete selected stylesheet.' );
perf_check( wp_styles()->registered['skye/css-mobile']->deps === array( 'skye/css' ), 'The alias preserves native dependency resolution with one download.' );
$foreign_tag = '<link rel="stylesheet" id="skye-css-css" href="https://example.test/own.css" media="all" integrity="foreign" />';
perf_check( $critical->style_tag( $foreign_tag, 'skye/css', 'https://example.test/own.css', 'all' ) === $foreign_tag, 'Foreign tag-level delivery attributes retain ordinary blocking semantics.' );
$html = perf_style_output();
$parser = new WP_HTML_Tag_Processor( $html );
$preloads = $fallbacks = 0;
while ( $parser->next_tag( 'LINK' ) ) {
	if ( $parser->get_attribute( 'rel' ) === 'preload' ) {
		$preloads++;
	}
	if ( $parser->get_attribute( 'rel' ) === 'stylesheet' ) {
		$fallbacks++;
	}
}
perf_check( $preloads === 1 && $fallbacks === 1 && str_contains( $html, '<noscript>' ), 'One complete preload has a true native no-JavaScript stylesheet peer.' );
perf_check( str_contains( $html, 'skye/css-critical-inline-css' ), 'The actual critical bytes precede the complete stylesheet dependency.' );
perf_check( substr_count( $html, Assets\asset_uri( 'styles/main-mobile.css' ) ) === 0, 'The promoted critical path never requests both complete viewport peers.' );
perf_detach( $critical );

perf_critical_graph();
foreach ( array( 'gform_basic', 'gform_theme_components', 'gform_theme' ) as $handle ) {
	wp_enqueue_style( $handle, 'https://example.test/' . $handle . '.css', array(), null );
}
$critical = new CriticalStyles();
$form = array( 'id' => 3, 'fields' => array( new GF_Field_Text() ) );
$critical->observe_form( $form, false );
$critical->enqueue();
$body = 'window.syntheticNativeInitializer = current_page;';
$wrapped = $critical->form_script( $body, 3, 1 );
perf_check( $wrapped !== $body && substr_count( $wrapped, $body ) === 1 && ! str_contains( $wrapped, '/*__SKYE_NATIVE_FORM_SCRIPT__*/' ), 'A known native form body is embedded exactly once in the tested readiness gate.' );
perf_check( $critical->form_script( $body, 99, 1 ) === $body, 'Unobserved form IDs retain the original native initializer.' );
$unsupported = array( 'id' => 4, 'fields' => array( new GF_Field_Text() ), 'enableAnimation' => true );
$critical->observe_form( $unsupported, false );
perf_check( $critical->form_script( $body, 3, 1 ) === $body, 'A later richer form revokes the unprinted request opt-in.' );
perf_detach( $critical );

$originals = perf_critical_graph();
$critical = new CriticalStyles();
$critical->enqueue();
add_filter( 'skye_progressive_styles' , '__return_false' );
$critical->validate_delivery();
foreach ( $originals as $handle => $original ) {
	perf_check( wp_styles()->registered[$handle]->src === $original->src && wp_styles()->registered[$handle]->args === $original->args && wp_styles()->registered[$handle]->deps === $original->deps, 'A late refusal restores the exact original source/media/dependencies.' );
}
perf_check( ! wp_script_is( 'skye/css-loader', 'enqueued' ), 'A withdrawn unprinted optimization removes its own loader queue entry.' );
remove_filter( 'skye_progressive_styles', '__return_false' );
perf_detach( $critical );

foreach ( array( array( 'integrity' => 'foreign' ), array( 'rtl' => true ), array( 'unknown' => false ) ) as $extra ) {
	$originals = perf_critical_graph( $extra );
	$critical = new CriticalStyles();
	$critical->enqueue();
	perf_check( ! wp_script_is( 'skye/css-loader', 'enqueued' ) && wp_styles()->registered['skye/css']->extra === $extra, 'Foreign native semantics retain normal complete style output.' );
	perf_detach( $critical );
}

foreach ( array( 'authenticated', 'password', 'admin' ) as $context ) {
	$GLOBALS['perf_context'] = array( $context => true );
	perf_critical_graph();
	$critical = new CriticalStyles();
	$critical->enqueue();
	perf_check( ! wp_script_is( 'skye/css-loader', 'enqueued' ), 'Private/native screen contexts do not use the public critical path.' );
	perf_detach( $critical );
}
$GLOBALS['perf_context'] = array();
remove_filter( 'skye_critical_layout', 'perf_layout', 10 );
add_filter( 'skye_critical_layout', 'perf_wrong_layout', 10, 2 );
perf_check( ! CriticalStyles::supported_header(), 'A stale build hash cannot select an assumed matching layout.' );

remove_filter( 'skye_critical_layout', 'perf_wrong_layout', 10 );
add_filter( 'skye_critical_layout', 'perf_layout', 10, 2 );
$fixture = sys_get_temp_dir() . '/skye-font-hints-' . bin2hex( random_bytes( 5 ) );
mkdir( $fixture . '/dist', 0700, true );
$parent = $GLOBALS['perf_parent'];
try {
	$manifest = json_decode( file_get_contents( $parent . '/dist/assets.json' ), true );
	$manifest['_performance']['fonts'] = array( array( 'file' => 'fonts/fa-regular-400.woff2' ) );
	foreach ( array( 'main-critical.css', 'critical-styles.js', 'critical-form-gate.js' ) as $key ) {
		$relative = $manifest[$key];
		if ( ! is_dir( dirname( $fixture . '/dist/' . $relative ) ) ) {
			mkdir( dirname( $fixture . '/dist/' . $relative ), 0700, true );
		}
		copy( $parent . '/dist/' . $relative, $fixture . '/dist/' . $relative );
	}
	file_put_contents( $fixture . '/dist/assets.json', json_encode( $manifest ) );
	$GLOBALS['perf_parent'] = $fixture;
	perf_critical_graph();
	$critical = new CriticalStyles();
	$critical->enqueue();
	$fonts = Assets\font_preloads();
	perf_check( count( $fonts ) === 1, 'Configured font count is not tied to a branded fixed inventory.' );
	$foreign = $fonts[0];
	$foreign['integrity'] = 'foreign';
	perf_check( $critical->defer_font_preloads( array( $fonts[0], $foreign ) ) === array( $fonts[0], $foreign ), 'Same-URL foreign attributes prevent owned font relocation.' );
	$head = $critical->defer_font_preloads( $fonts );
	perf_check( $head === array(), 'An exact owned hint is prepared at the native body boundary.' );
	ob_start();
	$critical->render_font_preloads();
	$critical->render_font_preloads();
	$body = ob_get_clean();
	$parser = new WP_HTML_Tag_Processor( $body );
	$count = 0;
	while ( $parser->next_tag( 'LINK' ) ) {
		$count++;
		perf_check( $parser->get_attribute( 'as' ) === 'font' && $parser->get_attribute( 'crossorigin' ) === 'anonymous', 'Body serialization preserves native font and CORS metadata.' );
	}
	perf_check( $count === 1, 'Repeated body actions emit the exact font hint once.' );
	perf_detach( $critical );
} finally {
	$GLOBALS['perf_parent'] = $parent;
	perf_remove( $fixture );
}

echo json_encode( array( 'critical_assertions' => $GLOBALS['perf_checks'], 'result' => 'pass', 'wordpress' => $wp_version ) ) . "\n";

/**
 * Return an intentionally stale descriptor to test the real production refusal.
 *
 * @since 3.0.0
 * @param mixed $previous Original project response.
 * @param array $build Current descriptor.
 * @return array Controlled incorrect contract.
 */
function perf_wrong_layout( $previous, $build ) {
	return array( 'id' => $build['id'], 'build_hash' => 'stale', 'first_viewport' => true );
}

/** Provide only the effective native provider theme boundary for the production guard. @since 3.0.0 */
class GFFormDisplay {
	/** @var array Existing provider initialization records; none in this fixture. */
	public static $init_scripts = array();
	/**
	 * Return the known native theme without constructing or fetching a form.
	 * @since 3.0.0
	 * @param array $form Supplied native enqueue object.
	 * @return string Controlled effective theme.
	 */
	public static function get_form_theme_slug( $form ) {
		return 'gravity-theme';
	}
}
/** Supply a column-layout boundary without calling plugin internals or storage. @since 3.0.0 */
class GFCommon {
	/**
	 * Return the controlled native fill layout used by the production eligibility check.
	 * @since 3.0.0
	 * @param array $form Existing form configuration.
	 * @return string Effective native column mode.
	 */
	public static function get_form_column_layout( $form ) {
		return 'fill';
	}
}
/** Represent a known built-in field shape without rendering or storing an entry. @since 3.0.0 */
class GF_Field_Text {
	/** @var string Native built-in type checked by the production adapter. */
	public $type = 'text';
	/** @var string Native built-in width policy checked before layout-sensitive work. */
	public $size = 'large';
}
