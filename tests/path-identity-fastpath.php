<?php
/** Exercise fresh native POSIX identities, bounded aliases and independent filesystem changes. */

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit;
}

require dirname( __DIR__ ) . '/lib/package/assets/PathIdentity.php';

use Rocketsky\Skye\Packages\Assets\PathIdentity;

$checks = 0;

/**
 * Assert one production-path result without replacing any filesystem operation.
 *
 * @since 3.5.0
 * @param bool $condition Actual observed invariant.
 * @param string $message Contract under examination.
 * @return void Throws when the native result violates its contract.
 */
function skye_path_check( $condition, $message ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	++$checks;
}

/**
 * Count native ancestors for a portable fixture at the actual 64-prefix boundary.
 *
 * @since 3.5.0
 * @param string $path Existing absolute fixture path.
 * @return int Number of root-inclusive prefixes.
 */
function skye_path_depth( $path ) {
	$count = 1;
	while ( dirname( $path ) !== $path ) {
		$path = dirname( $path );
		++$count;
	}
	return $count;
}

/**
 * Change an owned fixture in another process while this process's caches stay warm.
 *
 * Native rename/unlink in this process would clear PHP's realpath cache and
 * conceal the external-update case. Arguments originate only in the private
 * temporary fixture below, never in a web request or shell command string.
 *
 * @since 3.5.0
 * @param string $path Owned directory, generation alias or file.
 * @param string|null $target Owned symlink target; null deletes the owned file.
 * @param bool $alias Whether an existing alias is replaced atomically.
 * @return void Throws if the separate native operation fails or emits diagnostics.
 */
