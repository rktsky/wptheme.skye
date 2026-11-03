<?php
/**
 * Register native theme image sizes and select verified WebP alternatives.
 */

namespace Rocketsky\Skye\Packages;

require_once __DIR__ . '/MediaPipeline.php';

/**
 * Register configured native sizes and present verified alternative formats.
 *
 * Core retains ownership of original files and responsive image selection.
 * Encoding and missing-size backfill belong exclusively to MediaPipeline.
 *
 * @since 3.0.0
 */
class Images {

	private static $webp_cache = [];


	/**
	 * Register image presentation and Core metadata invalidation.
	 *
	 * Conversion is owned by MediaPipeline, never by this rendering adapter.
	 *
	 * @return void
	 * @since 2021-03-04 First recorded constructor in Skye history.
	 * @since 3.0.0 Reworked native sizes and WebP lifecycle integration.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'images' ) );
		add_filter( 'wp_get_attachment_image', array( $this, 'picture' ), 10, 5 );
		add_filter( 'wp_update_attachment_metadata', array( $this, 'invalidate_webp' ), 10, 2 );
		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) {
			add_action( $hook, array( $this, 'metadata_changed' ), 10, 4 );
		}
		add_action( 'delete_attachment', array( __CLASS__, 'reset_webp_cache' ), 10, 0 );
	}

	/**
	 * Invalidate presentation after native metadata or file-pointer mutations.
	 *
	 * Direct metadata APIs do not pass through wp_update_attachment_metadata().
	 * Their completed hooks must also invalidate positive and negative results
	 * so another render in the same request observes the new native state.
	 *
	 * @param int|int[] $meta_ids Changed native metadata row IDs.
	 * @param int $attachment_id Native object whose metadata changed.
	 * @param string $key Changed metadata key.
	 * @param mixed $value Native stored or deleted value.
	 * @return void
	 * @since 3.0.0
	 */
	public function metadata_changed( $meta_ids, $attachment_id, $key, $value ) {
		if ( in_array( $key, array( '_wp_attachment_metadata', '_wp_attached_file' ), true ) ) {
			self::reset_webp_cache();
		}
	}

	/**
	 * Invalidate alternatives before Core persists an upload, edit or regeneration.
	 *
	 * Old own-ID outputs receive a retention window for in-flight HTML. The
	 * encoder's guarded publication alone may keep its verified namespace.
	 *
	 * @param array|mixed $metadata Incoming Core attachment metadata.
	 * @param int         $attachment_id Attachment being written.
	 * @return array|mixed Metadata with stale alternatives removed.
	 * @since 3.0.0
	 */
	public function invalidate_webp( $metadata, $attachment_id ) {
		self::reset_webp_cache();
		if ( is_array( $metadata ) && ! Media\MediaStore::is_publishing( $attachment_id ) ) {
			$previous = wp_get_attachment_metadata( $attachment_id, true );
			if ( is_array( $previous ) ) {
				$metadata = Media\MediaStore::preserve_retirement( $attachment_id, $previous, $metadata );
			}
			unset( $metadata['skye_webp'] );
		}
		return $metadata;
	}

