<?php
/**
 * Add missing responsive native sizes through bounded, lease-guarded background work.
 *
 * @see https://developer.wordpress.org/reference/functions/wp_get_missing_image_subsizes/
 * @see https://developer.wordpress.org/reference/classes/wp_image_editor/resize/
 * @see https://developer.wordpress.org/reference/classes/wp_image_editor/save/
 * @since 3.0.0
 */

namespace Rocketsky\Skye\Packages\Media;

/**
 * Use registered native size definitions for additive background backfill.
 *
 * Dimensions come from Core registration rather than a project crop inventory.
 * File inspection and native image editing are confined to process(), which
 * requires the existing worker lease.
 *
 * @since 3.0.0
 */
class NativeSizes {

	/** @var array<string,string> Exclusively reserved output paths and planned MIME types. */
	private static $targets = array();

	/** @var int Native reservations attempted by the current bounded invocation. */
	private static $attempted = 0;

	/** @var int Nested native processes sharing one reservation allowance. */
	private static $process_depth = 0;
	/** @var int Maximum reservations allowed by the outermost caller. */
	private static $native_limit = 2;
	/** @var float Absolute deadline shared by nested native work. */
	private static $process_deadline = 0.0;
	/** @var int Active saves sharing the scoped output-format guard. */
	private static $filter_depth = 0;

	/**
	 * Return the additive size schema shared by registration and background work.
	 *
	 * Existing named sizes and their crops remain untouched for older content.
	 *
	 * @return array Named native WordPress width, height, and crop definitions.
	 * @since 3.0.0
	 */
	public static function definitions() {
		return wp_get_registered_image_subsizes();
	}

	/**
	 * Append at most the requested missing sizes under the caller's worker budget.
	 *
	 * Current Core fields, files, and WebP namespaces survive every partial pass.
	 * The native missing-size API excludes impossible upscales. Original JPEG/PNG
	 * sources are read afresh for each resize, including native EXIF orientation.
	 *
	 * @param int $attachment_id Native attachment identifier.
	 * @param string $revision Exact persistent job owned by the caller.
	 * @param int $limit Maximum new native files to create in this invocation.
	 * @param float $seconds Remaining shared soft budget between codec operations.
	 * @return array Status, committed count, shared reservation count, and optional error code.
	 * @since 3.0.0
	 */
	public static function process( $attachment_id, $revision, $limit = 2, $seconds = 6 ) {
		$deadline = microtime( true ) + max( 0, (float) $seconds );
		if ( self::$process_depth === 0 ) {
			self::$attempted = 0;
			self::$native_limit = max( 0, min( 2, (int) $limit ) );
			self::$process_deadline = $deadline;
		}
		$deadline = min( $deadline, self::$process_deadline );
		self::$process_depth++;
		$snapshot = false;
		$source = false;
		$created = array();
		$committed = false;
		try {
			$snapshot = MediaStore::snapshot( (int) $attachment_id, $revision );
			if ( ! is_array( $snapshot ) || ! MediaStore::current( $snapshot ) ) {
				return self::result( 'stale', 0, 'revision_changed' );
			}
			require_once ABSPATH . 'wp-admin/includes/image.php';
			wp_cache_delete( $attachment_id, 'post_meta' );
			$missing = array_intersect_key( wp_get_missing_image_subsizes( $attachment_id ), self::definitions() );
			if ( ! $missing ) {
				return self::result( 'complete' );
			}
			if ( ! is_finite( (float) $seconds ) || $seconds <= 0 || $limit <= 0 || microtime( true ) >= $deadline ) {
				return self::result( 'pending' );
			}
			$source = self::source( $snapshot );
			if ( is_wp_error( $source ) ) {
				return self::result( $source->get_error_code() === 'native_animated_png' ? 'unsupported' : 'retry', 0, $source->get_error_code() );
			}
			$entries = array();
			foreach ( $missing as $name => $size ) {
				if ( count( $entries ) >= min( 2, (int) $limit ) || self::$attempted >= self::$native_limit || microtime( true ) >= $deadline ) {
					break;
				}
				if ( ! MediaStore::current( $snapshot ) ) {
					return self::result( 'stale', 0, 'revision_changed' );
				}
				$entry = self::encode( $snapshot, $source, $name, $size, $created );
				if ( is_wp_error( $entry ) ) {
					return self::result( $entry->get_error_code() === 'native_output_format' ? 'deferred_format' : 'retry', 0, $entry->get_error_code() );
				}
				$entries[$name] = $entry;
			}
			if ( ! $entries ) {
				return self::result( 'pending' );
			}
			clearstatcache( true, $source['path'] );
			if ( hash_file( 'sha256', $source['path'] ) !== $source['sha256'] ) {
				return self::result( 'retry', 0, 'source_changed' );
			}
			if ( ! MediaStore::append_sizes( $snapshot, $entries ) ) {
				return self::result( MediaStore::current( $snapshot ) ? 'retry' : 'stale', 0, 'native_commit_failed' );
			}
			$committed = true;
			return self::result( count( $entries ) === count( $missing ) ? 'complete' : 'pending', count( $entries ) );
		} catch ( \Throwable $error ) {
			return self::result( 'retry', 0, 'native_worker_exception' );
		} finally {
			if ( ! $committed && is_array( $snapshot ) && is_array( $source ) ) {
				self::cleanup( $snapshot, $source, $created );
			}
			self::$process_depth--;
		}
	}

