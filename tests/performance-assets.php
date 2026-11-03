<?php
/** Verify actual asset path/manifest, icon and early-form contracts against native Core. */
require __DIR__ . '/performance-fixture.php';
use Rocketsky\Skye\Assets;
use Rocketsky\Skye\Packages\Icons;
use Rocketsky\Skye\Packages\Performance;

$fixture = sys_get_temp_dir() . '/skye-assets-' . bin2hex( random_bytes( 5 ) );
mkdir( $fixture, 0700 );
try {
	foreach ( array( '', '../secret', '/absolute', 'a/../secret', "a\0b", 'https://other.test/x', 'a//b', 'back\\slash' ) as $bad ) {
		perf_check( Assets\relative_path( $bad ) === '', 'Reject ambiguous or external asset path.' );
	}
	perf_check( Assets\relative_path( '.own-font.woff2' ) === '.own-font.woff2', 'A safe leading dot is not silently renamed.' );
	foreach ( array( '{bad', 'null', '42', '"scalar"', '[]' ) as $json ) {
		file_put_contents( $fixture . '/manifest.json', $json );
		$manifest = new Assets\JsonManifest( $fixture . '/manifest.json' );
		perf_check( $manifest->get() === array(), 'Malformed/non-object manifests become an empty safe snapshot.' );
	}
	file_put_contents( $fixture . '/manifest.json', '{"false":false,"zero":0,"nested":{"name":"ok"}}' );
	$manifest = new Assets\JsonManifest( $fixture . '/manifest.json' );
	perf_check( $manifest->getPath( 'false', 'wrong' ) === false && $manifest->getPath( 'zero', 'wrong' ) === 0, 'Manifest lookup preserves explicit false/zero.' );
	perf_check( $manifest->getPath( 'nested.name' ) === 'ok' && $manifest->getPath( 'nested.no', 'fallback' ) === 'fallback', 'Nested lookup is bounded and deterministic.' );
	$GLOBALS['perf_child'] = $fixture . '/child';
	mkdir( $GLOBALS['perf_child'] . '/dist', 0700, true );
	file_put_contents( $GLOBALS['perf_child'] . '/dist/assets.json', '{"main.css":"styles/child-hash.css"}' );
	perf_check( Assets\asset_uri( 'styles/main.css' ) === 'https://example.test/child/dist/styles/child-hash.css', 'A complete child manifest owns both URI and path.' );
	perf_check( Assets\dist_path( 'styles/main.css' ) === $GLOBALS['perf_child'] . '/dist/styles/child-hash.css', 'Child physical output follows the same manifest.' );
	$GLOBALS['perf_child'] = $fixture . '/glyphs';
	mkdir( $GLOBALS['perf_child'] . '/dist', 0700, true );
	file_put_contents( $GLOBALS['perf_child'] . '/dist/assets.json', json_encode( array( '_performance' => array( 'version' => 1, 'icons' => array( 'solid/safe' => array( 'view_box' => '0 0 16 16', 'paths' => array( array( 'd' => 'M0 0h16v16H0z' ) ) ) ) ) ) ) );
	$svg = Icons::render( 'solid/safe', array( 'class' => 'test" onload="not-executed' ) );
	$parser = new WP_HTML_Tag_Processor( $svg );
	perf_check( $parser->next_tag( 'SVG' ) && $parser->get_attribute( 'viewBox' ) === '0 0 16 16' && $parser->get_attribute( 'aria-hidden' ) === 'true' && $parser->get_attribute( 'onload' ) === null, 'A valid trusted geometry record uses escaped decorative SVG markup.' );
	perf_check( $parser->next_tag( 'PATH' ) && $parser->get_attribute( 'd' ) === 'M0 0h16v16H0z', 'The renderer preserves the validated provider path without accepting arbitrary nodes.' );
	$GLOBALS['perf_child'] = $fixture . '/malformed';
	mkdir( $GLOBALS['perf_child'] . '/dist', 0700, true );
	file_put_contents( $GLOBALS['perf_child'] . '/dist/assets.json', '{"_performance":{"version":1,"fonts":"bad"}}' );
	perf_check( Assets\font_preloads() === array(), 'Malformed font inventory fails closed without a PHP warning.' );
	$GLOBALS['perf_child'] = $GLOBALS['perf_parent'];
	$canonical = Assets\build_root()['uri'] . 'styles/example.css';
	add_filter( 'skye_public_asset_url', '__return_false' );
	perf_check( Assets\public_url( $canonical ) === $canonical, 'Invalid delivery output preserves canonical URL.' );
	remove_filter( 'skye_public_asset_url', '__return_false' );
	perf_check( Icons::needs_styles(), 'Unknown icon inventories retain complete native provider CSS.' );
	perf_check( Icons::render( 'solid/../../secret' ) === '', 'Untrusted glyph names never select files or markup.' );
	$GLOBALS['wp_styles'] = new WP_Styles();
	wp_register_style( 'skye/fontawesome', '/provider.css', array(), null );
	$html = Icons::render( 'solid/not-in-subset', array( 'class' => 'x" onload="not-executed' ) );
	perf_check( wp_style_is( 'skye/fontawesome', 'enqueued' ), 'A missing subset entry restores the native full provider.' );
	$parser = new WP_HTML_Tag_Processor( $html );
	perf_check( $parser->next_tag( 'I' ) && $parser->get_attribute( 'onload' ) === null, 'Fallback classes remain escaped attributes.' );
	$controls = new \Rocketsky\Skye\Packages\FormControls();
	$button = '<input type="submit" name="submit" value="Keep native" class="search-submit">';
	$search_button = $controls->search( $button );
	$parser = new WP_HTML_Tag_Processor( $search_button );
	perf_check( $parser->next_tag( 'INPUT' ) && $parser->has_class( 'btn-solid' ) && $parser->has_class( 'theme-primary' ) && $parser->get_attribute( 'name' ) === 'submit' && $parser->get_attribute( 'value' ) === 'Keep native', 'The search submitter receives native Bootstrap 6 classes without changing submission data.' );
	$comment_button = apply_filters( 'comment_form_submit_button', $button );
	perf_check( $controls->submit_button( $comment_button ) === $comment_button, 'Native comment/provider button class adaptation is byte-idempotent.' );
	$performance = new Performance();
	add_filter( 'skye_form_assets', 'perf_forms' );
	$performance->enqueue_form_assets();
	perf_check( $GLOBALS['perf_forms'] === array( array( 3, false ), array( 3, true ) ), 'Known valid form descriptions deduplicate by actual ID/AJAX contract.' );
	$GLOBALS['perf_context']['admin'] = true;
	$performance->enqueue_form_assets();
	perf_check( count( $GLOBALS['perf_forms'] ) === 2, 'Admin requests never auto-enqueue frontend declarations.' );
	$GLOBALS['perf_context'] = array();
	perf_check( Assets\font_preloads() === array(), 'System font defaults add no invented font hints.' );
	require dirname( __DIR__ ) . '/lib/setup.php';
	add_filter( 'skye_preload_resources', 'perf_responsive_hints' );
	ob_start();
	wp_preload_resources();
	$preload_html = ob_get_clean();
	$tags = new WP_HTML_Tag_Processor( $preload_html );
	perf_check( $tags->next_tag( 'LINK' ) && $tags->get_attribute( 'imagesrcset' ) === 'https://example.test/small.webp 400w, https://example.test/large.webp 800w' && $tags->get_attribute( 'href' ) === null, 'Actual Core serialization retains responsive candidates without a redundant fallback download.' );
	perf_check( $tags->get_attribute( 'imagesizes' ) === '100vw' && $tags->get_attribute( 'fetchpriority' ) === 'high', 'Native preload sizes and selected priority survive unchanged.' );
	remove_filter( 'skye_preload_resources', 'perf_responsive_hints' );
	add_filter( 'skye_preload_resources', '__return_false' );
	perf_check( \Rocketsky\Skye\Setup\preload_resources( array() ) === array(), 'Malformed provider output cannot make array_merge fatal.' );
	remove_filter( 'skye_preload_resources', '__return_false' );
	add_filter( 'skye_preload_resources', 'perf_bad_hints' );
	perf_check( \Rocketsky\Skye\Setup\preload_resources( array() ) === array(), 'Invalid responsive arrays never reach Core URL deduplication.' );
	echo json_encode( array( 'asset_assertions' => $GLOBALS['perf_checks'], 'result' => 'pass', 'wordpress' => $wp_version ) ) . "\n";
} finally {
	perf_remove( $fixture );
}

