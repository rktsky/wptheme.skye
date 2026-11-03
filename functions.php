<?php
/** Load Skye's owned libraries and packages while retaining child-theme overrides. */

$skye_includes = array(
	'lib/assets.php',
	'lib/rocketsky/helpers.php',
	'lib/extras.php',
	'lib/titles.php',
	'lib/setup.php',
	'lib/routing.php',
	'lib/rocketsky/acf/options.php',
	'lib/rocketsky/disable_emoijs.php',
	'lib/rocketsky/gravityforms.php',
	'lib/rocketsky/localize.php',
	'lib/rocketsky/other.php',
	'lib/rocketsky/redirectchild.php',
	'lib/rocketsky/rights.php',
	'lib/package/init.php',
);
foreach ( $skye_includes as $skye_file ) {
	$skye_path = locate_template( $skye_file, false, false );
	if ( ! $skye_path ) {
		throw new RuntimeException( 'Missing Skye library: ' . $skye_file );
	}
	require_once $skye_path;
}
unset( $skye_file, $skye_path );
