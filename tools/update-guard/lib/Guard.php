<?php
/** Gate protected native plugin packages before deactivation and observe exact outcomes. */

namespace Rocketsky\Skye\UpdateGuard;

require_once __DIR__ . '/Profile.php';

/** Keep reviewed original packages updateable without trusting version strings alone. */
final class Guard {
	/** @var string[] Independently configured scope remains known when profiles fail. */
	private $protected;
	/** @var array|null Validated original identities, or null for unavailable profiles. */
	private $profiles;
	/** @var string Local profile loaded only for update or administrator guard checks. */
	private $profile_file;
	/** @var bool One bounded JSON read per administrative process. */
	private $loaded = false;
	/** @var bool A malformed scope remains an administrator-visible guard failure. */
	private $configuration_error = false;
	/** @var array Per-plugin approval and original activation snapshots for this process. */
	private $pending = array();
	/** @var array Native public result owners for targeted early failure propagation. */
	private $owners = array();
	/** @var string[] Only private directories created by this instance may be removed. */
	private $temporary = array();

	/**
	 * Register early independent boundaries without loading any theme or provider code.
	 *
	 * Missing profiles fail closed only for the explicitly protected scope. Existing
	 * native errors and decisions for other plugins or Core remain unchanged.
	 *
	 * @since 1.0.0
	 * @param string[] $protected Explicit native directory/entry-point basenames.
	 * @param string $profile_file Local reviewed JSON file, never a remote URL.
	 * @return void
	 */
	public function __construct( $protected, $profile_file ) {
		$scope = array();
		$valid = is_array( $protected ) && count( $protected ) > 0 && count( $protected ) <= 16;
		foreach ( is_array( $protected ) ? array_slice( $protected, 0, 16 ) : array() as $plugin ) {
			if ( Profile::plugin( $plugin ) && ! in_array( $plugin, $scope, true ) ) {
				$scope[] = $plugin;
			} else {
				$valid = false;
			}
		}
		$this->protected = $scope;
		if ( ! $valid ) {
			$this->loaded = true;
			$this->configuration_error = true;
			update_option( 'skye_update_guard_configuration_error', 'invalid_scope', false );
		}
		$this->profile_file = $profile_file;
		add_filter( 'auto_update_plugin', array( $this, 'automatic' ), PHP_INT_MAX, 2 );
		add_filter( 'upgrader_pre_download', array( $this, 'download' ), PHP_INT_MAX, 4 );
		add_filter( 'upgrader_pre_install', array( $this, 'pre_install' ), 1, 2 );
		add_filter( 'upgrader_source_selection', array( $this, 'source' ), PHP_INT_MAX, 4 );
		add_filter( 'upgrader_install_package_result', array( $this, 'result' ), PHP_INT_MAX, 2 );
		add_action( 'shutdown', array( $this, 'cleanup' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_filter( 'site_status_tests', array( $this, 'health_tests' ) );
	}

	/**
	 * Veto only unknown protected offers without enabling a previously disabled update.
	 *
	 * The archive is still independently checked before installation. An offer or
	 * unchanged version number never establishes original source identity.
	 *
	 * @since 1.0.0
	 * @param bool|null $update Existing native automatic-update decision.
	 * @param object $item Native plugin offer.
	 * @return bool|null Unchanged decision for known/unprotected offers, otherwise false.
	 */
	public function automatic( $update, $item ) {
		$plugin = is_object( $item ) ? ( $item->plugin ?? '' ) : '';
		if ( ! in_array( $plugin, $this->protected, true ) ) {
			return $update;
		}
		$version = is_string( $item->new_version ?? null ) ? $item->new_version : '';
		$this->load();
		if ( is_multisite() || ! isset( $this->profiles[$plugin][$version] ) || ! $this->integration( $plugin, $version ) ) {
			$this->event( $plugin, '', 'unreviewed_offer', 'blocked' );
			return false;
		}
		return $update;
	}

	/**
	 * Verify the complete original package before Core can deactivate a protected plugin.
	 *
	 * Native download_url uses the same HTTP/auth hooks and signature setting as
	 * WP_Upgrader::run (false). Previous WP_Error values remain untouched. Local
	 * packages or earlier downloaded replies are copied, never removed or modified.
	 * Compression and timestamps do not affect the complete extracted-file identity.
	 *
	 * @since 1.0.0
	 * @param false|string|\WP_Error $reply Earlier native pre-download result.
	 * @param string $package Native URL or local package path; never logged or persisted.
	 * @param \WP_Upgrader $upgrader Native owner, deliberately not recursively called.
	 * @param array $extra Native context; bulk updates may supply only plugin.
	 * @return false|string|\WP_Error Original result, verified private archive, or refusal.
	 */
	public function download( $reply, $package, $upgrader, $extra ) {
		$plugin = $extra['plugin'] ?? '';
		if ( ! in_array( $plugin, $this->protected, true ) ) {
			return $reply;
		}
		$this->owners[$plugin] = $upgrader;
		if ( is_wp_error( $reply ) ) {
			$upgrader->result = $reply;
			return $reply;
		}
		unset( $this->pending[$plugin] );
		$this->load();
		if ( is_multisite() || ! isset( $this->profiles[$plugin] ) || ! $this->direct() ) {
			return $this->refuse( $plugin, 'profile_or_filesystem_unavailable' );
		}
		$owned_download = false;
		$local = null;
		try {
			$private = $this->private_directory();
			if ( is_string( $reply ) ) {
				$local = $reply;
			} elseif ( false === $reply && is_string( $package ) && ! preg_match( '~\A[a-zA-Z][a-zA-Z0-9+.-]*://~', $package ) && is_file( $package ) ) {
				$local = $package;
			} elseif ( false === $reply ) {
				$local = download_url( $package, 300, false );
				if ( is_wp_error( $local ) ) {
					$upgrader->result = $local;
					return $local;
				}
				$owned_download = true;
			} else {
				return $this->refuse( $plugin, 'unsupported_download_reply' );
			}
			global $wp_filesystem;
			$archive = $private . '/' . dirname( $plugin ) . '.zip';
			if ( ! is_string( $local ) || ! is_file( $local ) || filesize( $local ) > Profile::MAX_BYTES || ! $wp_filesystem->copy( $local, $archive, false, 0600 ) ) {
				return $this->refuse( $plugin, 'package_unreadable' );
			}
			$this->check_archive( $archive );
			$unpacked = $private . '/unpacked';
			$unzip = unzip_file( $archive, $unpacked );
			if ( is_wp_error( $unzip ) ) {
				return $this->refuse( $plugin, 'package_extraction_failed' );
			}
			$children = array_values( array_diff( scandir( $unpacked ), array( '.', '..' ) ) );
			if ( $children !== array( dirname( $plugin ) ) ) {
				return $this->refuse( $plugin, 'package_target_mismatch' );
			}
			$version = $this->identify( $unpacked . '/' . dirname( $plugin ), $plugin );
			if ( false === $version ) {
				return $this->refuse( $plugin, 'unreviewed_package' );
			}
			if ( ! $this->integration( $plugin, $version ) ) {
				return $this->refuse( $plugin, 'integration_prerequisite_mismatch' );
			}
			$offer = get_site_transient( 'update_plugins' );
			$offered = is_object( $offer ) ? ( $offer->response[$plugin]->new_version ?? null ) : null;
			if ( is_string( $offered ) && $offered !== $version ) {
				return $this->refuse( $plugin, 'offer_package_mismatch' );
			}
			$this->pending[$plugin] = array( 'version' => $version, 'state' => $this->snapshot(), 'original' => $this->identify( WP_PLUGIN_DIR . '/' . dirname( $plugin ), $plugin ) );
			if ( is_plugin_active( $plugin ) && false === $this->pending[$plugin]['original'] ) {
				unset( $this->pending[$plugin] );
				return $this->refuse( $plugin, 'active_original_unreviewed' );
			}
			return $archive;
		} catch ( \Throwable $error ) {
			return $this->refuse( $plugin, 'package_validation_failed' );
		} finally {
			if ( $owned_download && is_string( $local ) && is_file( $local ) ) {
				unlink( $local );
			}
		}
	}

	/**
	 * Refuse missing early approvals before Core's priority-ten deactivation callback.
	 *
	 * @since 1.0.0
	 * @param bool|\WP_Error $response Existing native installation response.
	 * @param array $extra Native package context, including bulk's reduced form.
	 * @return bool|\WP_Error Unchanged result or targeted early refusal.
	 */
	public function pre_install( $response, $extra ) {
		$plugin = $extra['plugin'] ?? '';
		if ( is_wp_error( $response ) || ! in_array( $plugin, $this->protected, true ) ) {
			return $response;
		}
		return isset( $this->pending[$plugin] ) && $this->integration( $plugin, $this->pending[$plugin]['version'] ) ? $response : $this->refuse( $plugin, 'early_approval_missing' );
	}

	/**
	 * Recheck the actual staged tree, including native uploaded ZIP replacements.
	 *
	 * Native install/overwrite has no plugin key and does not deactivate a plugin.
	 * An update can already have deactivated it: on our own late refusal restore
	 * only the unchanged previously reviewed original via native silent activation.
	 * No arbitrary failed upgrade or changed original is ever reactivated here.
	 *
	 * @since 1.0.0
	 * @param string|\WP_Error $source Actual native selected source directory.
	 * @param string $remote_source Native unpacked root, not retained.
	 * @param \WP_Upgrader $upgrader Native upgrader determines plugin-install context.
	 * @param array $extra Native plugin/update/install context.
	 * @return string|\WP_Error Unchanged source or refusal after safe activation restoration.
	 */
	public function source( $source, $remote_source, $upgrader, $extra ) {
		$plugin = $extra['plugin'] ?? '';
		if ( is_wp_error( $source ) ) {
			if ( in_array( $plugin, $this->protected, true ) ) {
				$this->owners[$plugin] = $upgrader;
				return $this->failed( $source, $plugin );
			}
			return $source;
		}
		if ( ! in_array( $plugin, $this->protected, true ) && $upgrader instanceof \Plugin_Upgrader && ( $extra['action'] ?? '' ) === 'install' ) {
			foreach ( $this->protected as $candidate ) {
				if ( basename( rtrim( $source, '/\\' ) ) === dirname( $candidate ) ) {
					$plugin = $candidate;
					break;
				}
			}
		}
		if ( ! in_array( $plugin, $this->protected, true ) ) {
			return $source;
		}
		$this->owners[$plugin] = $upgrader;
		$this->load();
		$version = $this->direct() && basename( rtrim( $source, '/\\' ) ) === dirname( $plugin ) ? $this->identify( $source, $plugin ) : false;
		$pending = $this->pending[$plugin] ?? null;
		if ( is_multisite() || false === $version || ! $this->integration( $plugin, $version ) || ( $pending && $pending['version'] !== $version ) || ( ( $extra['plugin'] ?? '' ) === $plugin && ! $pending ) ) {
			if ( ! $this->restore( $plugin ) ) {
				return $this->refuse( $plugin, 'activation_restore_failed', 'restoration_failed' );
			}
			return $this->refuse( $plugin, 'staged_package_mismatch' );
		}
		if ( ! $pending ) {
			$this->pending[$plugin] = array( 'version' => $version, 'state' => $this->snapshot(), 'original' => $this->identify( WP_PLUGIN_DIR . '/' . dirname( $plugin ), $plugin ) );
		}
		$this->pending[$plugin]['staged'] = true;
		return $source;
	}

	/**
	 * Observe successful changed originals only; a bulk request is not a success record.
	 *
	 * The project must perform a fresh anonymous FPM delivery check. This action
	 * does not claim runtime compatibility and never receives package URLs/errors.
	 *
	 * @since 1.0.0
	 * @param array|\WP_Error $result Actual native single-package result, returned untouched.
	 * @param array $extra Native context; install obtains its identity from destination.
	 * @return array|\WP_Error Unchanged native result.
	 */
	public function result( $result, $extra ) {
		if ( is_wp_error( $result ) ) {
			$plugin = $extra['plugin'] ?? '';
			if ( in_array( $plugin, $this->protected, true ) && isset( $this->pending[$plugin] ) && empty( $this->pending[$plugin]['staged'] ) && empty( $this->pending[$plugin]['failure_handled'] ) && ! str_starts_with( $result->get_error_code(), 'skye_update_guard_' ) ) {
				return $this->failed( $result, $plugin );
			}
			return $result;
		}
		if ( ! is_array( $result ) ) {
			return $result;
		}
		foreach ( $this->pending as $plugin => $pending ) {
			if ( ( $extra['plugin'] ?? '' ) !== $plugin && basename( rtrim( $result['destination'] ?? '', '/\\' ) ) !== dirname( $plugin ) ) {
				continue;
			}
			$version = $this->identify( WP_PLUGIN_DIR . '/' . dirname( $plugin ), $plugin );
			if ( $version === $pending['version'] && $version !== $pending['original'] ) {
				$this->event( $plugin, $version, 'runtime_check_required', 'changed' );
			}
			unset( $this->pending[$plugin] );
		}
		return $result;
	}

	/**
	 * Preserve a native earlier-filter refusal after restoring its still-original active state.
	 *
	 * pre_install errors after Core's deactivation arrive at the result filter;
	 * source-selection errors can be recovered immediately. Never copy the native
	 * error payload into an event, option or log, and never reactivate changed files.
	 *
	 * @since 1.0.0
	 * @param \WP_Error $error Original native error object, retained when recovery succeeds.
	 * @param string $plugin Protected native basename with an early approval snapshot.
	 * @return \WP_Error Original refusal or a persistent critical restoration error.
	 */
	private function failed( $error, $plugin ) {
		if ( ! $this->restore( $plugin ) ) {
			return $this->refuse( $plugin, 'activation_restore_failed', 'restoration_failed' );
		}
		if ( isset( $this->pending[$plugin] ) ) {
			$this->pending[$plugin]['failure_handled'] = true;
		}
		if ( isset( $this->owners[$plugin] ) ) {
			$this->owners[$plugin]->result = $error;
		}
		$this->event( $plugin, '', 'native_installation_refused', 'blocked' );
		return $error;
	}

	/**
	 * Compare the complete staged/original tree with a single exact reviewed version.
	 *
	 * @since 1.0.0
	 * @param string $directory Local direct-filesystem plugin directory.
	 * @param string $plugin Protected native basename.
	 * @return string|false Reviewed exact version or no matching original identity.
	 */
	private function identify( $directory, $plugin ) {
		try {
			$files = Profile::inventory( $directory );
			$data = get_file_data( $directory . '/' . basename( $plugin ), array( 'Version' => 'Version' ) );
			$version = $data['Version'];
			return isset( $this->profiles[$plugin][$version] ) && $files === $this->profiles[$plugin][$version]['files'] ? $version : false;
		} catch ( \Throwable $error ) {
			return false;
		}
	}

	/**
	 * Snapshot only native activation state to permit our own late-refusal recovery.
	 *
	 * @since 1.0.0
	 * @return array Native local activation order for the supported Single Site.
	 */
	private function snapshot() {
		return array( 'local' => (array) get_option( 'active_plugins', array() ) );
	}

	/**
	 * Restore only our formerly active unchanged original, without activation hooks.
	 *
	 * Core may have silently deactivated it before source selection. Preserve the
	 * exact former local order where the activation set otherwise stayed unchanged.
	 * Concurrent unrelated activation changes are never overwritten.
	 *
	 * @since 1.0.0
	 * @param string $plugin Protected plugin under our own source rejection.
	 * @return bool Whether original activation remained intact or was restored safely.
	 */
	private function restore( $plugin ) {
		$pending = $this->pending[$plugin] ?? null;
		if ( ! $pending || ! in_array( $plugin, $pending['state']['local'], true ) || is_plugin_active( $plugin ) ) {
			return true;
		}
		if ( false === $pending['original'] || $this->identify( WP_PLUGIN_DIR . '/' . dirname( $plugin ), $plugin ) !== $pending['original'] ) {
			return false;
		}
		if ( is_wp_error( activate_plugin( $plugin, '', false, true ) ) || ! is_plugin_active( $plugin ) ) {
			return false;
		}
		$current = (array) get_option( 'active_plugins', array() );
		$previous = $pending['state']['local'];
		sort( $current );
		$sorted = $previous;
		sort( $sorted );
		if ( $current === $sorted ) {
			update_option( 'active_plugins', $previous );
		}
		return true;
	}

	/**
	 * Read profiles only for administrative checks, retaining targeted fail-closed scope.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function load() {
		if ( $this->loaded ) {
			return;
		}
		$this->loaded = true;
		try {
			$this->profiles = Profile::load( $this->profile_file, $this->protected );
		} catch ( \Throwable $error ) {
			$this->profiles = null;
			foreach ( $this->protected as $plugin ) {
				$this->event( $plugin, '', 'profile_unavailable', 'blocked' );
			}
		}
	}

	/**
	 * Bind one reviewed provider to the explicitly verified first-party integration.
	 *
	 * @since 1.0.0
	 * @param string $plugin Protected native basename.
	 * @param string $version Exact original version under review.
	 * @return bool Whether all scoped own-source prerequisite hashes currently match.
	 */
	private function integration( $plugin, $version ) {
		$expected = $this->profiles[$plugin][$version]['prerequisites'] ?? null;
		$theme = $this->profiles[$plugin][$version]['theme'] ?? null;
		try {
			return is_array( $expected ) && is_array( $theme ) && $theme['template'] === get_option( 'template' ) && $theme['stylesheet'] === get_option( 'stylesheet' ) &&
				$theme['template'] === get_template() && $theme['stylesheet'] === get_stylesheet() && $expected === Profile::prerequisites( WP_CONTENT_DIR, array_keys( $expected ) );
		} catch ( \Throwable $error ) {
			return false;
		}
	}

	/**
	 * Require the local native filesystem; unsupported transports fail visibly closed.
	 *
	 * @since 1.0.0
	 * @return bool Whether native copy/unzip and local identity checks share the same root.
	 */
	private function direct() {
		global $wp_filesystem;
		return is_object( $wp_filesystem ) && $wp_filesystem->method === 'direct' && class_exists( '\ZipArchive' );
	}

	/**
	 * Allocate only a private native temporary root outside public WordPress directories.
	 *
	 * @since 1.0.0
	 * @return string Newly owned mode-0700 directory.
	 * @throws \RuntimeException For unsafe or unavailable native temporary storage.
	 */
	private function private_directory() {
		$base = realpath( get_temp_dir() );
		foreach ( array( ABSPATH, WP_CONTENT_DIR ) as $public ) {
			$root = realpath( $public );
			if ( ! $base || ! $root || $base === $root || str_starts_with( $base, $root . DIRECTORY_SEPARATOR ) ) {
				throw new \RuntimeException( 'Private update storage is unavailable.' );
			}
		}
		$directory = $base . '/skye-update-' . bin2hex( random_bytes( 16 ) );
		if ( ! mkdir( $directory, 0700 ) ) {
			throw new \RuntimeException( 'Private update storage cannot be created.' );
		}
		$this->temporary[] = $directory;
		return $directory;
	}

	/**
	 * Bound archive expansion before passing it to the original native unzip implementation.
	 *
	 * @since 1.0.0
	 * @param string $archive Owned local ZIP copy, never a caller's mutable archive.
	 * @return void
	 * @throws \RuntimeException For unsafe members, duplicates, links or exceeded budgets.
	 */
	private function check_archive( $archive ) {
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $archive, \ZipArchive::RDONLY ) ) {
			throw new \RuntimeException( 'Unsupported update archive.' );
		}
		try {
			$bytes = 0;
			$seen = array();
			if ( $zip->numFiles > Profile::MAX_FILES * 2 ) {
				throw new \RuntimeException( 'Update archive exceeds its entry budget.' );
			}
			for ( $index = 0; $index < $zip->numFiles; ++$index ) {
				$stat = $zip->statIndex( $index );
				$name = rtrim( $stat['name'] ?? '', '/' );
				$bytes += $stat['size'] ?? Profile::MAX_BYTES + 1;
				$zip->getExternalAttributesIndex( $index, $system, $attributes );
				$type = ( $attributes >> 16 ) & 0170000;
				if ( ! Profile::path( $name ) || isset( $seen[$name] ) || $bytes > Profile::MAX_BYTES || ( $stat['size'] ?? 0 ) > Profile::MAX_FILE_BYTES ||
					( $system === 3 && ! in_array( $type, array( 0, 0100000, 0040000 ), true ) ) || ! empty( $stat['encryption_method'] ) ) {
					throw new \RuntimeException( 'Unsupported update archive member.' );
				}
				$seen[$name] = true;
			}
		} finally {
			$zip->close();
		}
	}

