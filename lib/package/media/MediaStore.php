<?php
/**
 * Persist media jobs and publish derivatives without overwriting concurrent Core changes.
 *
 * @see https://developer.wordpress.org/reference/classes/wpdb/prepare/
 * @see https://developer.wordpress.org/reference/classes/wpdb/query/
 * @see https://developer.wordpress.org/reference/functions/wp_cache_delete/
 * @since 3.0.0
 */

namespace Rocketsky\Skye\Packages\Media;

use Rocketsky\Skye\Packages\Images;

/**
 * Own the atomic boundary shared by native resizing, WebP encoding, and scheduling.
 *
 * Core's metadata update can insert a missing row and cannot condition a write
 * on both a job revision and a lease. The guarded SQL below deliberately never
 * upserts Core metadata. Native cache invalidation and metadata actions follow
 * successful publication; Redis is not required for locking correctness.
 *
 * @since 3.0.0
 */
class MediaStore {

	const PENDING_KEY = '_skye_webp_pending';
	const PROGRESS_KEY = '_skye_webp_progress';
	const FILES_KEY = '_skye_webp_files';
	const FAILURE_KEY = '_skye_webp_failure';
	const DELETING_KEY = '_skye_webp_deleting';
	const LOCK_OPTION = 'skye_webp_worker_lock';
	const LOCK_TTL = 600;
	const RETENTION = 86400;

	/** @var string Current request's lease, never an authentication credential. */
	private static $lease = '';

	/** @var int Attachment whose own publication must not enqueue itself. */
	private static $publishing = 0;