function skye_path_replace( $path, $target, $alias = false ) {
	if ( null === $target ) {
		$code = 'exit(unlink($argv[1]) ? 0 : 2);';
	} elseif ( $alias ) {
		$code = 'exit(symlink($argv[2], $argv[1] . ".next") && rename($argv[1] . ".next", $argv[1]) ? 0 : 2);';
	} else {
		$code = 'exit(rename($argv[1], $argv[1] . ".previous") && symlink($argv[2], $argv[1]) ? 0 : 2);';
	}
	$process = proc_open( array( PHP_BINARY, '-r', $code, $path, $target ?? '' ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
	if ( ! is_resource( $process ) ) {
		throw new RuntimeException( 'Cannot start independent fixture mutation.' );
	}
	$output = stream_get_contents( $pipes[1] );
	$error = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	skye_path_check( proc_close( $process ) === 0 && $output === '' && $error === '', 'The independent fixture change completes without touching parent PHP caches.' );
}

/**
 * Remove the private temporary fixture without following its deployment aliases.
 *
 * @since 3.5.0
 * @param string $path Own temporary file, directory or symbolic link.
 * @return void No source, remote filesystem or symlink target is modified.
 */
function skye_path_remove( $path ) {
	if ( is_link( $path ) || ! is_dir( $path ) ) {
		if ( file_exists( $path ) || is_link( $path ) ) {
			unlink( $path );
		}
		return;
	}
	foreach ( new FilesystemIterator( $path, FilesystemIterator::SKIP_DOTS ) as $entry ) {
		skye_path_remove( $entry->getPathname() );
	}
	rmdir( $path );
}

$fixture = tempnam( sys_get_temp_dir(), 'skye-path-identity-' );
if ( false === $fixture || ! unlink( $fixture ) || ! mkdir( $fixture, 0700 ) ) {
	throw new RuntimeException( 'Cannot prepare the private filesystem fixture.' );
}
$fixture = realpath( $fixture );
try {
	foreach ( array( null, false, 0, array(), '', '.', '../relative', 'file:///tmp/file', 'php://memory', "a\0b", "/a\0b", str_repeat( '/', 16385 ) ) as $invalid ) {
		skye_path_check( PathIdentity::canonical( $invalid ) === false, 'Invalid, relative, stream and oversized candidates are refused.' );
	}
	skye_path_check( PathIdentity::canonical( $fixture . '/missing' ) === false, 'Missing native paths remain unavailable.' );
	skye_path_check( PathIdentity::canonical( $fixture ) === $fixture, 'An existing physical directory keeps its native identity.' );
	skye_path_check( PathIdentity::canonical( $fixture . DIRECTORY_SEPARATOR ) === $fixture, 'A native trailing separator preserves the existing directory contract.' );

	if ( DIRECTORY_SEPARATOR === '/' ) {
		skye_path_check( PathIdentity::canonical( '/' ) === '/', 'The POSIX root remains a valid physical path.' );
		skye_path_check( PathIdentity::canonical( 'C:\\Windows' ) === false, 'A Windows spelling is not a POSIX local path.' );
		foreach ( array( 'physical', 'other', 'old', 'new' ) as $directory ) {
			mkdir( $fixture . '/' . $directory . '/sub', 0700, true );
			file_put_contents( $fixture . '/' . $directory . '/sub/file.css', $directory );
		}
		$file = $fixture . '/physical/sub/file.css';
		skye_path_check( PathIdentity::canonical( $file ) === $file && realpath( $file ) === $file, 'The physical file identity is warm before its parent changes.' );
		skye_path_replace( $fixture . '/physical', $fixture . '/other' );
		skye_path_check( PathIdentity::canonical( $file ) === $fixture . '/other/sub/file.css', 'A separately replaced physical parent cannot retain a stale canonical identity.' );

		$current = $fixture . '/current';
		symlink( 'old', $current );
		$alias_file = $current . '/sub/file.css';
		skye_path_check( PathIdentity::canonical( $alias_file ) === $fixture . '/old/sub/file.css', 'A relative native generation alias remains supported.' );
		skye_path_replace( $current, 'new', true );
		skye_path_check( PathIdentity::canonical( $alias_file ) === $fixture . '/new/sub/file.css', 'A warm root alias observes a separate process generation swap.' );
		skye_path_check( PathIdentity::canonical( $current . '///sub/./file.css' ) === $fixture . '/new/sub/file.css', 'Noncanonical spelling still uses native alias and dot-segment semantics.' );

		symlink( 'current', $fixture . '/outer' );
		$nested = $fixture . '/outer/sub/file.css';
		skye_path_check( PathIdentity::canonical( $nested ) === $fixture . '/new/sub/file.css', 'An explicit relative alias chain remains supported.' );
		skye_path_replace( $fixture . '/new', $fixture . '/other' );
		skye_path_check( PathIdentity::canonical( $nested ) === $fixture . '/other/sub/file.css', 'A separately replaced alias target refreshes its previously cached parent prefixes.' );

		$removed = $fixture . '/other/sub/file.css';
		skye_path_check( PathIdentity::canonical( $removed ) === $removed, 'A physical file is warm before another process removes it.' );
		skye_path_replace( $removed, null );
		skye_path_check( PathIdentity::canonical( $removed ) === false && PathIdentity::canonical( $nested ) === false, 'Removed physical and alias leaves cannot reuse a previous native identity.' );
		symlink( 'cycle', $fixture . '/cycle' );
		skye_path_check( PathIdentity::canonical( $fixture . '/cycle' ) === false, 'Native symbolic-link cycles remain refused.' );
		symlink( 'absent-target', $fixture . '/dangling' );
		skye_path_check( PathIdentity::canonical( $fixture . '/dangling' ) === false, 'A dangling alias remains unavailable.' );

		$deep = $fixture;
		while ( skye_path_depth( $deep ) < 64 ) {
			$deep .= '/d';
		}
		mkdir( $deep, 0700, true );
		skye_path_check( realpath( $deep ) === $deep && PathIdentity::canonical( $deep ) === $deep, 'Exactly 64 native root-inclusive prefixes remain supported.' );
		mkdir( $deep . '/d', 0700 );
		skye_path_check( realpath( $deep . '/d' ) === $deep . '/d' && PathIdentity::canonical( $deep . '/d' ) === false, 'An existing physical path beyond the 64-prefix budget is refused.' );

		$last = 'old';
		for ( $index = 0; $index < 34; ++$index ) {
			$name = 'chain-' . $index;
			symlink( $last, $fixture . '/' . $name );
			$last = $name;
		}
		skye_path_check( PathIdentity::canonical( $fixture . '/' . $last . '/sub/file.css' ) === false, 'An excessive native alias chain remains outside the finite traversal budgets.' );
	}
	echo json_encode( array( 'status' => 'pass', 'assertions' => $checks, 'php' => PHP_VERSION, 'platform' => PHP_OS_FAMILY, 'native_filesystem' => true, 'windows_fast_path' => false ), JSON_UNESCAPED_SLASHES ) . "\n";
} finally {
	skye_path_remove( $fixture );
}
