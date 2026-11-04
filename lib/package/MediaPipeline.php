<?php
/**
 * Keep public image alternatives current through native WordPress media hooks.
 */

namespace Rocketsky\Skye\Packages;

use Rocketsky\Skye\Packages\Media\MediaStore;
use Rocketsky\Skye\Packages\Media\WebpGenerator;
use Rocketsky\Skye\Packages\Media\NativeSizes;

require_once __DIR__ . '/media/NativeSizes.php';
require_once __DIR__ . '/media/MediaStore.php';
require_once __DIR__ . '/media/WebpGenerator.php';

/**
 * Schedule one bounded background worker for uploads and the existing library.
 *
 * Normal page rendering reads one autoloaded installation marker. It never
 * searches jobs, touches image files, computes hashes or invokes an encoder.
 *
 * @since 3.0.0
 */
class MediaPipeline {

	const VERSION = 3;
	const VERSION_OPTION = 'skye_webp_pipeline_version';
	const STATE_OPTION = 'skye_webp_pipeline_scan';
	const SIZES_OPTION = 'skye_webp_registered_sizes';
	const CLEANUP_STATE_OPTION = 'skye_webp_cleanup_scan';
	const WORK_HOOK = 'skye_media_webp_work';
	const DISCOVER_HOOK = 'skye_media_webp_discover';
	const RECOVER_HOOK = 'skye_media_webp_recover';
	const CLEANUP_HOOK = 'skye_media_webp_cleanup';
	const CLEANUP_DISCOVER_HOOK = 'skye_media_webp_cleanup_discover';

	/** @var bool Whether this media-write request needs one scheduler update. */
	private $schedule = false;

	/**
	 * Register native lifecycle events without inspecting the media library.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'install' ), 30 );
		add_action( 'admin_init', array( $this, 'sync_sizes' ) );
		add_action( 'added_post_meta', array( $this, 'metadata_saved' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'metadata_saved' ), 10, 4 );
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'generated' ), 20, 3 );
		add_filter( 'image_editor_save_pre', array( $this, 'editing' ), 20, 2 );
		add_action( 'delete_attachment', array( $this, 'deleted' ) );
		add_action( 'shutdown', array( $this, 'flush' ) );
		add_action( self::WORK_HOOK, array( $this, 'work' ) );
		add_action( self::DISCOVER_HOOK, array( $this, 'discover' ) );
		add_action( self::RECOVER_HOOK, array( $this, 'recover' ) );
		add_action( self::CLEANUP_HOOK, array( $this, 'cleanup' ) );
		add_action( self::CLEANUP_DISCOVER_HOOK, array( $this, 'cleanup_discover' ) );
		add_action( 'switch_theme', array( $this, 'stop' ) );
	}

	/**
	 * Resolve deployment policy without inspecting files, jobs or codec support.
	 *
	 * Rendering and background work share this contract. Unavailable encoders
	 * are handled by the worker; native images remain the complete fallback.
	 *
	 * @return bool Whether this installation permits background media optimization.
	 * @since 3.0.0
	 */
	public static function enabled() {
		return (bool) apply_filters( 'skye_media_pipeline_enabled', true );
	}

