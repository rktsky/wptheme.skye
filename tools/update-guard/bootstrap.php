<?php
/** Bootstrap the independent update guard only from explicit local MU configuration. */

defined( 'ABSPATH' ) || exit;

if ( defined( 'SKYE_UPDATE_GUARD_PLUGINS' ) && defined( 'SKYE_UPDATE_GUARD_PROFILE' ) ) {
	require_once __DIR__ . '/lib/Guard.php';
	new \Rocketsky\Skye\UpdateGuard\Guard( SKYE_UPDATE_GUARD_PLUGINS, SKYE_UPDATE_GUARD_PROFILE );
}
