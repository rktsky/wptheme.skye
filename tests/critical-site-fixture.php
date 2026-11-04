<?php
/** Admit one controlled loopback-only first viewport for positive browser delivery tests. */

if ( ! defined( 'ABSPATH' ) || ! str_contains( ABSPATH, '/.git/runtime/' ) || wp_get_environment_type() !== 'local' ||
	! in_array( $_SERVER['REMOTE_ADDR'] ?? '', array( '127.0.0.1', '::1' ), true ) ||
	! in_array( $_GET['skye_critical_browser'] ?? '', array( 'positive', 'late-optout' ), true ) ) {
	return;
}
add_filter( 'skye_critical_layout', 'skye_critical_browser_layout', 10, 2 );
add_filter( 'skye_icon_subset_complete', '__return_true' );
add_filter( 'body_class', 'skye_critical_browser_body' );
add_filter( 'the_content', '__return_empty_string' );
add_action( 'wp_enqueue_scripts', 'skye_critical_browser_viewport', 998 );
add_action( 'wp_enqueue_scripts', 'skye_critical_browser_late_optout', PHP_INT_MAX );

/**
 * Return only this synthetic viewport's exact native build contract.
 *
 * This fixture has no arbitrary content; its footer starts below the viewport.
 * It is not an eligibility rule for production pages or saved customer content.
 *
 * @since 3.0.0
 * @param mixed $previous Previous layout contract.
 * @param array $build Actual deployed build descriptor.
 * @return array Matching controlled first-viewport contract.
 */
function skye_critical_browser_layout( $previous, $build ) {
	return array( 'id' => $build['id'], 'build_hash' => $build['build_hash'], 'first_viewport' => true );
}

/**
 * Mark this disposable page without altering the theme's actual templates.
 *
 * @since 3.0.0
 * @param array $classes Native body classes.
 * @return array Classes including the controlled test marker.
 */
function skye_critical_browser_body( $classes ) {
	$classes[] = 'skye-critical-browser';
	return $classes;
}

/**
 * Keep arbitrary footer content below the controlled first viewport.
 *
 * Native inline style preservation is itself part of the admitted test contract.
 * A separate request verifies revocation before native style printing.
 *
 * @since 3.0.0
 * @return void
 */
function skye_critical_browser_viewport() {
	wp_add_inline_style( 'skye/css', 'body.skye-critical-browser main { min-height: 100vh; }' );
}

/**
 * Revoke the already selected optimization before Core prints native styles.
 *
 * @since 3.0.0
 * @return void
 */
function skye_critical_browser_late_optout() {
	if ( ( $_GET['skye_critical_browser'] ?? '' ) === 'late-optout' ) {
		add_filter( 'skye_progressive_styles', '__return_false' );
	}
}
