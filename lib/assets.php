<?php
/** Resolve validated build assets without changing stored content or Core URLs. */

namespace Rocketsky\Skye\Assets;

/** Read one immutable deployment manifest, tolerating missing or invalid builds. */
class JsonManifest {
	/** @var array Valid decoded records; malformed input never escapes this boundary. */
	private $manifest = array();

	/**
	 * Read a bounded manifest so an incomplete deployment cannot cause a fatal error.
	 *
	 * @since 2015-11-11 First recorded manifest implementation in Skye history.
	 * @param string $manifest_path Absolute path to the generated JSON file.
	 * @return void
	 */
	public function __construct( $manifest_path ) {
		if ( is_string( $manifest_path ) && is_file( $manifest_path ) && is_readable( $manifest_path ) && filesize( $manifest_path ) <= 1048576 ) {
			$contents = file_get_contents( $manifest_path );
			$data = is_string( $contents ) ? json_decode( $contents, true ) : null;
			if ( is_array( $data ) ) {
				$this->manifest = $data;
			}
		}
	}

	/**
	 * Return decoded records for callers that need a shared request-local snapshot.
	 *
	 * @since 2015-11-11
	 * @return array Manifest records, or an empty array for an unavailable build.
	 */
	public function get() {
		return $this->manifest;
	}

	/**
	 * Resolve an exact or dotted key without treating false or zero as absence.
	 *
	 * @since 2015-11-11
	 * @param string|null $key Exact filename or dotted configuration key; null returns all.
	 * @param mixed $default Value returned when the key is absent.
	 * @return mixed Found record, complete manifest or the supplied default.
	 */
	public function getPath( $key = '', $default = null ) {
		$collection = $this->manifest;
		if ( $key === null ) {
			return $collection;
		}
		if ( ! is_string( $key ) ) {
			return $default;
		}
		if ( array_key_exists( $key, $collection ) ) {
			return $collection[$key];
		}
		foreach ( explode( '.', $key ) as $segment ) {
			if ( ! is_array( $collection ) || ! array_key_exists( $segment, $collection ) ) {
				return $default;
			}
			$collection = $collection[$segment];
		}
		return $collection;
	}
}

/**
 * Validate a relative owned path before combining it with a theme root.
 *
 * This is a path contract, not permission to read arbitrary filesystem input.
 *
 * @since 3.0.0
 * @param mixed $filename Candidate asset path.
 * @return string Normalized relative path, or an empty refusal.
 */
function relative_path( $filename ) {
	if ( ! is_string( $filename ) || ! preg_match( '~^[a-zA-Z0-9_./-]+$~D', $filename ) || str_starts_with( $filename, '/' ) ||
		in_array( '..', explode( '/', $filename ), true ) || str_contains( $filename, '//' ) ) {
		return '';
	}
	return ltrim( $filename, './' );
}

/**
 * Select a child build only when its own manifest exists; otherwise use the parent.
 *
 * Child theme overrides remain a complete deployment boundary rather than mixing
 * one child's manifest with another theme's files.
 *
 * @since 3.0.0
 * @return array{path:string,uri:string,manifest:JsonManifest} Request-local build root.
 */
function build_root() {
	static $roots = array();
	$path = get_template_directory();
	$uri = get_template_directory_uri();
	if ( get_stylesheet_directory() !== $path && is_file( get_stylesheet_directory() . '/dist/assets.json' ) ) {
		$path = get_stylesheet_directory();
		$uri = get_stylesheet_directory_uri();
	}
	if ( ! isset( $roots[$path] ) ) {
		$roots[$path] = array( 'path' => $path . '/dist/', 'uri' => $uri . '/dist/', 'manifest' => new JsonManifest( $path . '/dist/assets.json' ) );
	}
	return $roots[$path];
}

/**
 * Resolve both current directory-aware and historical flat manifest records.
 *
 * No stored content is rewritten; legacy filenames remain usable during migration.
 *
 * @since 3.0.0
 * @param string $filename Relative requested asset.
 * @return string Safe relative output, or an empty refusal.
 */
function resolved_path( $filename ) {
	$filename = relative_path( $filename );
	if ( $filename === '' ) {
		return '';
	}
	$entry = build_root()['manifest']->getPath( $filename, build_root()['manifest']->getPath( basename( $filename ), $filename ) );
	if ( ! is_string( $entry ) || relative_path( $entry ) === '' ) {
		return $filename;
	}
	if ( ! str_contains( $entry, '/' ) && dirname( $filename ) !== '.' ) {
		$entry = dirname( $filename ) . '/' . $entry;
	}
	return $entry;
}

