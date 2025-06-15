<?php
/**
 * Skye includes
 *
 * The $skye_includes array determines the code library included in your theme.
 * Add or remove files to the array as needed. Supports child theme overrides.
 *
 * Please note that missing files will produce a fatal error.
 */

$skye_includes = [

	'lib/assets.php',		// Scripts and stylesheets
	'lib/extras.php',		// Custom functions
	'lib/setup.php',		 // Theme setup
	'lib/titles.php',		// Page titles
	'lib/walker/class-wp-bootstrap-navwalker.php', // Boostrap Navigation

	'lib/rocketsky/acf/options.php', // rocketsky Theme Options
	'lib/rocketsky/disable_emoijs.php',
	'lib/rocketsky/gravityforms.php', // rocketsky gravityforms functions
	'lib/rocketsky/helpers.php', // rocketsky helper functions
	'lib/rocketsky/hooks.php', // rocketsky hooks	
	'lib/rocketsky/localize.php', // rocketsky JS Localize
	'lib/rocketsky/other.php', // rocketsky other functions
	'lib/rocketsky/redirectchild.php', // rocketsky Template - Redirect to Child Page
	'lib/rocketsky/rights.php', // rocketsky Rights function

	'lib/svg/scalable-vector-graphics.php', // rocketsky SVG handling
	'lib/package/init.php' // Loading packages

];

foreach ($skye_includes as $file) {
	if (!$filepath = locate_template($file)) {
		trigger_error(sprintf(__('Error locating %s for inclusion', 'skye'), $file), E_USER_ERROR);
	}
	require_once $filepath;
}
unset($file, $filepath);

if( file_exists( 'vendor/autoload.php' ) ) {
	require_once 'vendor/autoload.php';
}

function rs_base() {
	$template = 'base.php';
	return locate_template( $template );
}
add_filter( 'template_include', 'rs_base', 109 );
