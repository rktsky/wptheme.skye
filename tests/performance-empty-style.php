<?php
/**
 * Verify the empty GF stylesheet alias through real WordPress style queues.
 *
 * Backported from completed Volksgarage Website 6fecb117; only fixture names and paths generalized.
 * Run: SKYE_WORDPRESS_CORE=/absolute/wordpress php tools/tests/empty-form-style.php
 * Only provider paths/request context are fixtures. Core registers, resolves and
 * prints every dependency and inline style; no site, HTTP request or DB is used.
 *
 * @since 3.0.0
 */

namespace Rocketsky\Skye\Packages {
	/**
	 * Supply the isolated admin boundary without loading an account or installation.
	 * @return bool Whether this fixture represents an admin request.
	 * @since 3.0.0
	 */
	function is_admin() {
		return ! empty( $GLOBALS['skye_perf_empty_context']['admin'] );
	}
	/**
	 * Supply the AJAX request boundary independently of AJAX-enabled form markup.
	 * @return bool Whether the actual fixture request is AJAX.
	 * @since 3.0.0
	 */
	function wp_doing_ajax() {
		return ! empty( $GLOBALS['skye_perf_empty_context']['ajax'] );
	}
	/**
	 * Supply the cron request boundary without dispatching any event.
	 * @return bool Whether this fixture is a cron process.
	 * @since 3.0.0
	 */
	function wp_doing_cron() {
		return ! empty( $GLOBALS['skye_perf_empty_context']['cron'] );
	}
	/**
	 * Supply only authentication state, never a user or session fixture.
	 * @return bool Whether the frontend visitor is authenticated.
	 * @since 3.0.0
	 */
	function is_user_logged_in() {
		return ! empty( $GLOBALS['skye_perf_empty_context']['authenticated'] );
	}
	/**
	 * Supply the native customizer boundary without loading a customizer session.
	 * @return bool Whether this is a customizer preview.
	 * @since 3.0.0
	 */
	function is_customize_preview() {
		return ! empty( $GLOBALS['skye_perf_empty_context']['customizer'] );
	}
	/**
	 * Supply the queried preview flag without content or metadata.
	 * @return bool Whether the queried request is a preview.
	 * @since 3.0.0
	 */
	function is_preview() {
		return ! empty( $GLOBALS['skye_perf_empty_context']['preview'] );
	}
	/**
	 * Supply the queried feed flag without fetching a feed.
	 * @return bool Whether the queried request is a feed.
	 * @since 3.0.0
	 */
	function is_feed() {
		return ! empty( $GLOBALS['skye_perf_empty_context']['feed'] );
	}
	/**
	 * Supply the native not-found boundary without requesting an error page.
	 * @return bool Whether this fixture represents a404 response.
	 * @since 3.0.0
	 */
	function is_404() {
		return ! empty( $GLOBALS['skye_perf_empty_context']['not_found'] );
	}
	/**
	 * Supply an already-queried native post object without a database lookup.
	 * @return \WP_Post Existing fixture post with only public-boundary properties.
	 * @since 3.0.0
	 */
	function get_queried_object() {
		return new \WP_Post( (object) array(
			'post_status' => ! empty( $GLOBALS['skye_perf_empty_context']['unpublished'] ) ? 'private' : 'publish',
			'post_password' => ! empty( $GLOBALS['skye_perf_empty_context']['unlocked_password'] ) ? 'fixture-protection' : '',
		) );
	}
	/**
	 * Represent a singular page without loading a real post.
	 * @return bool Always true for the controlled frontend fixture.
	 * @since 3.0.0
	 */
	function is_singular() {
		return true;
	}
	/**
	 * Supply password protection without a real password, cookie or protected post.
	 * @return bool Whether the current page requires its password.
	 * @since 3.0.0
	 */
	function post_password_required() {
		return ! empty( $GLOBALS['skye_perf_empty_context']['protected'] );
	}
}

