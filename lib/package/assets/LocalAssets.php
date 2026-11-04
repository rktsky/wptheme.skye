<?php
/** Resolve bounded public static sources and publish content-addressed derivative assets. */

namespace Rocketsky\Skye\Packages\Assets;

/** Keep source packages immutable and fail back to native delivery on storage errors. */
final class LocalAssets {
	/**
	 * Read only a real public CSS/JavaScript file mapped by native WordPress roots.
	 *
	 * No HTTP requests, arbitrary filesystem paths or request-supplied filenames
	 * enter this boundary. Child/plugin symlink roots remain explicitly supported.
	 *
	 * @since 3.1.0
	 * @param string $url Registered canonical or configured delivery URL.
	 * @param string $extension Required public static extension, css or js.
	 * @return array|false Source bytes/path/hash or false for an unknown source.
	 */
	public static function read( $url, $extension ) {
		static $sources = array();
		$url = apply_filters( 'skye_canonical_asset_url', $url );
		$key = $extension . ':' . ( is_string( $url ) ? $url : '' );
		if ( array_key_exists( $key, $sources ) ) {
			return $sources[$key];
		}
		$sources[$key] = false;
		if ( ! is_string( $url ) || ! in_array( $extension, array( 'css', 'js' ), true ) ) {
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
			$directory = realpath( $root[1] );
			$path = realpath( trailingslashit( $root[1] ) . substr( $parts['path'], strlen( $prefix ) ) );
			if ( ! $directory || ! $path || ! str_starts_with( $path, $directory . DIRECTORY_SEPARATOR ) ||
				! is_file( $path ) || ! is_readable( $path ) || filesize( $path ) > 1048576 ) {
				continue;
			}
			$bytes = file_get_contents( $path, false, null, 0, 1048577 );
			if ( ! is_string( $bytes ) || strlen( $bytes ) > 1048576 ) {
				return false;
			}
			$sources[$key] = array( 'bytes' => $bytes, 'path' => $path, 'url' => $url, 'sha256' => hash( 'sha256', $bytes ) );
			return $sources[$key];
		}
		return false;
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
	 * The input key binds source bytes, canonical base and delivery configuration.
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
		$key = hash( 'sha256', $source['sha256'] . '|' . $source['url'] . '|' . $mapping );
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