/**
 * Expose a final delivery URL only for an owned theme asset.
 *
 * Optional CDN mapping happens at presentation time; canonical media and saved
 * metadata retain their native values. Invalid filter output keeps the original.
 *
 * @since 3.0.0
 * @param string $url Canonical own asset URL.
 * @return string Original or validated delivery URL.
 */
function public_url( $url ) {
	$root = build_root();
	if ( ! is_string( $url ) || ! str_starts_with( $url, $root['uri'] ) ) {
		return $url;
	}
	$mapped = apply_filters( 'skye_public_asset_url', $url );
	$parts = is_string( $mapped ) ? wp_parse_url( $mapped ) : false;
	return is_array( $parts ) && in_array( $parts['scheme'] ?? '', array( 'http', 'https' ), true ) && ! empty( $parts['host'] ) &&
		! isset( $parts['user'] ) && ! isset( $parts['pass'] ) && ! preg_match( '/[\x00-\x20]/', $mapped ) ? $mapped : $url;
}

/**
 * Return the current immutable asset URL for native enqueue calls.
 *
 * @since 2021-02-28
 * @param string $filename Relative source asset name.
 * @return string Final public URL, or an empty string for an invalid path.
 */
function asset_uri( $filename ) {
	$relative = resolved_path( $filename );
	return $relative === '' ? '' : public_url( build_root()['uri'] . $relative );
}

/**
 * Return the source-tree path for callers that explicitly require original assets.
 *
 * @since 2021-02-28
 * @param string $filename Relative path inside assets/.
 * @return string Absolute source path, or an empty refusal.
 */
function asset_path( $filename ) {
	$relative = relative_path( $filename );
	return $relative === '' ? '' : get_template_directory() . '/assets/' . $relative;
}

/**
 * Return the immutable build path without confusing a URL with a filesystem path.
 *
 * @since 2021-02-28
 * @param string $filename Relative asset name resolved through the manifest.
 * @return string Absolute build path, or an empty refusal.
 */
function dist_path( $filename ) {
	$relative = resolved_path( $filename );
	return $relative === '' ? '' : build_root()['path'] . $relative;
}

/**
 * Read generated performance descriptors without parsing CSS during rendering.
 *
 * @since 3.0.0
 * @return array Verified-version configuration or an empty fallback.
 */
function performance_manifest() {
	$data = build_root()['manifest']->getPath( '_performance', array() );
	return is_array( $data ) && ( $data['version'] ?? null ) === 1 ? $data : array();
}

/**
 * Describe complementary complete styles from the same build breakpoint.
 *
 * A missing mobile variant falls back to one ordinary complete stylesheet.
 *
 * @since 3.0.0
 * @return array<string,array{file:string,media:string}> Native handle descriptors.
 */
function stylesheet_variants() {
	$config = performance_manifest();
	$breakpoint = $config['mobileBreakpoint'] ?? null;
	if ( ! is_int( $breakpoint ) || $breakpoint < 1 || ! build_root()['manifest']->getPath( 'main-mobile.css' ) ) {
		return array( 'skye/css' => array( 'file' => 'styles/main.css', 'media' => 'all' ) );
	}
	return array(
		'skye/css' => array( 'file' => 'styles/main.css', 'media' => '(min-width: ' . $breakpoint . 'px)' ),
		'skye/css-mobile' => array( 'file' => 'styles/main-mobile.css', 'media' => '(max-width: ' . ( $breakpoint - 0.02 ) . 'px)' ),
	);
}

/**
 * Add configured own font hints without inventing a fixed brand font inventory.
 *
 * Only build-owned relative font files qualify. System fonts need no hints.
 *
 * @since 3.0.0
 * @return array<int,array> Native preload records.
 */
function font_preloads() {
	$result = array();
	foreach ( performance_manifest()['fonts'] ?? array() as $font ) {
		if ( ! is_array( $font ) || ! is_string( $font['file'] ?? null ) || ! str_starts_with( $font['file'], 'fonts/' ) || relative_path( $font['file'] ) === '' ) {
			continue;
		}
		$result[] = array( 'href' => asset_uri( $font['file'] ), 'as' => 'font', 'type' => 'font/woff2', 'crossorigin' => 'anonymous' );
	}
	return $result;
}