namespace {
	error_reporting( E_ALL );
	/**
	 * Fail rather than accepting a warning during native registration or printing.
	 * @param int $severity Native diagnostic severity.
	 * @param string $message Diagnostic text.
	 * @param string $file Diagnostic source path.
	 * @param int $line Diagnostic source line.
	 * @return bool Whether the diagnostic was handled.
	 * @since 3.0.0
	 */
	function skye_perf_empty_error( $severity, $message, $file, $line ) {
		if ( error_reporting() & $severity ) {
			throw new \ErrorException( $message, 0, $severity, $file, $line );
		}
		return false;
	}
	set_error_handler( 'skye_perf_empty_error' );
	$core = getenv( 'SKYE_WORDPRESS_CORE' );
	if ( ! $core || ! is_file( $core . '/wp-includes/class-wp-styles.php' ) ) {
		throw new \RuntimeException( 'Set SKYE_WORDPRESS_CORE to a real WordPress source checkout.' );
	}
	define( 'ABSPATH', rtrim( $core, '/' ) . '/' );
	define( 'WPINC', 'wp-includes' );
	define( 'WP_DEBUG', false );
	foreach ( array(
		'version.php', 'compat.php', 'plugin.php', 'load.php', 'class-wp-error.php', 'class-wp-post.php', 'functions.php', 'theme.php',
		'compat-utf8.php', 'utf8.php', 'formatting.php', 'kses.php', 'script-loader.php', 'class-wp-token-map.php',
		'html-api/html5-named-character-references.php', 'html-api/class-wp-html-attribute-token.php',
		'html-api/class-wp-html-span.php', 'html-api/class-wp-html-text-replacement.php',
		'html-api/class-wp-html-decoder.php', 'html-api/class-wp-html-tag-processor.php',
	) as $file ) {
		if ( in_array( $file, array( 'compat-utf8.php', 'utf8.php', 'class-wp-token-map.php' ), true ) && ! is_file( ABSPATH . WPINC . '/' . $file ) ) {
			continue;
		}
		require_once ABSPATH . WPINC . '/' . $file;
	}
	require dirname( __DIR__ ) . '/lib/package/Performance.php';
	require dirname( __DIR__ ) . '/lib/package/CriticalStyles.php';

	use Rocketsky\Skye\Packages\Performance;
	use Rocketsky\Skye\Packages\CriticalStyles;

	/**
	 * Expose only the provider's canonical URL and local filesystem boundary.
	 * @since 3.0.0
	 */
	class GFCommon {
		/** @var string Controlled local provider directory. */
		public static $path;
		/**
		 * Return the canonical provider URL before final presentation/CDN filters.
		 * @return string Public provider base URL.
		 * @since 3.0.0
		 */
		public static function get_base_url() {
			return 'https://example.test/wp-content/plugins/gravityforms';
		}
		/**
		 * Return the temporary provider directory, never a real plugin installation.
		 * @return string Temporary absolute fixture directory.
		 * @since 3.0.0
		 */
		public static function get_base_path() {
			return self::$path;
		}
	}

	/**
	 * Supply the sole option needed when Core escapes inline CSS source metadata.
	 * @param mixed $previous Previous short-circuit value.
	 * @param string $name Requested native option name.
	 * @return mixed UTF-8 charset or the unchanged short-circuit value.
	 * @since 3.0.0
	 */
	function skye_perf_empty_option( $previous, $name ) {
		if ( $name === 'blog_charset' ) {
			return 'UTF-8';
		}
		throw new \RuntimeException( 'Unexpected option read: ' . $name );
	}
	add_filter( 'pre_option', 'skye_perf_empty_option', 10, 2 );
	$checks = 0;
	$performance = new Performance();
	do_action( 'init' );
	$fixture = sys_get_temp_dir() . '/vg-empty-style-' . bin2hex( random_bytes( 6 ) );
	mkdir( $fixture . '/assets/css/dist', 0700, true );
	GFCommon::$path = $fixture;
	$file = $fixture . '/assets/css/dist/theme-components.min.css';
	file_put_contents( $file, '' );
	file_put_contents( $fixture . '/assets/css/dist/theme-components.css', '' );
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$GLOBALS['skye_perf_empty_context'] = array();
	$private_constant = $argv[1] ?? '';
	if ( $private_constant ) {
		if ( ! in_array( $private_constant, array( 'REST_REQUEST', 'XMLRPC_REQUEST', 'WP_CLI' ), true ) ) {
			throw new \RuntimeException( 'Unknown private request fixture.' );
		}
		define( $private_constant, true );
	}

	/**
	 * Assert a concrete contract of the actual theme class or real Core queue.
	 * @param bool $condition Observed behavior.
	 * @param string $message Required result.
	 * @return void
	 * @since 3.0.0
	 */
	function skye_perf_empty_check( $condition, $message ) {
		if ( ! $condition ) {
			throw new \RuntimeException( $message );
		}
		$GLOBALS['checks']++;
	}

