<?php
/** Resolve bounded public static sources and publish content-addressed derivative assets. */

namespace Rocketsky\Skye\Packages\Assets;

require_once __DIR__ . '/PathIdentity.php';

/** Keep source packages immutable and fail back to native delivery on storage errors. */
final class LocalAssets {
	/**
	 * Read only a real public CSS/JavaScript file mapped by native WordPress roots.
	 *
	 * No HTTP requests, arbitrary filesystem paths or request-supplied filenames
	 * enter this boundary. Child/plugin symlink roots remain explicitly supported.
	 * Source identities are refreshed on every call. Immutable source bytes may
	 * be reused within one request, but registration/provider decisions are not.
	 *
	 * @since 3.1.0
	 * @param string $url Registered canonical or configured delivery URL.
	 * @param string $extension Required public static extension, css or js.
	 * @return array|false Source bytes/path/hash or false for an unknown source.
	 */
	public static function read( $url, $extension ) {
		$url = apply_filters( 'skye_canonical_asset_url', $url );
		if ( ! is_string( $url ) || strlen( $url ) > 4096 || ! in_array( $extension, array( 'css', 'js' ), true ) ) {
			return false;
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || ! in_array( $parts['scheme'] ?? '', array( 'http', 'https' ), true ) || empty( $parts['host'] ) ||
			isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ||
			! preg_match( '~^[a-zA-Z0-9_./-]+\.' . $extension . '$~D', $parts['path'] ?? '' ) || in_array( '..', explode( '/', $parts['path'] ), true ) ) {
			return false;
		}
		$roots = array(
			array( get_stylesheet_directory_uri(), get_stylesheet_directory() ),
			array( get_template_directory_uri(), get_template_directory() ),
			array( plugins_url(), WP_PLUGIN_DIR ),
			array( includes_url(), ABSPATH . WPINC ),
			array( content_url(), WP_CONTENT_DIR ),
		);
		foreach ( $roots as $root ) {
			$base = wp_parse_url( $root[0] );
			$prefix = trailingslashit( $base['path'] ?? '/' );
			if ( ! is_array( $base ) || strtolower( $parts['host'] ) !== strtolower( $base['host'] ?? '' ) ||
				( $parts['port'] ?? null ) !== ( $base['port'] ?? null ) || ! str_starts_with( $parts['path'], $prefix ) ) {
				continue;
			}
			$source = self::source( trailingslashit( $root[1] ) . substr( $parts['path'], strlen( $prefix ) ), $root[1] );
			if ( $source ) {
				return array( 'bytes' => $source['bytes'], 'path' => $source['path'], 'url' => $url, 'sha256' => $source['sha256'] );
			}
		}
		return false;
	}

	/**
	 * Read one stable public source without retaining stale or unbounded request data.
	 *
	 * Every lookup refreshes the logical path and its allowed root, including cache
	 * hits. Changed identities release their old bytes. Descriptor and final path
	 * identities must agree around a bounded read; failed reads are not cached.
	 * At most 128 sources/eight MiB remain in memory. Immutable files and atomic
	 * deployment are required: same-stat in-place mutations are not detectable.
	 *
	 * @since 3.4.0
	 * @param string $file Absolute source candidate derived from a native public root.
	 * @param string $root Actual native root, including a supported root symlink.
	 * @return array{bytes:string,path:string,sha256:string}|false Stable source or refusal.
	 */
	private static function source( $file, $root ) {
		static $sources = array();
		static $total = 0;
		$identity = self::source_identity( $file, $root );
		if ( isset( $sources[$file] ) && $sources[$file]['identity'] !== $identity ) {
			$total -= strlen( $sources[$file]['source']['bytes'] );
			unset( $sources[$file] );
		}
		if ( false === $identity ) {
			return false;
		}
		if ( isset( $sources[$file] ) ) {
			return $sources[$file]['source'];
		}
		if ( count( $sources ) >= 128 || $total + $identity['stat']['size'] > 8388608 ) {
			return false;
		}
		$stream = @fopen( $identity['path'], 'rb' );
		if ( false === $stream ) {
			return false;
		}
		try {
			if ( self::stat_identity( fstat( $stream ) ) !== $identity['stat'] ) {
				return false;
			}
			$bytes = stream_get_contents( $stream, 1048577 );
			if ( ! is_string( $bytes ) || strlen( $bytes ) !== $identity['stat']['size'] ||
				self::stat_identity( fstat( $stream ) ) !== $identity['stat'] || self::source_identity( $file, $root ) !== $identity ) {
				return false;
			}
		} finally {
			fclose( $stream );
		}
		$source = array( 'bytes' => $bytes, 'path' => $identity['path'], 'sha256' => hash( 'sha256', $bytes ) );
		$sources[$file] = array( 'identity' => $identity, 'source' => $source );
		$total += strlen( $bytes );
		return $source;
	}

