<?php
/** Resolve current native filesystem identities without trusting cached parent aliases. */

namespace Rocketsky\Skye\Packages\Assets;

/** Refresh bounded logical and symlink-target prefixes before native canonicalization. */
final class PathIdentity {
	/** @var int Maximum components in one absolute candidate, including its root. */
	private const MAX_DEPTH = 64;
	/** @var int Total prefix inspections across one native symlink chain. */
	private const MAX_PREFIXES = 256;
	/** @var int Finite symlink transitions, including native deployment root aliases. */
	private const MAX_LINKS = 32;
	/** @var int Maximum path bytes before any filesystem operation. */
	private const MAX_BYTES = 16384;

	/**
	 * Resolve a fresh local identity, retaining supported native root symlinks.
	 *
	 * Clearing only the leaf misses cached intermediate directories. Clear each
	 * logical prefix and inspect actual links, then refresh their target prefixes
	 * including the remaining suffix. Native realpath still performs the final
	 * canonicalization; callers decide whether the result belongs to their root.
	 * Depth, prefix and link budgets reject loops or excessive paths. No global
	 * realpath-cache flush, directory scan or request-local identity cache is used.
	 *
	 * @since 3.4.0
	 * @param string $path Absolute native local candidate.
	 * @return string|false Current canonical path, or a bounded refusal.
	 * @see https://www.php.net/manual/en/function.clearstatcache.php
	 * @see https://www.php.net/manual/en/function.readlink.php
	 */
	public static function canonical( $path ) {
		$original = self::prefixes( $path );
		if ( false === $original ) {
			return false;
		}
		$candidate = $path;
		$seen = array();
		$remaining = self::MAX_PREFIXES;
		for ( $links = 0; $links <= self::MAX_LINKS; ++$links ) {
			if ( isset( $seen[$candidate] ) ) {
				return false;
			}
			$seen[$candidate] = true;
			$prefixes = self::prefixes( $candidate );
			if ( false === $prefixes || count( $prefixes ) > $remaining ) {
				return false;
			}
			$remaining -= count( $prefixes );
			foreach ( $prefixes as $prefix ) {
				clearstatcache( true, $prefix );
			}
			$next = null;
			foreach ( $prefixes as $prefix ) {
				$stat = @lstat( $prefix );
				if ( ! is_array( $stat ) || ! is_int( $stat['mode'] ?? null ) ) {
					return false;
				}
				if ( ( $stat['mode'] & 0170000 ) === 0120000 ) {
					$target = @readlink( $prefix );
					if ( ! is_string( $target ) || $target === '' || strpos( $target, "\0" ) !== false ) {
						return false;
					}
					$next = ( self::absolute( $target ) ? $target : dirname( $prefix ) . '/' . $target ) . substr( $candidate, strlen( $prefix ) );
					break;
				}
			}
			if ( null !== $next ) {
				$candidate = $next;
				continue;
			}
			if ( count( $original ) > $remaining ) {
				return false;
			}
			foreach ( $original as $prefix ) {
				clearstatcache( true, $prefix );
			}
			$resolved = realpath( $candidate );
			return false !== $resolved && realpath( $path ) === $resolved ? $resolved : false;
		}
		return false;
	}

	/**
	 * Enumerate one bounded ancestor chain without resolving or normalizing links.
	 *
	 * Relative link targets keep their native dot-segment semantics. Each full
	 * candidate remains absolute and is ultimately interpreted by native PHP.
	 *
	 * @since 3.4.0
	 * @param string $path Candidate before any filesystem lookup.
	 * @return string[]|false Root-first prefixes or an invalid/excessive-path refusal.
	 */
	private static function prefixes( $path ) {
		if ( ! is_string( $path ) || $path === '' || strlen( $path ) > self::MAX_BYTES || strpos( $path, "\0" ) !== false || ! self::absolute( $path ) ) {
			return false;
		}
		$trimmed = rtrim( $path, DIRECTORY_SEPARATOR === '\\' ? '/\\' : '/' );
		if ( $trimmed !== '' && preg_match( '~^[a-zA-Z]:$~D', $trimmed ) !== 1 ) {
			$path = $trimmed;
		}
		$prefixes = array();
		for ( $depth = 0; $depth < self::MAX_DEPTH; ++$depth ) {
			$prefixes[] = $path;
			$parent = dirname( $path );
			if ( $parent === $path ) {
				return array_reverse( $prefixes );
			}
			if ( $parent === '.' || $parent === '' ) {
				return false;
			}
			$path = $parent;
		}
		return false;
	}

	/**
	 * Admit only native absolute paths rather than stream wrappers or relative names.
	 *
	 * Windows drive roots are recognized only on Windows; ordinary Unix paths
	 * retain their native spelling for exact realpath-cache invalidation keys.
	 *
	 * @since 3.4.0
	 * @param string $path Candidate or native readlink target.
	 * @return bool Whether the spelling denotes an absolute local path here.
	 */
	private static function absolute( $path ) {
		return str_starts_with( $path, DIRECTORY_SEPARATOR ) ||
			( DIRECTORY_SEPARATOR === '\\' && preg_match( '~^[a-zA-Z]:[\\\\/]~D', $path ) === 1 );
	}
}
