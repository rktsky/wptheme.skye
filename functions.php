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

	'lib/cubetech/acf/options.php', // cubetech Theme Options
	'lib/cubetech/disable_emoijs.php',
	'lib/cubetech/gravityforms.php', // cubetech gravityforms functions
	'lib/cubetech/helpers.php', // cubetech helper functions
	'lib/cubetech/hooks.php', // cubetech hooks	
	'lib/cubetech/localize.php', // cubetech JS Localize
	'lib/cubetech/other.php', // cubetech other functions
	'lib/cubetech/redirectchild.php', // cubetech Template - Redirect to Child Page
	'lib/cubetech/rights.php', // cubetech Rights function

	'lib/svg/scalable-vector-graphics.php', // cubetech SVG handling
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

function ct_base() {
	$template = 'base.php';
	return locate_template( $template );
}
add_filter( 'template_include', 'ct_base', 109 );