/**
 * Describe synthetic valid, repeated and malformed forms without provider storage.
 *
 * @since 3.0.0
 * @return array Small public view-model descriptions.
 */
function perf_forms() {
	return array( array( 'id' => 3 ), array( 'id' => '3' ), array( 'id' => 3, 'ajax' => true ), array( 'id' => '../3' ), array( 'id' => array() ), array( 'id' => '999999999999999999999999999999' ), array( 'id' => 7, 'ajax' => 'yes' ) );
}
/**
 * Observe native provider transport without rendering, submitting or delivering data.
 *
 * @since 3.0.0
 * @param int $id Known native form ID.
 * @param bool $ajax Effective rendering mode.
 * @return void
 */
function gravity_form_enqueue_scripts( $id, $ajax ) {
	$GLOBALS['perf_forms'][] = array( $id, $ajax );
}

/**
 * Supply known responsive candidates without guessing a global hero or format.
 *
 * @since 3.0.0
 * @return array Native image preload records for the controlled format.
 */
function perf_responsive_hints() {
	return array( array( 'as' => 'image', 'href' => 'https://example.test/fallback.webp', 'imagesrcset' => 'https://example.test/small.webp 400w, https://example.test/large.webp 800w', 'imagesizes' => '100vw', 'fetchpriority' => 'high' ) );
}

/**
 * Supply invalid provider shapes to test production refusal rather than native warnings.
 *
 * @since 3.0.0
 * @return array Malformed responsive candidates.
 */
function perf_bad_hints() {
	return array( array( 'as' => 'image', 'imagesrcset' => array( 'invalid' ) ), array( 'as' => 'font', 'href' => array() ) );
}