	/**
	 * Acquire the sole encoder lease, reclaiming only an expired exact value.
	 *
	 * add_option() is not used as a mutex: its duplicate-key path can overwrite
	 * another contender. INSERT IGNORE and token-bound UPDATE provide ownership.
	 *
	 * @return bool Whether this request owns the lease.
	 * @since 3.0.0
	 */
	public static function acquire() {
		global $wpdb;
		$value = sprintf( '%010d:%s', time() + self::LOCK_TTL, wp_generate_uuid4() );
		$won = $wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')",
			self::LOCK_OPTION, $value
		) );
		if ( ! $won ) {
			$previous = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK_OPTION ) );
			if ( ! is_string( $previous ) || (int) substr( $previous, 0, 10 ) >= time() ) {
				return false;
			}
			$won = $wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = %s, autoload = 'off' WHERE option_name = %s AND BINARY option_value = %s",
				$value, self::LOCK_OPTION, $previous
			) );
		}
		if ( 1 !== $won ) {
			return false;
		}
		self::$lease = $value;
		wp_cache_delete( self::LOCK_OPTION, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		return true;
	}

	/**
	 * Check ownership against the database rather than a process or Redis cache.
	 *
	 * @return bool Whether this request still owns an unexpired lease.
	 * @since 3.0.0
	 */
	public static function owns_lock() {
		global $wpdb;
		if ( '' === self::$lease || (int) substr( self::$lease, 0, 10 ) < time() ) {
			return false;
		}
		return self::$lease === $wpdb->get_var( $wpdb->prepare(
			"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK_OPTION
		) );
	}

	/**
	 * Release only this request's lease, preserving a replacement worker's lock.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public static function release() {
		global $wpdb;
		if ( '' !== self::$lease ) {
			$wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s AND BINARY option_value = %s", self::LOCK_OPTION, self::$lease
			) );
			wp_cache_delete( self::LOCK_OPTION, 'options' );
		}
		self::$lease = '';
	}

	/**
	 * Identify this worker's own metadata publication for native hook callbacks.
	 *
	 * @param int $attachment_id Attachment being written.
	 * @return bool Whether its metadata actions belong to this publication.
	 * @since 3.0.0
	 */
	public static function is_publishing( $attachment_id ) {
		return self::$publishing === (int) $attachment_id;
	}

	/**
	 * Verify deletion independently of an obsolete worker's cached post object.
	 *
	 * A codec may finish after Core's delete hook removed its reserved output.
	 * Only a confirmed deletion permits that worker to clean its own late save.
	 *
	 * @param array $snapshot Snapshot of the attachment being encoded.
	 * @return bool Whether Core removed the attachment or marked it for deletion.
	 * @since 3.0.0
	 */
	public static function deleted( array $snapshot ) {
		global $wpdb;
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} d
			ON d.post_id = p.ID AND d.meta_key = %s
			WHERE p.ID = %d AND p.post_type = 'attachment' AND d.meta_id IS NULL LIMIT 1",
			self::DELETING_KEY, $snapshot['attachment_id']
		) );
		return '' === $wpdb->last_error && null === $exists;
	}

	/**
	 * Queue work only while an eligible attachment still exists in the database.
	 *
	 * Core metadata has no unique post/key constraint. Update every existing job
	 * row, insert only through the same live-post predicate, then normalize rows
	 * another simultaneous first enqueue may have inserted. No deleted post is
	 * recreated, and these internal writes invalidate the native metadata cache.
	 *
	 * @param int    $attachment_id Existing native attachment ID.
	 * @param string $revision Opaque new pending token chosen by the scheduler.
	 * @return bool Whether the exact job is stored for a live eligible attachment.
	 * @since 3.0.0
	 */
	public static function enqueue( $attachment_id, $revision ) {
		global $wpdb;
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id < 1 || ! is_string( $revision ) || '' === $revision ) {
			return false;
		}
		$where = $wpdb->prepare(
			"p.ID = %d AND p.post_type = 'attachment' AND p.post_status <> 'trash'
			AND p.post_mime_type IN ('image/jpeg', 'image/png') AND d.meta_id IS NULL", $attachment_id
		);
		$update = $wpdb->prepare(
			"UPDATE {$wpdb->postmeta} j JOIN {$wpdb->posts} p ON p.ID = j.post_id
			LEFT JOIN {$wpdb->postmeta} d ON d.post_id = p.ID AND d.meta_key = %s
			SET j.meta_value = %s WHERE j.meta_key = %s AND {$where}",
			self::DELETING_KEY, $revision, self::PENDING_KEY
		);
		if ( false === $wpdb->query( $update ) ) {
			wp_cache_delete( $attachment_id, 'post_meta' );
			return false;
		}
		$inserted = $wpdb->query( $wpdb->prepare(
			"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
			SELECT p.ID, %s, %s FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} d ON d.post_id = p.ID AND d.meta_key = %s
			LEFT JOIN {$wpdb->postmeta} j ON j.post_id = p.ID AND j.meta_key = %s
			WHERE {$where} AND j.meta_id IS NULL LIMIT 1",
			self::PENDING_KEY, $revision, self::DELETING_KEY, self::PENDING_KEY
		) );
		$normalized = false !== $inserted ? $wpdb->query( $update ) : false;
		wp_cache_delete( $attachment_id, 'post_meta' );
		if ( false === $normalized ) {
			return false;
		}
		return '1' === (string) $wpdb->get_var( $wpdb->prepare(
			"SELECT 1 FROM {$wpdb->posts} p
			JOIN {$wpdb->postmeta} j ON j.post_id = p.ID AND j.meta_key = %s AND BINARY j.meta_value = %s
			LEFT JOIN {$wpdb->postmeta} d ON d.post_id = p.ID AND d.meta_key = %s
			WHERE {$where} LIMIT 1", self::PENDING_KEY, $revision, self::DELETING_KEY
		) );
	}

	/**
	 * Read one current Core row and its pending identity without exposing content.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $revision Exact persistent pending value.
	 * @return array|false Native metadata and the exact database snapshot, or false.
	 * @since 3.0.0
	 */
	public static function snapshot( $attachment_id, $revision ) {
		global $wpdb;
		if ( ! self::owns_lock() ) {
			return false;
		}
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT m.meta_id, m.meta_value, p.post_mime_type, a.meta_value AS attached_value
			FROM {$wpdb->postmeta} m
			JOIN {$wpdb->posts} p ON p.ID = m.post_id
			JOIN {$wpdb->postmeta} j ON j.post_id = p.ID AND j.meta_key = %s AND BINARY j.meta_value = %s
			JOIN {$wpdb->postmeta} a ON a.post_id = p.ID AND a.meta_key = '_wp_attached_file'
			LEFT JOIN {$wpdb->postmeta} d ON d.post_id = p.ID AND d.meta_key = %s
			WHERE m.post_id = %d AND m.meta_key = '_wp_attachment_metadata'
			AND p.post_type = 'attachment' AND p.post_status <> 'trash' AND d.meta_id IS NULL
			ORDER BY m.meta_id ASC LIMIT 1",
			self::PENDING_KEY, $revision, self::DELETING_KEY, $attachment_id
		), ARRAY_A );
		if ( ! $row ) {
			return false;
		}
		$metadata = maybe_unserialize( $row['meta_value'] );
		if ( ! is_array( $metadata ) ) {
			return false;
		}
		return array(
			'attachment_id' => (int) $attachment_id,
			'revision' => $revision,
			'metadata' => $metadata,
			'attached_file' => get_attached_file( $attachment_id, true ),
			'attached_value' => $row['attached_value'],
			'mime_type' => $row['post_mime_type'],
			'meta_id' => (int) $row['meta_id'],
			'raw' => $row['meta_value'],
		);
	}

	/**
	 * Build the shared joins and exact ownership conditions for a snapshot.
	 *
	 * Database identifiers come solely from wpdb, all stored values use prepare().
	 * The same conditions protect progress, inventory and Core publication.
	 *
	 * @param array $snapshot Snapshot returned by snapshot().
	 * @return array Joins and prepared WHERE expression.
	 * @since 3.0.0
	 */
	private static function conditions( array $snapshot ) {
		global $wpdb;
		$joins = "JOIN {$wpdb->posts} p ON p.ID = m.post_id
			JOIN {$wpdb->postmeta} j ON j.post_id = p.ID AND j.meta_key = '" . self::PENDING_KEY . "'
			JOIN {$wpdb->postmeta} a ON a.post_id = p.ID AND a.meta_key = '_wp_attached_file'
			JOIN {$wpdb->options} l ON l.option_name = '" . self::LOCK_OPTION . "'
			LEFT JOIN {$wpdb->postmeta} d ON d.post_id = p.ID AND d.meta_key = '" . self::DELETING_KEY . "'";
		$where = $wpdb->prepare(
			"m.meta_id = %d AND m.post_id = %d AND m.meta_key = '_wp_attachment_metadata'
			AND BINARY m.meta_value = %s AND BINARY j.meta_value = %s AND BINARY a.meta_value = %s
			AND BINARY l.option_value = %s AND CAST(SUBSTRING(l.option_value, 1, 10) AS UNSIGNED) >= UNIX_TIMESTAMP()
			AND p.post_type = 'attachment' AND p.post_status <> 'trash' AND d.meta_id IS NULL",
			$snapshot['meta_id'], $snapshot['attachment_id'], $snapshot['raw'], $snapshot['revision'], $snapshot['attached_value'], self::$lease
		);
		return array( $joins, $where );
	}

	/**
	 * Reject work invalidated by a new upload checkpoint, edit, delete or lease.
	 *
	 * @param array $snapshot Snapshot being processed.
	 * @return bool Whether all database preconditions still hold.
	 * @since 3.0.0
	 */
	public static function current( array $snapshot ) {
		global $wpdb;
		if ( ! self::owns_lock() ) {
			return false;
		}
		list( $joins, $where ) = self::conditions( $snapshot );
		return '1' === (string) $wpdb->get_var( "SELECT 1 FROM {$wpdb->postmeta} m {$joins} WHERE {$where} LIMIT 1" );
	}

	/**
	 * Save internal worker state only while its original Core row still exists.
	 *
	 * An INSERT SELECT supplies the same guard as an UPDATE and cannot recreate
	 * an attachment after the delete hook has invalidated its pending identity.
	 *
	 * @param array  $snapshot Current snapshot.
	 * @param string $key Internal progress or inventory key.
	 * @param array  $value Structured internal state.
	 * @return bool Whether the exact value was stored under the current snapshot.
	 * @since 3.0.0
	 */
	private static function write_state( array $snapshot, $key, array $value ) {
		global $wpdb;
		if ( ! in_array( $key, array( self::PROGRESS_KEY, self::FILES_KEY ), true ) || ! self::current( $snapshot ) ) {
			return false;
		}
		if ( self::FILES_KEY === $key ) {
			return self::merge_files( $snapshot, $value );
		}
		list( $joins, $where ) = self::conditions( $snapshot );
		$serialized = maybe_serialize( $value );
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->postmeta} s JOIN {$wpdb->postmeta} m ON m.post_id = s.post_id {$joins}
			SET s.meta_value = %s WHERE s.meta_key = %s AND {$where}", $serialized, $key
		) );
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
			SELECT p.ID, %s, %s FROM {$wpdb->postmeta} m {$joins}
			LEFT JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = %s
			WHERE {$where} AND s.meta_id IS NULL LIMIT 1", $key, $serialized, $key
		) );
		wp_cache_delete( $snapshot['attachment_id'], 'post_meta' );
		return self::current( $snapshot ) && get_post_meta( $snapshot['attachment_id'], $key, true ) === $value;
	}

	/**
	 * Read or checkpoint reusable, unpublished encoder results.
	 *
	 * @param array      $snapshot Current snapshot.
	 * @param array|null $data Optional new progress; null reads the existing value.
	 * @return array|bool Progress array when reading, guarded write result otherwise.
	 * @since 3.0.0
	 */
	public static function progress( array $snapshot, $data = null ) {
		if ( null !== $data ) {
			$data['_generation'] = substr( $snapshot['revision'], 11 );
			return self::write_state( $snapshot, self::PROGRESS_KEY, $data );
		}
		$value = get_post_meta( $snapshot['attachment_id'], self::PROGRESS_KEY, true );
		if ( ! is_array( $value ) || ( $value['_generation'] ?? '' ) !== substr( $snapshot['revision'], 11 ) ) {
			return array();
		}
		unset( $value['_generation'] );
		return $value;
	}

	/**
	 * Build the post/revision/lease guard shared by worker settlement and errors.
	 *
	 * @param int    $attachment_id Attachment owning a pending job.
	 * @param string $revision Exact token currently stored on that job.
	 * @return array Join clause and prepared predicate rooted at pending alias j.
	 * @since 3.0.0
	 */
	private static function job_conditions( $attachment_id, $revision ) {
		global $wpdb;
		$joins = "JOIN {$wpdb->posts} p ON p.ID = j.post_id
			JOIN {$wpdb->options} l ON l.option_name = '" . self::LOCK_OPTION . "'
			LEFT JOIN {$wpdb->postmeta} d ON d.post_id = p.ID AND d.meta_key = '" . self::DELETING_KEY . "'";
		$where = $wpdb->prepare(
			"j.post_id = %d AND j.meta_key = %s AND BINARY j.meta_value = %s
			AND BINARY l.option_value = %s AND CAST(SUBSTRING(l.option_value, 1, 10) AS UNSIGNED) >= UNIX_TIMESTAMP()
			AND p.post_type = 'attachment' AND p.post_status <> 'trash'
			AND p.post_mime_type IN ('image/jpeg', 'image/png') AND d.meta_id IS NULL",
			$attachment_id, self::PENDING_KEY, $revision, self::$lease
		);
		return array( $joins, $where );
	}

	/**
	 * Finish or defer only the exact job processed under this worker's lease.
	 *
	 * Native delete_post_meta() selects IDs before deleting and can therefore
	 * remove a newer revision of that row. One guarded multi-table DELETE removes
	 * finished pending, progress and failure rows together, without a later gap.
	 *
	 * @param int      $attachment_id Attachment owning the pending job.
	 * @param string   $revision Exact pending identity read by this worker.
	 * @param int|null $next_due Next UTC timestamp, or null to finish.
	 * @return bool Whether this exact pending value was changed or removed.
	 * @since 3.0.0
	 */
	public static function settle( $attachment_id, $revision, $next_due = null ) {
		global $wpdb;
		if ( ! self::owns_lock() ) {
			return false;
		}
		list( $joins, $where ) = self::job_conditions( $attachment_id, $revision );
		if ( null === $next_due ) {
			$sql = $wpdb->prepare(
				"DELETE j, s FROM {$wpdb->postmeta} j {$joins}
				LEFT JOIN {$wpdb->postmeta} s ON s.post_id = j.post_id AND s.meta_key IN (%s, %s)
				WHERE {$where}", self::PROGRESS_KEY, self::FAILURE_KEY
			);
		} else {
			$next = sprintf( '%010d:%s', max( 0, (int) $next_due ), substr( $revision, 11 ) );
			$sql = $wpdb->prepare(
				"UPDATE {$wpdb->postmeta} j {$joins} SET j.meta_value = %s WHERE {$where}", $next
			);
		}
		$changed = $wpdb->query( $sql );
		wp_cache_delete( $attachment_id, 'post_meta' );
		return is_int( $changed ) && $changed > 0;
	}

	/**
	 * Save retry details only for the newly deferred exact job under its live lease.
	 *
	 * UPDATE and INSERT SELECT both require a live attachment and no tombstone;
	 * a deletion between settlement and this write cannot create orphan metadata.
	 *
	 * @param int    $attachment_id Attachment whose job was deferred.
	 * @param string $revision Exact new pending token, including its new due time.
	 * @param array  $data Machine-readable attempts/generation/error state.
	 * @return bool Whether the exact state remains stored for the current job.
	 * @since 3.0.0
	 */
	public static function failure( $attachment_id, $revision, array $data ) {
		global $wpdb;
		if ( ! self::owns_lock() ) {
			return false;
		}
		list( $joins, $where ) = self::job_conditions( $attachment_id, $revision );
		$value = maybe_serialize( $data );
		$changed = $wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->postmeta} s JOIN {$wpdb->postmeta} j ON j.post_id = s.post_id {$joins}
			SET s.meta_value = %s WHERE s.meta_key = %s AND {$where}", $value, self::FAILURE_KEY
		) );
		$inserted = false !== $changed ? $wpdb->query( $wpdb->prepare(
			"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
			SELECT p.ID, %s, %s FROM {$wpdb->postmeta} j {$joins}
			LEFT JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = %s
			WHERE {$where} AND s.meta_id IS NULL LIMIT 1", self::FAILURE_KEY, $value, self::FAILURE_KEY
		) ) : false;
		wp_cache_delete( $attachment_id, 'post_meta' );
		return false !== $inserted && '1' === (string) $wpdb->get_var( $wpdb->prepare(
			"SELECT 1 FROM {$wpdb->postmeta} j {$joins}
			JOIN {$wpdb->postmeta} s ON s.post_id = j.post_id AND s.meta_key = %s AND BINARY s.meta_value = %s
			WHERE {$where} LIMIT 1", self::FAILURE_KEY, $value
		) );
	}

	/**
	 * Discard obsolete progress only while the new retry token is still current.
	 *
	 * @param int    $attachment_id Attachment with changed source bytes.
	 * @param string $revision Exact newly deferred pending token.
	 * @return bool Whether progress is absent under that same live job and lease.
	 * @since 3.0.0
	 */
	public static function clear_progress( $attachment_id, $revision ) {
		global $wpdb;
		if ( ! self::owns_lock() ) {
			return false;
		}
		list( $joins, $where ) = self::job_conditions( $attachment_id, $revision );
		$changed = $wpdb->query( $wpdb->prepare(
			"DELETE s FROM {$wpdb->postmeta} j {$joins}
			JOIN {$wpdb->postmeta} s ON s.post_id = j.post_id AND s.meta_key = %s
			WHERE {$where}", self::PROGRESS_KEY
		) );
		wp_cache_delete( $attachment_id, 'post_meta' );
		return false !== $changed && '1' === (string) $wpdb->get_var( $wpdb->prepare(
			"SELECT 1 FROM {$wpdb->postmeta} j {$joins}
			LEFT JOIN {$wpdb->postmeta} s ON s.post_id = j.post_id AND s.meta_key = %s
			WHERE {$where} AND s.meta_id IS NULL LIMIT 1", self::PROGRESS_KEY
		) );
	}

	/**
	 * Record ownership before reserving a generated file so crashes are recoverable.
	 *
	 * @param array  $snapshot Current snapshot.
	 * @param string $relative Upload-relative output path, not an original file.
	 * @return bool Whether the new own-ID path is safely inventoried.
	 * @since 3.0.0
	 */
	public static function remember_file( array $snapshot, $relative ) {
		if ( ! self::owned_path( $snapshot['attachment_id'], $relative ) ) {
			return false;
		}
		return self::write_state( $snapshot, self::FILES_KEY, array( $relative => time() ) );
	}

	/**
	 * Read the canonical inventory row directly rather than through object caches.
	 *
	 * Only the leased worker creates this internal row; retirement never inserts.
	 * Its exact raw value is retained for compare-and-swap, preserving additions
	 * made by an overlapping native edit between the read and a later write.
	 * Callers must inspect wpdb::$last_error to distinguish absence from read failure.
	 *
	 * @param int $attachment_id Attachment owning the file inventory.
	 * @return array|false Meta ID/raw value, or false when absent or the SQL read fails.
	 * @since 3.0.0
	 */
	private static function inventory_row( $attachment_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT meta_id, meta_value FROM {$wpdb->postmeta}
			WHERE post_id = %d AND meta_key = %s ORDER BY meta_id ASC LIMIT 1",
			$attachment_id, self::FILES_KEY
		), ARRAY_A );
		return is_array( $row ) ? $row : false;
	}

	/**
	 * Compare-and-swap an existing inventory under its caller's ownership boundary.
	 *
	 * Encoding supplies a full Core snapshot, retirement requires a live post,
	 * and ordinary cleanup additionally requires the same worker lease. This
	 * method never inserts or silently replaces an inventory changed meanwhile.
	 *
	 * @param int        $attachment_id Attachment owning the inventory.
	 * @param array      $row Exact previously read meta ID/raw value.
	 * @param array      $files Proposed merged inventory.
	 * @param array|null $snapshot Optional full encoder snapshot.
	 * @param bool       $require_lease Whether maintenance must own the worker lease.
	 * @return bool Whether the exact proposed inventory is stored under that guard.
	 * @since 3.0.0
	 */
	private static function inventory_write( $attachment_id, array $row, array $files, $snapshot = null, $require_lease = false ) {
		global $wpdb;
		if ( is_array( $snapshot ) ) {
			list( $snapshot_joins, $where ) = self::conditions( $snapshot );
			$joins = "JOIN {$wpdb->postmeta} m ON m.post_id = s.post_id {$snapshot_joins}";
		} else {
			$joins = "JOIN {$wpdb->posts} p ON p.ID = s.post_id
				LEFT JOIN {$wpdb->postmeta} d ON d.post_id = p.ID AND d.meta_key = '" . self::DELETING_KEY . "'";
			$where = "p.post_type = 'attachment' AND d.meta_id IS NULL";
			if ( $require_lease ) {
				$joins .= " JOIN {$wpdb->options} l ON l.option_name = '" . self::LOCK_OPTION . "'";
				$where .= $wpdb->prepare(
					' AND BINARY l.option_value = %s AND CAST(SUBSTRING(l.option_value, 1, 10) AS UNSIGNED) >= UNIX_TIMESTAMP()',
					self::$lease
				);
			}
		}
		$where .= $wpdb->prepare(
			' AND s.meta_id = %d AND s.post_id = %d AND s.meta_key = %s', $row['meta_id'], $attachment_id, self::FILES_KEY
		);
		$value = maybe_serialize( $files );
		$changed = $wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->postmeta} s {$joins} SET s.meta_value = %s
			WHERE {$where} AND BINARY s.meta_value = %s", $value, $row['meta_value']
		) );
		wp_cache_delete( $attachment_id, 'post_meta' );
		return false !== $changed && '1' === (string) $wpdb->get_var( $wpdb->prepare(
			"SELECT 1 FROM {$wpdb->postmeta} s {$joins}
			WHERE {$where} AND BINARY s.meta_value = %s LIMIT 1", $value
		) );
	}

	/**
	 * Merge new owned paths while retaining every concurrent retirement/addition.
	 *
	 * Three fresh raw-value attempts bound contention. MAX timestamps preserve
	 * the most recent retirement interval, so an edit cannot shorten retention.
	 * Inventory is durably written before the encoder reserves any output file.
	 *
	 * @param array $snapshot Full current encoder snapshot.
	 * @param array $incoming Owned relative paths mapped to retirement timestamps.
	 * @return bool Whether every requested path is safely inventoried.
	 * @since 3.0.0
	 */
	private static function merge_files( array $snapshot, array $incoming ) {
		global $wpdb;
		$id = $snapshot['attachment_id'];
		foreach ( $incoming as $relative => $timestamp ) {
			if ( ! self::owned_path( $id, $relative ) || ! is_int( $timestamp ) || $timestamp < 0 ) {
				return false;
			}
		}
		for ( $attempt = 0; $attempt < 3; $attempt++ ) {
			if ( ! self::current( $snapshot ) ) {
				return false;
			}
			$row = self::inventory_row( $id );
			$files = $row ? maybe_unserialize( $row['meta_value'] ) : array();
			if ( ! is_array( $files ) || '' !== $wpdb->last_error ) {
				return false;
			}
			foreach ( $incoming as $relative => $timestamp ) {
				if ( isset( $files[$relative] ) && ! is_numeric( $files[$relative] ) ) {
					return false;
				}
				$files[$relative] = max( (int) ( $files[$relative] ?? 0 ), $timestamp );
			}
			if ( count( $files ) > 4096 ) {
				return false;
			}
			if ( $row ) {
				if ( self::inventory_write( $id, $row, $files, $snapshot ) ) {
					return true;
				}
				continue;
			}
			list( $joins, $where ) = self::conditions( $snapshot );
			$inserted = $wpdb->query( $wpdb->prepare(
				"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
				SELECT p.ID, %s, %s FROM {$wpdb->postmeta} m {$joins}
				LEFT JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = %s
				WHERE {$where} AND s.meta_id IS NULL LIMIT 1", self::FILES_KEY, maybe_serialize( $files ), self::FILES_KEY
			) );
			wp_cache_delete( $id, 'post_meta' );
			if ( false === $inserted ) {
				return false;
			}
		}
		return false;
	}

	/**
	 * Publish a complete validated namespace without losing concurrent metadata.
	 *
	 * Unlike update_post_meta(), this UPDATE never inserts a deleted Core row.
	 * Pending identity, attached-file pointer, Core snapshot and lease ownership
	 * are compared in the same statement. All non-WebP Core fields stay exact.
	 *
	 * @param array $snapshot Current snapshot.
	 * @param array $alternatives Complete WebP manifest.
	 * @return bool Whether publication, including a verified no-op, succeeded.
	 * @since 3.0.0
	 */
	public static function publish( array $snapshot, array $alternatives ) {
		if ( ! self::current( $snapshot ) ) {
			return false;
		}
		$metadata = $snapshot['metadata'];
		$metadata['skye_webp'] = $alternatives;
		if ( ! Images::validate_webp_metadata( $metadata ) ) {
			return false;
		}
		return self::commit_metadata( $snapshot, $metadata );
	}

	/**
	 * Withdraw an unfavorable WebP set without changing any native Core fields.
	 *
	 * Retirement must be durable before the common metadata CAS removes the
	 * namespace, preserving old in-flight HTML for the complete retention period.
	 * A concurrent edit or new worker can never lose its newer metadata here.
	 *
	 * @param array $snapshot Exact metadata, attached-file, job and lease identity.
	 * @return bool Whether withdrawal or a verified absent-namespace no-op succeeded.
	 * @since 3.0.0
	 */
	public static function withdraw_webp( array $snapshot ) {
		if ( ! self::current( $snapshot ) ) {
			return false;
		}
		$metadata = $snapshot['metadata'];
		if ( ! array_key_exists( 'skye_webp', $metadata ) ) {
			return true;
		}
		if ( ! self::retire( $snapshot['attachment_id'], $metadata ) ) {
			return false;
		}
		unset( $metadata['skye_webp'] );
		return self::commit_metadata( $snapshot, $metadata );
	}

	/**
	 * Append newly generated native sizes without changing existing presentation.
	 *
	 * Existing Core fields and the active WebP namespace stay byte-exact. The
	 * shared guarded writer rejects edits, deleted rows, new jobs, or lost leases.
	 *
	 * @param array $snapshot Exact native metadata and worker ownership snapshot.
	 * @param array $sizes New owned entries keyed by registered migration size name.
	 * @return bool Whether the append was committed under the original guard.
	 * @since 3.0.0
	 */
	public static function append_sizes( array $snapshot, array $sizes ) {
		if ( ! $sizes || ! self::current( $snapshot ) ) {
			return false;
		}
		$metadata = $snapshot['metadata'];
		$existing = $metadata['sizes'] ?? array();
		if ( ! is_array( $existing ) ) {
			return false;
		}
		$definitions = NativeSizes::definitions();
		$directory = dirname( $metadata['file'] ?? '' );
		foreach ( $sizes as $name => $entry ) {
			$file = is_array( $entry ) ? ( $entry['file'] ?? null ) : null;
			$relative = ( '.' === $directory ? '' : $directory . '/' ) . ( is_string( $file ) ? $file : '' );
			if ( ! isset( $definitions[$name] ) || array_key_exists( $name, $existing ) || ! is_string( $file )
				|| wp_basename( $file ) !== $file || ! self::owned_path( $snapshot['attachment_id'], $relative )
				|| (int) ( $entry['width'] ?? 0 ) < 1 || (int) ( $entry['height'] ?? 0 ) < 1
				|| (int) ( $entry['filesize'] ?? 0 ) < 1 || ! in_array( $entry['mime-type'] ?? '', array( 'image/jpeg', 'image/png' ), true ) ) {
				return false;
			}
			$existing[$name] = $entry;
		}
		$metadata['sizes'] = $existing;
		if ( ! self::commit_metadata( $snapshot, $metadata ) ) {
			return false;
		}
		self::clear_progress( $snapshot['attachment_id'], $snapshot['revision'] );
		return true;
	}

	/**
	 * Commit one exact Core metadata snapshot with native filters and actions.
	 *
	 * Native backfill, WebP publication and withdrawal use this single CAS.
	 * Filters may veto but cannot silently replace another metadata contract.
	 *
	 * @param array $snapshot Exact metadata, attached-file, job and lease identity.
	 * @param array $metadata Complete proposed metadata value.
	 * @return bool Whether the exact guarded update or verified no-op succeeded.
	 * @since 3.0.0
	 */
	private static function commit_metadata( array $snapshot, array $metadata ) {
		global $wpdb;
		if ( ! self::current( $snapshot ) ) {
			return false;
		}
		$previous_publishing = self::$publishing;
		self::$publishing = $snapshot['attachment_id'];
		try {
			$filtered = apply_filters( 'wp_update_attachment_metadata', $metadata, $snapshot['attachment_id'] );
			if ( $filtered !== $metadata ) {
				return false;
			}
			if ( $metadata === $snapshot['metadata'] ) {
				return self::current( $snapshot );
			}
			do_action( 'update_post_meta', $snapshot['meta_id'], $snapshot['attachment_id'], '_wp_attachment_metadata', $metadata );
			do_action( 'update_postmeta', $snapshot['meta_id'], $snapshot['attachment_id'], '_wp_attachment_metadata', maybe_serialize( $metadata ) );
			if ( ! self::owns_lock() ) {
				return false;
			}
			list( $joins, $where ) = self::conditions( $snapshot );
			$changed = $wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->postmeta} m {$joins} SET m.meta_value = %s WHERE {$where}", maybe_serialize( $metadata )
			) );
			if ( 1 !== $changed ) {
				return false;
			}
			wp_cache_delete( $snapshot['attachment_id'], 'post_meta' );
			Images::reset_webp_cache();
			do_action( 'updated_post_meta', $snapshot['meta_id'], $snapshot['attachment_id'], '_wp_attachment_metadata', $metadata );
			do_action( 'updated_postmeta', $snapshot['meta_id'], $snapshot['attachment_id'], '_wp_attachment_metadata', maybe_serialize( $metadata ) );
			return true;
		} finally {
			self::$publishing = $previous_publishing;
		}
	}

	/**
	 * Delay retirement from the moment a published manifest becomes invalid.
	 *
	 * Existing in-flight HTML may still refer to those outputs. Legacy one-time
	 * files without an own-ID filename are intentionally never adopted for deletion.
	 * At most three fresh raw-value merges preserve files reserved concurrently.
	 *
	 * @param int   $attachment_id Attachment being edited.
	 * @param array $metadata Previous metadata, possibly including a WebP namespace.
	 * @return bool Whether all inventoried published files have durable retention.
	 * @since 3.0.0
	 */
	public static function retire( $attachment_id, array $metadata ) {
		global $wpdb;
		if ( ! is_array( $metadata['skye_webp'] ?? null ) ) {
			return true;
		}
		$directory = dirname( is_string( $metadata['file'] ?? null ) ? $metadata['file'] : '' );
		$sizes = is_array( $metadata['skye_webp']['sizes'] ?? null ) ? $metadata['skye_webp']['sizes'] : array();
		$entries = array_merge( array( $metadata['skye_webp']['full'] ?? array() ), $sizes );
		$paths = array();
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || ! is_string( $entry['file'] ?? null ) ) {
				continue;
			}
			$relative = ( '.' === $directory ? '' : $directory . '/' ) . ( $entry['file'] ?? '' );
			if ( self::owned_path( $attachment_id, $relative ) ) {
				$paths[$relative] = time();
			}
		}
		for ( $attempt = 0; $paths && $attempt < 3; $attempt++ ) {
			$row = self::inventory_row( $attachment_id );
			// Uninventoried legacy outputs cannot be removed by our cleanup worker.
			if ( ! $row ) {
				return '' === $wpdb->last_error;
			}
			$files = maybe_unserialize( $row['meta_value'] );
			if ( ! is_array( $files ) ) {
				return false;
			}
			foreach ( $paths as $relative => $timestamp ) {
				if ( array_key_exists( $relative, $files ) ) {
					if ( ! is_numeric( $files[$relative] ) ) {
						return false;
					}
					$files[$relative] = max( (int) $files[$relative], $timestamp );
				}
			}
			if ( self::inventory_write( $attachment_id, $row, $files ) ) {
				return true;
			}
		}
		return ! $paths;
	}

	/**
	 * Bound a generated basename stem without splitting native UTF-8 characters.
	 *
	 * Forty characters use at most 160 UTF-8 bytes, leaving room for both owned
	 * encoder suffixes and IDs. Original metadata/source filenames stay untouched.
	 *
	 * @param string $filename Verified source basename, without directories.
	 * @return string Bounded native stem, or image when no safe stem remains.
	 * @since 3.0.0
	 */
	public static function filename_stem( $filename ) {
		if ( ! is_string( $filename ) || wp_basename( $filename ) !== $filename || false !== strpbrk( $filename, "/\\:\0" ) ) {
			return 'image';
		}
		$stem = wp_html_excerpt( pathinfo( $filename, PATHINFO_FILENAME ), 40, '' );
		return $stem !== '' ? $stem : 'image';
	}

	/**
	 * Validate a relative file belongs to this attachment's own generated namespace.
	 *
	 * @param int $attachment_id Owning attachment.
	 * @param string $relative Upload-relative path.
	 * @return bool Whether the path is eligible for ownership tracking.
	 * @since 3.0.0
	 */
	private static function owned_path( $attachment_id, $relative ) {
		return is_string( $relative ) && '' !== $relative && '/' !== $relative[0]
			&& false === strpbrk( $relative, "\\:\0" ) && ! in_array( '..', explode( '/', $relative ), true )
			&& ( 1 === preg_match( '/\A.+-skye-webp-' . (int) $attachment_id . '-q82-[a-f0-9]{12}(?:-[0-9]+)?\.webp\z/', wp_basename( $relative ) )
				|| self::native_path( $attachment_id, $relative ) );
	}

	/**
	 * Identify an owned native basename after the shared relative-path validation.
	 *
	 * @param int $attachment_id Owning attachment.
	 * @param string $relative Already validated uploads-relative inventory path.
	 * @return bool Whether this is a reserved native JPEG/PNG output.
	 * @since 3.0.0
	 */
	private static function native_path( $attachment_id, $relative ) {
		return 1 === preg_match( '/-skye-native-' . (int) $attachment_id . '-v1-[a-f0-9]{12}(?:-[0-9]+)?\.(?:jpg|png)\z/', wp_basename( $relative ) );
	}

	/**
	 * Remove only inventoried own-ID outputs after retirement or permanent deletion.
	 *
	 * Core originals/subsizes and currently published or resumable outputs are
	 * protected. Own native files are retained until permanent deletion to cover
	 * Core's metadata-before-backup ordering; ambiguous native crash outputs share
	 * that conservative lifetime. No glob, other attachment or legacy file is deleted.
	 * Ordinary cleanup requires the worker lease; permanent deletion instead
	 * requires a fresh tombstone/missing-post check. Inventory pruning uses fresh
	 * compare-and-swap and removes only paths actually deleted by this invocation.
	 *
	 * @param int  $attachment_id Attachment owning the inventory.
	 * @param bool $all Whether the attachment is being permanently deleted.
	 * @return bool Whether delayed cleanup is still needed.
	 * @since 3.0.0
	 */
	public static function cleanup( $attachment_id, $all = false ) {
		global $wpdb;
		if ( ( ! $all && ! self::owns_lock() ) || ( $all && ! self::deleted( array( 'attachment_id' => $attachment_id ) ) ) ) {
			return true;
		}
		$uploads = wp_get_upload_dir();
		$base = realpath( $uploads['basedir'] );
		if ( false === $base ) {
			return true;
		}
		$raw = $wpdb->get_var( $wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d
			AND meta_key = '_wp_attachment_metadata' ORDER BY meta_id ASC LIMIT 1", $attachment_id
		) );
		if ( '' !== $wpdb->last_error ) {
			return true;
		}
		$metadata = maybe_unserialize( $raw );
		$metadata = is_array( $metadata ) ? $metadata : array();
		$original = is_string( $metadata['file'] ?? null ) ? $metadata['file'] : '';
		$directory = dirname( $original );
		$keep = array( $original );
		if ( is_string( $metadata['original_image'] ?? null ) ) {
			$keep[] = ( '.' === $directory ? '' : $directory . '/' ) . $metadata['original_image'];
		}
		$native_sizes = is_array( $metadata['sizes'] ?? null ) ? $metadata['sizes'] : array();
		foreach ( $native_sizes as $entry ) {
			if ( is_array( $entry ) && is_string( $entry['file'] ?? null ) ) {
				$keep[] = ( '.' === $directory ? '' : $directory . '/' ) . ( $entry['file'] ?? '' );
			}
		}
		// Native editor restore must retain generated files referenced only by backups.
		$backup_raw = $wpdb->get_var( $wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d
			AND meta_key = '_wp_attachment_backup_sizes' ORDER BY meta_id ASC LIMIT 1", $attachment_id
		) );
		if ( '' !== $wpdb->last_error ) {
			return true;
		}
		$backups = maybe_unserialize( $backup_raw );
		if ( ! $all && is_array( $backups ) ) {
			foreach ( $backups as $entry ) {
				if ( is_array( $entry ) && is_string( $entry['file'] ?? null ) ) {
					$keep[] = ( '.' === $directory ? '' : $directory . '/' ) . $entry['file'];
				}
			}
		}
		if ( ! $all ) {
			$webp = is_array( $metadata['skye_webp'] ?? null ) ? $metadata['skye_webp'] : array();
			$sizes = is_array( $webp['sizes'] ?? null ) ? $webp['sizes'] : array();
			$alternatives = array_merge( array( $webp['full'] ?? array() ), $sizes );
			$raw = $wpdb->get_var( $wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s ORDER BY meta_id ASC LIMIT 1",
				$attachment_id, self::PROGRESS_KEY
			) );
			if ( '' !== $wpdb->last_error ) {
				return true;
			}
			$progress = maybe_unserialize( $raw );
			$progress = is_array( $progress ) ? $progress : array();
			$alternatives = array_merge( $alternatives, is_array( $progress['entries'] ?? null ) ? $progress['entries'] : array() );
			foreach ( $alternatives as $entry ) {
				if ( is_array( $entry ) && is_string( $entry['file'] ?? null ) ) {
					$keep[] = ( '.' === $directory ? '' : $directory . '/' ) . ( $entry['file'] ?? '' );
				}
			}
		}
		// Read retirement times after metadata: an invalidation retires before Core saves its changed row.
		$row = self::inventory_row( $attachment_id );
		$files = $row ? maybe_unserialize( $row['meta_value'] ) : array();
		if ( ! is_array( $files ) || '' !== $wpdb->last_error ) {
			return true;
		}
		$waiting = false;
		$removed = array();
		foreach ( $files as $relative => $retired_at ) {
			if ( ! self::owned_path( $attachment_id, $relative ) || in_array( $relative, $keep, true ) ) {
				continue;
			}
			if ( ! is_numeric( $retired_at ) || ( ! $all && ! self::owns_lock() ) ) {
				$waiting = true;
				continue;
			}
			$file = $base . '/' . $relative;
			if ( ! is_file( $file ) ) {
				$removed[$relative] = (int) $retired_at;
				continue;
			}
			// Core writes edited metadata before its backup row. Delegate native
			// deletion to permanent attachment removal, covering that write gap.
			// Ambiguous crash/lease-loss reservations are intentionally retained;
			// provably unpublished current failures are removed by NativeSizes.
			if ( ! $all && self::native_path( $attachment_id, $relative ) ) {
				continue;
			}
			if ( ! $all && (int) $retired_at + self::RETENTION > time() ) {
				$waiting = true;
				continue;
			}
			if ( wp_delete_file_from_directory( $file, $base ) ) {
				$removed[$relative] = (int) $retired_at;
			} else {
				$waiting = true;
			}
		}
		if ( ! $all && $removed ) {
			return ! self::prune_files( $attachment_id, $removed ) || $waiting;
		}
		return $waiting;
	}

	/**
	 * Prune deleted paths from the latest inventory without overwriting additions.
	 *
	 * A newer retirement timestamp is retained conservatively. Lost CAS attempts
	 * retry at most three fresh reads; a later cleanup can finish after contention.
	 *
	 * @param int   $attachment_id Attachment whose normal cleanup owns the lease.
	 * @param array $removed Actually removed relative paths and their old timestamps.
	 * @return bool Whether the guarded latest inventory was successfully pruned.
	 * @since 3.0.0
	 */
	private static function prune_files( $attachment_id, array $removed ) {
		for ( $attempt = 0; $attempt < 3; $attempt++ ) {
			if ( ! self::owns_lock() ) {
				return false;
			}
			$row = self::inventory_row( $attachment_id );
			$files = $row ? maybe_unserialize( $row['meta_value'] ) : false;
			if ( ! is_array( $files ) ) {
				return false;
			}
			foreach ( $removed as $relative => $timestamp ) {
				if ( isset( $files[$relative] ) && is_numeric( $files[$relative] ) && (int) $files[$relative] <= $timestamp ) {
					unset( $files[$relative] );
				}
			}
			if ( self::inventory_write( $attachment_id, $row, $files, null, true ) ) {
				return true;
			}
		}
		return false;
	}
}