	/**
	 * Remove only this instance's private roots using the original filesystem API.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function cleanup() {
		global $wp_filesystem;
		if ( $this->direct() ) {
			foreach ( $this->temporary as $directory ) {
				$wp_filesystem->delete( $directory, true );
			}
		}
		$this->temporary = array();
	}

	/**
	 * Return a native bounded error without copying provider URLs or transport messages.
	 *
	 * @since 1.0.0
	 * @param string $plugin Protected native basename.
	 * @param string $reason Fixed internal reason code.
	 * @param string $status Blocked or failed-restoration event.
	 * @return \WP_Error Targeted native update refusal.
	 */
	private function refuse( $plugin, $reason, $status = 'blocked' ) {
		$this->event( $plugin, '', $reason, $status );
		/* translators: 1: Plugin basename, 2: Fixed update guard reason code. */
		$error = new \WP_Error( 'skye_update_guard_' . $reason, sprintf( __( 'Update of %1$s refused: %2$s. The package requires an exact reviewed original-file profile.', 'skye-update-guard' ), $plugin, $reason ) );
		// Core's upgrade()/install() wrappers read this public result, not run()'s early return.
		if ( isset( $this->owners[$plugin] ) ) {
			$this->owners[$plugin]->result = $error;
		}
		return $error;
	}

