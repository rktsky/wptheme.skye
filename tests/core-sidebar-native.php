<?php
/** Verify the actual WordPress footer widget renderer without persisting widget or site settings. */
if ( 'cli' !== PHP_SAPI ) {
	exit;
}
$site = realpath( getenv( 'SKYE_SITE_ROOT' ) ?: '' );
if ( ! $site || ! is_file( $site . '/wp-load.php' ) || ! str_contains( $site, '/.git/runtime/' ) ) {
	throw new RuntimeException( 'SKYE_SITE_ROOT must select an isolated .git/runtime WordPress installation.' );
}
require $site . '/wp-load.php';
$checks = 0;
$skye_widget_calls = 0;
$skye_widget_active = true;

/**
 * Assert actual native output rather than a duplicate widget renderer.
 *
 * @since 3.0.0
 * @param bool $condition Observed native behavior.
 * @param string $message Required rendering contract.
 * @return void
 * @throws RuntimeException On a failed native rendering contract.
 */
function skye_native_sidebar_check( $condition, $message ) {
	$GLOBALS['checks']++;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Provide a local widget body while Core owns its registered sidebar wrappers.
 *
 * @since 3.0.0
 * @param array $args Actual native sidebar rendering arguments.
 * @return void
 */
function skye_native_sidebar_widget( $args ) {
	$GLOBALS['skye_widget_calls']++;
	echo $args['before_widget'];
	echo esc_html( 'NATIVE_FOOTER_WIDGET_MARKER' );
	echo $args['after_widget'];
}

/**
 * Select the test widget only in this process instead of writing sidebar options.
 *
 * @since 3.0.0
 * @param array $sidebars Native sidebar-to-widget map.
 * @return array Original map with the isolated footer test selection.
 */
function skye_native_sidebar_selection( $sidebars ) {
	$sidebars['sidebar-footer'] = $GLOBALS['skye_widget_active'] ? array( 'skye-native-fixture' ) : array();
	return $sidebars;
}

wp_register_sidebar_widget( 'skye-native-fixture', 'Skye isolated widget', 'skye_native_sidebar_widget', array( 'classname' => 'skye-native-fixture' ) );
add_filter( 'sidebars_widgets', 'skye_native_sidebar_selection' );
try {
	skye_native_sidebar_check( is_registered_sidebar( 'sidebar-footer' ), 'The actual theme registers the footer sidebar.' );
	$level = ob_get_level();
	$html = skye_footer_sidebar_html();
	skye_native_sidebar_check( str_contains( $html, 'NATIVE_FOOTER_WIDGET_MARKER' ) && str_contains( $html, '<section' ) && str_contains( $html, '</section>' ), 'The original native widget renderer supplies the registered wrapper and body.' );
	skye_native_sidebar_check( 1 === $skye_widget_calls && $level === ob_get_level(), 'Native widget renders once and the caller buffer depth remains stable.' );
	$brand = skye_brand_model();
	$footer = skye_component_html( 'footer', false, array( 'brand' => $brand, 'sidebar_html' => $html ) );
	skye_native_sidebar_check( str_contains( $footer, 'NATIVE_FOOTER_WIDGET_MARKER' ) && str_contains( $footer, 'footer-widgets' ), 'The actual footer template receives the ready native markup.' );
	$skye_widget_active = false;
	$empty = skye_footer_sidebar_html();
	skye_native_sidebar_check( '' === $empty && 1 === $skye_widget_calls, 'Empty footer does not call the actual widget renderer.' );
	$empty_footer = skye_component_html( 'footer', false, array( 'brand' => $brand, 'sidebar_html' => $empty ) );
	skye_native_sidebar_check( ! str_contains( $empty_footer, 'footer-widgets' ), 'The actual empty footer has no orphan wrapper.' );
	echo wp_json_encode( array( 'status' => 'pass', 'assertions' => $checks, 'wordpress' => $wp_version, 'php' => PHP_VERSION, 'persistent_widget_writes' => false ) ) . "\n";
} finally {
	remove_filter( 'sidebars_widgets', 'skye_native_sidebar_selection' );
	wp_unregister_sidebar_widget( 'skye-native-fixture' );
}
