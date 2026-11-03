<?php
/** Run the explicit PHP regression list in isolated processes and reject unclassified tests. */

if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	exit;
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
);
$fixtures = array( 'core-fixture.php', 'performance-fixture.php', 'critical-site-fixture.php', 'server.php', 'site-fixture.php', 'site-verify.php' );
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
		fwrite( STDERR, file_get_contents( $output ) );
	}
}
fwrite( STDOUT, json_encode( array( 'processes' => count( $cases ), 'failed' => $failed, 'logs' => $logs ), JSON_UNESCAPED_SLASHES ) . "\n" );
exit( $failed ? 1 : 0 );