	/**
	 * Stop scheduled dispatchers while preserving recoverable jobs and originals.
	 *
	 * Disabling the policy or switching themes must not leave recurring workers.
	 * Re-enabling installs dispatchers again and resumes the saved scan cursor.
	 * A failed native unschedule retains the marker so a later request retries.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function stop() {
		if ( ! $this->write_version( 0 ) ) {
			return;
		}
		$stopped = true;
		foreach ( array( self::WORK_HOOK, self::DISCOVER_HOOK, self::RECOVER_HOOK, self::CLEANUP_HOOK, self::CLEANUP_DISCOVER_HOOK ) as $hook ) {
			if ( false === wp_unschedule_hook( $hook ) ) {
				$stopped = false;
			}
		}
		if ( $stopped ) {
			delete_option( self::VERSION_OPTION );
		}
		$this->schedule = false;
	}

	/**
	 * Install background discovery once, including updates of an active theme.
	 *
	 * The autoloaded version marker makes subsequent frontend calls constant-time.
	 * Existing host WP-CLI cron and ordinary WP-Cron use the same native events.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function install() {
		if ( ! self::enabled() ) {
			if ( get_option( self::VERSION_OPTION, false ) !== false ) {
				$this->stop();
			}
			return;
		}
		if ( (int) get_option( self::VERSION_OPTION, 0 ) === self::VERSION ) {
			return;
		}
		$state = get_option( self::STATE_OPTION, array() );
		$state = is_array( $state ) ? $state : array();
		if ( (int) ( $state['version'] ?? 0 ) !== self::VERSION ) {
			$reset = array( 'cursor' => 0, 'complete' => false, 'version' => self::VERSION );
			if ( ! update_option( self::STATE_OPTION, $reset, false ) && get_option( self::STATE_OPTION ) !== $reset ) {
				return;
			}
		}
		$sizes = NativeSizes::definitions();
		if ( ! update_option( self::SIZES_OPTION, $sizes, false ) && get_option( self::SIZES_OPTION ) !== $sizes ) {
			return;
		}
		if ( ! $this->write_version( 0 ) ) {
			return;
		}
		$discovery = $this->schedule_event( self::DISCOVER_HOOK, time() + 5 );
		$recovery = wp_next_scheduled( self::RECOVER_HOOK );
		if ( ! $recovery ) {
			$recovery = wp_schedule_event( time() + 60, 'hourly', self::RECOVER_HOOK );
		}
		if ( $discovery && $recovery ) {
			$this->write_version( self::VERSION );
		}
	}



	/**
	 * Discover added or changed registered crops outside ordinary frontend requests.
	 *
	 * Admin and recovery requests compare native dimensions without hashing files.
	 * Missing temporary registrations do not erase the known schema or restart a
	 * scan. Every real addition/change restarts bounded ID discovery automatically;
	 * previously created Core files and metadata remain untouched.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function sync_sizes() {
		if ( ! self::enabled() ) {
			return;
		}
		$sizes = NativeSizes::definitions();
		$known = get_option( self::SIZES_OPTION, array() );
		$known = is_array( $known ) ? $known : array();
		$changed = false;
		foreach ( $sizes as $name => $definition ) {
			if ( ! isset( $known[$name] ) || $known[$name] !== $definition ) {
				$changed = true;
				break;
			}
		}
		if ( ! $changed ) {
			return;
		}
		$state = array( 'cursor' => 0, 'complete' => false, 'version' => self::VERSION );
		if ( ! update_option( self::STATE_OPTION, $state, false ) && get_option( self::STATE_OPTION ) !== $state ) {
			return;
		}
		$merged = array_replace( $known, $sizes );
		if ( ! update_option( self::SIZES_OPTION, $merged, false ) && get_option( self::SIZES_OPTION ) !== $merged ) {
			return;
		}
		$this->schedule_event( self::DISCOVER_HOOK, time() + 2 );
	}

	/**
	 * Persist a verified installation transition before changing native events.
	 *
	 * Zero marks partial setup or teardown. It forces reinstallation if policy
	 * changes during a failed shutdown; a missing marker never hides an event
	 * created by an installation whose initial database write failed.
	 *
	 * @param int $version Completed version or zero for a pending transition.
	 * @return bool Whether the marker was saved or already has the exact value.
	 * @since 3.0.0
	 */
	private function write_version( $version ) {
		return update_option( self::VERSION_OPTION, $version, true ) || (string) get_option( self::VERSION_OPTION, false ) === (string) $version;
	}

	/**
	 * Queue a fresh identity after Core has actually saved attachment metadata.
	 *
	 * @param int    $meta_id Native metadata row ID.
	 * @param int    $attachment_id Attachment whose Core row changed.
	 * @param string $key Native metadata key.
	 * @param mixed  $metadata New metadata value.
	 * @return void
	 * @since 3.0.0
	 */
	public function metadata_saved( $meta_id, $attachment_id, $key, $metadata ) {
		if ( '_wp_attachment_metadata' === $key && is_array( $metadata ) && ! MediaStore::is_publishing( $attachment_id ) ) {
			$this->queue( $attachment_id );
		}
	}

