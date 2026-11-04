<?php
/** Run the explicit PHP regression list in isolated processes and reject unclassified tests. */

if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	exit;
}

/**
 * Surface a failed regression's actual diagnostics as one native Actions annotation.
 *
 * Keep the complete bytes in stderr and the artifact; only this additional UI
 * message is UTF-8-normalized and bounded. Preserve both ends of long output so
 * setup context and the final exception remain visible without changing status.
 *
 * @since 3.0.0
 * @param string $name Explicit classified regression case name.
 * @param string $diagnostics Complete captured native stdout/stderr bytes.
 * @param int $code Observed subprocess exit code.
 * @return void
 */
function skye_test_failure_annotation( $name, $diagnostics, $code ) {
	if ( 'true' !== getenv( 'GITHUB_ACTIONS' ) ) {
		return;
	}
	$message = 'Exit code ' . $code . ":\n" . ( '' === $diagnostics ? 'The regression emitted no diagnostic output.' : mb_scrub( $diagnostics, 'UTF-8' ) );
	if ( strlen( $message ) > 12288 ) {
		$message = mb_strcut( $message, 0, 8192, 'UTF-8' ) . "\n[Annotation truncated; complete output remains in the job log and test artifact.]\n" . mb_strcut( $message, -4096, null, 'UTF-8' );
	}
	$escapes = array( '%' => '%25', "\r" => '%0D', "\n" => '%0A' );
	$title = strtr( $name, $escapes + array( ':' => '%3A', ',' => '%2C' ) );
	fwrite( STDOUT, '::error title=PHP regression ' . $title . '::' . strtr( $message, $escapes ) . "\n" );
}

$root = dirname( __DIR__, 2 );
$cases = array(
	array( 'architecture.php' ),
	array( 'security.php' ),
	array( 'core-test.php' ),
	array( 'core-test.php', '--without-acf' ),
	array( 'media-pipeline.php' ),
	array( 'performance-assets.php' ),
	array( 'performance-strategies.php' ),
	array( 'performance-empty-style.php' ),
	array( 'performance-form-controls.php' ),
	array( 'performance-critical.php' ),
	array( 'performance-static-delivery.php' ),
	array( 'full-css-image-set.php' ),
	array( 'environment-mail.php' ),
	array( 'mobile-navigation-native.php' ),
);
$fixtures = array( 'core-fixture.php', 'performance-fixture.php', 'critical-site-fixture.php', 'server.php', 'site-fixture.php', 'site-verify.php', 'core-sidebar-native.php' );
$known = array_unique( array_merge( array_column( $cases, 0 ), $fixtures ) );
$actual = array_map( 'basename', glob( $root . '/tests/*.php' ) );
$unknown = array_diff( $actual, $known );
if ( $unknown ) {
	throw new RuntimeException( 'Classify new PHP tests before release: ' . implode( ', ', $unknown ) );
}
$base = getenv( 'SKYE_TEST_LOG_DIR' ) ?: $root . '/.git/runtime/php-tests';
$logs = $base . '/' . getmypid() . '-' . bin2hex( random_bytes( 4 ) );
if ( ! mkdir( $logs, 0700, true ) && ! is_dir( $logs ) ) {
	throw new RuntimeException( 'Cannot create isolated test logs.' );
}
$failed = array();
foreach ( $cases as $case ) {
	$filename = $root . '/tests/' . $case[0];
	if ( ! is_file( $filename ) ) {
		throw new RuntimeException( 'Missing required regression: ' . $case[0] );
	}
	$name = pathinfo( $case[0], PATHINFO_FILENAME ) . ( $case[1] ?? '' );
	$output = $logs . '/' . $name . '.log';
	$command = array_merge( array( PHP_BINARY, $filename ), array_slice( $case, 1 ) );
	$process = proc_open( $command, array( 0 => array( 'pipe', 'r' ), 1 => array( 'file', $output, 'w' ), 2 => array( 'redirect', 1 ) ), $pipes, $root );
	if ( ! is_resource( $process ) ) {
		throw new RuntimeException( 'Cannot start regression: ' . $name );
	}
	fclose( $pipes[0] );
	$code = proc_close( $process );
	fwrite( STDOUT, $name . ': ' . ( 0 === $code ? 'PASS' : 'FAIL (' . $code . ')' ) . "\n" );
	if ( 0 !== $code ) {
		$failed[] = $name;
		$diagnostics = file_get_contents( $output );
		fwrite( STDERR, $diagnostics );
		skye_test_failure_annotation( $name, $diagnostics, $code );
	}
}
fwrite( STDOUT, json_encode( array( 'processes' => count( $cases ), 'failed' => $failed, 'logs' => $logs ), JSON_UNESCAPED_SLASHES ) . "\n" );
exit( $failed ? 1 : 0 );