	/**
	 * Publish one bounded nonsecret event for an existing project health integration.
	 *
	 * @since 1.0.0
	 * @param string $plugin Explicit native plugin basename.
	 * @param string $version Validated reviewed version or an empty unknown version.
	 * @param string $reason Fixed internal reason code.
	 * @param string $status blocked, changed or restoration_failed.
	 * @return void
	 */
	private function event( $plugin, $version, $reason, $status ) {
		$event = array( 'plugin' => $plugin, 'version' => $version, 'reason' => $reason, 'status' => $status );
		if ( $status === 'restoration_failed' ) {
			// A unique native option insert cannot overwrite another process's critical record.
			add_option( 'skye_update_guard_critical_' . hash( 'sha256', $plugin ), $event, '', false );
		}
		if ( $status !== 'changed' ) {
			$stored = array_intersect_key( (array) get_option( 'skye_update_guard_status', array() ), array_flip( $this->protected ) );
			$key = $plugin;
			if ( ( $stored[$key]['status'] ?? '' ) !== 'restoration_failed' ) {
				$stored[$key] = $event;
				update_option( 'skye_update_guard_status', $stored, false );
			}
		}
		do_action( 'skye_update_guard_event', $event );
	}

	/**
	 * Expose guard configuration and sticky recovery failures without testing delivery twice.
	 *
	 * @since 1.0.0
	 * @param array $tests Existing native site-health tests.
	 * @return array Original tests plus this independent local guard check.
	 */
	public function health_tests( $tests ) {
		$tests['direct']['skye_update_guard'] = array( 'label' => __( 'Verified plugin update guard', 'skye-update-guard' ), 'test' => array( $this, 'health' ) );
		return $tests;
	}

