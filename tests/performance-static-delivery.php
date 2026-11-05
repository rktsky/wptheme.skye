<?php
/** Exercise actual Core serialization, immutable local derivatives and conservative CDN boundaries. */

namespace Rocketsky\Skye\Packages\Assets {
	/** Return only the controlled public child root. @since 3.1.0 @return string Fixture URL. */
	function get_stylesheet_directory_uri() {
		return \Rocketsky\Skye\Assets\get_stylesheet_directory_uri();
	}
	/** Return only the controlled physical child root. @since 3.1.0 @return string Fixture directory. */
	function get_stylesheet_directory() {
		return \Rocketsky\Skye\Assets\get_stylesheet_directory();
	}
	/** Return only the controlled public parent root. @since 3.1.0 @return string Fixture URL. */
	function get_template_directory_uri() {
		return \Rocketsky\Skye\Assets\get_template_directory_uri();
	}
	/** Return only the controlled physical parent root. @since 3.1.0 @return string Fixture directory. */
	function get_template_directory() {
		return \Rocketsky\Skye\Assets\get_template_directory();
	}
	/**
	 * Open the real source while placing a controlled atomic replacement at the I/O boundary.
	 *
	 * Only the exact owned fixture path can trigger a race; ordinary calls use PHP's
	 * original filesystem. No installed WordPress or provider source is changed.
	 *
	 * @since 3.4.0
	 * @param string $filename Actual source candidate opened by LocalAssets.
	 * @param string $mode Native read mode.
	 * @return resource|false Native source stream or open failure.
	 */
	function fopen( $filename, $mode ) {
		$race = $GLOBALS['delivery_source_race'] ?? array();
		if ( $filename === ( $race['path'] ?? null ) && 'before-open' === ( $race['when'] ?? null ) ) {
			\rename( $race['replacement'], $filename );
			unset( $GLOBALS['delivery_source_race'] );
		}
		$stream = \fopen( $filename, $mode );
		if ( is_resource( $stream ) ) {
			$GLOBALS['delivery_source_streams'][(int) $stream] = $filename;
			$GLOBALS['delivery_source_opens'][$filename] = ( $GLOBALS['delivery_source_opens'][$filename] ?? 0 ) + 1;
		}
		if ( $filename === ( $race['path'] ?? null ) && 'after-open' === ( $race['when'] ?? null ) ) {
			\rename( $race['replacement'], $race['destination'] ?? $filename );
			unset( $GLOBALS['delivery_source_race'] );
		}
		return $stream;
	}
	/**
	 * Record native descriptor checks and optionally reject one malformed fixture record.
	 *
	 * @since 3.4.0
	 * @param resource $stream Actual open native source.
	 * @return array|false Original stat record or deliberately invalid fixture record.
	 */
	function fstat( $stream ) {
		$path = $GLOBALS['delivery_source_streams'][(int) $stream];
		$GLOBALS['delivery_source_fstats'][$path] = ( $GLOBALS['delivery_source_fstats'][$path] ?? 0 ) + 1;
		$stat = \fstat( $stream );
		if ( $path === ( $GLOBALS['delivery_bad_fstat'] ?? null ) ) {
			$stat['ino'] = array( 'unsupported' );
		}
		return $stat;
	}
	/**
	 * Keep native path identities under test, including malformed-record refusal.
	 *
	 * @since 3.4.0
	 * @param string $path Actual canonical fixture source.
	 * @return array|false Native path stat, or one deliberately malformed fixture field.
	 */
	function lstat( $path ) {
		$stat = \lstat( $path );
		if ( $path === ( $GLOBALS['delivery_bad_lstat']['path'] ?? null ) && is_array( $stat ) ) {
			$stat[$GLOBALS['delivery_bad_lstat']['key']] = array( 'unsupported' );
		}
		return $stat;
	}
	/**
	 * Read real source bytes before a controlled same-descriptor size change.
	 *
	 * @since 3.4.0
	 * @param resource $stream Native read stream.
	 * @param int $length Explicit production read bound.
	 * @return string|false Original bytes or native read failure.
	 */
	function stream_get_contents( $stream, $length ) {
		$path = $GLOBALS['delivery_source_streams'][(int) $stream];
		$GLOBALS['delivery_source_reads'][$path][] = $length;
		$bytes = \stream_get_contents( $stream, $length );
		$race = $GLOBALS['delivery_source_race'] ?? array();
		if ( $path === ( $race['path'] ?? null ) && 'after-read' === ( $race['when'] ?? null ) ) {
			\file_put_contents( $path, 'window.changedAfterRead = true;' );
			unset( $GLOBALS['delivery_source_race'] );
		}
		return $bytes;
	}

