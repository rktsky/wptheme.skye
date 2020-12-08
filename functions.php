<?php
/**
 * Sage includes
 *
 * The $sage_includes array determines the code library included in your theme.
 * Add or remove files to the array as needed. Supports child theme overrides.
 *
 * Please note that missing files will produce a fatal error.
 *
 * @link https://github.com/roots/sage/pull/1042
 */

$sage_includes = [

	'lib/assets.php',		// Scripts and stylesheets
	'lib/extras.php',		// Custom functions
	'lib/setup.php',		 // Theme setup
	'lib/titles.php',		// Page titles
	'lib/wrapper.php',	 // Theme wrapper class
	'lib/customizer.php', // Theme customizer
	'lib/walker/class-wp-bootstrap-navwalker.php', // Boostrap Navigation
	'lib/walker/megadropdown_walker.php', // Megadropdown Navigation

	'lib/cubetech/acf/options.php', // cubetech Theme Options
	'lib/cubetech/disable_emoijs.php',
	'lib/cubetech/gravityforms.php', // cubetech gravityforms functions
	'lib/cubetech/helpers.php', // cubetech helper functions
	'lib/cubetech/hooks.php', // cubetech hooks	
	'lib/cubetech/localize.php', // cubetech JS Localize
	'lib/cubetech/other.php', // cubetech other functions
	'lib/cubetech/redirectchild.php', // cubetech Template - Redirect to Child Page

	'lib/svg/scalable-vector-graphics.php', // cubetech SVG handling
	'lib/package/init.php' // Loading packages

];

foreach ($sage_includes as $file) {
	if (!$filepath = locate_template($file)) {
		trigger_error(sprintf(__('Error locating %s for inclusion', 'sage'), $file), E_USER_ERROR);
	}

	require_once $filepath;
}
unset($file, $filepath);