	/**
	 * Refresh a canonical regular-file identity inside the current native root.
	 *
	 * Resolving the root and candidate anew preserves intentional root symlinks
	 * while rejecting file/directory symlink escapes and disappearing deployments.
	 * No directory contents or unrelated filesystem paths are inventoried.
	 *
	 * @since 3.4.0
	 * @param string $file Absolute source candidate from the reviewed URL mapping.
	 * @param string $root Current native source root.
	 * @return array{path:string,root:string,stat:array}|false Current identity or refusal.
	 */
	private static function source_identity( $file, $root ) {
		$directory = PathIdentity::canonical( $root );
		$path = PathIdentity::canonical( $file );
		if ( ! $directory || ! $path || ! str_starts_with( $path, $directory . DIRECTORY_SEPARATOR ) ) {
			return false;
		}
		clearstatcache( true, $path );
		$stat = self::stat_identity( @lstat( $path ) );
		if ( false === $stat || realpath( $path ) !== $path || ! is_readable( $path ) ) {
			return false;
		}
		return array( 'path' => $path, 'root' => $directory, 'stat' => $stat );
	}

	/**
	 * Compare only native regular-file identity fields across path and open handle.
	 *
	 * Type and size checks prevent malformed or unsupported filesystem records
	 * from reaching arithmetic, hashing or the request-local source cache.
	 *
	 * @since 3.4.0
	 * @param array|false $stat Native lstat/fstat record.
	 * @return array|false Exact bounded identity, or false for unsupported records.
	 */
	private static function stat_identity( $stat ) {
		$identity = array();
		foreach ( array( 'dev', 'ino', 'mode', 'size', 'mtime', 'ctime' ) as $key ) {
			if ( ! is_array( $stat ) || ! isset( $stat[$key] ) || ! is_int( $stat[$key] ) ) {
				return false;
			}
			$identity[$key] = $stat[$key];
		}
		if ( $stat['dev'] < 0 || $stat['ino'] < 0 || $stat['mode'] < 0 || ( $stat['mode'] & 0170000 ) !== 0100000 || $stat['size'] < 0 || $stat['size'] > 1048576 ) {
			return false;
		}
		return $identity;
	}

	/**
	 * Publish one immutable JS derivative and a public nonsecret integrity manifest.
	 *
	 * Atomic replacement never changes source files or removes previous hashes,
	 * which may still be referenced by ordinary page/browser caches.
	 *
	 * @since 3.1.0
	 * @param string $bytes Complete reviewed static source concatenation.
	 * @param array $sources Public source hashes, without local paths or inline data.
	 * @return array|false Immutable path/URL/hash descriptor, or false on any failure.
	 */
	public static function publish_script( $bytes, $sources ) {
		if ( ! is_string( $bytes ) || $bytes === '' || strlen( $bytes ) > 2097152 ) {
			return false;
		}
		$cache = self::directory();
		if ( ! $cache ) {
			return false;
		}
		$directory = $cache['path'];
		$hash = hash( 'sha256', $bytes );
		$name = 'bundle-' . $hash . '.js';
		$path = $directory . '/' . $name;
		if ( is_link( $path ) ) {
			return false;
		}
		if ( ! is_file( $path ) || filesize( $path ) !== strlen( $bytes ) || hash_file( 'sha256', $path ) !== $hash ) {
			if ( ! self::atomic_write( $path, $bytes ) ) {
				return false;
			}
		}
		$manifest = array( 'version' => 1, 'file' => $name, 'sha256' => $hash, 'bytes' => strlen( $bytes ), 'sources' => $sources );
		$manifest_path = $directory . '/bundle-' . $hash . '.json';
		if ( ! is_file( $manifest_path ) && ! self::atomic_write( $manifest_path, wp_json_encode( $manifest ) ) ) {
			return false;
		}
		$url = $cache['url'] . '/' . $name;
		return array( 'path' => $path, 'url' => apply_filters( 'skye_static_asset_url', $url ), 'sha256' => $hash );
	}