	/**
	 * Also cover regeneration whose final Core metadata happens to be identical.
	 *
	 * @param array  $metadata Native generated metadata, returned unchanged.
	 * @param int    $attachment_id Attachment being generated.
	 * @param string $context Native create/update context.
	 * @return array Unmodified Core metadata.
	 * @since 3.0.0
	 */
	public function generated( $metadata, $attachment_id, $context = 'create' ) {
		$this->queue( $attachment_id );
		return $metadata;
	}

	/**
	 * Retain a retry opportunity for same-dimension, same-filename editor saves.
	 *
	 * @param object $editor Native WordPress image editor, returned unchanged.
	 * @param int    $attachment_id Attachment being edited.
	 * @return object Unmodified native editor.
	 * @since 3.0.0
	 */
	public function editing( $editor, $attachment_id ) {
		$this->queue( $attachment_id );
		return $editor;
	}

	/**
	 * Persist work without encoding or writing the Cron option for every subsize.
	 *
	 * New uploads/edits receive higher worker priority than the automatic backfill.
	 * Original formats outside JPEG/PNG and deleting attachments are untouched.
	 * Discovery may advance only after a successful acknowledgement; a failed
	 * database write or changed eligibility leaves that candidate retryable.
	 *
	 * @param int  $attachment_id Native attachment ID.
	 * @param bool $backfill Whether this is lower-priority library discovery.
	 * @return bool Whether this candidate has a durable job or an existing backfill job.
	 * @since 3.0.0
	 */
	public function queue( $attachment_id, $backfill = false ) {
		if ( ! self::enabled() ) {
			return false;
		}
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id < 1 || 'attachment' !== get_post_type( $attachment_id ) || 'trash' === get_post_status( $attachment_id )
			|| ! in_array( get_post_mime_type( $attachment_id ), array( 'image/jpeg', 'image/png' ), true )
			|| get_post_meta( $attachment_id, MediaStore::DELETING_KEY, true ) ) {
			return false;
		}
		if ( $backfill && get_post_meta( $attachment_id, MediaStore::PENDING_KEY, true ) ) {
			return true;
		}
		$value = sprintf( '%010d:%d-%s', time() + 5, $backfill ? 1 : 0, wp_generate_uuid4() );
		if ( MediaStore::enqueue( $attachment_id, $value ) ) {
			$this->schedule = true;
			return true;
		}
		return false;
	}

	/**
	 * Coalesce all media checkpoints in this request into one dispatcher event.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function flush() {
		if ( $this->schedule && self::enabled() ) {
			$this->schedule = ! $this->schedule_event( self::WORK_HOOK, time() + 5 );
		}
	}

	/**
	 * Keep one native dispatcher at the earliest requested time.
	 *
	 * Core rejects matching single events within ten minutes. Remove a later
	 * event before advancing it; if replacement fails, restore the previous event
	 * where possible. Persistent jobs and hourly recovery survive either failure.
	 *
	 * @param string $hook Existing pipeline hook.
	 * @param int    $when Earliest requested UTC timestamp.
	 * @return bool Whether an event exists no later than the requested time.
	 * @since 3.0.0
	 */
	private function schedule_event( $hook, $when ) {
		$when = max( time() + 2, (int) $when );
		$previous = wp_next_scheduled( $hook, array() );
		if ( false !== $previous && $previous <= $when ) {
			return true;
		}
		if ( false !== $previous && true !== wp_unschedule_event( $previous, $hook, array(), true ) ) {
			return false;
		}
		if ( true === wp_schedule_single_event( $when, $hook, array(), true ) ) {
			return true;
		}
		if ( false !== $previous && false === wp_next_scheduled( $hook, array() ) ) {
			wp_schedule_single_event( $previous, $hook, array(), true );
		}
		return false;
	}

	/**
	 * Discover the existing library in bounded ID batches, without offset drift.
	 *
	 * WP_Query has no native ID-after argument. This small prepared keyset query
	 * reads IDs only and never hydrates content, ACF fields or original image data.
	 * A failed enqueue stops before that ID so a later event retries it. Native
	 * deleting markers are excluded rather than permanently blocking the cursor.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function discover() {
		if ( ! self::enabled() ) {
			return;
		}
		global $wpdb;
		$state = get_option( self::STATE_OPTION, array() );
		$state = is_array( $state ) ? $state : array();
		$cursor = (int) ( $state['cursor'] ?? 0 );
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} d ON d.post_id = p.ID AND d.meta_key = %s
			WHERE p.post_type = 'attachment' AND p.post_status <> 'trash'
			AND p.post_mime_type IN ('image/jpeg', 'image/png') AND p.ID > %d AND d.meta_id IS NULL
			ORDER BY p.ID ASC LIMIT 20", MediaStore::DELETING_KEY, $cursor
		) );
		if ( ! is_array( $ids ) || $wpdb->last_error !== '' ) {
			$this->schedule_event( self::DISCOVER_HOOK, time() + 60 );
			return;
		}
		$complete = count( $ids ) < 20;
		foreach ( $ids as $id ) {
			if ( ! $this->queue( (int) $id, true ) ) {
				$complete = false;
				break;
			}
			$cursor = (int) $id;
		}
		$state = array( 'cursor' => $cursor, 'complete' => $complete, 'version' => self::VERSION );
		update_option( self::STATE_OPTION, $state, false );
		$this->flush();
		if ( ! $state['complete'] ) {
			$this->schedule_event( self::DISCOVER_HOOK, time() + 30 );
		}
	}

	/**
	 * Run at most four attachments and two native files within a six-second soft budget.
	 *
	 * Native codecs cannot be safely interrupted mid-file. A single codec call
	 * may exceed the soft budget; the next operation always checks elapsed time.
	 * Pending values survive Cron unscheduling, timeouts and interrupted workers.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function work() {
		if ( ! self::enabled() ) {
			return;
		}
		global $wpdb;
		if ( ! MediaStore::acquire() ) {
			$this->schedule_event( self::WORK_HOOK, time() + 60 );
			return;
		}
		$deadline = microtime( true ) + 6;
		$native_remaining = 2;
		try {
			for ( $count = 0; $count < 4 && microtime( true ) < $deadline; $count++ ) {
				$due = sprintf( '%010d:~', time() );
				$job = $wpdb->get_row( $wpdb->prepare(
					"SELECT j.post_id, j.meta_value FROM {$wpdb->postmeta} j
					JOIN {$wpdb->posts} p ON p.ID = j.post_id
					LEFT JOIN {$wpdb->postmeta} d ON d.post_id = p.ID AND d.meta_key = %s
					WHERE j.meta_key = %s AND j.meta_value <= %s AND p.post_type = 'attachment'
					AND p.post_status <> 'trash' AND p.post_mime_type IN ('image/jpeg', 'image/png') AND d.meta_id IS NULL
					ORDER BY CASE WHEN SUBSTRING(j.meta_value, 12, 1) = '1' THEN 1 ELSE 0 END, j.meta_value ASC LIMIT 1",
					MediaStore::DELETING_KEY, MediaStore::PENDING_KEY, $due
				), ARRAY_A );
				if ( ! $job ) {
					break;
				}
				$id = (int) $job['post_id'];
				$revision = $job['meta_value'];
				$native = NativeSizes::process( $id, $revision, $native_remaining, max( 0, $deadline - microtime( true ) ) );
				$native_remaining -= (int) ( $native['attempted'] ?? 0 );
				$result = $native;
				if ( in_array( $native['status'] ?? '', array( 'complete', 'deferred_format' ), true ) ) {
					$result = microtime( true ) < $deadline
						? WebpGenerator::process( $id, $revision, 32, max( 0, $deadline - microtime( true ) ) )
						: array( 'status' => 'pending' );
					// A terminal WebP decision cannot finish native sizes blocked by an external output policy.
					if ( 'deferred_format' === $native['status'] && ( 'complete' === ( $result['status'] ?? '' ) || 'not_smaller' === ( $result['error'] ?? '' ) ) ) {
						$result = array( 'status' => 'retry', 'error' => 'native_output_format' );
					}
				}

				$this->settle( $id, $revision, $result );
			}
		} finally {
			MediaStore::release();
			$this->schedule_pending();
		}
	}

	/**
	 * Complete exact jobs or defer their retries without losing a newer upload.
	 *
	 * Transient failures back off up to one day. They remain recoverable rather
	 * than requiring a manual restart after a fixed number of attempts.
	 *
	 * @param int    $id Attachment ID.
	 * @param string $revision Exact job identity processed.
	 * @param array  $result Generator status and optional machine-readable code.
	 * @return void
	 * @since 3.0.0
	 */
	private function settle( $id, $revision, array $result ) {
		$status = $result['status'] ?? 'retry';
		if ( in_array( $status, array( 'complete', 'unsupported' ), true ) ) {
			MediaStore::settle( $id, $revision );
		} elseif ( 'pending' === $status ) {
			MediaStore::settle( $id, $revision, time() + 2 );
		} else {
			$failure = get_post_meta( $id, MediaStore::FAILURE_KEY, true );
			$generation = substr( $revision, 11 );
			$attempts = ( $failure['generation'] ?? '' ) === $generation ? (int) ( $failure['attempts'] ?? 0 ) : 0;
			$attempts++;
			$delay = min( DAY_IN_SECONDS, 60 * pow( 2, min( 11, $attempts - 1 ) ) );
			$next_due = time() + (int) $delay;
			$next_revision = sprintf( '%010d:%s', $next_due, $generation );
			if ( MediaStore::settle( $id, $revision, $next_due ) ) {
				if ( 'source_changed' === ( $result['error'] ?? '' ) ) {
					MediaStore::clear_progress( $id, $next_revision );
				}
				MediaStore::failure( $id, $next_revision, array(
					'generation' => $generation, 'attempts' => $attempts, 'code' => $result['error'] ?? 'worker_failed',
				) );
			}
		}
		if ( MediaStore::cleanup( $id ) ) {
			$this->schedule_cleanup( $id, time() + MediaStore::RETENTION );
		}
	}

	/**
	 * Ensure the next durable job has a dispatcher, even after a previous crash.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function schedule_pending() {
		global $wpdb;
		$value = $wpdb->get_var( $wpdb->prepare(
			"SELECT j.meta_value FROM {$wpdb->postmeta} j JOIN {$wpdb->posts} p ON p.ID = j.post_id
			LEFT JOIN {$wpdb->postmeta} d ON d.post_id = p.ID AND d.meta_key = %s
			WHERE j.meta_key = %s AND p.post_type = 'attachment' AND p.post_status <> 'trash'
			AND p.post_mime_type IN ('image/jpeg', 'image/png') AND d.meta_id IS NULL
			ORDER BY j.meta_value ASC LIMIT 1", MediaStore::DELETING_KEY, MediaStore::PENDING_KEY
		) );
		if ( is_string( $value ) ) {
			$this->schedule_event( self::WORK_HOOK, max( time() + 2, (int) substr( $value, 0, 10 ) ) );
		}
	}

	/**
	 * Recover unscheduled durable jobs and interrupted library discovery hourly.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function recover() {
		if ( ! self::enabled() ) {
			return;
		}
		$this->sync_sizes();
		$this->schedule_pending();
		// Continue past the last ID even after the initial scan, recovering a new
		// upload whose enqueue was interrupted without rescanning older media.
		$this->schedule_event( self::DISCOVER_HOOK, time() + 2 );
		$this->schedule_event( self::CLEANUP_DISCOVER_HOOK, time() + 2 );
	}

	/**
	 * Recover lost cleanup events from bounded durable ownership inventories.
	 *
	 * Only background recovery visits these rows. A six-second soft budget and
	 * twenty-ID keyset batches bound each pass. Published, resumable and native
	 * outputs need no cleanup event; retired sidecars reuse the ordinary guarded
	 * cleanup path. Scheduling/read failures stop before that ID. Completed passes
	 * reset the nonautoload cursor for the next hourly cycle without busy looping.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function cleanup_discover() {
		if ( ! self::enabled() ) {
			return;
		}
		if ( ! MediaStore::acquire() ) {
			$this->schedule_event( self::CLEANUP_DISCOVER_HOOK, time() + 60 );
			return;
		}
		global $wpdb;
		$deadline = microtime( true ) + 6;
		try {
			$cursor = (int) get_option( self::CLEANUP_STATE_OPTION, 0 );
			$ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT DISTINCT p.ID FROM {$wpdb->postmeta} i JOIN {$wpdb->posts} p ON p.ID = i.post_id
				LEFT JOIN {$wpdb->postmeta} d ON d.post_id = p.ID AND d.meta_key = %s
				WHERE i.meta_key = %s AND p.post_type = 'attachment' AND p.post_status <> 'trash'
				AND d.meta_id IS NULL AND p.ID > %d ORDER BY p.ID ASC LIMIT 20",
				MediaStore::DELETING_KEY, MediaStore::FILES_KEY, $cursor
			) );
			if ( ! is_array( $ids ) || '' !== $wpdb->last_error ) {
				$this->schedule_event( self::CLEANUP_DISCOVER_HOOK, time() + 60 );
				return;
			}
			$complete = count( $ids ) < 20;
			foreach ( $ids as $id ) {
				if ( microtime( true ) >= $deadline ) {
					$complete = false;
					break;
				}
				$required = MediaStore::cleanup_required( (int) $id );
				if ( null === $required || ( $required && ! $this->schedule_cleanup( (int) $id, time() + 5 ) ) ) {
					$complete = false;
					break;
				}
				$cursor = (int) $id;
			}
			update_option( self::CLEANUP_STATE_OPTION, $complete ? 0 : $cursor, false );
			if ( ! $complete ) {
				$this->schedule_event( self::CLEANUP_DISCOVER_HOOK, time() + 30 );
			}
		} finally {
			MediaStore::release();
		}
	}

	/**
	 * Acknowledge one native cleanup event without duplicating an existing timer.
	 *
	 * Its durable ownership inventory allows background rediscovery after native
	 * scheduling fails, so no optimistic scheduler result can lose the sidecars.
	 *
	 * @param int $attachment_id Attachment owning retired files.
	 * @param int $when Earliest requested execution timestamp.
	 * @return bool Whether its existing event or new native schedule is confirmed.
	 * @since 3.0.0
	 */
	private function schedule_cleanup( $attachment_id, $when ) {
		return false !== wp_next_scheduled( self::CLEANUP_HOOK, array( $attachment_id ) ) ||
			true === wp_schedule_single_event( $when, self::CLEANUP_HOOK, array( $attachment_id ), true );
	}

	/**
	 * Clean retired outputs automatically after their retention interval.
	 *
	 * @param int $attachment_id Attachment owning the recorded outputs.
	 * @return void
	 * @since 3.0.0
	 */
	public function cleanup( $attachment_id ) {
		if ( ! self::enabled() ) {
			return;
		}
		$attachment_id = (int) $attachment_id;
		if ( ! MediaStore::acquire() ) {
			$this->schedule_cleanup( $attachment_id, time() + 60 );
			return;
		}
		try {
			if ( MediaStore::cleanup( $attachment_id ) ) {
				$this->schedule_cleanup( $attachment_id, time() + MediaStore::RETENTION );
			}
		} finally {
			MediaStore::release();
		}
	}

	/**
	 * Invalidate the worker before Core permanently deletes its attachment.
	 *
	 * Only owned sidecars are removed; Core remains responsible for its originals.
	 *
	 * @param int $attachment_id Attachment being permanently deleted.
	 * @return void
	 * @since 3.0.0
	 */
	public function deleted( $attachment_id ) {
		update_post_meta( $attachment_id, MediaStore::DELETING_KEY, 1 );
		delete_post_meta( $attachment_id, MediaStore::PENDING_KEY );
		wp_clear_scheduled_hook( self::CLEANUP_HOOK, array( (int) $attachment_id ) );
		MediaStore::cleanup( (int) $attachment_id, true );
	}
}