	/** Resolve a controlled public upload boundary without touching site data. @since 3.1.0 @param mixed $time Unused native argument. @param bool $create Unused native argument. @return array Owned fixture upload paths. */
	function wp_upload_dir( $time = null, $create = true ) {
		return array( 'basedir' => $GLOBALS['delivery_fixture'] . '/uploads', 'baseurl' => 'https://example.test/wp-content/uploads', 'error' => $GLOBALS['delivery_storage_error'] ?? false );
	}
}
namespace Rocketsky\Skye\Packages {
	/** Share the controlled upload boundary with delivery settings. @since 3.1.0 @param mixed $time Unused native argument. @param bool $create Unused native argument. @return array Owned fixture paths. */
	function wp_upload_dir( $time = null, $create = true ) {
		return Assets\wp_upload_dir( $time, $create );
	}
	/** Isolate only the settings read; the real settings sanitizer and mapper remain under test. @since 3.1.0 @param string $name Native option name. @param mixed $default Native default. @return mixed Fixture option or default. */
	function get_option( $name, $default = false ) {
		return $name === 'skye_delivery' ? ( $GLOBALS['delivery_options'] ?? $default ) : \get_option( $name, $default );
	}
}
namespace {
	if ( 'cli' !== PHP_SAPI ) {
		http_response_code( 403 );
		exit;
	}
	require __DIR__ . '/performance-fixture.php';
	/** Supply a public fixture URL instead of loading blog records. @since 3.1.0 @param string $path Native URL suffix. @return string Fixture origin URL. */
	function home_url( $path = '' ) {
		return 'https://example.test' . $path;
	}
	/** Supply the immutable public Core root. @since 3.1.0 @param string $path Native URL suffix. @return string Fixture includes URL. */
	function includes_url( $path = '' ) {
		return 'https://example.test/wp-includes/' . $path;
	}
	/** Supply the immutable public content root. @since 3.1.0 @param string $path Native URL suffix. @return string Fixture content URL. */
	function content_url( $path = '' ) {
		return 'https://example.test/wp-content' . $path;
	}
	/** Supply the immutable public plugin root. @since 3.1.0 @param string $path Native URL suffix. @return string Fixture plugin URL. */
	function plugins_url( $path = '' ) {
		return 'https://example.test/wp-content/plugins' . $path;
	}
	use Rocketsky\Skye\Packages\StaticDelivery;
	use Rocketsky\Skye\Packages\DeliverySettings;
	use Rocketsky\Skye\Packages\Assets\LocalAssets;
	use Rocketsky\Skye\Packages\Assets\FullCss;

	$GLOBALS['delivery_fixture'] = sys_get_temp_dir() . '/skye-delivery-' . bin2hex( random_bytes( 5 ) );
	mkdir( $GLOBALS['delivery_fixture'] . '/uploads', 0700, true );
	$GLOBALS['delivery_fixture'] = realpath( $GLOBALS['delivery_fixture'] );
	define( 'WP_CONTENT_DIR', $GLOBALS['delivery_fixture'] );
	define( 'WP_PLUGIN_DIR', $GLOBALS['delivery_fixture'] . '/plugins' );
	require dirname( __DIR__ ) . '/lib/package/StaticDelivery.php';
	require dirname( __DIR__ ) . '/lib/package/DeliverySettings.php';
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_POST = array();
	do_action( 'wp' );
	$GLOBALS['perf_child'] = $GLOBALS['delivery_fixture'] . '/theme';
	mkdir( $GLOBALS['perf_child'], 0700 );
	file_put_contents( $GLOBALS['perf_child'] . '/one.js', 'window.one = 1;' );
	file_put_contents( $GLOBALS['perf_child'] . '/two.js', 'window.two = window.one + 1;' );
	$css_source = '/* license */@font-face{font-family:Own;src:url("../font.woff2")} .all{color:red;background-image:image-set(url("./one.webp") 1x,url("../two.webp") 2x)} @media(print){.all{color:black}}';
	file_put_contents( $GLOBALS['perf_child'] . '/all.css', $css_source );
	$before_hash = hash_file( 'sha256', $GLOBALS['perf_child'] . '/one.js' );
	$cache_probe = $argv[1] ?? '';
	if ( in_array( $cache_probe, array( '--source-cache-entries', '--source-cache-bytes' ), true ) ) {
		try {
			delivery_source_cache_probe( $cache_probe );
			echo json_encode( array( 'checks' => $GLOBALS['perf_checks'] ), JSON_THROW_ON_ERROR );
		} finally {
			perf_remove( $GLOBALS['delivery_fixture'] );
		}
		exit;
	}

