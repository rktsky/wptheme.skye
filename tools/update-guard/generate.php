<?php
/** Generate exact original-file profiles without executing or changing provider source. */

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit;
}

require_once __DIR__ . '/lib/Profile.php';

use Rocketsky\Skye\UpdateGuard\Profile;

$options = getopt( '', array( 'integration-root:', 'integration-file:', 'template:', 'stylesheet:' ), $first );
$arguments = array_slice( $argv, $first );
// Explicit integration evidence prevents an original provider alone becoming an approval.
if ( ! isset( $options['integration-root'], $options['integration-file'], $options['template'], $options['stylesheet'] ) || ! Profile::theme( $options['template'] ) || ! Profile::theme( $options['stylesheet'] ) || count( $arguments ) < 3 || count( $arguments ) % 3 !== 0 ) {
	fwrite( STDERR, "Usage: php generate.php --template=parent --stylesheet=child --integration-root=/site/wp-content --integration-file=themes/project/own.php [--integration-file=...] plugin/main.php exact-version original-directory [...]\n" );
	exit( 2 );
}

try {
	$plugins = array();
	$prerequisites = Profile::prerequisites( $options['integration-root'], (array) $options['integration-file'] );
	for ( $index = 0; $index < count( $arguments ); $index += 3 ) {
		$plugin = $arguments[$index];
		$version = $arguments[$index + 1];
		if ( ! Profile::plugin( $plugin ) || isset( $plugins[$plugin][$version] ) || strlen( $version ) > 64 || preg_match( '~\A[0-9]+(?:\.[0-9]+){1,3}(?:[-.][a-zA-Z0-9]+)*\z~D', $version ) !== 1 ) {
			throw new RuntimeException( 'Invalid or duplicate exact plugin/version identity.' );
		}
		$files = Profile::inventory( $arguments[$index + 2] );
		if ( ! isset( $files[basename( $plugin )] ) ) {
			throw new RuntimeException( 'Original package is missing its configured plugin entry point.' );
		}
		$plugins[$plugin][$version] = array( 'files' => $files, 'prerequisites' => $prerequisites, 'theme' => array( 'template' => $options['template'], 'stylesheet' => $options['stylesheet'] ) );
	}
	ksort( $plugins, SORT_STRING );
	echo json_encode( array( 'schema' => 1, 'plugins' => $plugins ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n";
} catch ( Throwable $error ) {
	fwrite( STDERR, "Original profile generation failed; source paths/content were not logged.\n" );
	exit( 1 );
}
