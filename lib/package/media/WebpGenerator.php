<?php
/**
 * Incremental native WebP generation for an exclusively leased media revision.
 *
 * Original files and Core metadata remain unchanged. MediaStore owns snapshots,
 * leases, progress and publication; this worker only prepares verified sidecars.
 *
 * @see https://developer.wordpress.org/reference/functions/wp_get_image_editor/
 * @see https://developer.wordpress.org/reference/classes/wp_image_editor/save/
 * @see https://developer.wordpress.org/reference/hooks/image_editor_output_format/
 * @see https://developer.wordpress.org/reference/functions/wp_unique_filename/
 * @see https://developer.wordpress.org/reference/functions/wp_delete_file_from_directory/
 * @since 3.0.0
 */

namespace Rocketsky\Skye\Packages\Media;

/**
 * Prepare complete alternative sets without encoding in a frontend image call.
 *
 * @since 3.0.0
 */
class WebpGenerator {

	const QUALITY = 82;

	/** @var array<string,bool> Exact exclusively reserved paths currently being encoded. */
	private static $targets = [];
	/** @var int Nested worker encodes sharing the same temporary Core filters. */
	private static $filter_depth = 0;

	/**
	 * Advance one revision and publish only after every eligible source is ready.
	 *
	 * File/time budgets are checked between native operations; a single native
	 * decoder/encoder cannot be interrupted safely. No partial set is published.
	 * Every physical WebP must be strictly smaller than its exact native source;
	 * one unfavorable variant keeps the entire native set in use.
	 * Errors expose machine codes only, never filesystem paths or image metadata.
	 *
	 * @param int    $attachment_id Native attachment ID.
	 * @param string $revision      Exact opaque pending token owned by the caller.
	 * @param int    $limit         Maximum new files to encode in this invocation.
	 * @param float  $seconds       Soft elapsed-time budget between operations.
	 * @return array{status:string,error?:string} Complete, pending, retry, unsupported or stale.
	 * @since 3.0.0
	 */
	public static function process( $attachment_id, $revision, $limit = 3, $seconds = 6 ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id < 1 || ! is_string( $revision ) || $revision === '' ) {
			return self::result( 'unsupported', 'invalid_job' );
		}
		$limit = max( 0, (int) $limit );
		$seconds = (float) $seconds;
		if ( ! is_finite( $seconds ) || $seconds < 0 ) {
			return self::result( 'retry', 'invalid_budget' );
		}
		$deadline = microtime( true ) + $seconds;
		$snapshot = false;
		$plan = false;
		$created = [];
		$retain = false;

