<?php
/** Regress the real static checker against allowed contracts and forbidden source fixtures. */

if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	exit;
}

/**
 * Run the production checker in a subprocess without executing fixture PHP.
 *
 * Parsed code may deliberately contain forbidden writes, but no site is loaded
 * and those statements are never evaluated by this architecture regression.
 *
 * @since 3.0.0
 * @param string $root Owned temporary fixture directory.
 * @return array{status:int,output:string} Actual checker result.
 */
function architecture_run( $root ) {
	$process = proc_open( array( PHP_BINARY, dirname( __DIR__ ) . '/tools/coding-standards/check.php', $root ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
	if ( ! is_resource( $process ) ) {
		throw new RuntimeException( 'Could not launch the architecture checker.' );
	}
	fclose( $pipes[0] );
	$output = stream_get_contents( $pipes[1] ) . stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	return array( 'status' => proc_close( $process ), 'output' => $output );
}

/**
 * Remove only the directory created by this isolated regression.
 *
 * @since 3.0.0
 * @param string $path Owned fixture path.
 * @return void
 */
function architecture_remove( $path ) {
	foreach ( scandir( $path ) as $name ) {
		if ( '.' === $name || '..' === $name ) {
			continue;
		}
		$child = $path . '/' . $name;
		if ( is_dir( $child ) && ! is_link( $child ) ) {
			architecture_remove( $child );
		} else {
			unlink( $child );
		}
	}
	rmdir( $path );
}

$root = sys_get_temp_dir() . '/skye-architecture-' . bin2hex( random_bytes( 8 ) );
mkdir( $root, 0700 );
mkdir( $root . '/components' );
mkdir( $root . '/lib' );
$checks = 0;
try {
	$good = <<<'PHP'
<?php
/** Prepare a scalar fixture without adding business logic to a template. */
/**
 * Preserve a value so the checker sees a complete ordinary function contract.
 *
 * @since 3.0.0
 * @param string $value Local scalar fixture.
 * @return string Unchanged value.
 */
function example( $value ) {
	return $value;
}
PHP;
	file_put_contents( $root . '/lib/model.php', $good );
	$result = architecture_run( $root );
	if ( 0 !== $result['status'] ) {
		throw new RuntimeException( 'Valid source was rejected: ' . $result['output'] );
	}
	$checks++;
	$cases = array(
		array( $good, 'declarations belong in lib/' ),
		array( '<?php /** Display fixture. */ update_post_meta( 1, "field", "value" );', 'update_post_meta belongs in lib/' ),
		array( '<?php /** Display fixture. */ get_component( "card", false, array( "template" => "foreign" ) );', 'reserved component context key template' ),
		array( '<?php echo "<div>Raw markup</div>";', 'file purpose header is required' ),
		array( '<?php /** Display fixture. */ ?>' . '<style>body{color:red}</style>', 'inline styling or behavior belongs in assets/' ),
	);
	foreach ( $cases as $case ) {
		file_put_contents( $root . '/components/invalid.php', $case[0] );
		$result = architecture_run( $root );
		if ( 1 !== $result['status'] || ! str_contains( $result['output'], $case[1] ) ) {
			throw new RuntimeException( 'Expected a specific source rejection: ' . $result['output'] );
		}
		$checks++;
	}
	unlink( $root . '/components/invalid.php' );
	file_put_contents( $root . '/lib/model.php', str_replace( ' * @param string $value Local scalar fixture.' . "\n", '', $good ) );
	$result = architecture_run( $root );
	if ( 1 !== $result['status'] || ! str_contains( $result['output'], 'typed @param $value is required' ) ) {
		throw new RuntimeException( 'Missing parameter documentation was not detected.' );
	}
	$checks++;
} finally {
	architecture_remove( $root );
}
echo 'Architecture regression passed (' . $checks . " cases).\n";