	/**
	 * Register the unchanged native dependency graph with a fresh Core registry.
	 * @param string|false|null $src Controlled provider URL, existing alias or native default.
	 * @param array $extra Additional native metadata attached before optimization.
	 * @return WP_Styles Actual new Core registry.
	 * @since 3.0.0
	 */
	function skye_perf_empty_graph( $src = null, $extra = array() ) {
		$GLOBALS['wp_styles'] = new \WP_Styles();
		$styles = wp_styles();
		$styles->base_url = 'https://example.test';
		$styles->default_version = 'fixture';
		wp_register_style( 'provider-dependency', '/dependency.css', array(), null );
		wp_register_style( 'gform_basic', '/basic.css', array(), '3.2.1' );
		wp_register_style( 'gform_theme_components', $src ?? GFCommon::get_base_url() . '/assets/css/dist/theme-components.min.css', array( 'provider-dependency' ), '3.2.1', '(min-width: 1px)' );
		wp_register_style( 'gform_theme', '/theme.css', array( 'gform_theme_components' ), '3.2.1' );
		wp_enqueue_style( 'gform_basic' );
		wp_enqueue_style( 'gform_theme' );
		foreach ( $extra as $key => $value ) {
			// Model already-stored legacy state without invoking Core's deprecated setter.
			if ( $key === 'conditional' ) {
				$styles->registered['gform_theme_components']->extra[$key] = $value;
			} else {
				wp_style_add_data( 'gform_theme_components', $key, $value );
			}
		}
		return $styles;
	}

	/**
	 * Capture exactly the HTML emitted by the real Core style resolver.
	 * @return string Native dependency, stylesheet and inline-style output.
	 * @since 3.0.0
	 */
	function skye_perf_empty_output() {
		ob_start();
		wp_styles()->do_items();
		return ob_get_clean();
	}

