<?php

namespace Roots\Sage\Cubetech;

/**
 * Localize dat script
 * You can add own domains and values
 */
function ct_localize_scripts() {
	wp_localize_script( 'sage/js', 'projectsAjax', array(
		'url' => admin_url( 'admin-ajax.php' )
	));
}

// Execute it after script is registered
add_action('wp_loaded', __NAMESPACE__ . '\\ct_localize_scripts')

?>