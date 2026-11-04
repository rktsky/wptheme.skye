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
}