	/**
	 * Report local profile integrity and retained update failures, never HTTP compatibility.
	 *
	 * A successful delivery event cannot dismiss a failed activation restoration.
	 * An operator must resolve and explicitly remove the affected local status.
	 *
	 * @since 1.0.0
	 * @return array Native site-health result with no provider URLs, source or credentials.
	 */
	public function health() {
		$this->load();
		$critical = $this->profiles === null || $this->configuration_error || get_option( 'skye_update_guard_configuration_error', false ) || is_multisite();
		$warning = false;
		foreach ( $this->profiles ?? array() as $plugin => $versions ) {
			$compatible = false;
			foreach ( array_keys( $versions ) as $version ) {
				$compatible = $compatible || $this->integration( $plugin, $version );
			}
			$critical = $critical || ! $compatible;
		}
		foreach ( array_intersect_key( (array) get_option( 'skye_update_guard_status', array() ), array_flip( $this->protected ) ) as $state ) {
			$critical = $critical || ( $state['status'] ?? '' ) === 'restoration_failed';
			$warning = true;
		}
		foreach ( $this->protected as $plugin ) {
			$critical = $critical || get_option( 'skye_update_guard_critical_' . hash( 'sha256', $plugin ), false ) !== false;
		}
		return array(
			'label' => $critical ? __( 'Plugin update guard requires repair', 'skye-update-guard' ) : ( $warning ? __( 'Plugin update guard retained an update refusal', 'skye-update-guard' ) : __( 'Plugin update guard profiles are valid', 'skye-update-guard' ) ),
			'status' => $critical ? 'critical' : ( $warning ? 'recommended' : 'good' ),
			'badge' => array( 'label' => __( 'Update guard', 'skye-update-guard' ), 'color' => 'blue' ),
			'description' => '<p>' . esc_html__( 'Only explicitly reviewed original plugin files and their verified local integration may pass. This checks the update gate, not frontend delivery.', 'skye-update-guard' ) . '</p>',
			'actions' => '',
			'test' => 'skye_update_guard',
		);
	}

	/**
	 * Keep unresolved gate/recovery errors visible to administrators without sending mail.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function notice() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}
		$result = $this->health();
		if ( $result['status'] !== 'good' ) {
			wp_admin_notice( esc_html( $result['label'] ), array( 'type' => $result['status'] === 'critical' ? 'error' : 'warning', 'dismissible' => false ) );
		}
	}
}