	/**
	 * Verify an original local JPEG/PNG source without trusting cached file pointers.
	 *
	 * @param array $snapshot Current native metadata and ownership guard.
	 * @return array|\WP_Error Canonical source identity, directory and byte digest.
	 * @since 3.0.0
	 */
	private static function source( array $snapshot ) {
		$metadata = $snapshot['metadata'];
		$upload = wp_get_upload_dir();
		$base = ! empty( $upload['basedir'] ) ? realpath( $upload['basedir'] ) : false;
		$attached = is_string( $snapshot['attached_file'] ?? null ) ? realpath( $snapshot['attached_file'] ) : false;
		$file = $metadata['file'] ?? null;
		if ( $base === false || $attached === false || ! is_string( $file ) || ! empty( $upload['error'] )
			|| ! self::relative_path( $file ) || realpath( $base . '/' . $file ) !== $attached || is_link( $base . '/' . $file )
			|| strpos( $attached, $base . DIRECTORY_SEPARATOR ) !== 0 ) {
			return new \WP_Error( 'native_source_path' );
		}
		$original = ! empty( $metadata['original_image'] ) ? $metadata['original_image'] : wp_basename( $attached );
		if ( ! is_string( $original ) || wp_basename( $original ) !== $original || false !== strpbrk( $original, "/\\:\0" ) ) {
			return new \WP_Error( 'native_source_path' );
		}
		$path = dirname( $attached ) . '/' . $original;
		$real = realpath( $path );
		if ( $real === false || dirname( $real ) !== dirname( $attached ) || is_link( $path ) || ! is_file( $real ) || ! is_readable( $real ) ) {
			return new \WP_Error( 'native_source_unavailable' );
		}
		$image = wp_getimagesize( $real );
		$sha = hash_file( 'sha256', $real );
		if ( ! $image || ! in_array( $image['mime'] ?? '', array( 'image/jpeg', 'image/png' ), true ) || ! is_string( $sha ) ) {
			return new \WP_Error( 'native_source_format' );
		}
		if ( $image['mime'] === 'image/png' ) {
			$animated = WebpGenerator::animated_png( $real );
			if ( is_wp_error( $animated ) ) {
				return new \WP_Error( 'native_png_header' );
			}
			if ( $animated ) {
				return new \WP_Error( 'native_animated_png' );
			}
		}
		return array( 'path' => $real, 'directory' => dirname( $attached ), 'relative_directory' => dirname( $file ), 'mime' => $image['mime'], 'sha256' => $sha );
	}

