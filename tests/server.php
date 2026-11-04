<?php
/** Serve the disposable local WordPress fixture while denying development artifacts. */

if ( 'cli-server' !== PHP_SAPI || ! in_array( $_SERVER['REMOTE_ADDR'] ?? '', array( '127.0.0.1', '::1' ), true ) ) {
	http_response_code( 403 );
	exit;
}
$root = realpath( getenv( 'SKYE_SITE_ROOT' ) ?: '' );
if ( ! $root || ! str_contains( $root, '/.git/runtime/' ) ) {
	http_response_code( 500 );
	exit;
}
$path = rawurldecode( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) ?: '/' );
foreach ( explode( '/', $path ) as $part ) {
	if ( str_starts_with( $part, '.' ) || in_array( strtolower( $part ), array( 'tests', 'tools', 'node_modules', 'wp-config.php' ), true ) ) {
		http_response_code( 404 );
		exit;
	}
}
$file = realpath( $root . $path );
if ( $file && is_file( $file ) ) {
	return false;
}
require $root . '/index.php';
