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

// define constants of theme
// theme text domain, override it here and also in style.css
define( 'THEME_TEXT_DOMAIN', 'wptheme.sage' );

$sage_includes = [
  'lib/assets.php',    // Scripts and stylesheets
  'lib/extras.php',    // Custom functions
  'lib/setup.php',     // Theme setup
  'lib/titles.php',    // Page titles
  'lib/wrapper.php',   // Theme wrapper class
  'lib/customizer.php', // Theme customizer
  'lib/wp_bootstrap_navwalker.php', // Boostrap Navigation
  'lib/cubetech/redirectchild.php', // cubetech Template - Redirect to Child Page
  'lib/cubetech/image.php', // cubetech Image Function
  'lib/cubetech/image-sizes.php', // cubetech Register Image Sizes
  'lib/cubetech/localize.php', // cubetech JS Localize
  'lib/cubetech/disable_emoijs.php',
  'lib/svg/scalable-vector-graphics.php',
  'lib/svg/scalable-vector-graphics.php', // cubetech SVG handling
  'lib/cubetech/acf/options.php' // cubetech Theme Options
];

foreach ($sage_includes as $file) {
  if (!$filepath = locate_template($file)) {
    trigger_error(sprintf(__('Error locating %s for inclusion', 'sage'), $file), E_USER_ERROR);
  }

  require_once $filepath;
}
unset($file, $filepath);