	/**
	 * Reserve, resize, and verify one owned native file without altering its master.
	 *
	 * A scoped final output-format guard pins only this exclusively reserved path.
	 * Ordinary format filters are consulted first; JPEG/PNG mappings are honored.
	 * Unsupported mappings retry without producing or publishing an unowned file.
	 *
	 * @param array $snapshot Current metadata and worker ownership snapshot.
	 * @param array $source Verified local original source identity.
	 * @param string $name New registered size name.
	 * @param array $size Native missing-size dimensions and crop contract.
	 * @param array $created Exclusively reserved paths owned by this invocation.
	 * @return array|\WP_Error Verified native size entry or bounded failure code.
	 * @since 3.0.0
	 */
	private static function encode( array $snapshot, array $source, $name, array $size, array &$created ) {
		$formats = apply_filters( 'image_editor_output_format', array(), $source['path'], $source['mime'] );
		$mime = is_array( $formats ) ? ( $formats[$source['mime']] ?? $source['mime'] ) : $source['mime'];
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) {
			return new \WP_Error( 'native_output_format' );
		}
		$extension = $mime === 'image/png' ? '.png' : '.jpg';
		$signature = substr( hash( 'sha256', $source['sha256'] . serialize( array( $name, $size, $mime ) ) ), 0, 12 );
		$id = (int) $snapshot['attachment_id'];
		$file = MediaStore::filename_stem( wp_basename( $snapshot['metadata']['file'] ) ) . '-' . $name . '-skye-native-' . $id . '-v1-' . $signature . $extension;
		$file = wp_unique_filename( $source['directory'], $file );
		if ( ! is_string( $file ) || wp_basename( $file ) !== $file || ! preg_match( '/-skye-native-' . $id . '-v1-' . $signature . '(?:-[0-9]+)?' . preg_quote( $extension, '/' ) . '\z/', $file ) ) {
			return new \WP_Error( 'native_output_name' );
		}
		$relative = ( $source['relative_directory'] === '.' ? '' : $source['relative_directory'] . '/' ) . $file;
		if ( ! MediaStore::remember_file( $snapshot, $relative ) ) {
			return new \WP_Error( 'native_ownership' );
		}
		$path = $source['directory'] . '/' . $file;
		self::$attempted++;
		$handle = fopen( $path, 'x' );
		if ( $handle === false ) {
			return new \WP_Error( 'native_reservation' );
		}
		fclose( $handle );
		$created[] = $path;
		self::$targets[$path] = $mime;
		if ( self::$filter_depth === 0 ) {
			add_filter( 'image_editor_output_format', array( __CLASS__, 'output_format' ), PHP_INT_MAX, 3 );
		}
		self::$filter_depth++;
		try {
			$editor = wp_get_image_editor( $source['path'], array( 'mime_type' => $source['mime'], 'output_mime_type' => $mime ) );
			if ( is_wp_error( $editor ) ) {
				return new \WP_Error( 'native_editor_load' );
			}
			if ( ! empty( $snapshot['metadata']['image_meta'] ) && is_wp_error( $editor->maybe_exif_rotate() ) ) {
				return new \WP_Error( 'native_editor_orientation' );
			}
			$dimensions = $editor->get_size();
			$width = (int) ( $size['width'] ?? 0 );
			$height = (int) ( $size['height'] ?? 0 );
			$crop = is_bool( $size['crop'] ?? null ) || is_array( $size['crop'] ?? null ) ? $size['crop'] : false;
			$expected = image_resize_dimensions( $dimensions['width'], $dimensions['height'], $width, $height, $crop );
			if ( ! $expected || is_wp_error( $editor->resize( $width ?: null, $height ?: null, $crop ) ) ) {
				return new \WP_Error( 'native_editor_resize' );
			}
			$saved = $editor->save( $path, $mime );
			clearstatcache( true, $path );
			$image = wp_getimagesize( $path );
			if ( is_wp_error( $saved ) || ! is_array( $saved ) || ( $saved['path'] ?? null ) !== $path || ( $saved['file'] ?? null ) !== $file
				|| ( $saved['mime-type'] ?? null ) !== $mime || ! $image || $image[0] !== $expected[4] || $image[1] !== $expected[5]
				|| ( $image['mime'] ?? null ) !== $mime || is_link( $path ) || filesize( $path ) < 1 ) {
				return new \WP_Error( 'native_output_verification' );
			}
			return array( 'file' => $file, 'width' => $image[0], 'height' => $image[1], 'mime-type' => $mime, 'filesize' => filesize( $path ) );
		} finally {
			unset( self::$targets[$path] );
			self::$filter_depth--;
			if ( self::$filter_depth === 0 ) {
				remove_filter( 'image_editor_output_format', array( __CLASS__, 'output_format' ), PHP_INT_MAX );
			}
		}
	}

	/**
	 * Keep only this encoder's exact reserved native destination in its planned format.
	 *
	 * @param array $formats Existing native output-format mappings.
	 * @param string $filename Native editor destination path.
	 * @param string $mime Current requested format.
	 * @return array Existing mappings with the owned destination pinned safely.
	 * @since 3.0.0
	 */
	public static function output_format( $formats, $filename, $mime ) {
		if ( isset( self::$targets[$filename] ) ) {
			$formats[$mime] = self::$targets[$filename];
		}
		return $formats;
	}

	/**
	 * Remove only this invocation's unpublished reservations when ownership is clear.
	 *
	 * Ambiguous lost-lease outputs stay inventoried for safe later cleanup.
	 *
	 * @param array $snapshot Original guarded metadata snapshot.
	 * @param array $source Canonical source and output directory.
	 * @param array $created Paths exclusively created by this invocation.
	 * @return void
	 * @since 3.0.0
	 */
	private static function cleanup( array $snapshot, array $source, array $created ) {
		try {
			if ( ! MediaStore::current( $snapshot ) && ! MediaStore::deleted( $snapshot ) ) {
				return;
			}
			foreach ( $created as $path ) {
				if ( dirname( $path ) === $source['directory'] && ! is_link( $path ) && is_file( $path ) ) {
					wp_delete_file_from_directory( $path, $source['directory'] );
				}
			}
		} catch ( \Throwable $error ) {
			// The durable inventory remains available for later safe maintenance.
		}
	}

	/**
	 * Reject unsafe uploads-relative source paths before resolving local files.
	 *
	 * @param mixed $path Native metadata path.
	 * @return bool Whether each nonempty component remains inside uploads.
	 * @since 3.0.0
	 */
	private static function relative_path( $path ) {
		return is_string( $path ) && $path !== '' && $path[0] !== '/' && false === strpbrk( $path, "\\:\0" ) && ! in_array( '..', explode( '/', $path ), true );
	}

	/**
	 * Return a machine-readable result without exposing paths, captions, or bytes.
	 *
	 * @param string $status Worker state.
	 * @param int $created Number of committed new native sizes.
	 * @param string $error Optional bounded error code.
	 * @return array{status:string,created:int,attempted:int,error?:string} Worker state, committed and attempted size counts, and optional error code.
	 * @since 3.0.0
	 */
	private static function result( $status, $created = 0, $error = '' ) {
		$result = array( 'status' => $status, 'created' => $created, 'attempted' => self::$attempted );
		if ( $error !== '' ) {
			$result['error'] = $error;
		}
		return $result;
	}
}