	/** Build a real native queue and metadata; production code resolves its own order. @since 3.1.0 @param bool $changed Whether to simulate an unreviewed version. @return array Explicit immutable plan. */
	function delivery_graph( $changed = false ) {
		$GLOBALS['wp_scripts'] = new WP_Scripts();
		wp_scripts()->base_url = 'https://example.test';
		wp_register_script( 'delivery-one', 'https://example.test/child/one.js', array(), $changed ? 'new' : '1', array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_register_script( 'delivery-two', 'https://example.test/child/two.js', array( 'delivery-one' ), '1', array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_add_inline_script( 'delivery-one', 'window.firstNative = "nonce-fixture";', 'before' );
		wp_add_inline_script( 'delivery-two', 'window.finalNative = true;', 'after' );
		wp_enqueue_script( 'delivery-two' );
		return array( 'native_deferred_initialization' => true, 'carrier' => 'delivery-two', 'sources' => array(
			'delivery-one' => array( 'url' => 'https://example.test/child/one.js', 'version' => '1', 'dependencies' => array() ),
			'delivery-two' => array( 'url' => 'https://example.test/child/two.js', 'version' => '1', 'dependencies' => array( 'delivery-one' ) ),
		) );
	}
	/** Supply only a controlled plan to the real extension hook. @since 3.1.0 @param mixed $previous Earlier plan. @return mixed Current fixture plan. */
	function delivery_plan( $previous ) {
		return $GLOBALS['delivery_plan'] ?? $previous;
	}
	/** Capture the actual native footer, including native inline tags. @since 3.1.0 @return string Real native HTML. */
	function delivery_output() {
		ob_start();
		do_action( 'wp_print_footer_scripts' );
		return ob_get_clean();
	}
	/** Render the actual Core footer registry without unrelated compression-option reads. @since 3.1.0 @return void */
	function delivery_native_footer() {
		wp_scripts()->do_footer_items();
	}
	add_action( 'wp_print_footer_scripts', 'delivery_native_footer', 20 );
	add_filter( 'skye_static_script_bundle', 'delivery_plan' );
	try {
		$GLOBALS['delivery_plan'] = delivery_graph();
		$baseline = delivery_output();
		$GLOBALS['delivery_plan'] = delivery_graph();
		$observer = new StaticDelivery();
		$html = delivery_output();
		preg_match_all( '~<script\b(?![^>]*\bsrc=)[^>]*>.*?</script>~s', $baseline, $native_inline );
		preg_match_all( '~<script\b(?![^>]*\bsrc=)[^>]*>.*?</script>~s', $html, $optimized_inline );
		perf_check( $native_inline[0] === $optimized_inline[0], 'All native inline tags, metadata, bodies and order remain byte-identical to the unoptimized native output.' );
		perf_check( substr_count( $html, ' src=' ) === 1, 'Native aliases produce one bundle without changing dependency order.' );
		perf_check( str_contains( $html, 'window.firstNative = "nonce-fixture";' ) && str_contains( $html, 'window.finalNative = true;' ), 'All original inline metadata remains natively serialized.' );
		perf_check( wp_scripts()->registered['delivery-one']->src === false, 'Earlier source becomes a native alias.' );
		$bundle = wp_scripts()->registered['delivery-two']->src;
		perf_check( str_contains( $bundle, '/skye-static-assets/bundle-' ) && str_contains( $html, 'defer' ), 'Last carrier retains its native defer strategy and immutable public source.' );
		$files = glob( $GLOBALS['delivery_fixture'] . '/uploads/skye-static-assets/bundle-*.js' );
		perf_check( count( $files ) === 1 && ! str_contains( file_get_contents( $files[0] ), 'nonce-fixture' ), 'Bundle contains only static sources and no inline nonce data.' );
		perf_check( hash_file( 'sha256', $GLOBALS['perf_child'] . '/one.js' ) === $before_hash, 'Original static sources remain byte-identical.' );
		remove_filter( 'print_scripts_array', array( $observer, 'prepare_scripts' ), PHP_INT_MAX );
		$GLOBALS['delivery_plan'] = delivery_graph();
		$observer = new StaticDelivery();
		delivery_output();
		perf_check( wp_scripts()->registered['delivery-two']->src === $bundle, 'Repeated native requests reuse the same content-addressed artifact.' );
		remove_filter( 'print_scripts_array', array( $observer, 'prepare_scripts' ), PHP_INT_MAX );
		foreach ( array( 'version', 'post', 'authenticated', 'metadata', 'storage' ) as $scenario ) {
			$GLOBALS['delivery_storage_error'] = $scenario === 'storage' ? 'unavailable' : false;
			$GLOBALS['delivery_plan'] = delivery_graph( $scenario === 'version' );
			$_SERVER['REQUEST_METHOD'] = $scenario === 'post' ? 'POST' : 'GET';
			$GLOBALS['perf_context']['authenticated'] = $scenario === 'authenticated';
			if ( $scenario === 'metadata' ) {
				wp_script_add_data( 'delivery-one', 'unreviewed', true );
			}
			$observer = new StaticDelivery();
			$output = delivery_output();
			perf_check( substr_count( $output, ' src=' ) === 2, 'Unreviewed request/version/metadata retains complete native sources: ' . $scenario );
			remove_filter( 'print_scripts_array', array( $observer, 'prepare_scripts' ), PHP_INT_MAX );
		}
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$GLOBALS['delivery_storage_error'] = false;
		$GLOBALS['perf_context'] = array();
		$rebased = FullCss::rebase( $css_source, 'https://example.test/child/all.css' );
		perf_check( str_contains( $rebased, 'url("https://example.test/font.woff2")' ) && str_contains( $rebased, '@media(print)' ) && str_contains( $rebased, '/* license */' ), 'Complete CSS retains license/rules/media and resolves its relative URLs.' );
		perf_check( str_contains( $rebased, 'image-set(url("https://example.test/child/one.webp") 1x,url("https://example.test/two.webp") 2x)' ), 'The complete source, derivative cache and native serializer retain both rebased image-set candidates.' );
		foreach ( array( '@import "other.css";', 'x{background:url("bad\\ path")}', '/* unclosed', 'x{content:"unclosed}', 'x{background:url(invalid space.png)}', 'x{background:url(invalid"quote.png)}', 'x{background:image-set("relative.webp" 1x)}', 'x{background:u\\72l(relative.png)}', '@\\69mport "x.css";', '</STYLE><script>x</script>' ) as $bad ) {
			perf_check( FullCss::rebase( $bad, 'https://example.test/child/all.css' ) === false, 'Unsupported CSS retains native delivery.' );
		}
		$source = LocalAssets::read( 'https://example.test/child/all.css', 'css' );
		$legacy_key = hash( 'sha256', $source['sha256'] . '|' . $source['url'] . '|canonical-v1' );
		$legacy_bytes = '/* derivative from an earlier CSS grammar */';
		$legacy_hash = hash( 'sha256', $legacy_bytes );
		$cache_directory = $GLOBALS['delivery_fixture'] . '/uploads/skye-static-assets';
		$legacy_file = $cache_directory . '/style-' . $legacy_hash . '.css';
		$legacy_manifest = $cache_directory . '/style-source-' . $legacy_key . '.json';
		file_put_contents( $legacy_file, $legacy_bytes );
		file_put_contents( $legacy_manifest, wp_json_encode( array( 'version' => 1, 'file' => basename( $legacy_file ), 'sha256' => $legacy_hash, 'bytes' => strlen( $legacy_bytes ) ) ) );
		perf_check( $source && LocalAssets::inline_css( $source ) === $rebased && LocalAssets::inline_css( $source ) === $rebased, 'Real CSS cache round-trip preserves complete derivative bytes.' );
		perf_check( file_get_contents( $legacy_file ) === $legacy_bytes && is_file( $legacy_manifest ), 'The new grammar never reuses or removes an older valid derivative.' );
		$css_file = $cache_directory . '/style-' . hash( 'sha256', $rebased ) . '.css';
		file_put_contents( $css_file, 'corrupt' );
		perf_check( LocalAssets::inline_css( $source ) === $rebased && file_get_contents( $css_file ) === $rebased, 'Hash mismatch repairs only the owned derivative, never the source.' );
		$GLOBALS['wp_styles'] = new WP_Styles();
		wp_styles()->base_url = 'https://example.test';
		wp_enqueue_style( 'delivery-css', $source['url'], array(), null, 'print' );
		wp_add_inline_style( 'delivery-css', '.native-after{color:blue}' );
		$GLOBALS['delivery_style_plan'] = array( 'delivery-css' => array( 'url' => $source['url'], 'version' => null, 'dependencies' => array() ) );
		add_filter( 'skye_inline_stylesheets', 'delivery_style_plan' );
		ob_start();
		wp_styles()->do_items();
		$output = ob_get_clean();
		perf_check( ! str_contains( $output, '<link' ) && str_contains( $output, 'media="print"' ) && str_contains( $output, '.native-after{color:blue}' ), 'Actual Core serializer retains native media and separate inline-after CSS.' );
		$native_tag = '<link rel="stylesheet" id="delivery-css-css" href="' . $source['url'] . '" integrity="sha256-unknown" media="print">';
		perf_check( $observer->inline_style( $native_tag, 'delivery-css', $source['url'], 'print' ) === $native_tag, 'Foreign integrity or CSP-related attributes restore the unchanged native link.' );
		$settings = new DeliverySettings();
		$url = 'https://example.test/wp-content/uploads/photo.webp';
		perf_check( $settings->map( $url ) === $url, 'CDN defaults to disabled.' );
		$GLOBALS['delivery_options'] = array( 'cdn_enabled' => true, 'cdn_url' => 'https://cdn.example.net/assets', 'prefixes' => DeliverySettings::prefixes() );
		$mapped = $settings->map( $url );
		perf_check( $mapped === 'https://cdn.example.net/assets/wp-content/uploads/photo.webp' && $settings->canonical( $mapped ) === $url, 'Same-origin static mapping supports configured base paths and reversible canonical identity.' );
		foreach ( array( 'https://foreign.test/wp-content/uploads/photo.webp', 'https://example.test/wp-admin/admin.css', 'https://example.test/wp-content/uploads/../private.js', 'https://example.test/wp-content/uploads/file.php', $url . '?token=secret', $url . '?ver=1&nonce=secret' ) as $bad ) {
			perf_check( $settings->map( $bad ) === $bad, 'Foreign, executable, traversal, administrator and signed URLs stay native.' );
		}
		perf_check( $settings->map( $url . '?ver=1' ) === $mapped . '?ver=1', 'Native version query remains supported.' );
		foreach ( array( 'http://cdn.example.net', 'https://user:pass@cdn.example.net', 'https://127.0.0.1', 'https://cdn.example.net/?x=1', 'https://cdn.example.net/../bad' ) as $bad ) {
			$clean = DeliverySettings::sanitize( array( 'cdn_enabled' => true, 'cdn_url' => $bad ) );
			perf_check( ! $clean['cdn_enabled'] && $clean['cdn_url'] === '', 'Invalid delivery settings disable mapping without any network request.' );
		}
		$images = array( 400 => array( 'url' => $url, 'descriptor' => 'w', 'value' => 400 ) );
		perf_check( $settings->srcset( $images )[400] === array( 'url' => $mapped, 'descriptor' => 'w', 'value' => 400 ), 'Native srcset descriptors remain identical.' );
		$preloads = $settings->preloads( array( array( 'href' => $url, 'as' => 'image', 'imagesrcset' => $url . ' 400w', 'fetchpriority' => 'high' ) ) );
		perf_check( $preloads[0]['href'] === $mapped && $preloads[0]['imagesrcset'] === $mapped . ' 400w' && $preloads[0]['fetchpriority'] === 'high', 'Native responsive preload matches the final mapped delivery URL.' );
		foreach ( array( 'POST', 'HEAD' ) as $method ) {
			$_SERVER['REQUEST_METHOD'] = $method;
			perf_check( $settings->map( $url ) === $url, 'Writing and special-method requests remain at the origin.' );
		}
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$GLOBALS['perf_context']['admin'] = true;
		perf_check( $settings->map( $url ) === $url, 'Administrative URLs are never CDN-mapped.' );
		$GLOBALS['perf_context'] = array();
		$GLOBALS['delivery_options'] = array();
		delivery_source_identity_contracts();
		delivery_external_parent_contracts();
		foreach ( array( '--source-cache-entries', '--source-cache-bytes' ) as $probe ) {
			delivery_source_cache_child( $probe );
		}
		$GLOBALS['perf_child'] = $GLOBALS['delivery_fixture'] . '/font-theme';
		mkdir( $GLOBALS['perf_child'] . '/dist/fonts', 0700, true );
		file_put_contents( $GLOBALS['perf_child'] . '/dist/assets.json', json_encode( array( 'main.css' => 'all.css', 'fonts/own.woff2' => 'fonts/own-hash.woff2', '_performance' => array( 'version' => 1, 'fonts' => array( array( 'file' => 'fonts/own.woff2' ) ) ) ) ) );
		$fonts = \Rocketsky\Skye\Assets\font_preloads();
		$GLOBALS['wp_styles'] = new WP_Styles();
		wp_register_style( 'skye/css', 'https://example.test/child/dist/all.css', array(), null );
		$critical = new \Rocketsky\Skye\Packages\CriticalStyles();
		perf_check( count( $fonts ) === 1 && $critical->defer_font_preloads( $fonts ) === $fonts, 'Complete-CSS font hints stay in head unless explicitly opted into body delivery.' );
		add_filter( 'skye_font_preload_position', 'delivery_font_body' );
		$foreign = $fonts;
		$foreign[0]['fetchpriority'] = 'high';
		perf_check( $critical->defer_font_preloads( $foreign ) === $foreign, 'Foreign attributes on an owned font URL retain its exact original head hint.' );
		perf_check( $critical->defer_font_preloads( $fonts ) === array(), 'Explicit body opt-in moves only identity-matched own font hints.' );
		ob_start();
		$critical->render_font_preloads();
		$font_html = ob_get_clean();
		perf_check( substr_count( $font_html, '<link ' ) === 1 && str_contains( $font_html, 'crossorigin="anonymous"' ) && str_contains( $font_html, $fonts[0]['href'] ), 'Actual prepared body font component preserves file/type/CORS exactly.' );
		ob_start();
		$critical->render_font_preloads();
		$again = ob_get_clean();
		perf_check( $again === '', 'Repeated native body actions cannot print duplicate hints.' );
		remove_filter( 'skye_font_preload_position', 'delivery_font_body' );
		echo 'Static delivery: ' . $GLOBALS['perf_checks'] . " assertions passed against native Core.\n";
	} finally {
		perf_remove( $GLOBALS['delivery_fixture'] );
	}
	/** Supply the explicit fixture stylesheet boundary to the real extension hook. @since 3.1.0 @param mixed $previous Earlier plan. @return mixed Fixture plan. */
	function delivery_style_plan( $previous ) {
		return $GLOBALS['delivery_style_plan'] ?? $previous;
	}
	/** Explicitly select body delivery for the native complete-CSS regression. @since 3.1.0 @param string $position Existing placement. @return string Reviewed body hint placement. */
	function delivery_font_body( $position ) {
		return 'body';
	}

	/**
	 * Exercise source invalidation and real descriptor/path races through the public reader.
	 *
	 * Every mutation belongs to the disposable theme tree. Source cache tests do not
	 * alter native registrations, form data, provider eligibility or original packages.
	 *
	 * @since 3.4.0
	 * @return void
	 */
	function delivery_source_identity_contracts() {
		$root = $GLOBALS['perf_child'];
		$path = $root . '/identity.js';
		$url = 'https://example.test/child/identity.js';
		$first = 'window.value = 1;';
		$second = 'window.value = 2;';
		file_put_contents( $path, $first );
		$record = LocalAssets::read( $url, 'js' );
		perf_check( $record === array( 'bytes' => $first, 'path' => realpath( $path ), 'url' => $url, 'sha256' => hash( 'sha256', $first ) ), 'The public source record preserves exact bytes, canonical path, URL and hash.' );
		perf_check( LocalAssets::read( $url, 'js' ) === $record && $GLOBALS['delivery_source_opens'][$path] === 1, 'An unchanged current identity reuses its original bytes without reopening the source.' );
		perf_check( $GLOBALS['delivery_source_fstats'][$path] === 2 && $GLOBALS['delivery_source_reads'][$path] === array( 1048577 ), 'The real source is descriptor-checked before and after a bounded read.' );
		$mtime = filemtime( $path );
		file_put_contents( $path . '.next', $second );
		touch( $path . '.next', $mtime );
		rename( $path . '.next', $path );
		$changed = LocalAssets::read( $url, 'js' );
		perf_check( $changed['bytes'] === $second && $changed['sha256'] !== $record['sha256'] && filemtime( $path ) === $mtime, 'Atomic replacement invalidates a cached source even with the same size and modification time.' );
		unlink( $path );
		perf_check( LocalAssets::read( $url, 'js' ) === false, 'A deleted source never returns its prior cached bytes.' );
		file_put_contents( $path, '' );
		perf_check( LocalAssets::read( $url, 'js' )['bytes'] === '', 'A recreated empty regular source is supported after a negative read.' );
		perf_check( LocalAssets::read( 'https://example.test/child/new.js', 'js' ) === false, 'An absent source retains native fallback.' );
		file_put_contents( $root . '/new.js', $first );
		perf_check( LocalAssets::read( 'https://example.test/child/new.js', 'js' )['bytes'] === $first, 'A previous missing result cannot hide a newly created source.' );
		mkdir( $root . '/directory.js', 0700 );
		perf_check( LocalAssets::read( 'https://example.test/child/directory.js', 'js' ) === false, 'A directory with a public asset extension is rejected before opening.' );
		$outside = $GLOBALS['delivery_fixture'] . '/outside.js';
		file_put_contents( $outside, $second );
		symlink( $path, $root . '/linked.js' );
		perf_check( LocalAssets::read( 'https://example.test/child/linked.js', 'js' )['bytes'] === '', 'An in-root source link retains the existing canonical mapping contract.' );
		unlink( $root . '/linked.js' );
		symlink( $outside, $root . '/linked.js' );
		perf_check( LocalAssets::read( 'https://example.test/child/linked.js', 'js' ) === false, 'Retargeting a previously cached source link outside its native root is rejected.' );
		foreach ( array( 'before-open', 'after-open', 'after-read' ) as $when ) {
			$race_path = $root . '/race-' . $when . '.js';
			file_put_contents( $race_path, $first );
			file_put_contents( $race_path . '.next', $second );
			$GLOBALS['delivery_source_race'] = array( 'when' => $when, 'path' => $race_path, 'replacement' => $race_path . '.next' );
			perf_check( LocalAssets::read( 'https://example.test/child/race-' . $when . '.js', 'js' ) === false, 'A real ' . $when . ' source mutation fails descriptor/path parity.' );
			perf_check( 'before-open' === $when ? empty( $GLOBALS['delivery_source_reads'][$race_path] ) : $GLOBALS['delivery_source_fstats'][$race_path] >= 1, 'Native descriptor checks run before any changed source can be accepted.' );
			perf_check( is_array( LocalAssets::read( 'https://example.test/child/race-' . $when . '.js', 'js' ) ), 'A refused source race is not permanently cached.' );
		}
		foreach ( array( 'dev', 'ino', 'mode', 'size', 'mtime', 'ctime' ) as $key ) {
			$GLOBALS['delivery_bad_lstat'] = array( 'path' => $path, 'key' => $key );
			perf_check( LocalAssets::read( $url, 'js' ) === false, 'A malformed native ' . $key . ' identity fails without warnings.' );
		}
		unset( $GLOBALS['delivery_bad_lstat'] );
		$GLOBALS['delivery_bad_fstat'] = $path;
		perf_check( LocalAssets::read( $url, 'js' ) === false, 'A malformed descriptor identity is rejected before accepting source bytes.' );
		unset( $GLOBALS['delivery_bad_fstat'] );
		perf_check( LocalAssets::read( $url, 'js' )['bytes'] === '', 'A valid native identity remains readable after malformed-record failures.' );
		foreach ( array( 'old', 'new' ) as $generation ) {
			mkdir( $GLOBALS['delivery_fixture'] . '/generation-' . $generation, 0700 );
			file_put_contents( $GLOBALS['delivery_fixture'] . '/generation-' . $generation . '/root.js', $generation );
			file_put_contents( $GLOBALS['delivery_fixture'] . '/generation-' . $generation . '/root-race.js', $generation );
		}
		$link = $GLOBALS['delivery_fixture'] . '/theme-current';
		symlink( $GLOBALS['delivery_fixture'] . '/generation-old', $link );
		$GLOBALS['perf_child'] = $link;
		perf_check( LocalAssets::read( 'https://example.test/child/root.js', 'js' )['bytes'] === 'old', 'An intentional native theme-root symlink remains supported.' );
		unlink( $link );
		symlink( $GLOBALS['delivery_fixture'] . '/generation-new', $link );
		perf_check( LocalAssets::read( 'https://example.test/child/root.js', 'js' )['bytes'] === 'new', 'A changed native root resolves its new source instead of cached URL bytes.' );
		unlink( $link );
		symlink( $GLOBALS['delivery_fixture'] . '/generation-old', $link );
		symlink( $GLOBALS['delivery_fixture'] . '/generation-new', $link . '.next' );
		$old_path = $GLOBALS['delivery_fixture'] . '/generation-old/root-race.js';
		$GLOBALS['delivery_source_race'] = array( 'when' => 'after-open', 'path' => $old_path, 'replacement' => $link . '.next', 'destination' => $link );
		perf_check( LocalAssets::read( 'https://example.test/child/root-race.js', 'js' ) === false && $GLOBALS['delivery_source_fstats'][$old_path] === 2 && count( $GLOBALS['delivery_source_reads'][$old_path] ) === 1, 'The final logical-path check rejects a root swap even while both native descriptor checks still agree.' );
		perf_check( LocalAssets::read( 'https://example.test/child/root-race.js', 'js' )['bytes'] === 'new', 'A root changed during reading can be resolved normally on the next call.' );
		$GLOBALS['perf_child'] = $root;
	}

	/**
	 * Replace one owned directory or generation alias from an independent PHP process.
	 *
	 * Parent-process realpath entries must remain warm: native unlink/rename in
	 * that process would invalidate the very cache boundary under regression.
	 *
	 * @since 3.4.0
	 * @param string $path Owned physical directory or generation alias.
	 * @param string $target Existing owned replacement target.
	 * @param bool $generation Whether to atomically replace an existing symlink.
	 * @return void
	 */
	function delivery_external_replace( $path, $target, $generation = false ) {
		$program = $generation
			? 'exit(symlink($argv[2], $argv[1] . ".next") && rename($argv[1] . ".next", $argv[1]) ? 0 : 2);'
			: 'exit(rename($argv[1], $argv[1] . ".previous") && symlink($argv[2], $argv[1]) ? 0 : 2);';
		$process = proc_open( array( PHP_BINARY, '-r', $program, $path, $target ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
		if ( ! is_resource( $process ) ) {
			throw new RuntimeException( 'Cannot start the external source replacement.' );
		}
		fclose( $pipes[0] );
		$output = stream_get_contents( $pipes[1] );
		$error = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		perf_check( proc_close( $process ) === 0 && $output === '' && $error === '', 'The separate replacement process completes without changing parent PHP caches.' );
	}

	/**
	 * Reject warmed parent escapes and refresh native root generations without a global flush.
	 *
	 * Mutations occur only in child processes and owned fixture paths. Targets,
	 * relative link aliases and root ancestors exercise the actual shared resolver.
	 *
	 * @since 3.4.0
	 * @return void
	 */
	function delivery_external_parent_contracts() {
		$root = $GLOBALS['perf_child'];
		$outside = $GLOBALS['delivery_fixture'] . '/outside-parent';
		mkdir( $outside, 0700 );
		file_put_contents( $outside . '/source.js', 'window.outside = true;' );
		mkdir( $root . '/physical', 0700 );
		file_put_contents( $root . '/physical/source.js', 'window.inside = true;' );
		$url = 'https://example.test/child/physical/source.js';
		perf_check( LocalAssets::read( $url, 'js' )['bytes'] === 'window.inside = true;', 'The physical parent and source are warm before external replacement.' );
		delivery_external_replace( $root . '/physical', $outside );
		perf_check( LocalAssets::read( $url, 'js' ) === false && $GLOBALS['delivery_source_opens'][$root . '/physical/source.js'] === 1, 'A separately replaced physical parent cannot expose outside bytes under a stale canonical spelling.' );
		mkdir( $root . '/alias-target', 0700 );
		file_put_contents( $root . '/alias-target/source.js', 'window.alias = true;' );
		symlink( 'alias-target', $root . '/target-link' );
		symlink( 'target-link', $root . '/public-link' );
		perf_check( LocalAssets::read( 'https://example.test/child/public-link/source.js', 'js' )['bytes'] === 'window.alias = true;', 'A native in-root relative symlink chain remains supported.' );
		delivery_external_replace( $root . '/target-link', $outside, true );
		perf_check( LocalAssets::read( 'https://example.test/child/public-link/source.js', 'js' ) === false, 'A changed target alias cannot reuse separately cached canonical target parents.' );
		foreach ( array( 'old', 'new' ) as $generation ) {
			mkdir( $GLOBALS['delivery_fixture'] . '/parent-generation-' . $generation . '/theme', 0700, true );
			file_put_contents( $GLOBALS['delivery_fixture'] . '/parent-generation-' . $generation . '/theme/generation.js', $generation );
		}
		$current = $GLOBALS['delivery_fixture'] . '/parent-current';
		symlink( $GLOBALS['delivery_fixture'] . '/parent-generation-old', $current );
		$GLOBALS['perf_child'] = $current . '/theme';
		perf_check( LocalAssets::read( 'https://example.test/child/generation.js', 'js' )['bytes'] === 'old', 'A native root ancestor can be an intentional generation symlink.' );
		delivery_external_replace( $current, $GLOBALS['delivery_fixture'] . '/parent-generation-new', true );
		perf_check( LocalAssets::read( 'https://example.test/child/generation.js', 'js' )['bytes'] === 'new', 'An external root-ancestor generation swap refreshes both ownership and source bytes.' );
		$GLOBALS['perf_child'] .= '/';
		perf_check( LocalAssets::read( 'https://example.test/child/generation.js', 'js' )['bytes'] === 'new', 'A trailing native root separator does not hide an allowed root alias.' );
		$GLOBALS['perf_child'] = $root;
		symlink( 'cycle.js', $root . '/cycle.js' );
		perf_check( LocalAssets::read( 'https://example.test/child/cycle.js', 'js' ) === false, 'A symlink cycle fails closed within the finite alias budget.' );
		$deep = $root;
		for ( $index = 0; $index < 70; ++$index ) {
			$deep .= '/d';
			mkdir( $deep, 0700 );
		}
		file_put_contents( $deep . '/source.js', 'window.depth = true;' );
		perf_check( LocalAssets::read( 'https://example.test/child' . str_repeat( '/d', 70 ) . '/source.js', 'js' ) === false && empty( $GLOBALS['delivery_source_opens'][$deep . '/source.js'] ), 'An excessive absolute ancestor depth is rejected before any source stream opens.' );
	}

	/**
	 * Verify finite source-cache limits in a fresh process with no prefilled cache entries.
	 *
	 * @since 3.4.0
	 * @param string $mode Exact owned source-cache probe mode.
	 * @return void
	 */
	function delivery_source_cache_probe( $mode ) {
		$root = $GLOBALS['perf_child'];
		file_put_contents( $root . '/too-large.js', str_repeat( 'x', 1048577 ) );
		perf_check( LocalAssets::read( 'https://example.test/child/too-large.js', 'js' ) === false && empty( $GLOBALS['delivery_source_opens'][$root . '/too-large.js'] ), 'An oversized source is refused before opening or occupying a cache entry.' );
		$count = '--source-cache-entries' === $mode ? 128 : 8;
		$bytes = 128 === $count ? '/* source */' : str_repeat( 'x', 1048576 );
		for ( $index = 0; $index < $count; ++$index ) {
			file_put_contents( $root . '/bounded-' . $index . '.js', $bytes );
			perf_check( LocalAssets::read( 'https://example.test/child/bounded-' . $index . '.js', 'js' )['bytes'] === $bytes, 'The documented source-cache boundary accepts each in-budget source.' );
		}
		file_put_contents( $root . '/extra.js', 'x' );
		perf_check( LocalAssets::read( 'https://example.test/child/extra.js', 'js' ) === false && empty( $GLOBALS['delivery_source_opens'][$root . '/extra.js'] ), 'The first excess cache entry or byte retains native fallback without another read.' );
		perf_check( LocalAssets::read( 'https://example.test/child/bounded-0.js', 'js' )['bytes'] === $bytes && $GLOBALS['delivery_source_opens'][$root . '/bounded-0.js'] === 1, 'A verified existing source remains reusable at the cache limit.' );
		unlink( $root . '/bounded-0.js' );
		perf_check( LocalAssets::read( 'https://example.test/child/bounded-0.js', 'js' ) === false, 'Deletion revokes and releases one full source entry at the cache limit.' );
		perf_check( LocalAssets::read( 'https://example.test/child/extra.js', 'js' )['bytes'] === 'x', 'Freed entry/byte capacity can serve a previously refused source.' );
	}

	/**
	 * Run the same entry point with fresh static state for each real memory-limit probe.
	 *
	 * @since 3.4.0
	 * @param string $mode Explicit cache-entry or byte-limit probe.
	 * @return void
	 * @throws RuntimeException When the child emits diagnostics or fails its source contracts.
	 */
	function delivery_source_cache_child( $mode ) {
		$process = proc_open( array( PHP_BINARY, __FILE__, $mode ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
		if ( ! is_resource( $process ) ) {
			throw new RuntimeException( 'Cannot start the source-cache regression.' );
		}
		fclose( $pipes[0] );
		$output = stream_get_contents( $pipes[1] );
		$error = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$exit = proc_close( $process );
		if ( 0 !== $exit || '' !== $error ) {
			throw new RuntimeException( 'Source-cache regression failed: ' . $error . $output );
		}
		$result = json_decode( $output, true, 512, JSON_THROW_ON_ERROR );
		perf_check( is_int( $result['checks'] ?? null ) && $result['checks'] > 0, 'The isolated source-cache probe completed every declared assertion.' );
		$GLOBALS['perf_checks'] += $result['checks'];
	}
}
