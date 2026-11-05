<?php
/** Prune explicitly selected responsive candidates using only complete native metadata. */

namespace Rocketsky\Skye\Packages;

/** Share the metadata-only dominance rule without selecting attachments or registering global filters. */
final class ImageCandidates {
	/** @var int Maximum native candidates examined by one explicit call. */
	private const MAX_SOURCES = 128;
	/** @var int Maximum metadata records, including the original image. */
	private const MAX_METADATA = 256;
	/** @var array<string,string> Formats whose candidate identity is understood by this policy. */
	private const FORMATS = array( 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp' );

	/**
	 * Parse only ordinary absolute image URLs with an unambiguous native path.
	 *
	 * Query-based optimizers, credentials and rewritten path segments cannot be
	 * matched to stored byte sizes reliably, so they retain native delivery.
	 * Parsing never fetches a destination or inspects a filesystem path.
	 *
	 * @since 3.4.0
	 * @param mixed $value Actual source URL supplied by the native srcset caller.
	 * @return array|null Native URL parts or null for an unsupported identity.
	 */
	private static function url( $value ) {
		if ( ! is_string( $value ) || strlen( $value ) > 4096 || preg_match( '/[\x00-\x20\\\\]/', $value ) ) {
			return null;
		}
		$parts = wp_parse_url( $value );
		if ( ! is_array( $parts ) || ! in_array( $parts['scheme'] ?? '', array( 'http', 'https' ), true ) ||
			empty( $parts['host'] ) || empty( $parts['path'] ) || ! str_starts_with( $parts['path'], '/' ) ||
			isset( $parts['query'] ) || isset( $parts['fragment'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ||
			( isset( $parts['port'] ) && ( $parts['port'] < 1 || $parts['port'] > 65535 ) ) ||
			in_array( '..', explode( '/', $parts['path'] ), true ) || in_array( '.', explode( '/', $parts['path'] ), true ) ) {
			return null;
		}
		$decoded = rawurldecode( $parts['path'] );
		if ( preg_match( '/[\x00-\x1f\x7f\\\\]/', $decoded ) || substr_count( $decoded, '/' ) !== substr_count( $parts['path'], '/' ) ||
			in_array( '..', explode( '/', $decoded ), true ) || in_array( '.', explode( '/', $decoded ), true ) ) {
			return null;
		}
		return $parts;
	}

	/**
	 * Extract a bounded native filename without normalizing ambiguous metadata paths.
	 *
	 * @since 3.4.0
	 * @param mixed $value Original metadata filename or its ordinary relative path.
	 * @return string Exact native basename, or an empty refusal.
	 */
	private static function filename( $value ) {
		if ( ! is_string( $value ) || $value === '' || strlen( $value ) > 4096 || str_starts_with( $value, '/' ) ||
			preg_match( '/[\x00-\x1f\x7f\\\\?#]/', $value ) || in_array( '..', explode( '/', $value ), true ) || in_array( '.', explode( '/', $value ), true ) ) {
			return '';
		}
		return wp_basename( $value );
	}

	/**
	 * Validate the dimensions, real recorded byte size and format of one known filename.
	 *
	 * Numeric strings, estimated sizes and contradictory MIME records cannot
	 * justify removing a browser candidate. The signed-32-bit dimension ceiling
	 * bounds unsupported extreme metadata without changing that native metadata.
	 *
	 * @since 3.4.0
	 * @param array $entry One original native image or subsize metadata record.
	 * @param string $file Exact native basename already checked for ambiguity.
	 * @return array|null Comparable native dimensions/bytes/format, or null for unknown metadata.
	 */
	private static function record( $entry, $file ) {
		$extension = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		$format = self::FORMATS[$extension] ?? null;
		if ( $format === null || ( isset( $entry['mime-type'] ) && $entry['mime-type'] !== $format ) ||
			! is_int( $entry['filesize'] ?? null ) || $entry['filesize'] < 1 ||
			! is_int( $entry['width'] ?? null ) || $entry['width'] < 1 || $entry['width'] > 2147483647 ||
			! is_int( $entry['height'] ?? null ) || $entry['height'] < 1 || $entry['height'] > 2147483647 ) {
			return null;
		}
		return array( 'width' => $entry['width'], 'height' => $entry['height'], 'bytes' => $entry['filesize'], 'format' => $format );
	}

	/**
	 * Remove a candidate only when a known wider peer is smaller in bytes and crop-compatible.
	 *
	 * The caller owns attachment and request scope; this helper registers no hook
	 * and knows nothing about logos, users, options or administrative requests.
	 * It examines at most 128 sources and 256 metadata records, performs no file,
	 * database or HTTP I/O, and preserves source order plus every surviving byte.
	 * Unknown candidates remain untouched. Conflicting or incomplete metadata for
	 * the same filename cannot establish a dominance relationship in either direction.
	 *
	 * @since 3.4.0
	 * @param mixed $sources Native responsive candidate array keyed by integer width.
	 * @param mixed $image_src Native source URL for this complete candidate set.
	 * @param mixed $image_meta Native image metadata, or the verified metadata of its delivered format.
	 * @return mixed Original input or the same candidate array with proven dominated entries removed.
	 */
	public static function prune( $sources, $image_src, $image_meta ) {
		if ( ! is_array( $sources ) || count( $sources ) < 2 || count( $sources ) > self::MAX_SOURCES || ! is_array( $image_meta ) ||
			( isset( $image_meta['sizes'] ) && ! is_array( $image_meta['sizes'] ) ) || count( $image_meta['sizes'] ?? array() ) >= self::MAX_METADATA ) {
			return $sources;
		}
		$origin = self::url( $image_src );
		if ( $origin === null ) {
			return $sources;
		}
		$metadata = array();
		$entries = array( $image_meta );
		foreach ( $image_meta['sizes'] ?? array() as $entry ) {
			$entries[] = $entry;
		}
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$file = self::filename( $entry['file'] ?? null );
			if ( $file === '' ) {
				continue;
			}
			$record = self::record( $entry, $file );
			if ( ! array_key_exists( $file, $metadata ) ) {
				$metadata[$file] = $record;
			} elseif ( $metadata[$file] !== $record ) {
				$metadata[$file] = null;
			}
		}
		$origin_file = rawurldecode( wp_basename( $origin['path'] ) );
		if ( ! isset( $metadata[$origin_file] ) ) {
			return $sources;
		}
		$known = array();
		foreach ( $sources as $width => $source ) {
			if ( ! is_int( $width ) || ! is_array( $source ) || ( $source['descriptor'] ?? null ) !== 'w' ||
				! is_int( $source['value'] ?? null ) || $source['value'] < 1 || $width !== $source['value'] ) {
				continue;
			}
			$url = self::url( $source['url'] ?? null );
			if ( $url === null || $url['scheme'] !== $origin['scheme'] || $url['host'] !== $origin['host'] ||
				( $url['port'] ?? null ) !== ( $origin['port'] ?? null ) || dirname( $url['path'] ) !== dirname( $origin['path'] ) ) {
				continue;
			}
			$file = rawurldecode( wp_basename( $url['path'] ) );
			if ( str_contains( $file, '/' ) || str_contains( $file, '\\' ) ) {
				continue;
			}
			$record = $metadata[$file] ?? null;
			if ( is_array( $record ) && $record['width'] === $width ) {
				$known[$width] = $record;
			}
		}
		foreach ( $known as $width => $candidate ) {
			foreach ( $known as $alternative ) {
				if ( $alternative['width'] > $candidate['width'] && $alternative['bytes'] < $candidate['bytes'] &&
					$alternative['format'] === $candidate['format'] &&
					wp_image_matches_ratio( $candidate['width'], $candidate['height'], $alternative['width'], $alternative['height'] ) ) {
					unset( $sources[$width] );
					break;
				}
			}
		}
		return $sources;
	}
}
