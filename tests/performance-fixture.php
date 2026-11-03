<?php
/** Bootstrap actual Core parsers and dependency registries with local request boundaries only. */

namespace Rocketsky\Skye\Packages {
	/** Supply public/private request state without loading a site or principal. @since 3.0.0 @return bool Whether the fixture is administrative. */
	function is_admin() {
		return ! empty( $GLOBALS['perf_context']['admin'] );
	}
	/** Supply Ajax context so production checks stay under test. @since 3.0.0 @return bool Fixture Ajax state. */
	function wp_doing_ajax() {
		return ! empty( $GLOBALS['perf_context']['ajax'] );
	}
	/** Supply Cron context without running jobs. @since 3.0.0 @return bool Fixture Cron state. */
	function wp_doing_cron() {
		return false;
	}
	/** Supply identity without a database. @since 3.0.0 @return bool Fixture login state. */
	function is_user_logged_in() {
		return ! empty( $GLOBALS['perf_context']['authenticated'] );
	}
	/** Supply preview state without starting Customizer. @since 3.0.0 @return bool Fixture Customizer state. */
	function is_customize_preview() {
		return false;
	}
	/** Supply native preview state without querying posts. @since 3.0.0 @return bool Fixture preview state. */
	function is_preview() {
		return false;
	}
	/** Supply feed state without rendering endpoints. @since 3.0.0 @return bool Fixture feed state. */
	function is_feed() {
		return false;
	}
	/** Supply routing failure state without a real query. @since 3.0.0 @return bool Fixture 404 state. */
	function is_404() {
		return false;
	}
	/** Supply a public queried object boundary without protected data. @since 3.0.0 @return object|null Fixture post. */
	function get_queried_object() {
		return null;
	}
	/** Supply the singular scope of the controlled page. @since 3.0.0 @return bool Fixture singular state. */
	function is_singular() {
		return true;
	}
	/** Supply a password gate without real cookies or posts. @since 3.0.0 @return bool Fixture password state. */
	function post_password_required() {
		return ! empty( $GLOBALS['perf_context']['password'] );
	}
}

namespace Rocketsky\Skye\Assets {
	/** Return the controlled parent filesystem boundary. @since 3.0.0 @return string Fixture parent directory. */
	function get_template_directory() {
		return $GLOBALS['perf_parent'];
	}
	/** Return the controlled parent URL without network access. @since 3.0.0 @return string Fixture parent URI. */
	function get_template_directory_uri() {
		return 'https://example.test/theme';
	}
	/** Return a selected child boundary for the actual root resolver. @since 3.0.0 @return string Fixture child directory. */
	function get_stylesheet_directory() {
		return $GLOBALS['perf_child'] ?? $GLOBALS['perf_parent'];
	}
	/** Return a controlled child URI without a WordPress installation. @since 3.0.0 @return string Fixture child URI. */
	function get_stylesheet_directory_uri() {
		return 'https://example.test/child';
	}
}

namespace {
	$core = getenv( 'SKYE_WORDPRESS_CORE' );
	if ( ! $core || ! is_file( $core . '/wp-includes/class-wp-styles.php' ) ) {
		throw new \RuntimeException( 'Set SKYE_WORDPRESS_CORE to an official local Core source directory.' );
	}
	define( 'ABSPATH', rtrim( $core, '/' ) . '/' );
	define( 'WPINC', 'wp-includes' );
	define( 'WP_DEBUG', false );
	foreach ( array(
		'version.php', 'compat.php', 'plugin.php', 'load.php', 'class-wp-error.php', 'class-wp-post.php', 'functions.php',
		'compat-utf8.php', 'utf8.php', 'http.php', 'general-template.php', 'formatting.php', 'kses.php', 'script-loader.php', 'class-wp-token-map.php',
		'html-api/html5-named-character-references.php', 'html-api/class-wp-html-attribute-token.php',
		'html-api/class-wp-html-span.php', 'html-api/class-wp-html-text-replacement.php',
		'html-api/class-wp-html-decoder.php', 'html-api/class-wp-html-tag-processor.php',
	) as $file ) {
		require_once ABSPATH . WPINC . '/' . $file;
	}
	/**
	 * Reject native warnings so malformed source cannot silently pass a regression.
	 *
	 * @since 3.0.0
	 * @param int $severity Native error level.
	 * @param string $message Native diagnostic.
	 * @return bool Whether the diagnostic was handled.
	 */
	function perf_diagnostic( $severity, $message ) {
		if ( error_reporting() & $severity ) {
			throw new \ErrorException( $message, 0, $severity );
		}
		return false;
	}
	set_error_handler( 'perf_diagnostic' );
	/**
	 * Supply only necessary public site options, never persistent fixture data.
	 *
	 * @since 3.0.0
	 * @param mixed $previous Native short-circuit value.
	 * @param string $name Requested option.
	 * @return mixed Controlled option or original value.
	 */
	function perf_option( $previous, $name ) {
		if ( $name === 'blog_charset' ) {
			return 'UTF-8';
		}
		if ( in_array( $name, array( 'siteurl', 'home' ), true ) ) {
			return 'https://example.test';
		}
		return $previous;
	}
	add_filter( 'pre_option', 'perf_option', 10, 2 );
	/**
	 * Resolve fixture component locations while keeping the actual loader under test.
	 *
	 * @since 3.0.0
	 * @param string|array $templates Relative native candidate paths.
	 * @param bool $load Native load flag, unused by the actual component lookup.
	 * @param bool $once Native require-once flag, unused by this boundary.
	 * @return string Existing own template path or empty refusal.
	 */
	function locate_template( $templates, $load = false, $once = false ) {
		foreach ( (array) $templates as $template ) {
			$file = dirname( __DIR__ ) . '/' . $template;
			if ( is_file( $file ) ) {
				return $file;
			}
		}
		return '';
	}
	/**
	 * Assert production/native observations without maintaining another implementation.
	 *
	 * @since 3.0.0
	 * @param bool $condition Observed result.
	 * @param string $message Required contract.
	 * @return void
	 */
	function perf_check( $condition, $message ) {
		if ( ! $condition ) {
			throw new \RuntimeException( $message );
		}
		$GLOBALS['perf_checks'] = ( $GLOBALS['perf_checks'] ?? 0 ) + 1;
	}
	/**
	 * Remove only temporary fixtures created by these programs.
	 *
	 * @since 3.0.0
	 * @param string $path Owned temporary path.
	 * @return void
	 */
	function perf_remove( $path ) {
		if ( is_dir( $path ) && ! is_link( $path ) ) {
			foreach ( scandir( $path ) as $name ) {
				if ( $name !== '.' && $name !== '..' ) {
					perf_remove( $path . '/' . $name );
				}
			}
			rmdir( $path );
		} else {
			unlink( $path );
		}
	}
	$GLOBALS['perf_context'] = array();
	$GLOBALS['perf_parent'] = dirname( __DIR__ );
	require dirname( __DIR__ ) . '/lib/assets.php';
	require dirname( __DIR__ ) . '/lib/rocketsky/helpers.php';
	require dirname( __DIR__ ) . '/lib/package/Performance.php';
	require dirname( __DIR__ ) . '/lib/package/FormControls.php';
	require dirname( __DIR__ ) . '/lib/package/Icons.php';
	require dirname( __DIR__ ) . '/lib/package/CriticalStyles.php';
	do_action( 'init' );
	do_action( 'wp_enqueue_scripts' );
}