	/**
	 * Reuse a hash-verified complete CSS derivative instead of reparsing on visitors.
	 *
	 * The input key binds the CSS grammar version, source bytes, canonical base and
	 * delivery configuration. Older derivatives remain available to open pages.
	 * Cache manifests contain only hashes and public artifact names. Unsupported
	 * source syntax and unavailable storage retain the native stylesheet link.
	 *
	 * @since 3.1.0
	 * @param array $source Bounded public source record returned by read().
	 * @return string|false Complete rebased CSS bytes or false for native fallback.
	 */
	public static function inline_css( $source ) {
		$cache = self::directory();
		if ( ! $cache || ! is_array( $source ) ) {
			return false;
		}
		$mapping = apply_filters( 'skye_static_asset_cache_key', 'canonical-v1' );
		if ( ! is_string( $mapping ) || strlen( $mapping ) > 4096 ) {
			return false;
		}
		$key = hash( 'sha256', 'full-css-v2|' . $source['sha256'] . '|' . $source['url'] . '|' . $mapping );
		$manifest_path = $cache['path'] . '/style-source-' . $key . '.json';
		if ( is_file( $manifest_path ) && ! is_link( $manifest_path ) && filesize( $manifest_path ) < 4096 ) {
			$record = json_decode( file_get_contents( $manifest_path ), true );
			if ( is_array( $record ) && preg_match( '/^style-[a-f0-9]{64}\.css$/D', $record['file'] ?? '' ) &&
				preg_match( '/^[a-f0-9]{64}$/D', $record['sha256'] ?? '' ) ) {
				$path = $cache['path'] . '/' . $record['file'];
				if ( is_file( $path ) && ! is_link( $path ) && filesize( $path ) <= 1048576 ) {
					$bytes = file_get_contents( $path );
					if ( is_string( $bytes ) && hash( 'sha256', $bytes ) === $record['sha256'] && stripos( $bytes, '</style' ) === false ) {
						return $bytes;
					}
				}
			}
		}
		$bytes = FullCss::rebase( $source['bytes'], $source['url'] );
		if ( $bytes === false ) {
			return false;
		}
		$hash = hash( 'sha256', $bytes );
		$name = 'style-' . $hash . '.css';
		$path = $cache['path'] . '/' . $name;
		if ( is_link( $path ) || ( ( ! is_file( $path ) || filesize( $path ) !== strlen( $bytes ) || hash_file( 'sha256', $path ) !== $hash ) && ! self::atomic_write( $path, $bytes ) ) ) {
			return false;
		}
		$record = wp_json_encode( array( 'version' => 1, 'file' => $name, 'sha256' => $hash, 'bytes' => strlen( $bytes ) ) );
		return self::atomic_write( $manifest_path, $record ) ? $bytes : false;
	}

	/**
	 * Resolve only the owned public upload-cache directory without touching sources.
	 *
	 * @since 3.1.0
	 * @return array|false Absolute path and canonical URL, or false when unavailable.
	 */
	private static function directory() {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) || empty( $uploads['baseurl'] ) ) {
			return false;
		}
		$directory = trailingslashit( $uploads['basedir'] ) . 'skye-static-assets';
		if ( is_link( $directory ) || ! wp_mkdir_p( $directory ) || ! is_writable( $directory ) ) {
			return false;
		}
		return array( 'path' => $directory, 'url' => trailingslashit( $uploads['baseurl'] ) . 'skye-static-assets' );
	}

	/**
	 * Atomically install only a prepared owned cache file in its existing directory.
	 *
	 * Temporary paths are checked because tempnam may otherwise fall back to the
	 * system temporary directory when the intended directory is unavailable.
	 *
	 * @since 3.1.0
	 * @param string $path Absolute new cache artifact path.
	 * @param string $bytes Bounded public payload to publish.
	 * @return bool Whether the full artifact was installed successfully.
	 */
	private static function atomic_write( $path, $bytes ) {
		if ( ! is_string( $bytes ) || is_link( $path ) ) {
			return false;
		}
		$temporary = tempnam( dirname( $path ), '.skye-prepared-' );
		if ( ! $temporary || realpath( dirname( $temporary ) ) !== realpath( dirname( $path ) ) ) {
			if ( $temporary ) {
				unlink( $temporary );
			}
			return false;
		}
		$success = file_put_contents( $temporary, $bytes, LOCK_EX ) === strlen( $bytes ) && chmod( $temporary, 0644 ) && rename( $temporary, $path );
		if ( is_file( $temporary ) ) {
			unlink( $temporary );
		}
		return $success;
	}
}