		try {
			$snapshot = MediaStore::snapshot( $attachment_id, $revision );
			if ( ! is_array( $snapshot ) || ! MediaStore::current( $snapshot ) ) {
				return self::result( 'stale', 'revision_changed' );
			}
			$plan = self::plan( $snapshot );
			if ( is_wp_error( $plan ) ) {
				return self::problem_result( $plan );
			}
			if ( ! wp_image_editor_supports( [ 'mime_type' => 'image/webp' ] ) ) {
				return self::result( 'retry', 'webp_unavailable' );
			}
			$progress = MediaStore::progress( $snapshot );
			if ( ! MediaStore::current( $snapshot ) ) {
				return self::result( 'stale', 'revision_changed' );
			}
			if ( is_array( $progress ) && ! empty( $progress['fingerprint'] ) && $progress['fingerprint'] !== $plan['fingerprint'] ) {
				return self::result( 'stale', 'source_changed' );
			}
			$previous = is_array( $progress['entries'] ?? null ) ? $progress['entries'] : [];
			$legacy = self::legacy_entries( $snapshot['metadata'] );
			$entries = [];
			$encoded = 0;

			foreach ( $plan['files'] as $name => $source ) {
				$candidates = [ $previous[$name] ?? null, $legacy[$name] ?? null ];
				foreach ( $candidates as $candidate ) {
					if ( self::valid_entry( $candidate, $source, $plan['directory'] ) ) {
						$entries[$name] = $candidate;
						break;
					}
				}
				if ( isset( $entries[$name] ) || $encoded >= $limit || microtime( true ) >= $deadline ) {
					continue;
				}
				if ( ! MediaStore::current( $snapshot ) ) {
					return self::result( 'stale', 'revision_changed' );
				}
				$entry = self::encode( $snapshot, $plan, $source, $created );
				if ( is_wp_error( $entry ) ) {
					return self::problem_result( $entry );
				}
				$entries[$name] = $entry;
				$encoded++;
			}

			if ( ! self::sources_unchanged( $plan ) ) {
				return self::result( 'stale', 'source_changed' );
			}
			if ( ! MediaStore::current( $snapshot ) ) {
				return self::result( 'stale', 'revision_changed' );
			}
			foreach ( $entries as $name => $entry ) {
				$path = $plan['directory'] . '/' . $entry['file'];
				clearstatcache( true, $path );
				$bytes = ! is_link( $path ) && is_file( $path ) ? filesize( $path ) : false;
				if ( ! is_int( $bytes ) || $bytes !== (int) $entry['filesize'] ) {
					return self::result( 'retry', 'output_changed' );
				}
				// Both values are physical byte counts, never Core metadata or filtered sizes.
				if ( $bytes >= $plan['files'][$name]['bytes'] ) {
					if ( ! MediaStore::withdraw_webp( $snapshot ) ) {
						return self::result( MediaStore::current( $snapshot ) ? 'retry' : 'stale', 'withdrawal_not_saved' );
					}
					return self::result( 'unsupported', 'not_smaller' );
				}
			}
			if ( count( $entries ) !== count( $plan['files'] ) ) {
				if ( ! MediaStore::progress( $snapshot, [ 'fingerprint' => $plan['fingerprint'], 'entries' => $entries ] ) ) {
					return self::result( MediaStore::current( $snapshot ) ? 'retry' : 'stale', 'progress_not_saved' );
				}
				$retain = true;
				return self::result( 'pending' );
			}
			$alternatives = self::alternatives( $snapshot['metadata'], $plan, $entries );
			if ( ! MediaStore::publish( $snapshot, $alternatives ) ) {
				return self::result( MediaStore::current( $snapshot ) ? 'retry' : 'stale', 'publication_not_saved' );
			}
			$retain = true;
			return self::result( 'complete' );
		} catch ( \Throwable $error ) {
			return self::result( 'retry', 'worker_exception' );
		} finally {
			if ( ! $retain && is_array( $snapshot ) && is_array( $plan ) && ! is_wp_error( $plan ) ) {
				self::cleanup( $snapshot, $plan, $created );
			}
		}
	}

	/**
	 * Describe only current local JPEG/PNG files, deduplicating native size aliases.
	 *
	 * Source hashes form part of progress identity. GIF, WebP, AVIF and animated
	 * PNG masters remain untouched instead of silently discarding animation.
	 *
	 * @param array $snapshot Lease-bound native metadata and absolute attached file.
	 * @return array|\WP_Error Source plan or a bounded machine-readable failure.
	 * @since 3.0.0
	 */
	private static function plan( array $snapshot ) {
		$metadata = $snapshot['metadata'] ?? null;
		if ( ! is_array( $metadata ) || ! self::relative_path( $metadata['file'] ?? null ) ||
			( isset( $metadata['sizes'] ) && ! is_array( $metadata['sizes'] ) ) || count( $metadata['sizes'] ?? [] ) > 128 ) {
			return self::problem( 'source_metadata', 'unsupported' );
		}
		$mime = $snapshot['mime_type'] ?? '';
		if ( ! in_array( $mime, [ 'image/jpeg', 'image/png' ], true ) ) {
			return self::problem( 'source_format', 'unsupported' );
		}
		$upload = wp_get_upload_dir();
		$base = ! empty( $upload['basedir'] ) ? realpath( $upload['basedir'] ) : false;
		$master = is_string( $snapshot['attached_file'] ?? null ) ? realpath( $snapshot['attached_file'] ) : false;
		$native_path = $base === false ? '' : $base . '/' . $metadata['file'];
		if ( ! empty( $upload['error'] ) || $base === false || $master === false ||
			strpos( $master, $base . DIRECTORY_SEPARATOR ) !== 0 || realpath( $native_path ) !== $master ||
			is_link( $native_path ) || dirname( $native_path ) !== dirname( $master ) ) {
			return self::problem( 'source_path' );
		}
		$directory = dirname( $master );
		$full_name = wp_basename( $metadata['file'] );
		$full = self::source( $directory . '/' . $full_name, $directory, $metadata['width'] ?? 0, $metadata['height'] ?? 0 );
		if ( is_wp_error( $full ) ) {
			return $full;
		}
		if ( $full['mime'] !== $mime ) {
			return self::problem( 'source_mime', 'unsupported' );
		}
		$files = [ $full_name => $full ];
		$variants = [];
		foreach ( $metadata['sizes'] ?? [] as $name => $size ) {
			if ( ! is_array( $size ) || ! is_string( $size['file'] ?? null ) ) {
				return self::problem( 'source_metadata', 'unsupported' );
			}
			// Existing alternative formats keep their Core pointers and are not encoded again.
			if ( ! preg_match( '/\.(?:jpe?g|png)\z/i', $size['file'] ) ) {
				continue;
			}
			if ( ! self::basename( $size['file'] ) ) {
				return self::problem( 'source_path', 'unsupported' );
			}
			if ( isset( $files[$size['file']] ) ) {
				// Shared files are inspected and hashed once; aliases still need matching native dimensions.
				$source = $files[$size['file']];
				if ( (int) ( $size['width'] ?? 0 ) !== $source['width'] || (int) ( $size['height'] ?? 0 ) !== $source['height'] ) {
					return self::problem( 'source_alias', 'unsupported' );
				}
			} else {
				$source = self::source( $directory . '/' . $size['file'], $directory, $size['width'] ?? 0, $size['height'] ?? 0 );
				if ( is_wp_error( $source ) ) {
					return $source;
				}
			}
			$files[$size['file']] = $source;
			$variants[$name] = $size['file'];
		}
		$native = $metadata;
		unset( $native['skye_webp'] );
		$fingerprint = hash( 'sha256', serialize( [ $native, $files, $snapshot['attached_file'], $mime ] ) );
		return [ 'directory' => $directory, 'relative_directory' => dirname( $metadata['file'] ),
			'full' => $full_name, 'files' => $files, 'variants' => $variants, 'fingerprint' => $fingerprint ];
	}

	/**
	 * Verify one native source without changing orientation, crop or stored bytes.
	 *
	 * @param string $path      Existing absolute candidate path.
	 * @param string $directory Canonical attachment directory.
	 * @param int    $width     Required native pixel width.
	 * @param int    $height    Required native pixel height.
	 * @return array|\WP_Error Canonical source identity or failure.
	 * @since 3.0.0
	 */
	private static function source( $path, $directory, $width, $height ) {
		$real = realpath( $path );
		if ( $real === false || dirname( $real ) !== $directory || is_link( $path ) || ! is_file( $real ) || ! is_readable( $real ) ) {
			return self::problem( 'source_unavailable' );
		}
		clearstatcache( true, $real );
		$image = wp_getimagesize( $real );
		if ( ! $image || ! in_array( $image['mime'] ?? '', [ 'image/jpeg', 'image/png' ], true ) ||
			(int) $width < 1 || (int) $height < 1 || $image[0] !== (int) $width || $image[1] !== (int) $height ) {
			return self::problem( 'source_dimensions', 'unsupported' );
		}
		if ( $image['mime'] === 'image/png' ) {
			$animated = self::animated_png( $real );
			if ( is_wp_error( $animated ) ) {
				return $animated;
			}
			if ( $animated ) {
				return self::problem( 'animated_png', 'unsupported' );
			}
		}
		$bytes = filesize( $real );
		$sha = hash_file( 'sha256', $real );
		if ( ! is_int( $bytes ) || $bytes < 1 || ! is_string( $sha ) || strlen( $sha ) !== 64 ) {
			return self::problem( 'source_hash' );
		}
		return [ 'path' => $real, 'file' => wp_basename( $path ), 'width' => $image[0],
			'height' => $image[1], 'mime' => $image['mime'], 'bytes' => $bytes, 'sha256' => $sha ];
	}

	/**
	 * Inspect PNG chunk headers before IDAT so animated PNGs are never flattened.
	 *
	 * NativeSizes shares this same fail-closed contract during additive resizing.
	 *
	 * Chunk payloads are skipped, not loaded into memory. A malformed or excessive
	 * header sequence fails conservatively instead of assuming a static image.
	 *
	 * @param string $path Verified local PNG path.
	 * @return bool|\WP_Error Whether acTL precedes IDAT, or an invalid-header failure.
	 * @since 3.0.0
	 */
	public static function animated_png( $path ) {
		$handle = fopen( $path, 'rb' );
		if ( $handle === false ) {
			return self::problem( 'png_unavailable' );
		}
		try {
			if ( fread( $handle, 8 ) !== "\x89PNG\r\n\x1a\n" ) {
				return self::problem( 'png_header', 'unsupported' );
			}
			$bytes = filesize( $path );
			for ( $count = 0; $count < 1024; $count++ ) {
				$header = fread( $handle, 8 );
				if ( ! is_string( $header ) || strlen( $header ) !== 8 ) {
					return self::problem( 'png_header', 'unsupported' );
				}
				$chunk = unpack( 'Nlength/a4type', $header );
				if ( $chunk['type'] === 'acTL' ) {
					return true;
				}
				if ( $chunk['type'] === 'IDAT' ) {
					return false;
				}
				if ( $chunk['type'] === 'IEND' || $chunk['length'] > $bytes - ftell( $handle ) - 4 ||
					fseek( $handle, $chunk['length'] + 4, SEEK_CUR ) !== 0 ) {
					return self::problem( 'png_header', 'unsupported' );
				}
			}
			return self::problem( 'png_header', 'unsupported' );
		} finally {
			fclose( $handle );
		}
	}

	/**
	 * Collect published v1 candidates without trusting or deleting their files.
	 *
	 * Every returned candidate still passes source-hash and full decode checks
	 * before reuse. Saved publications may be reused only after the same source verification.
	 *
	 * @param array $metadata Native attachment metadata including an optional namespace.
	 * @return array<string,array> Potential entries keyed by native source basename.
	 * @since 3.0.0
	 */
	private static function legacy_entries( array $metadata ) {
		$legacy = $metadata['skye_webp'] ?? null;
		if ( ! is_array( $legacy ) || ( $legacy['version'] ?? null ) !== 1 || ( $legacy['quality'] ?? null ) !== self::QUALITY ||
			( $legacy['source_file'] ?? null ) !== ( $metadata['file'] ?? null ) ) {
			return [];
		}
		$entries = [];
		$candidates = is_array( $legacy['sizes'] ?? null ) ? array_values( $legacy['sizes'] ) : [];
		$candidates[] = $legacy['full'] ?? null;
		foreach ( $candidates as $entry ) {
			if ( is_array( $entry ) && self::basename( $entry['source'] ?? null ) ) {
				$entries[$entry['source']] = $entry;
			}
		}
		return $entries;
	}

	/**
	 * Validate an existing sidecar against its source and decode it natively.
	 *
	 * Metadata alone, a WebP suffix or valid image headers are insufficient for
	 * resumable work. Existing invalid/legacy files are never removed here.
	 *
	 * @param mixed  $entry     Candidate namespace/progress entry.
	 * @param array  $source    Current verified source identity.
	 * @param string $directory Canonical attachment directory.
	 * @return bool Whether the exact source and a decodable WebP still agree.
	 * @since 3.0.0
	 */
	private static function valid_entry( $entry, array $source, $directory ) {
		if ( ! is_array( $entry ) || ! self::basename( $entry['file'] ?? null ) || ! preg_match( '/\.webp\z/i', $entry['file'] ) ||
			( $entry['source'] ?? null ) !== $source['file'] || ( $entry['source_sha256'] ?? null ) !== $source['sha256'] ||
			( $entry['mime-type'] ?? null ) !== 'image/webp' || (int) ( $entry['width'] ?? 0 ) !== $source['width'] ||
			(int) ( $entry['height'] ?? 0 ) !== $source['height'] || (int) ( $entry['filesize'] ?? 0 ) < 1 ) {
			return false;
		}
		$path = $directory . '/' . $entry['file'];
		$real = realpath( $path );
		if ( $real === false || dirname( $real ) !== $directory || is_link( $path ) || ! is_file( $real ) || ! is_readable( $real ) ) {
			return false;
		}
		clearstatcache( true, $real );
		$image = wp_getimagesize( $real );
		if ( ! $image || ( $image['mime'] ?? '' ) !== 'image/webp' || $image[0] !== $source['width'] ||
			$image[1] !== $source['height'] || filesize( $real ) !== (int) $entry['filesize'] ) {
			return false;
		}
		$editor = wp_get_image_editor( $real, [ 'mime_type' => 'image/webp' ] );
		if ( is_wp_error( $editor ) ) {
			return false;
		}
		$size = $editor->get_size();
		return is_array( $size ) && (int) ( $size['width'] ?? 0 ) === $source['width'] && (int) ( $size['height'] ?? 0 ) === $source['height'];
	}

	/**
	 * Reserve and encode one owned sidecar, recording ownership before file work.
	 *
	 * Existing files are protected by both Core uniqueness and exclusive creation.
	 * Temporary format/quality filters are scoped to the native encode and removed
	 * even when an editor or third-party filter throws.
	 *
	 * @param array $snapshot Current MediaStore lease snapshot.
	 * @param array $plan     Current source plan and canonical output directory.
	 * @param array $source   Verified JPEG/PNG source.
	 * @param array $created  This invocation's exclusively created absolute paths.
	 * @return array|\WP_Error Verified v1 entry or bounded failure.
	 * @since 3.0.0
	 */
	private static function encode( array $snapshot, array $plan, array $source, array &$created ) {
		$id = (int) $snapshot['attachment_id'];
		$name = MediaStore::filename_stem( $source['file'] ) . '-skye-webp-' . $id . '-q82-' . substr( $source['sha256'], 0, 12 ) . '.webp';
		$name = wp_unique_filename( $plan['directory'], $name );
		if ( ! self::basename( $name ) || ! preg_match( '/-skye-webp-' . $id . '-q82-' . substr( $source['sha256'], 0, 12 ) . '(?:-[0-9]+)?\.webp\z/', $name ) ) {
			return self::problem( 'output_name' );
		}
		$relative = ( $plan['relative_directory'] === '.' ? '' : $plan['relative_directory'] . '/' ) . $name;
		if ( ! MediaStore::remember_file( $snapshot, $relative ) ) {
			return self::problem( 'ownership_not_saved', MediaStore::current( $snapshot ) ? 'retry' : 'stale' );
		}
		$path = $plan['directory'] . '/' . $name;
		$handle = fopen( $path, 'x' );
		if ( $handle === false ) {
			return self::problem( 'output_reservation' );
		}
		$created[] = $path;
		fclose( $handle );
		if ( ! MediaStore::current( $snapshot ) ) {
			return self::problem( 'revision_changed', 'stale' );
		}
		self::$targets[$path] = true;
		if ( self::$filter_depth === 0 ) {
			add_filter( 'wp_editor_set_quality', [ __CLASS__, 'webp_quality' ], PHP_INT_MAX, 2 );
			add_filter( 'image_editor_output_format', [ __CLASS__, 'output_format' ], PHP_INT_MAX, 3 );
		}
		self::$filter_depth++;
		try {
			$editor = wp_get_image_editor( $source['path'], [ 'mime_type' => $source['mime'], 'output_mime_type' => 'image/webp' ] );
			if ( is_wp_error( $editor ) ) {
				return self::problem( 'editor_load' );
			}
			if ( is_wp_error( $editor->set_quality( self::QUALITY ) ) ) {
				return self::problem( 'editor_quality' );
			}
			$saved = $editor->save( $path, 'image/webp' );
			if ( is_wp_error( $saved ) || ! is_array( $saved ) || ( $saved['path'] ?? null ) !== $path ||
				( $saved['file'] ?? null ) !== $name || ( $saved['mime-type'] ?? null ) !== 'image/webp' || $editor->get_quality() !== self::QUALITY ) {
				return self::problem( 'editor_save' );
			}
			clearstatcache( true, $path );
			$entry = [ 'file' => $saved['file'], 'source' => $source['file'], 'width' => $source['width'],
				'height' => $source['height'], 'mime-type' => 'image/webp', 'filesize' => filesize( $path ), 'source_sha256' => $source['sha256'] ];
			return self::valid_entry( $entry, $source, $plan['directory'] ) ? $entry : self::problem( 'output_verification' );
		} finally {
			unset( self::$targets[$path] );
			self::$filter_depth--;
			if ( self::$filter_depth === 0 ) {
				remove_filter( 'wp_editor_set_quality', [ __CLASS__, 'webp_quality' ], PHP_INT_MAX );
				remove_filter( 'image_editor_output_format', [ __CLASS__, 'output_format' ], PHP_INT_MAX );
			}
		}
	}

	/**
	 * Keep explicit reserved WebP saves from being redirected to an unowned file.
	 *
	 * @param array  $formats  Native source-to-output MIME mappings.
	 * @param string $filename Native requested destination.
	 * @param string $mime     Requested output MIME at Core's save boundary.
	 * @return array Existing mappings with only the owned WebP target pinned.
	 * @since 3.0.0
	 */
	public static function output_format( $formats, $filename, $mime ) {
		if ( $mime === 'image/webp' && is_string( $filename ) && isset( self::$targets[$filename] ) ) {
			$formats['image/webp'] = 'image/webp';
		}
		return $formats;
	}

	/**
	 * Preserve the agreed quality while Core switches from JPEG/PNG to WebP.
	 *
	 * @param int    $quality Existing native quality.
	 * @param string $mime    MIME being configured by the native editor.
	 * @return int Existing quality, or 82 for this worker's active WebP encodes.
	 * @since 3.0.0
	 */
	public static function webp_quality( $quality, $mime ) {
		return $mime === 'image/webp' && self::$filter_depth > 0 ? self::QUALITY : $quality;
	}

	/**
	 * Recheck all source bytes immediately before saving progress or publishing.
	 *
	 * @param array $plan Current canonical source paths and hash snapshot.
	 * @return bool Whether every source still has exactly the planned bytes.
	 * @since 3.0.0
	 */
	private static function sources_unchanged( array $plan ) {
		foreach ( $plan['files'] as $source ) {
			clearstatcache( true, $source['path'] );
			if ( is_link( $source['path'] ) || ! is_file( $source['path'] ) || filesize( $source['path'] ) !== $source['bytes'] || hash_file( 'sha256', $source['path'] ) !== $source['sha256'] ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Build the existing namespace only after every eligible size is verified.
	 *
	 * @param array $metadata Native metadata, never mutated by this method.
	 * @param array $plan     Complete full/alias source mapping.
	 * @param array $entries  Verified entries keyed by source basename.
	 * @return array Complete version-1 publication compatible with existing Images.
	 * @since 3.0.0
	 */
	private static function alternatives( array $metadata, array $plan, array $entries ) {
		$result = [ 'version' => 1, 'quality' => self::QUALITY, 'source_file' => $metadata['file'],
			'full' => $entries[$plan['full']], 'sizes' => [] ];
		foreach ( $plan['variants'] as $name => $source ) {
			$result['sizes'][$name] = $entries[$source];
		}
		return $result;
	}

	/**
	 * Remove only this call's unpublished owned outputs under a safe ownership state.
	 *
	 * A lost lease/revision may mean publication succeeded before an exception.
	 * In that ambiguous case the ledger remains for MediaStore's later cleanup;
	 * an attachment deleted during native encoding permits its late output to be
	 * removed. Existing progress, legacy files and originals are never deleted here.
	 *
	 * @param array $snapshot Original lease snapshot.
	 * @param array $plan     Canonical attachment output directory.
	 * @param array $created  Paths exclusively created in this invocation only.
	 * @return void
	 * @since 3.0.0
	 */
	private static function cleanup( array $snapshot, array $plan, array $created ) {
		if ( ! $created ) {
			return;
		}
		try {
			if ( ! MediaStore::current( $snapshot ) && ! MediaStore::deleted( $snapshot ) ) {
				return;
			}
			$id = (int) $snapshot['attachment_id'];
			foreach ( $created as $path ) {
				if ( dirname( $path ) === $plan['directory'] && strpos( wp_basename( $path ), '-skye-webp-' . $id . '-q82-' ) !== false && ! is_link( $path ) && is_file( $path ) ) {
					wp_delete_file_from_directory( $path, $plan['directory'] );
				}
			}
		} catch ( \Throwable $error ) {
			// The recorded ownership survives a cleanup failure; no source is touched.
			return;
		}
	}

	/**
	 * Accept only a native local uploads-relative path, never a wrapper or traversal.
	 *
	 * @param mixed $path Candidate relative metadata path.
	 * @return bool Whether its nonempty path segments are safe native components.
	 * @since 3.0.0
	 */
	private static function relative_path( $path ) {
		if ( ! is_string( $path ) || $path === '' || $path[0] === '/' || strpbrk( $path, "\\:\0" ) !== false ) {
			return false;
		}
		foreach ( explode( '/', $path ) as $part ) {
			if ( ! self::basename( $part ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Validate a single native source/sidecar filename without interpreting paths.
	 *
	 * @param mixed $name Candidate file basename.
	 * @return bool Whether no traversal, separator, wrapper or null byte is present.
	 * @since 3.0.0
	 */
	private static function basename( $name ) {
		return is_string( $name ) && $name !== '' && $name !== '.' && $name !== '..' && strpbrk( $name, "/\\:\0" ) === false;
	}

	/**
	 * Create a safe internal failure without leaking source data into job errors.
	 *
	 * @param string $code   Stable machine-readable reason.
	 * @param string $status Retry, unsupported or stale classification.
	 * @return \WP_Error Safe worker failure with its status in error data.
	 * @since 3.0.0
	 */
	private static function problem( $code, $status = 'retry' ) {
		return new \WP_Error( $code, 'WebP revision could not be completed.', [ 'status' => $status ] );
	}

	/**
	 * Translate an internal native error into the public worker result contract.
	 *
	 * @param \WP_Error $error Internal machine-readable worker failure.
	 * @return array{status:string,error:string} Safe retry/unsupported/stale result.
	 * @since 3.0.0
	 */
	private static function problem_result( \WP_Error $error ) {
		$data = $error->get_error_data();
		return self::result( is_array( $data ) ? ( $data['status'] ?? 'retry' ) : 'retry', $error->get_error_code() );
	}

	/**
	 * Return only the small orchestration contract required by MediaPipeline.
	 *
	 * @param string $status Complete, pending, retry, unsupported or stale.
	 * @param string $error  Optional machine-readable failure reason.
	 * @return array{status:string,error?:string} Worker result without source details.
	 * @since 3.0.0
	 */
	private static function result( $status, $error = '' ) {
		$result = [ 'status' => $status ];
		if ( $error !== '' ) {
			$result['error'] = $error;
		}
		return $result;
	}
}