	try {
		if ( $private_constant ) {
			$styles = skye_perf_empty_graph();
			$original = $styles->registered['gform_theme_components'];
			$performance->alias_empty_form_style( array(), true );
			skye_perf_empty_check( $styles->registered['gform_theme_components'] === $original, 'The native private request constant keeps the exact original provider registration.' );
		} else {
			skye_perf_empty_check( has_action( 'gform_enqueue_scripts', array( $performance, 'alias_empty_form_style' ) ) === 1001, 'The alias follows provider priority1000 and precedes CriticalStyles.' );
			$styles = skye_perf_empty_graph();
			$before = skye_perf_empty_output();
			skye_perf_empty_check( strpos( $before, 'gform_theme_components-css' ) !== false, 'The unoptimized native graph prints the empty provider LINK.' );
			$styles = skye_perf_empty_graph( null, array( 'after' => array( '.original{color:green}' ), 'custom-provider-data' => array( 'preserved' => true ) ) );
			$original = clone $styles->registered['gform_theme_components'];
			$queue = $styles->queue;
			do_action( 'gform_enqueue_scripts', array( 'id' => 6 ), true );
			$alias = $styles->registered['gform_theme_components'];
			skye_perf_empty_check( $alias->src === false && $styles->get_data( 'gform_theme_components', 'skye-empty-style-alias' ) === true, 'The verified empty local provider file becomes an explicitly marked native alias.' );
			skye_perf_empty_check( $styles->queue === $queue && $alias->deps === $original->deps && $alias->ver === $original->ver && $alias->args === $original->args, 'Queue, dependency order, version and media remain exact.' );
			$extra = $alias->extra;
			unset( $extra['skye-empty-style-alias'] );
			skye_perf_empty_check( $extra === $original->extra, 'Every pre-existing metadata value and inline byte survives native re-registration.' );
			$after = skye_perf_empty_output();
			skye_perf_empty_check( strpos( $after, 'gform_theme_components-css' ) === false && strpos( $after, '.original{color:green}' ) !== false, 'Core removes only the source LINK and still prints the original inline CSS.' );
			skye_perf_empty_check( $styles->done === array( 'gform_basic', 'provider-dependency', 'gform_theme_components', 'gform_theme' ), 'Core resolves and completes the original dependency chain including the alias.' );
			skye_perf_empty_check( strpos( $after, 'provider-dependency-css' ) < strpos( $after, 'gform_theme_components-inline-css' ) && strpos( $after, 'gform_theme_components-inline-css' ) < strpos( $after, 'gform_theme-css' ), 'Dependency, original inline CSS and dependent theme retain native order.' );
			$state = serialize( $alias );
			$performance->alias_empty_form_style( array(), true );
			skye_perf_empty_check( serialize( $styles->registered['gform_theme_components'] ) === $state, 'A repeated form enqueue does not reconstruct an already completed alias.' );

			foreach ( array( '.future{display:block}', ' ' ) as $css ) {
				file_put_contents( $file, $css );
				$styles = skye_perf_empty_graph();
				$original = $styles->registered['gform_theme_components'];
				$performance->alias_empty_form_style( array(), true );
				skye_perf_empty_check( $styles->registered['gform_theme_components'] === $original, 'Any future nonempty CSS, including whitespace, keeps its original registration.' );
			}
			file_put_contents( $file, '' );
			foreach ( array( '/other.css', 'https://cdn.example.test/assets/css/dist/theme-components.min.css', GFCommon::get_base_url() . '/assets/css/dist/theme-components.min.css?custom=1', GFCommon::get_base_url() . '/assets/css/dist/../dist/theme-components.min.css', false ) as $src ) {
				$styles = skye_perf_empty_graph( $src );
				$original = $styles->registered['gform_theme_components'];
				$performance->alias_empty_form_style( array(), true );
				skye_perf_empty_check( $styles->registered['gform_theme_components'] === $original, 'Unknown, rewritten, query-bearing or already source-less URLs remain untouched.' );
			}
			foreach ( array( 'rtl' => true, 'suffix' => '.min', 'path' => $file, 'alt' => false, 'title' => 'Original alternate', 'conditional' => false, 'inlined_src' => 'original.css' ) as $key => $value ) {
				$styles = skye_perf_empty_graph( null, array( $key => $value ) );
				$original = $styles->registered['gform_theme_components'];
				$performance->alias_empty_form_style( array(), true );
				skye_perf_empty_check( $styles->registered['gform_theme_components'] === $original, 'Special native style semantics remain exact, even when the metadata value is false: ' . $key );
			}
			foreach ( array( 'admin', 'ajax', 'cron', 'authenticated', 'customizer', 'preview', 'feed', 'protected', 'not_found', 'unpublished', 'unlocked_password' ) as $context ) {
				$GLOBALS['skye_perf_empty_context'] = array( $context => true );
				$styles = skye_perf_empty_graph();
				$original = $styles->registered['gform_theme_components'];
				$performance->alias_empty_form_style( array(), true );
				skye_perf_empty_check( $styles->registered['gform_theme_components'] === $original, 'Private/request-specific context retains native registration: ' . $context );
			}
			$GLOBALS['skye_perf_empty_context'] = array();
			foreach ( array( 'POST', 'PUT', 'DELETE' ) as $method ) {
				$_SERVER['REQUEST_METHOD'] = $method;
				$styles = skye_perf_empty_graph();
				$original = $styles->registered['gform_theme_components'];
				$performance->alias_empty_form_style( array(), true );
				skye_perf_empty_check( $styles->registered['gform_theme_components'] === $original, 'Non-read request keeps native registration: ' . $method );
			}
			$_SERVER['REQUEST_METHOD'] = 'HEAD';
			$styles = skye_perf_empty_graph( GFCommon::get_base_url() . '/assets/css/dist/theme-components.css' );
			$performance->alias_empty_form_style( array(), false );
			skye_perf_empty_check( $styles->registered['gform_theme_components']->src === false, 'The native nonminified/debug zero-byte file and a public HEAD are supported.' );
			$_SERVER['REQUEST_METHOD'] = 'GET';
			$styles = skye_perf_empty_graph();
			$styles->args['gform_theme_components'] = 'custom=1';
			$original = $styles->registered['gform_theme_components'];
			$performance->alias_empty_form_style( array(), true );
			skye_perf_empty_check( $styles->registered['gform_theme_components'] === $original, 'Native handle query arguments remain untouched.' );
			$styles = skye_perf_empty_graph();
			wp_dequeue_style( 'gform_theme' );
			$original = $styles->registered['gform_theme_components'];
			$performance->alias_empty_form_style( array(), true );
			skye_perf_empty_check( $styles->registered['gform_theme_components'] === $original, 'Registered but unused styles do not trigger optimization.' );
			$styles = skye_perf_empty_graph();
			skye_perf_empty_output();
			$original = $styles->registered['gform_theme_components'];
			$performance->alias_empty_form_style( array(), true );
			skye_perf_empty_check( $styles->registered['gform_theme_components'] === $original, 'Already printed stylesheet registrations are not changed.' );
			unlink( $file );
			$styles = skye_perf_empty_graph();
			$original = $styles->registered['gform_theme_components'];
			$performance->alias_empty_form_style( array(), true );
			skye_perf_empty_check( $styles->registered['gform_theme_components'] === $original, 'Missing local files fall back unchanged.' );
			symlink( $fixture . '/assets/css/dist/theme-components.css', $file );
			$styles = skye_perf_empty_graph();
			$original = $styles->registered['gform_theme_components'];
			$performance->alias_empty_form_style( array(), true );
			skye_perf_empty_check( $styles->registered['gform_theme_components'] === $original, 'Symlinked provider files stay outside the regular-file contract.' );
			unlink( $file );
			file_put_contents( $file, '' );
			chmod( $file, 0000 );
			$styles = skye_perf_empty_graph();
			$original = $styles->registered['gform_theme_components'];
			$performance->alias_empty_form_style( array(), true );
			skye_perf_empty_check( $styles->registered['gform_theme_components'] === $original, 'Unreadable local files retain their source.' );
			chmod( $file, 0600 );

			// Exercise the real critical observer boundary; no form rendering or asset IO is needed.
			$critical = new CriticalStyles();
			foreach ( array( 'enabled' => true, 'form_gate' => 'gate(function(){/*__SKYE_NATIVE_FORM_SCRIPT__*/});', 'simple_forms' => array( 6 => true ) ) as $name => $value ) {
				$property = new \ReflectionProperty( CriticalStyles::class, $name );
				$property->setValue( $critical, $value );
			}
			$select = new \ReflectionMethod( CriticalStyles::class, 'enqueue_form_styles' );
			add_filter( 'skye_progressive_styles', '__return_true' );
			$styles = skye_perf_empty_graph();
			$performance->alias_empty_form_style( array(), true );
			$select->invoke( $critical );
			skye_perf_empty_check( $styles->get_data( 'gform_theme_components', 'skye-after-paint' ) === true && $styles->get_data( 'gform_theme', 'skye-after-paint' ) === true, 'The verified empty alias stays in FORM_STYLES readiness metadata without requiring a LINK.' );
			wp_register_script( 'skye/css-loader', false );
			wp_enqueue_script( 'skye/css-loader' );
			$body = 'originalProviderBody();';
			skye_perf_empty_check( $critical->form_script( $body, 6, 1 ) === 'gate(function(){' . $body . '});', 'The real native form gate remains active when only the verified components LINK is absent.' );
			skye_perf_empty_check( strpos( skye_perf_empty_output(), 'gform_theme_components-css' ) === false, 'Critical delivery never recreates the removed empty LINK.' );
			foreach ( array( false, '', null ) as $src ) {
				$styles = skye_perf_empty_graph( false );
				$styles->registered['gform_theme_components']->src = $src;
				$select->invoke( $critical );
				skye_perf_empty_check( $styles->get_data( 'gform_theme', 'skye-after-paint' ) !== true, 'An unverified source-less registration cannot enable the complete GF opt-in.' );
			}
			$styles = skye_perf_empty_graph();
			wp_deregister_style( 'gform_basic' );
			wp_register_style( 'gform_basic', false );
			wp_style_add_data( 'gform_basic', 'skye-empty-style-alias', true );
			$select->invoke( $critical );
			skye_perf_empty_check( $styles->get_data( 'gform_theme', 'skye-after-paint' ) !== true, 'The alias marker cannot bypass missing sources on another GF handle.' );
		}
	} finally {
		foreach ( array( $file, $fixture . '/assets/css/dist/theme-components.css' ) as $path ) {
			if ( is_file( $path ) || is_link( $path ) ) {
				unlink( $path );
			}
		}
		foreach ( array( '/assets/css/dist', '/assets/css', '/assets', '' ) as $suffix ) {
			rmdir( $fixture . $suffix );
		}
	}
	echo wp_json_encode( array( 'empty_form_style_assertions' => $checks, 'result' => 'pass', 'wordpress' => $wp_version ) ) . PHP_EOL;
}
