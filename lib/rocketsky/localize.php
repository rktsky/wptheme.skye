<?php
/** Expose optional presentation configuration only after the real theme handle exists. */

namespace Rocketsky\Skye\Localize;

/**
 * Attach explicitly requested script data without a default unused AJAX payload.
 *
 * The legacy entry point remains callable; no nonce, credential or arbitrary
 * application object is automatically copied to the browser.
 *
 * @since 2025-06-16
 * @return void
 */
function ct_localize_scripts() {
	$data = apply_filters( 'skye_script_data', array() );
	if ( is_array( $data ) && $data && wp_script_is( 'skye/js', 'registered' ) ) {
		wp_localize_script( 'skye/js', 'skyeTheme', $data );
	}
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\ct_localize_scripts', 110 );
