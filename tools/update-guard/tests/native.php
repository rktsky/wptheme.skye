<?php
/** Exercise the actual native updater and MU guard in an explicitly disposable WordPress site. */

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit;
}

require_once dirname( __DIR__ ) . '/lib/Profile.php';

use Rocketsky\Skye\UpdateGuard\Profile;

/** Own synthetic packages and transport replies without changing any original package. */
final class Skye_Guard_Native_Fixture {
	/** @var int Successful meaningful assertions. */
	private $assertions = 0;
	/** @var string Canonical dedicated disposable WordPress root. */
	private $site;
	/** @var string Random owned private fixture root outside the web directory. */
	private $private;
	/** @var string Authored MU loader path, refused when already present. */
	private $mu;
	/** @var string Authored prerequisite directory, never a real theme. */
	private $integration;
	/** @var array Native option values with a unique absent-value sentinel. */
	private $options = array();
	/** @var mixed Native update transient retained before changing offers. */
	private $updates;
	/** @var array Original native Core-file hashes excluding configuration/content. */
	private $core;
	/** @var array Exact authored old/new plugin file models. */
	private $versions;
	/** @var array Captured bounded public integration events. */
	private $events = array();
	/** @var int Native activation hooks, which late restoration must not invoke. */
	private $activation_hooks = 0;
	/** @var int Number of reached protected pre-install boundaries in the current case. */
	private $pre_install_calls = 0;
	/** @var int Synthetic replies through actual download_url/HTTP code. */
	private $http_calls = 0;
	/** @var string|null Exact authored archive exposed by the synthetic HTTP response. */
	private $http_archive;
	/** @var bool Whether the source hook must corrupt its owned staged fixture. */
	private $corrupt_source = false;
	/** @var bool Whether the real native updater had deactivated the plugin before rejection. */
	private $late_was_inactive = false;
	/** @var string Bounded synthetic updater feedback for a failed assertion only. */
	private $feedback = '';
	/** @var bool Whether native plugin-header validation must reject silent reactivation. */
	private $prevent_reactivation = false;
	/** @var bool Whether to rename only the authored staged root while retaining all bytes. */
	private $rename_source = false;
	/** @var int Existing debug-log bytes, excluded from this run's warning check. */
	private $debug_offset = 0;
	/** @var WP_Error|null Authored upstream error retained by identity across the native pipeline. */
	private $external_error;
	/** @var int|string|null Native hook point producing the authored upstream error. */
	private $external_stage;
	/** @var mixed Public result of the most recent actual single/bulk native owner. */
	private $owner_result;

	private const PLUGIN = 'skye-guard-fixture/main.php';
	private const OTHER = 'skye-guard-unprotected/main.php';
	private const PREREQUISITE = 'themes/skye-guard-integration/integration.php';
	private const HTTP_URL = 'https://example.test/skye-native-guard-fixture.zip';

	/**
	 * Refuse arbitrary sites before creating only new authored test paths.
	 *
	 * @since 1.0.0
	 * @param string $site Dedicated site made by tools/testing/setup-site.py.
	 * @return void
	 * @throws RuntimeException For missing ownership or existing foreign fixture paths.
	 */
	public function __construct( $site ) {
		$this->site = realpath( $site );
		$marker = $this->site ? dirname( $this->site ) . '/.skye-disposable.json' : '';
		$owner = is_file( $marker ) ? json_decode( file_get_contents( $marker ), true ) : null;
		if ( ! $this->site || ! is_array( $owner ) || ( $owner['root'] ?? '' ) !== dirname( $this->site ) || ! is_file( $this->site . '/wp-load.php' ) ) {
			throw new RuntimeException( 'Expected an explicitly owned disposable WordPress site.' );
		}
		$this->mu = $this->site . '/wp-content/mu-plugins/skye-update-guard-native-tests.php';
		$this->integration = $this->site . '/wp-content/themes/skye-guard-integration';
		foreach ( array( $this->mu, $this->integration, $this->site . '/wp-content/plugins/' . dirname( self::PLUGIN ), $this->site . '/wp-content/plugins/' . dirname( self::OTHER ) ) as $path ) {
			if ( file_exists( $path ) || is_link( $path ) ) {
				throw new RuntimeException( 'Refusing an existing/foreign native guard fixture path.' );
			}
		}
		$this->private = dirname( $this->site ) . '/guard-native-' . bin2hex( random_bytes( 8 ) );
		$this->debug_offset = is_file( dirname( $this->site ) . '/debug.log' ) ? filesize( dirname( $this->site ) . '/debug.log' ) : 0;
		mkdir( $this->private, 0700 );
		mkdir( $this->integration, 0700 );
		file_put_contents( $this->integration . '/integration.php', "<?php\n/** Authored integration identity, deliberately never executed. */\n" );
		$this->versions = array( '1.0.0' => $this->files( '1.0.0' ), '1.1.0' => $this->files( '1.1.0' ) );
		$versions = array();
		foreach ( $this->versions as $version => $files ) {
			$original = $this->private . '/original-' . $version;
			$this->write_tree( $original, $files );
			$versions[$version] = array( 'prerequisites' => array( self::PREREQUISITE => hash_file( 'sha256', $this->integration . '/integration.php' ) ), 'theme' => array( 'stylesheet' => 'skye', 'template' => 'skye' ), 'files' => Profile::inventory( $original ) );
		}
		$profile = $this->private . '/profile.json';
		file_put_contents( $profile, json_encode( array( 'plugins' => array( self::PLUGIN => $versions ), 'schema' => 1 ), JSON_THROW_ON_ERROR ) );
		chmod( $profile, 0600 );
		file_put_contents( $this->mu, "<?php\n/** Load the actual update guard before themes in this owned native fixture. */\ndefine( 'SKYE_UPDATE_GUARD_PLUGINS', " . var_export( array( self::PLUGIN ), true ) . " );\ndefine( 'SKYE_UPDATE_GUARD_PROFILE', " . var_export( $profile, true ) . " );\nrequire " . var_export( dirname( __DIR__ ) . '/bootstrap.php', true ) . ";\n" );
		$this->core = $this->core_identity();
	}