	/**
	 * Clear only request-local presentation results after an explicit media write.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public static function reset_webp_cache() {
		self::$webp_cache = array();
	}

	/**
	 * Validate the complete namespace before the worker may atomically publish it.
	 *
	 * This shares the frontend's format/dimension contract without filesystem IO.
	 *
	 * @param array $metadata Native metadata plus proposed WebP alternatives.
	 * @return bool Whether every proposed size has a valid source binding.
	 * @since 3.0.0
	 */
	public static function validate_webp_metadata( array $metadata ) {
		$copy = self::webp_metadata( $metadata );
		$alternatives = $metadata['skye_webp']['sizes'] ?? null;
		if ( false === $copy || ! is_array( $alternatives ) || count( $copy['sizes'] ) !== count( $alternatives ) ) {
			return false;
		}
		foreach ( $metadata['sizes'] ?? array() as $name => $size ) {
			if ( ! is_array( $size ) || ! is_string( $size['file'] ?? null ) ) {
				return false;
			}
			if ( preg_match( '/\.(?:jpe?g|png)\z/i', $size['file'] ) && ! isset( $copy['sizes'][$name] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Validate a saved WebP record against its current native source variant.
	 *
	 * Filename, dimensions, format, and digest checks reject stale alternatives
	 * without introducing filesystem work into frontend image rendering.
	 *
	 * @param mixed $entry Proposed WebP manifest entry.
	 * @param string $source Expected native source filename without directories.
	 * @param int $width Expected native source width in pixels.
	 * @param int $height Expected native source height in pixels.
	 * @return bool Whether the complete entry matches the native source contract.
	 * @since 3.0.0 Backported from the completed Website media lifecycle.
	 */
	private static function valid_webp( $entry, $source, $width, $height ) {

		return is_array( $entry ) && is_string( $entry['file'] ?? null ) &&
			$entry['file'] !== '' && strpbrk( $entry['file'], "/\\:\0" ) === false &&
			strtolower( substr( $entry['file'], -5 ) ) === '.webp' &&
			( $entry['source'] ?? null ) === $source &&
			(int) ( $entry['width'] ?? 0 ) === (int) $width && (int) $width > 0 &&
			(int) ( $entry['height'] ?? 0 ) === (int) $height && (int) $height > 0 &&
			( $entry['mime-type'] ?? null ) === 'image/webp' && (int) ( $entry['filesize'] ?? 0 ) > 0 &&
			is_string( $entry['source_sha256'] ?? null ) && preg_match( '/\A[a-f0-9]{64}\z/', $entry['source_sha256'] ) === 1;
	}

	/**
	 * Build a metadata copy from the still verified published WebP alternatives.
	 *
	 * JPEG and PNG originals share the same explicit alternative format contract.
	 * Newly backfilled native sizes keep their own fallback until the worker can
	 * publish a complete replacement; valid older alternatives remain usable.
	 *
	 * @param array $metadata Original Core metadata and optional WebP manifest.
	 * @return array|false The validated WebP metadata copy, or false.
	 * @since 3.0.0
	 */
	private static function webp_metadata( array $metadata ) {

		$webp = $metadata['skye_webp'] ?? null;
		if ( ! is_array( $webp ) || ( $webp['version'] ?? null ) !== 1 || ( $webp['quality'] ?? null ) !== 82 ||
			! is_array( $webp['sizes'] ?? null ) || ( isset( $metadata['sizes'] ) && ! is_array( $metadata['sizes'] ) ) ||
			! is_string( $metadata['file'] ?? null ) || ( $webp['source_file'] ?? null ) !== $metadata['file'] ||
			! preg_match( '/\.(?:jpe?g|png)\z/i', $metadata['file'] ) ||
			! self::valid_webp( $webp['full'] ?? null, wp_basename( $metadata['file'] ), $metadata['width'] ?? 0, $metadata['height'] ?? 0 ) ) {
			return false;
		}

		$copy = $metadata;
		unset( $copy['skye_webp'] );
		$directory = dirname( $metadata['file'] );
		$copy['file'] = ( $directory === '.' ? '' : $directory . '/' ) . $webp['full']['file'];
		$copy['filesize'] = $webp['full']['filesize'];
		$copy['sizes'] = [];
		foreach ( $metadata['sizes'] ?? array() as $name => $size ) {
			if ( is_array( $size ) && self::valid_webp( $webp['sizes'][$name] ?? null, $size['file'] ?? '', $size['width'] ?? 0, $size['height'] ?? 0 ) ) {
				$copy['sizes'][$name] = $webp['sizes'][$name];
			}
		}

		return $copy;
	}

	/**
	 * Return a verified WebP source for image markup and optional hero preloads.
	 *
	 * Native WordPress size and srcset APIs retain crop selection and responsive
	 * behavior. Request-local caching avoids repeated metadata preparation, and
	 * unsupported or rewritten source URLs retain their native fallback.
	 *
	 * @param int $attachment_id Native attachment identifier.
	 * @param string|int[] $size Registered size name or requested width and height.
	 * @return array|false Source data with src, srcset, width, height, and jpeg_src
	 *                    keys, or false when no complete verified alternative exists.
	 * @since 3.0.0 Backported from the completed Website media lifecycle.
	 */
	public static function webp_source( $attachment_id, $size = 'full' ) {

		if ( ! MediaPipeline::enabled() ) {
			return false;
		}
		$attachment_id = (int) $attachment_id;
		$key = $attachment_id . ':' . serialize( $size );
		if ( array_key_exists( $key, self::$webp_cache ) ) {
			return self::$webp_cache[$key];
		}
		self::$webp_cache[$key] = false;
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $metadata ) || ( $copy = self::webp_metadata( $metadata ) ) === false ) {
			return false;
		}
		$image = wp_get_attachment_image_src( $attachment_id, $size, false );
		if ( ! $image || empty( $image[1] ) || empty( $image[2] ) || wp_parse_url( $image[0], PHP_URL_QUERY ) !== null ||
			! in_array( wp_parse_url( $image[0], PHP_URL_SCHEME ), [ 'https', 'http' ], true ) || ! wp_parse_url( $image[0], PHP_URL_HOST ) ||
			wp_parse_url( $image[0], PHP_URL_USER ) !== null ) {
			return false;
		}
		$original = rawurldecode( wp_basename( wp_parse_url( $image[0], PHP_URL_PATH ) ?? '' ) );
		$entry = null;
		if ( $original === wp_basename( $metadata['file'] ) ) {
			$entry = $metadata['skye_webp']['full'];
		} else {
			foreach ( $metadata['sizes'] ?? [] as $name => $variant ) {
				if ( is_array( $variant ) && ( $variant['file'] ?? null ) === $original && isset( $copy['sizes'][$name] ) ) {
					$entry = $copy['sizes'][$name];
					break;
				}
			}
		}
		if ( ! $entry ) {
			return false;
		}
		$src = substr( $image[0], 0, strrpos( $image[0], '/' ) + 1 ) . $entry['file'];
		$dimensions = [ (int) $image[1], (int) $image[2] ];
		$srcset = wp_calculate_image_srcset( $dimensions, $src, $copy, $attachment_id );
		if ( ! $srcset ) {
			$srcset = $src . ' ' . (int) $entry['width'] . 'w';
		}

		// Core filters may rewrite srcsets: never advertise JPEGs or another crop as WebP.
		$allowed = [ $metadata['skye_webp']['full']['file'] => $metadata['skye_webp']['full'] ];
		foreach ( $copy['sizes'] as $variant ) {
			$allowed[$variant['file']] = $variant;
		}
		foreach ( explode( ', ', $srcset ) as $candidate ) {
			if ( ! preg_match( '/\A(\S+) ([1-9][0-9]*)w\z/', $candidate, $parts ) ) {
				return false;
			}
			if ( ! in_array( wp_parse_url( $parts[1], PHP_URL_SCHEME ), [ 'https', 'http' ], true ) ||
				! wp_parse_url( $parts[1], PHP_URL_HOST ) || wp_parse_url( $parts[1], PHP_URL_USER ) !== null ) {
				return false;
			}
			$file = rawurldecode( wp_basename( wp_parse_url( $parts[1], PHP_URL_PATH ) ?? '' ) );
			if ( ! isset( $allowed[$file] ) || (int) $parts[2] !== (int) $allowed[$file]['width'] ||
				! wp_image_matches_ratio( (int) $entry['width'], (int) $entry['height'], (int) $allowed[$file]['width'], (int) $allowed[$file]['height'] ) ) {
				return false;
			}
		}
		self::$webp_cache[$key] = [ 'src' => $src, 'srcset' => $srcset,
			'width' => $dimensions[0], 'height' => $dimensions[1], 'jpeg_src' => $image[0] ];

		return self::$webp_cache[$key];
	}

	/**
	 * Wrap one native image with a verified WebP source and preserve its fallback.
	 *
	 * The original image HTML retains alt text, sizes, and loading priority.
	 * Existing pictures, unsupported markup, missing components and admin output
	 * remain unchanged. The shared capture helper restores buffers on errors.
	 *
	 * @param string $html Native attachment image HTML supplied by WordPress.
	 * @param int $attachment_id Native attachment identifier.
	 * @param string|int[] $size Registered size name or requested width and height.
	 * @param bool $icon Whether WordPress requested an attachment icon.
	 * @param array $attributes Native image attributes supplied by the filter.
	 * @return string Picture markup when a verified source exists, otherwise the
	 *                original image HTML without alteration.
	 * @since 3.0.0 Backported from the completed Website media lifecycle.
	 */
	public function picture( $html, $attachment_id, $size, $icon, $attributes ) {

		if ( $icon || ! is_string( $html ) || $html === '' || is_admin() || ! class_exists( '\WP_HTML_Tag_Processor' ) ||
			stripos( $html, '<picture' ) !== false || ( $source = self::webp_source( $attachment_id, $size ) ) === false ) {
			return $html;
		}
		$tag = new \WP_HTML_Tag_Processor( $html );
		if ( ! method_exists( $tag, 'next_token' ) || ! $tag->next_token() ) {
			return $html;
		}
		while ( $tag->get_token_type() === '#text' && trim( $tag->get_modifiable_text() ) === '' ) {
			if ( ! $tag->next_token() ) {
				return $html;
			}
		}
		if ( $tag->get_tag() !== 'IMG' || $tag->is_tag_closer() || $tag->get_attribute( 'src' ) !== $source['jpeg_src'] ) {
			return $html;
		}
		$sizes = $tag->get_attribute( 'sizes' );
		while ( $tag->next_token() ) {
			if ( $tag->get_token_type() !== '#text' || trim( $tag->get_modifiable_text() ) !== '' ) {
				return $html;
			}
		}
		if ( ! function_exists( 'skye_component_html' ) ) {
			return $html;
		}
		$picture = skye_component_html( 'media', 'picture', array( 'native_image' => $html, 'webp_source' => $source, 'sizes' => $sizes ) );
		return trim( $picture ) !== '' ? $picture : $html;
	}

	/**
	 * Register explicit theme sizes while leaving Core defaults available.
	 *
	 * Child-theme configuration takes precedence. Empty configuration is valid;
	 * a starter theme must not warn merely because no custom crop is necessary.
	 * Malformed definitions are skipped rather than changing Core size contracts.
	 *
	 * @return void
	 * @since 2021-03-04 First recorded size registration in Skye history.
	 * @since 3.0.0 Reworked the existing size registration contract.
	 */
	public function images() {
		$path = locate_template( 'lib/config/image_sizes.php', false, false );
		$rs_image_sizes = array();
		if ( is_string( $path ) && $path !== '' && is_readable( $path ) ) {
			include $path;
		}
		$definitions = apply_filters( 'skye_image_sizes', $rs_image_sizes );
		if ( ! is_array( $definitions ) ) {
			return;
		}
		foreach ( $definitions as $name => $definition ) {
			if ( ! is_string( $name ) || $name === '' || ! is_array( $definition ) ) {
				continue;
			}
			$width = $definition['width'] ?? null;
			$height = $definition['height'] ?? null;
			$crop = $definition['crop'] ?? false;
			if ( ! is_int( $width ) || ! is_int( $height ) || $width < 0 || $height < 0 || ( $width === 0 && $height === 0 ) ) {
				continue;
			}
			if ( is_array( $crop ) ) {
				if ( count( $crop ) !== 2 || ! isset( $crop[0], $crop[1] ) ||
					! in_array( $crop[0], array( 'left', 'center', 'right' ), true ) ||
					! in_array( $crop[1], array( 'top', 'center', 'bottom' ), true ) ) {
					continue;
				}
			} elseif ( ! is_bool( $crop ) ) {
				continue;
			}
			add_image_size( $name, $width, $height, $crop );
		}
	}

	/**
	 * Preserve the legacy notice callback without imposing project-specific crops.
	 *
	 * Native sizes remain usable with an empty theme configuration, so no warning
	 * is justified. Kept as a no-op for callers from existing Skye descendants.
	 *
	 * @return void
	 * @since 2021-03-04 First recorded notice callback in Skye history.
	 * @since 3.0.0 Replaced the previous prefix-based warning.
	 */
	public function check_images() {
	}

}