	/**
	 * Describe inert authored plugin code rather than copying provider implementation.
	 *
	 * @since 1.0.0
	 * @param string $version Exact synthetic version.
	 * @return array<string,string> Complete authored package bytes.
	 */
	private function files( $version ) {
		return array( 'main.php' => "<?php\n/**\n * Plugin Name: Skye native guard fixture\n * Version: " . $version . "\n */\n", 'assets/native.js' => 'window.skyeNativeGuard = ' . json_encode( $version ) . ";\n", '.original' => 'Complete original identity ' . $version . "\n" );
	}

	/**
	 * Write only an owned authored tree, including hidden original members.
	 *
	 * @since 1.0.0
	 * @param string $directory New or previously owned destination.
	 * @param array<string,string> $files Explicit fixture bytes.
	 * @return void
	 */
	private function write_tree( $directory, $files ) {
		if ( ! is_dir( $directory ) ) {
			mkdir( $directory, 0700, true );
		}
		foreach ( $files as $relative => $bytes ) {
			$parent = dirname( $directory . '/' . $relative );
			if ( ! is_dir( $parent ) ) {
				mkdir( $parent, 0700, true );
			}
			file_put_contents( $directory . '/' . $relative, $bytes );
		}
	}

	/**
	 * Create a small package fixture with real archive metadata and optional unsafe member.
	 *
	 * @since 1.0.0
	 * @param string $label Unique owned archive label.
	 * @param string $plugin Target native basename.
	 * @param array<string,string> $files Exact authored members.
	 * @param bool $traversal Whether to include the deliberately unsafe traversal member.
	 * @param bool $repacked Whether to vary entry order, timestamps and compression only.
	 * @return string New private archive path.
	 */
	private function archive( $label, $plugin, $files, $traversal = false, $repacked = false ) {
		$path = $this->private . '/' . $label . '.zip';
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path, ZipArchive::CREATE | ZipArchive::EXCL ) ) {
			throw new RuntimeException( 'Cannot create the authored native ZIP fixture.' );
		}
		foreach ( $repacked ? array_reverse( $files, true ) : $files as $name => $bytes ) {
			$member = dirname( $plugin ) . '/' . $name;
			$zip->addFromString( $member, $bytes );
			if ( $repacked ) {
				$zip->setCompressionName( $member, ZipArchive::CM_STORE );
				$zip->setMtimeName( $member, 1577836800 );
			}
		}
		if ( $traversal ) {
			$zip->addFromString( '../must-not-exist.txt', 'Rejected before extraction.' );
		}
		$zip->close();
		return $path;
	}

	/**
	 * Hash original Core only; SQLite, authored plugins and local configuration are excluded.
	 *
	 * @since 1.0.0
	 * @return array<string,string> Complete original Core-file identity.
	 */
	private function core_identity() {
		$files = array();
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->site, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
			$relative = substr( $file->getPathname(), strlen( $this->site ) + 1 );
			if ( str_starts_with( $relative, 'wp-content/' ) || $relative === 'wp-config.php' || $relative === '.maintenance' || $file->isLink() || ! $file->isFile() ) {
				continue;
			}
			$files[$relative] = hash_file( 'sha256', $file->getPathname() );
		}
		ksort( $files );
		return $files;
	}

	/**
	 * Fail immediately with a meaningful contract assertion instead of swallowing native errors.
	 *
	 * @since 1.0.0
	 * @param bool $condition Actual observed native behavior.
	 * @param string $message Concrete asserted contract.
	 * @return void
	 * @throws RuntimeException If the native contract fails.
	 */
	private function check( $condition, $message ) {
		if ( ! $condition ) {
			throw new RuntimeException( $message );
		}
		++$this->assertions;
	}

	/**
	 * Capture only the documented four-field public event model.
	 *
	 * @since 1.0.0
	 * @param array $event Bounded production event.
	 * @return void
	 */
	public function event( $event ) {
		$this->events[] = $event;
	}

	/**
	 * Count native activation hooks so silent recovery cannot masquerade as a fresh activation.
	 *
	 * @since 1.0.0
	 * @param string $plugin Native plugin basename.
	 * @return void
	 */
	public function activation( $plugin ) {
		if ( $plugin === self::PLUGIN ) {
			++$this->activation_hooks;
		}
	}

	/**
	 * Observe actual pre-install reachability without changing its native response.
	 *
	 * @since 1.0.0
	 * @param bool|WP_Error $response Native installation response.
	 * @param array $extra Native package context.
	 * @return bool|WP_Error Unchanged native response.
	 */
	public function pre_install( $response, $extra ) {
		if ( ( $extra['plugin'] ?? '' ) === self::PLUGIN ) {
			++$this->pre_install_calls;
		}
		return $response;
	}

	/**
	 * Corrupt only our authored staged tree after real Core deactivation, never originals.
	 *
	 * @since 1.0.0
	 * @param string|WP_Error $source Native selected working source.
	 * @param string $remote Native unselected working root.
	 * @param WP_Upgrader $upgrader Native owner, retained unmodified.
	 * @param array $extra Native plugin context.
	 * @return string|WP_Error Unchanged native source location.
	 */
	public function source( $source, $remote, $upgrader, $extra ) {
		if ( $this->rename_source && ! is_wp_error( $source ) && ( $extra['plugin'] ?? '' ) === self::PLUGIN ) {
			$this->late_was_inactive = ! is_plugin_active( self::PLUGIN );
			$renamed = rtrim( $source, '/\\' ) . '-unreviewed-target';
			if ( ! rename( rtrim( $source, '/\\' ), $renamed ) ) {
				throw new RuntimeException( 'Cannot rename the owned staged test root.' );
			}
			return $renamed;
		}
		if ( $this->corrupt_source && ! is_wp_error( $source ) && ( $extra['plugin'] ?? '' ) === self::PLUGIN ) {
			$this->late_was_inactive = ! is_plugin_active( self::PLUGIN );
			file_put_contents( $source . '/late-mutated.txt', 'Owned staged source corruption.' );
			if ( $this->prevent_reactivation ) {
				// Make native validate_plugin reject the header without changing original files.
				wp_cache_set( 'plugins', array( '' => array() ), 'plugins' );
			}
		}
		return $source;
	}

	/**
	 * Return one authored upstream pre-install error at its actual native priority.
	 *
	 * Priorities five and twenty distinguish a refusal before Core deactivation
	 * from one after it; the guard must preserve both exact native error objects.
	 *
	 * @since 1.0.0
	 * @param bool|WP_Error $response Current native filter response.
	 * @param array $extra Original package hook context.
	 * @return bool|WP_Error Authored upstream error only at the configured test boundary.
	 */
	public function upstream_install_error( $response, $extra ) {
		if ( $this->external_error && ( $extra['plugin'] ?? '' ) === self::PLUGIN && $GLOBALS['wp_filter']['upgrader_pre_install']->current_priority() === $this->external_stage ) {
			$this->late_was_inactive = ! is_plugin_active( self::PLUGIN );
			return $this->external_error;
		}
		return $response;
	}

	/**
	 * Return an authored upstream source error after genuine native deactivation.
	 *
	 * @since 1.0.0
	 * @param string|WP_Error $source Current native selected source.
	 * @param string $remote Native unpacked source root, retained unchanged.
	 * @param WP_Upgrader $upgrader Original native owner.
	 * @param array $extra Original package context.
	 * @return string|WP_Error Authored upstream error only for its explicit probe.
	 */
	public function upstream_source_error( $source, $remote, $upgrader, $extra ) {
		if ( $this->external_error && $this->external_stage === 'source' && ( $extra['plugin'] ?? '' ) === self::PLUGIN ) {
			$this->late_was_inactive = ! is_plugin_active( self::PLUGIN );
			return $this->external_error;
		}
		return $source;
	}

	/**
	 * Stream one exact authored ZIP through the real Core download_url HTTP contract.
	 *
	 * All unrelated requests retain the fixture's original external-transport block.
	 * No TLS, authentication, HTTP parser or downloader implementation is replaced.
	 *
	 * @since 1.0.0
	 * @param mixed $previous Existing native preflight response.
	 * @param array $arguments Original native HTTP request arguments.
	 * @param string $url Exact requested URL.
	 * @return mixed Synthetic fixture response only for its exact configured URL.
	 */
	public function http( $previous, $arguments, $url ) {
		if ( $url !== self::HTTP_URL || ! $this->http_archive ) {
			return $previous;
		}
		++$this->http_calls;
		if ( empty( $arguments['stream'] ) || empty( $arguments['filename'] ) || ! copy( $this->http_archive, $arguments['filename'] ) ) {
			return new WP_Error( 'skye_guard_fixture_stream', 'Native fixture download was not streamed.' );
		}
		return array( 'headers' => array(), 'body' => '', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => $arguments['filename'] );
	}

	/**
	 * Supply an actual native update offer while retaining the caller-owned package.
	 *
	 * @since 1.0.0
	 * @param string $plugin Authored plugin target.
	 * @param string $package Local fixture path or exact synthetic HTTP URL.
	 * @param string $version Actual offered version.
	 * @return object Native updater offer object.
	 */
	private function offer( $plugin, $package, $version ) {
		$item = (object) array( 'plugin' => $plugin, 'slug' => dirname( $plugin ), 'new_version' => $version, 'package' => $package, 'url' => 'https://example.test/', 'autoupdate' => true );
		set_site_transient( 'update_plugins', (object) array( 'last_checked' => time(), 'checked' => array( $plugin => '1.0.0' ), 'response' => array( $plugin => $item ), 'no_update' => array() ) );
		return $item;
	}

	/**
	 * Run the actual native single or bulk upgrade with bounded captured fixture feedback.
	 *
	 * @since 1.0.0
	 * @param string $plugin Authored native target.
	 * @param string $package Local fixture archive or exact synthetic HTTP URL.
	 * @param string $version Explicit offered version.
	 * @param string $mode Native single, bulk, automatic or install execution path.
	 * @return mixed Native upgrader result without interpreting a bulk request as success.
	 */
	private function upgrade( $plugin, $package, $version, $mode = 'single' ) {
		$item = $this->offer( $plugin, $package, $version );
		$upgrader = null;
		ob_start();
		try {
			if ( $mode === 'automatic' ) {
				return ( new WP_Automatic_Updater() )->update( 'plugin', $item );
			}
			if ( $mode === 'bulk' ) {
				$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
				$results = $upgrader->bulk_upgrade( array( $plugin ), array( 'clear_update_cache' => false ) );
				return is_array( $results ) ? ( $results[$plugin] ?? null ) : $results;
			}
			$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
			return $mode === 'install' ? $upgrader->install( $package, array( 'overwrite_package' => true, 'clear_update_cache' => false ) ) : $upgrader->upgrade( $plugin, array( 'clear_update_cache' => false ) );
		} finally {
			// Each case completes its native request lifecycle before reusing this slug.
			if ( $upgrader ) {
				$this->owner_result = $upgrader->result;
				$restored = $upgrader->restore_temp_backup();
				$deleted = $upgrader->delete_temp_backup();
				remove_action( 'shutdown', array( $upgrader, 'restore_temp_backup' ), 10 );
				remove_action( 'shutdown', array( $upgrader, 'delete_temp_backup' ), 100 );
				if ( is_wp_error( $restored ) || is_wp_error( $deleted ) ) {
					throw new RuntimeException( 'Native fixture request backup finalization failed.' );
				}
			}
			$this->feedback = substr( wp_strip_all_tags( ob_get_clean() ), 0, 2000 );
		}
	}

	/**
	 * Restore the authored baseline between independent behavioral cases.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function baseline() {
		$this->remove( WP_PLUGIN_DIR . '/' . dirname( self::PLUGIN ) );
		$this->write_tree( WP_PLUGIN_DIR . '/' . dirname( self::PLUGIN ), $this->versions['1.0.0'] );
		wp_clean_plugins_cache( false );
		$active = $this->options['active_plugins'];
		$active[] = self::PLUGIN;
		update_option( 'active_plugins', $active );
		$this->activation_hooks = 0;
		$this->pre_install_calls = 0;
		$this->events = array();
		$this->late_was_inactive = false;
	}

	/**
	 * Assert that a real native rejection retained both original bytes and exact activation.
	 *
	 * @since 1.0.0
	 * @param string $label Unique synthetic archive and assertion label.
	 * @param array<string,string> $files Authored received package members.
	 * @param string $version Advertised native version.
	 * @param string $mode Native updater path.
	 * @param bool $traversal Whether the ZIP must include a traversal member.
	 * @param bool $early Whether rejection must occur before native pre-install entirely.
	 * @return WP_Error Actual native refusal, retained for upstream-error identity checks.
	 */
	private function reject( $label, $files, $version, $mode = 'single', $traversal = false, $early = true ) {
		$this->baseline();
		$before = Profile::inventory( WP_PLUGIN_DIR . '/' . dirname( self::PLUGIN ) );
		$active = get_option( 'active_plugins' );
		$archive = $this->archive( $label, self::PLUGIN, $files, $traversal );
		$original_zip = hash_file( 'sha256', $archive );
		$result = $this->upgrade( self::PLUGIN, $archive, $version, $mode );
		$this->check( is_wp_error( $result ), $label . ': the real native updater returns an error; got ' . get_debug_type( $result ) . ': ' . $this->feedback );
		$this->check( Profile::inventory( WP_PLUGIN_DIR . '/' . dirname( self::PLUGIN ) ) === $before, $label . ': every installed original byte remains identical.' );
		$this->check( get_option( 'active_plugins' ) === $active && is_plugin_active( self::PLUGIN ), $label . ': original native activation and ordering remain identical.' );
		$this->check( hash_file( 'sha256', $archive ) === $original_zip, $label . ': caller-owned archive is neither removed nor modified.' );
		$this->check( $this->activation_hooks === 0, $label . ': rejection does not invoke native activation hooks.' );
		$this->check( ! array_filter( $this->events, array( $this, 'changed' ) ), $label . ': a failed result never announces a changed package.' );
		if ( $early ) {
			$this->check( $this->pre_install_calls === 0, $label . ': rejection precedes Core deactivation and installation.' );
		}
		return $result;
	}

	/**
	 * Prove other native filter owners keep their exact error and original activation.
	 *
	 * @since 1.0.0
	 * @param string $label Unique authored package/assertion identity.
	 * @param int|string $stage Native pre-install priority or source selector.
	 * @return void
	 */
	private function upstream_rejection( $label, $stage ) {
		$this->external_error = new WP_Error( 'skye_authored_upstream_error', 'The authored upstream integration refused this package.' );
		$this->external_stage = $stage;
		$result = $this->reject( $label, $this->versions['1.1.0'], '1.1.0', 'single', false, false );
		$this->check( $result === $this->external_error && $this->owner_result === $this->external_error, $label . ': the identical upstream error survives in both outer native return and public owner result.' );
		$this->check( $this->late_was_inactive === ( $stage !== 5 ), $label . ': the probe reaches its intended side of native Core deactivation.' );
		$this->external_error = null;
		$this->external_stage = null;
	}

	/**
	 * Select successful-change events without depending on translated message text.
	 *
	 * @since 1.0.0
	 * @param array $event Actual production event.
	 * @return bool Whether a package was reported changed.
	 */
	public function changed( $event ) {
		return ( $event['status'] ?? '' ) === 'changed';
	}

	/**
	 * Prove the real official CLI still loads the MU gate while skipping themes and plugins.
	 *
	 * The child executes original CLI/Core code and the actual production runtime.
	 * Its small authored probe only invokes the updater and inspects native results.
	 * No bootstrap or provider implementation is duplicated for this test.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function cli() {
		$cli = getenv( 'SKYE_WP_CLI' ) ?: dirname( dirname( $this->site ) ) . '/update-guard-tools/wp-cli-2.12.0.phar';
		$this->check( is_file( $cli ), 'The official WP-CLI PHAR is explicitly available for the skip-themes regression.' );
		$this->baseline();
		$archive = $this->archive( 'cli-unreviewed', self::PLUGIN, $this->files( '9.0.0' ) );
		$probe = $this->private . '/cli-probe.php';
		$program = <<<'PHP'
<?php
/** Invoke the actual native updater with themes and normal plugins skipped. */
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
WP_Filesystem();
$plugin = 'skye-guard-fixture/main.php';
$loaded = class_exists( '\Rocketsky\Skye\UpdateGuard\Guard', false );
$theme_skipped = ! class_exists( '\Rocketsky\Skye\Packages\Branding', false );
$before = \Rocketsky\Skye\UpdateGuard\Profile::inventory( WP_PLUGIN_DIR . '/skye-guard-fixture' );
$active = get_option( 'active_plugins' );
$offer = (object) array( 'plugin' => $plugin, 'slug' => 'skye-guard-fixture', 'new_version' => '9.0.0', 'package' => $args[0] );
set_site_transient( 'update_plugins', (object) array( 'last_checked' => time(), 'checked' => array( $plugin => '1.0.0' ), 'response' => array( $plugin => $offer ) ) );
ob_start();
$result = ( new Plugin_Upgrader( new Automatic_Upgrader_Skin() ) )->upgrade( $plugin, array( 'clear_update_cache' => false ) );
ob_end_clean();
$files_unchanged = \Rocketsky\Skye\UpdateGuard\Profile::inventory( WP_PLUGIN_DIR . '/skye-guard-fixture' ) === $before;
$activation_unchanged = get_option( 'active_plugins' ) === $active && is_plugin_active( $plugin );
if ( ! $loaded || ! $theme_skipped || ! is_wp_error( $result ) || ! $files_unchanged || ! $activation_unchanged ) {
	fwrite( STDERR, "Actual CLI skip-themes update protection failed.\n" );
	exit( 2 );
}
echo json_encode( array( 'mu_loaded' => true, 'theme_skipped' => true, 'native_error' => true, 'files_unchanged' => true, 'activation_unchanged' => true ) ) . "\n";
PHP;
		file_put_contents( $probe, $program );
		$process = proc_open( array( PHP_BINARY, $cli, '--path=' . $this->site, '--skip-themes', '--skip-plugins', '--no-color', 'eval-file', $probe, $archive ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
		$this->check( is_resource( $process ), 'The actual WP-CLI child process starts.' );
		fclose( $pipes[0] );
		$output = stream_get_contents( $pipes[1] );
		$error = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$status = proc_close( $process );
		$this->check( $status === 0, 'Actual WP-CLI child succeeds: ' . substr( $error, 0, 1000 ) );
		$result = json_decode( trim( $output ), true );
		$this->check( is_array( $result ) && $result === array( 'mu_loaded' => true, 'theme_skipped' => true, 'native_error' => true, 'files_unchanged' => true, 'activation_unchanged' => true ), 'The real CLI confirms independent MU loading and unchanged native state.' );
	}

	/**
	 * Keep a real failed native restoration critical after later successful package changes.
	 *
	 * Only the authored plugin's native metadata cache is controlled to trigger
	 * validate_plugin's genuine failure; no production or original code is patched.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function critical_restoration() {
		$this->baseline();
		$before = Profile::inventory( WP_PLUGIN_DIR . '/' . dirname( self::PLUGIN ) );
		$this->corrupt_source = true;
		$this->prevent_reactivation = true;
		$archive = $this->archive( 'failed-native-restoration', self::PLUGIN, $this->versions['1.1.0'] );
		$result = $this->upgrade( self::PLUGIN, $archive, '1.1.0' );
		$this->check( is_wp_error( $result ) && $this->late_was_inactive && ! is_plugin_active( self::PLUGIN ), 'A genuine native activation-validation failure is returned instead of claiming restoration.' );
		$this->check( Profile::inventory( WP_PLUGIN_DIR . '/' . dirname( self::PLUGIN ) ) === $before, 'Failed native restoration still preserves every original plugin byte.' );
		$state = get_option( 'skye_update_guard_status' );
		$this->check( ( $state[self::PLUGIN]['status'] ?? '' ) === 'restoration_failed', 'A failed native restoration persists its bounded critical guard status.' );
		$this->corrupt_source = false;
		$this->prevent_reactivation = false;
		wp_clean_plugins_cache( false );
		$this->baseline();
		$repaired = $this->archive( 'later-reviewed-change', self::PLUGIN, $this->versions['1.1.0'] );
		$this->check( $this->upgrade( self::PLUGIN, $repaired, '1.1.0' ) === true && count( array_filter( $this->events, array( $this, 'changed' ) ) ) === 1, 'A later actual reviewed package change is still observed normally.' );
		$this->check( get_option( 'skye_update_guard_status' ) === $state, 'A later changed event cannot erase the failed-restoration status.' );
		$tests = apply_filters( 'site_status_tests', array( 'direct' => array(), 'async' => array() ) );
		$callback = $tests['direct']['skye_update_guard']['test'] ?? null;
		$this->check( is_callable( $callback ) && ( call_user_func( $callback )['status'] ?? '' ) === 'critical', 'The actual independent guard Site Health test retains the critical restoration diagnosis.' );
	}

	/**
	 * Run a fresh real WordPress process for an atomic-record persistence probe.
	 *
	 * @since 1.0.0
	 * @param string $program Authored bounded PHP probe using the actual runtime.
	 * @return array Parsed JSON result, after asserting a clean native process exit.
	 */
	private function status_child( $program ) {
		$process = proc_open( array( PHP_BINARY, '-r', $program, $this->site, self::PLUGIN ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
		$this->check( is_resource( $process ), 'The independent native status process starts.' );
		fclose( $pipes[0] );
		$output = stream_get_contents( $pipes[1] );
		$error = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$status = proc_close( $process );
		$this->check( $status === 0 && $error === '', 'The independent native status process exits without diagnostics: ' . substr( $error, 0, 1000 ) );
		$result = json_decode( $output, true );
		$this->check( is_array( $result ), 'The independent native status process returns its bounded JSON evidence.' );
		return $result;
	}

	/**
	 * Retain a concurrently recorded critical failure despite an older warm option cache.
	 *
	 * The actual native recovery failure is tested separately above. This probe
	 * exercises only its production recorder in another real Core/SQLite process,
	 * then makes a genuine milder automatic-decision refusal in the warm parent.
	 * A final fresh process checks persistent state rather than a request cache.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function concurrent_status() {
		$key = 'skye_update_guard_critical_' . hash( 'sha256', self::PLUGIN );
		delete_option( $key );
		update_option( 'skye_update_guard_status', array( self::PLUGIN => array( 'plugin' => self::PLUGIN, 'version' => '', 'reason' => 'authored_previous_refusal', 'status' => 'blocked' ) ), false );
		get_option( 'skye_update_guard_status' );
		$record = <<<'PHP'
require $argv[1] . '/wp-load.php';
$tests = apply_filters( 'site_status_tests', array( 'direct' => array(), 'async' => array() ) );
$guard = $tests['direct']['skye_update_guard']['test'][0];
$recorder = new ReflectionMethod( $guard, 'event' );
$recorder->invoke( $guard, $argv[2], '', 'activation_restore_failed', 'restoration_failed' );
$key = 'skye_update_guard_critical_' . hash( 'sha256', $argv[2] );
echo json_encode( array( 'critical' => get_option( $key )['status'] ?? '' ) );
PHP;
		$recorded = $this->status_child( $record );
		$this->check( ( $recorded['critical'] ?? '' ) === 'restoration_failed', 'Another real WordPress process creates the production critical record.' );
		$this->check( apply_filters( 'auto_update_plugin', true, (object) array( 'plugin' => self::PLUGIN, 'new_version' => '9.0.0' ) ) === false, 'The warm parent subsequently records its actual milder protected refusal.' );
		$read = <<<'PHP'
require $argv[1] . '/wp-load.php';
$tests = apply_filters( 'site_status_tests', array( 'direct' => array(), 'async' => array() ) );
$result = call_user_func( $tests['direct']['skye_update_guard']['test'] );
$key = 'skye_update_guard_critical_' . hash( 'sha256', $argv[2] );
echo json_encode( array( 'critical' => get_option( $key )['status'] ?? '', 'health' => $result['status'] ) );
PHP;
		$fresh = $this->status_child( $read );
		$this->check( ( $fresh['critical'] ?? '' ) === 'restoration_failed' && ( $fresh['health'] ?? '' ) === 'critical', 'A fresh native process still sees the critical recovery record and Site Health after the stale parent refusal.' );
	}

	/**
	 * Bootstrap native Core and run the full targeted state-preservation sequence.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function run() {
		$_SERVER['HTTP_HOST'] = '127.0.0.1:8973';
		$_SERVER['REQUEST_METHOD'] = 'GET';
		require $this->site . '/wp-load.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-automatic-updater.php';
		WP_Filesystem();
		$this->check( class_exists( '\Rocketsky\Skye\UpdateGuard\Guard', false ) && has_filter( 'upgrader_pre_download' ) !== false, 'The actual MU loader registers the production guard before theme bootstrap finishes.' );
		foreach ( array( 'active_plugins', 'template', 'stylesheet', 'auto_plugin_theme_update_emails', 'skye_update_guard_status', 'skye_update_guard_configuration_error', 'skye_update_guard_critical_' . hash( 'sha256', self::PLUGIN ), 'recently_activated' ) as $name ) {
			$this->options[$name] = get_option( $name, '__skye_native_absent__' );
		}
		$this->updates = get_site_transient( 'update_plugins' );
		add_filter( 'file_mod_allowed', '__return_true' );
		add_filter( 'automatic_updater_disabled', '__return_false' );
		add_filter( 'automatic_updates_is_vcs_checkout', '__return_false' );
		add_action( 'skye_update_guard_event', array( $this, 'event' ) );
		add_action( 'activate_plugin', array( $this, 'activation' ) );
		add_filter( 'upgrader_pre_install', array( $this, 'pre_install' ), 9, 2 );
		add_filter( 'upgrader_pre_install', array( $this, 'upstream_install_error' ), 5, 2 );
		add_filter( 'upgrader_pre_install', array( $this, 'upstream_install_error' ), 20, 2 );
		add_filter( 'upgrader_source_selection', array( $this, 'upstream_source_error' ), 5, 4 );
		add_filter( 'upgrader_source_selection', array( $this, 'source' ), 20, 4 );
		add_filter( 'pre_http_request', array( $this, 'http' ), PHP_INT_MAX, 3 );
		$this->baseline();
		$this->check( apply_filters( 'auto_update_core', true, (object) array() ) === true, 'Core automatic-update policy is unchanged.' );
		$this->check( apply_filters( 'auto_update_plugin', null, (object) array( 'plugin' => self::OTHER, 'new_version' => '9.0.0' ) ) === null, 'Unprotected automatic decisions preserve native null.' );
		$this->check( apply_filters( 'auto_update_plugin', false, (object) array( 'plugin' => self::PLUGIN, 'new_version' => '1.1.0' ) ) === false, 'A known original never enables a disabled automatic update.' );
		$this->check( apply_filters( 'auto_update_plugin', true, (object) array( 'plugin' => self::PLUGIN, 'new_version' => '9.0.0' ) ) === false, 'An unknown protected automatic offer is refused.' );
		$this->reject( 'unknown-version', $this->files( '9.0.0' ), '9.0.0' );
		$manipulated = $this->versions['1.1.0'];
		$manipulated['assets/native.js'] .= 'window.unreviewed = true;';
		$this->reject( 'changed-source', $manipulated, '1.1.0' );
		$extra = $this->versions['1.1.0'];
		$extra['unexpected.php'] = "<?php\nthrow new RuntimeException('This package must never execute.');\n";
		$this->reject( 'extra-source', $extra, '1.1.0' );
		$missing = $this->versions['1.1.0'];
		unset( $missing['.original'] );
		$this->reject( 'missing-source', $missing, '1.1.0' );
		$this->reject( 'traversal', $this->versions['1.1.0'], '1.1.0', 'single', true );
		$this->reject( 'offer-mismatch', $this->versions['1.0.0'], '1.1.0' );
		$this->reject( 'bulk-changed-source', $manipulated, '1.1.0', 'bulk' );
		$this->reject( 'automatic-changed-source', $manipulated, '1.1.0', 'automatic' );
		$integration = file_get_contents( $this->integration . '/integration.php' );
		file_put_contents( $this->integration . '/integration.php', $integration . '// Unreviewed integration generation.' );
		$this->reject( 'integration-drift', $this->versions['1.1.0'], '1.1.0' );
		file_put_contents( $this->integration . '/integration.php', $integration );
		update_option( 'template', 'skye-guard-integration' );
		$this->reject( 'parent-theme-drift', $this->versions['1.1.0'], '1.1.0' );
		update_option( 'template', $this->options['template'] );
		update_option( 'stylesheet', 'skye-guard-integration' );
		$this->reject( 'child-theme-drift', $this->versions['1.1.0'], '1.1.0' );
		update_option( 'stylesheet', $this->options['stylesheet'] );
		$this->corrupt_source = true;
		$this->reject( 'late-source-corruption', $this->versions['1.1.0'], '1.1.0', 'single', false, false );
		$this->check( $this->late_was_inactive, 'The late-corruption probe actually reaches source selection after native Core deactivation.' );
		$this->corrupt_source = false;
		$this->rename_source = true;
		$this->reject( 'late-target-rename', $this->versions['1.1.0'], '1.1.0', 'single', false, false );
		$this->check( $this->late_was_inactive, 'The changed-target probe actually reaches source selection after native Core deactivation.' );
		$this->rename_source = false;
		$this->upstream_rejection( 'upstream-source-error', 'source' );
		$this->upstream_rejection( 'upstream-preinstall-early-error', 5 );
		$this->upstream_rejection( 'upstream-preinstall-late-error', 20 );
		$this->reject( 'uploaded-overwrite-corruption', $manipulated, '1.1.0', 'install', false, false );
		$this->cli();
		$this->baseline();
		$this->http_archive = $this->archive( 'http-original', self::PLUGIN, $this->versions['1.1.0'] );
		$result = $this->upgrade( self::PLUGIN, self::HTTP_URL, '1.1.0' );
		$this->check( $result === true, 'An exact reviewed original passes the real native single updater and HTTP downloader.' );
		$this->check( $this->http_calls === 1, 'Exactly one actual native download_url streaming request uses the synthetic transport.' );
		$this->check( Profile::inventory( WP_PLUGIN_DIR . '/' . dirname( self::PLUGIN ) ) === Profile::inventory( $this->private . '/original-1.1.0' ), 'The permitted original installs its complete expected file identity.' );
		$this->check( count( array_filter( $this->events, array( $this, 'changed' ) ) ) === 1, 'A successful changed single package emits exactly one required-runtime-check event.' );
		foreach ( $this->events as $event ) {
			$keys = array_keys( $event );
			sort( $keys );
			$this->check( $keys === array( 'plugin', 'reason', 'status', 'version' ) && $event['plugin'] === self::PLUGIN && $event['version'] === '1.1.0' && $event['status'] === 'changed' && ! str_contains( json_encode( $event ), 'https:' ), 'Events expose only the exact bounded nonsecret public model.' );
		}
		$this->baseline();
		$same = $this->archive( 'same-original', self::PLUGIN, $this->versions['1.0.0'] );
		$this->check( $this->upgrade( self::PLUGIN, $same, '1.0.0' ) === true && ! array_filter( $this->events, array( $this, 'changed' ) ), 'Reinstalling an identical reviewed original announces no false change.' );
		$this->baseline();
		$repacked = $this->archive( 'same-original-repacked', self::PLUGIN, $this->versions['1.0.0'], false, true );
		$this->check( hash_file( 'sha256', $same ) !== hash_file( 'sha256', $repacked ), 'The repacked original actually changes ZIP compression, timestamps and entry order.' );
		$this->check( $this->upgrade( self::PLUGIN, $repacked, '1.0.0' ) === true && ! array_filter( $this->events, array( $this, 'changed' ) ), 'Different ZIP metadata retains the exact original-file approval without a false changed event.' );
		$this->baseline();
		$overwrite = $this->archive( 'known-overwrite', self::PLUGIN, $this->versions['1.1.0'] );
		$this->check( $this->upgrade( self::PLUGIN, $overwrite, '1.1.0', 'install' ) === true, 'An exact uploaded ZIP overwrite works without a plugin context key.' );
		$this->check( count( array_filter( $this->events, array( $this, 'changed' ) ) ) === 1, 'A successful changed uploaded package emits one real-result event.' );
		$this->write_tree( WP_PLUGIN_DIR . '/' . dirname( self::OTHER ), $this->versions['1.0.0'] );
		$other = $this->archive( 'unprotected-original', self::OTHER, $this->versions['1.1.0'] );
		$this->events = array();
		$this->check( $this->upgrade( self::OTHER, $other, '1.1.0' ) === true && $this->events === array(), 'An unprotected native update remains permitted and produces no protected-provider event.' );
		$this->critical_restoration();
		$this->concurrent_status();
		$this->check( $this->core_identity() === $this->core, 'Every original Core file remains byte-identical after all actual updater paths.' );
		$debug = is_file( dirname( $this->site ) . '/debug.log' ) ? file_get_contents( dirname( $this->site ) . '/debug.log', false, null, $this->debug_offset ) : '';
		$this->check( preg_match( '/\b(?:PHP\s+)?(?:Warning|Deprecated|Notice|Fatal error|Parse error|Uncaught)\b/i', $debug ) !== 1, 'This native run and its real CLI child add no PHP warnings, notices, deprecations or exceptions to the debug log.' );
		echo json_encode( array( 'wordpress' => get_bloginfo( 'version' ), 'php' => PHP_VERSION, 'assertions' => $this->assertions, 'native_http_requests' => $this->http_calls, 'core_unchanged' => true, 'new_php_diagnostics' => 0, 'multisite' => 'not tested or claimed', 'status' => 'passed' ), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n";
	}

	/**
	 * Delete only a caller-owned authored fixture, never following a symbolic link.
	 *
	 * @since 1.0.0
	 * @param string $path One exact fixture path allocated by this test.
	 * @return void
	 */
	private function remove( $path ) {
		if ( is_link( $path ) || is_file( $path ) ) {
			unlink( $path );
		} elseif ( is_dir( $path ) ) {
			foreach ( array_diff( scandir( $path ), array( '.', '..' ) ) as $name ) {
				$this->remove( $path . '/' . $name );
			}
			rmdir( $path );
		}
	}

	/**
	 * Restore native fixture state and remove only explicitly owned test artifacts.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function cleanup() {
		if ( function_exists( 'update_option' ) ) {
			foreach ( $this->options as $name => $value ) {
				if ( $value === '__skye_native_absent__' ) {
					delete_option( $name );
				} else {
					update_option( $name, $value );
				}
			}
			if ( false === $this->updates ) {
				delete_site_transient( 'update_plugins' );
			} elseif ( null !== $this->updates ) {
				set_site_transient( 'update_plugins', $this->updates );
			}
		}
		$this->remove( $this->site . '/wp-content/plugins/' . dirname( self::PLUGIN ) );
		$this->remove( $this->site . '/wp-content/plugins/' . dirname( self::OTHER ) );
		$this->remove( $this->mu );
		$this->remove( $this->integration );
		$this->remove( $this->private );
	}
}

$fixture = null;
$status = 0;
try {
	$fixture = new Skye_Guard_Native_Fixture( $argv[1] ?? '' );
	$fixture->run();
} catch ( Throwable $error ) {
	fwrite( STDERR, 'Native guard regression failed: ' . $error->getMessage() . "\n" );
	$status = 1;
} finally {
	if ( $fixture ) {
		$fixture->cleanup();
	}
}
exit( $status );
