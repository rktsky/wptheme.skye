<?php
/** Verify automatic native UI branding, contrast and read-only identity boundaries on an isolated WordPress site. */

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit;
}
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$branding_source = dirname( __DIR__ );
$branding_runtime = realpath( $branding_source . '/.git/runtime' );
$branding_site = realpath( getenv( 'SKYE_SITE_ROOT' ) ?: '' );
if ( ! $branding_runtime || ! $branding_site || ! str_starts_with( $branding_site, $branding_runtime . '/' ) || ! is_file( $branding_site . '/wp-load.php' ) ) {
	throw new RuntimeException( 'Use a native disposable WordPress site inside this checkout .git/runtime.' );
}
require $branding_site . '/wp-load.php';
ini_set( 'error_log', 'php://stderr' );
if ( wp_get_environment_type() !== 'local' || ! defined( 'DB_ENGINE' ) || DB_ENGINE !== 'sqlite' || ! $wpdb instanceof WP_SQLite_DB ||
	! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( '127.0.0.1', 'localhost' ), true ) ) {
	throw new RuntimeException( 'Branding tests require a native local SQLite site on loopback.' );
}
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';

use Rocketsky\Skye\Packages\Branding;

$branding_checks = 0;
$branding_writes = 0;
$branding_http = 0;
$branding_filtered = null;
$branding_logo_override = null;
$branding_logo_id = 0;
$branding_legacy_logo = false;
$branding_fixture_root = '';
$branding_fixture_owned = false;
$branding_child_root = '';
$branding_package_root = '';

/**
 * Fail on actual native presentation or safety changes, without exposing site records.
 *
 * @since 3.4.0
 * @param bool $condition Actual observed behavior.
 * @param string $message Required public contract.
 * @return void
 */
function branding_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	$GLOBALS['branding_checks']++;
}

/**
 * Supply project palette changes through the actual public filter, without saved settings.
 *
 * @since 3.4.0
 * @param mixed $colors Actual generated build palette.
 * @return mixed Unchanged build palette or the controlled adapter result.
 */
function branding_palette_filter( $colors ) {
	return $GLOBALS['branding_filtered'] === null ? $colors : $GLOBALS['branding_filtered'];
}

/**
 * Supply a project logo adapter without changing the native default identity.
 *
 * @since 3.4.0
 * @param mixed $logo Actual website identity logo URL.
 * @return mixed Unchanged identity or the controlled malformed/valid override.
 */
function branding_logo_filter( $logo ) {
	return $GLOBALS['branding_logo_override'] === null ? $logo : $GLOBALS['branding_logo_override'];
}

/**
 * Select a synthetic attachment identity through Core's native theme-mod read filter.
 *
 * @since 3.4.0
 * @param mixed $logo Earlier native custom-logo identity.
 * @return int Controlled identity, without a persistent write.
 */
function branding_native_logo( $logo ) {
	return $GLOBALS['branding_logo_id'];
}

/**
 * Describe only the synthetic attachment through the actual native image-source boundary.
 *
 * @since 3.4.0
 * @param array|false $image Native resolved attachment image.
 * @param int $attachment_id Native selected attachment identity.
 * @return array|false Controlled synthetic image or unchanged unrelated image.
 */
function branding_attachment_image( $image, $attachment_id ) {
	return in_array( $attachment_id, array( 91317, 91318 ), true ) ? array( 'https://example.test/identity-' . $attachment_id . '.png', 320, 96, false ) : $image;
}

/**
 * Expose the legacy logo's stored raw value through Core rather than emulating ACF formatting.
 *
 * @since 3.4.0
 * @param mixed $previous Native option-read preemption result.
 * @return mixed Controlled existing legacy value, without writing a fixture option.
 */
function branding_legacy_option( $previous ) {
	return $GLOBALS['branding_legacy_logo'];
}

/**
 * Count any persistent SQL mutation after setup so UI delivery cannot silently save preferences.
 *
 * @since 3.4.0
 * @param string $query Native SQL statement, returned unchanged and never logged.
 * @return string Unchanged native statement.
 */
function branding_observe_query( $query ) {
	if ( preg_match( '/^\s*(?:INSERT|REPLACE|UPDATE|DELETE|CREATE|DROP|ALTER|TRUNCATE)\b/i', $query ) ) {
		$GLOBALS['branding_writes']++;
	}
	return $query;
}

/**
 * Block every transport attempt while recording whether presentation tried to fetch anything.
 *
 * @since 3.4.0
 * @param mixed $previous Native HTTP preemption result.
 * @return WP_Error Explicit local-test refusal.
 */
function branding_block_http( $previous ) {
	$GLOBALS['branding_http']++;
	return new WP_Error( 'branding_fixture_no_http', 'Native branding tests never fetch destinations.' );
}

/**
 * Point only the package resolver at a disposable owned deployment during path-negative tests.
 *
 * @since 3.4.0
 * @param string $directory Earlier native parent directory.
 * @return string Owned temporary parent directory or unchanged source directory.
 */
function branding_parent_directory( $directory ) {
	return $GLOBALS['branding_package_root'] ?: $directory;
}

/**
 * Replace one owned parent directory or deployment generation in a separate process.
 *
 * Parent PHP realpath entries must stay warm for the actual fail-closed test;
 * performing these mutations in that process would clear its cache implicitly.
 *
 * @since 3.4.0
 * @param string $path Owned directory or generation alias to replace.
 * @param string $target Existing owned replacement target.
 * @param bool $generation Whether to replace an existing generation link atomically.
 * @return void
 */
function branding_external_replace( $path, $target, $generation = false ) {
	$program = $generation
		? 'exit(symlink($argv[2], $argv[1] . ".next") && rename($argv[1] . ".next", $argv[1]) ? 0 : 2);'
		: 'exit(rename($argv[1], $argv[1] . ".previous") && symlink($argv[2], $argv[1]) ? 0 : 2);';
	$process = proc_open( array( PHP_BINARY, '-r', $program, $path, $target ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
	if ( ! is_resource( $process ) ) {
		throw new RuntimeException( 'Cannot start the external package replacement.' );
	}
	fclose( $pipes[0] );
	$output = stream_get_contents( $pipes[1] );
	$error = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	branding_check( proc_close( $process ) === 0 && $output === '' && $error === '', 'The separate replacement must complete without invalidating parent PHP caches.' );
}

/**
 * Remove only this invocation's owned deployment tree without following links.
 *
 * External-process tests create real nested directories and generation aliases;
 * fixed-depth glob cleanup would leave those fixtures behind or follow aliases.
 *
 * @since 3.4.0
 * @param string $path Exact owned fixture root or one of its literal children.
 * @return void
 */
function branding_remove_fixture( $path ) {
	clearstatcache( true, $path );
	$stat = lstat( $path );
	if ( ( $stat['mode'] & 0170000 ) !== 0040000 ) {
		unlink( $path );
		return;
	}
	foreach ( scandir( $path ) as $name ) {
		if ( $name !== '.' && $name !== '..' ) {
			branding_remove_fixture( $path . '/' . $name );
		}
	}
	rmdir( $path );
}

/**
 * Select a complete synthetic child build only for manifest ownership checks.
 *
 * @since 3.4.0
 * @param string $directory Earlier native stylesheet directory.
 * @return string Owned child build or the unchanged earlier directory.
 */
function branding_child_directory( $directory ) {
	return $GLOBALS['branding_child_root'] ?: $directory;
}

/**
 * Start another native UI response registry without persisting or replacing unrelated hooks.
 *
 * @since 3.4.0
 * @return Branding Fresh owned callback instance with Core's actual style registry.
 */
function branding_response() {
	foreach ( array( 'admin_enqueue_scripts', 'login_enqueue_scripts', 'admin_body_class', 'login_body_class', 'login_headerurl', 'login_headertext' ) as $hook ) {
		foreach ( $GLOBALS['wp_filter'][$hook]->callbacks ?? array() as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof Branding ) {
					remove_filter( $hook, $callback['function'], $priority );
				}
			}
		}
	}
	$GLOBALS['wp_styles'] = new WP_Styles();
	return new Branding();
}

/**
 * Read actual inline color declarations for independent contrast checks.
 *
 * @since 3.4.0
 * @param string $css Serialized native inline-style data.
 * @return array<string,string> Only six-digit package color properties.
 */
function branding_inline_colors( $css ) {
	preg_match_all( '/--skye-brand-([a-z-]+):(#[a-f0-9]{6});/', $css, $matches, PREG_SET_ORDER );
	$colors = array();
	foreach ( $matches as $match ) {
		$colors[$match[1]] = $match[2];
	}
	return $colors;
}

/**
 * Calculate WCAG luminance independently from the production implementation.
 *
 * @since 3.4.0
 * @param string $hex Actual six-digit serialized color.
 * @return float Relative luminance from the three independently decoded bytes.
 */
function branding_test_luminance( $hex ) {
	$rgb = sscanf( $hex, '#%02x%02x%02x' );
	$linear = array();
	foreach ( $rgb as $channel ) {
		$normalized = $channel / 255;
		$linear[] = $normalized < 0.04045 ? $normalized / 12.92 : ( ( $normalized + 0.055 ) / 1.055 ) ** 2.4;
	}
	return $linear[0] * 0.2126 + $linear[1] * 0.7152 + $linear[2] * 0.0722;
}

/**
 * Assert the actual emitted colors meet the normal-text threshold.
 *
 * @since 3.4.0
 * @param string $foreground Actual serialized foreground.
 * @param string $background Actual serialized surface.
 * @return bool Whether the independently computed ratio reaches 4.5.
 */
function branding_readable( $foreground, $background ) {
	$values = array( branding_test_luminance( $foreground ), branding_test_luminance( $background ) );
	return ( max( $values ) + 0.05 ) / ( min( $values ) + 0.05 ) >= 4.5;
}

branding_check( class_exists( Branding::class ) && function_exists( 'skye_brand_identity' ), 'Actual theme bootstrap must expose the shared raw identity and native branding package.' );
$branding_service = null;
foreach ( $GLOBALS['wp_filter']['login_enqueue_scripts']->callbacks[20] ?? array() as $branding_callback ) {
	if ( is_array( $branding_callback['function'] ) && $branding_callback['function'][0] instanceof Branding ) {
		$branding_service = $branding_callback['function'][0];
	}
}
branding_check( $branding_service instanceof Branding, 'The actual package initializer must attach the native login hook.' );
branding_check( has_filter( 'admin_enqueue_scripts', array( $branding_service, 'admin_styles' ) ) === 20 && has_filter( 'admin_body_class', array( $branding_service, 'admin_classes' ) ) === 10, 'Administration hooks must use the same initialized package instance.' );
branding_check( ! has_filter( 'wp_enqueue_scripts', array( $branding_service, 'admin_styles' ) ) && ! has_filter( 'wp_enqueue_scripts', array( $branding_service, 'login_styles' ) ), 'Branding must register no frontend enqueue callbacks.' );
branding_check( count( Branding::colors() ) === 4, 'The actual compiled source build must contain a complete validated branding palette.' );
branding_check( Branding::asset_uri( 'backend.css' ) !== '' && Branding::asset_uri( 'login.css' ) !== '', 'Both immutable package stylesheets must resolve from the actual parent build.' );

add_filter( 'skye_branding_colors', 'branding_palette_filter' );
add_filter( 'skye_branding_logo', 'branding_logo_filter' );
add_filter( 'theme_mod_custom_logo', 'branding_native_logo' );
add_filter( 'wp_get_attachment_image_src', 'branding_attachment_image', 10, 2 );
add_filter( 'pre_option_theme_options_theme_logo', 'branding_legacy_option' );
add_filter( 'query', 'branding_observe_query' );
add_filter( 'pre_http_request', 'branding_block_http', PHP_INT_MAX );

try {
	$branding_logo_id = 91317;
	$branding_legacy_logo = 'https://example.test/legacy.png';
	branding_check( Branding::logo() === 'https://example.test/identity-91317.png', 'Native custom-logo identity must take precedence over the legacy option.' );
	$branding_logo_id = 0;
	foreach ( array( 'https://example.test/legacy.png', 91318, array( 'ID' => 91318, 'url' => 'https://ignored.test/other.png' ), array( 'url' => 'https://example.test/legacy.png' ) ) as $branding_legacy ) {
		$branding_legacy_logo = $branding_legacy;
		$branding_expected = is_int( $branding_legacy ) || is_array( $branding_legacy ) && isset( $branding_legacy['ID'] ) ? 'https://example.test/identity-91318.png' : 'https://example.test/legacy.png';
		branding_check( Branding::logo() === $branding_expected, 'Legacy stored logo URL, attachment identity and ACF-shaped raw values must share the real native identity source.' );
	}
	$branding_legacy_logo = '';
	branding_check( Branding::logo() === '', 'An absent site logo must retain a text identity rather than inventing an image.' );
	foreach ( array( false, 17, array(), 'javascript:alert(1)', 'data:image/svg+xml,<svg/>', '//other.test/logo.png', 'https://user:password@example.test/logo.png', "https://example.test/a\n.png", 'https://example.test/a".png', 'https://example.test/a\\b.png', 'https://example.test/</style><script>bad</script>', 'https://example.test:99999/logo.png', "https://example.test/\xFF.png" ) as $branding_invalid ) {
		$branding_logo_override = $branding_invalid;
		branding_check( Branding::logo() === '', 'Malformed or CSS-active project logo values must fail closed without fetching or rendering markup.' );
	}
	$branding_logo_override = 'https://example.test/logo(1).png?first=A&second=B';
	branding_check( Branding::logo() === $branding_logo_override, 'Ordinary logo query strings and parentheses must remain intact.' );

	$branding_palette = array( 'primary' => '#DD8686', 'secondary' => '#181A30', 'background' => '#F5EFE7', 'text' => '#181A30' );
	$branding_filtered = $branding_palette;
	branding_check( Branding::colors() === array_map( 'strtolower', $branding_palette ), 'The public palette adapter must normalize valid existing project colors.' );
	foreach ( array( false, 'invalid', array(), array( 'primary' => '#123456' ), array_merge( $branding_palette, array( 'text' => '#123' ) ), array_merge( $branding_palette, array( 'primary' => '#abcdef;}body{display:none' ) ), array_merge( $branding_palette, array( 'primary' => array() ) ) ) as $branding_invalid ) {
		$branding_filtered = $branding_invalid;
		branding_check( Branding::colors() === array(), 'Incomplete or malformed project colors must preserve native UI colors as one complete fallback.' );
	}

	$branding_filtered = $branding_palette;
	$GLOBALS['wp_styles'] = new WP_Styles();
	$branding_service->admin_styles();
	branding_check( ! wp_style_is( 'skye/backend', 'enqueued' ), 'Calling the administrative boundary during a frontend request must enqueue no backend asset.' );
	set_current_screen( 'dashboard' );
	foreach ( array( '#dd8686', '#315f5d', '#ffffff', '#000000', '#808080', '#ffff00', '#00ffff' ) as $branding_primary ) {
		$branding_service = branding_response();
		$branding_filtered = array_merge( $branding_palette, array( 'primary' => $branding_primary ) );
		do_action( 'admin_enqueue_scripts', 'index.php' );
		$branding_css = implode( '', wp_styles()->get_data( 'skye/backend', 'after' ) ?: array() );
		$branding_tokens = branding_inline_colors( $branding_css );
		branding_check( wp_style_is( 'skye/backend', 'enqueued' ) && wp_styles()->registered['skye/backend']->deps === array( 'colors' ), 'The actual administration hook must enqueue one package skin after the native color scheme.' );
		branding_check( $branding_tokens['primary'] === $branding_primary && branding_readable( $branding_tokens['on-primary'], $branding_tokens['primary'] ) && branding_readable( $branding_tokens['on-secondary'], $branding_tokens['secondary'] ), 'Automatic button foregrounds must preserve the exact brand surface while meeting normal-text contrast.' );
		branding_check( branding_readable( $branding_tokens['link'], $branding_tokens['background'] ) && branding_readable( $branding_tokens['text'], $branding_tokens['background'] ) && branding_readable( $branding_tokens['on-hover'], $branding_tokens['hover'] ), 'Generated link, body and hover text must meet independently calculated contrast.' );
		branding_check( branding_readable( $branding_tokens['control-text'], '#ffffff' ) && branding_readable( $branding_tokens['control-link'], '#ffffff' ), 'Native white login controls must have independently readable text and links even for light brand accents.' );
		preg_match( '/--skye-brand-control-rgb:([0-9]+,[0-9]+,[0-9]+);/', $branding_css, $branding_control_rgb );
		preg_match( '/--skye-brand-primary-rgb:([0-9]+,[0-9]+,[0-9]+);/', $branding_css, $branding_primary_rgb );
		branding_check( ( $branding_control_rgb[1] ?? '' ) === implode( ',', array_map( 'hexdec', str_split( substr( $branding_tokens['control-link'], 1 ), 2 ) ) ) && ( $branding_primary_rgb[1] ?? '' ) === implode( ',', array_map( 'hexdec', str_split( substr( $branding_primary, 1 ), 2 ) ) ), 'Native control RGB must match its readable white-surface color while original brand RGB remains unchanged.' );
		$branding_classes = apply_filters( 'admin_body_class', 'keep-native-class' );
		branding_check( $branding_classes === 'keep-native-class skye-branding' && apply_filters( 'admin_body_class', $branding_classes ) === $branding_classes, 'Native administrative classes must survive, with one idempotent package scope.' );
		$branding_service->admin_styles();
		branding_check( implode( '', wp_styles()->get_data( 'skye/backend', 'after' ) ) === $branding_css, 'Repeated native enqueue callbacks must not duplicate inline color data.' );
	}
	$branding_service = branding_response();
	$branding_filtered = false;
	$branding_service->admin_styles();
	branding_check( ! wp_style_is( 'skye/backend', 'enqueued' ) && apply_filters( 'admin_body_class', 'keep' ) === 'keep', 'Missing palettes must retain native administrative CSS and body classes.' );
	$GLOBALS['current_screen'] = null;
	$branding_service = branding_response();
	$branding_filtered = array( 'primary' => '#ffff00', 'secondary' => '#cccccc', 'background' => '#111111', 'text' => '#ffffff' );
	$branding_service->login_styles();
	$branding_dark_tokens = branding_inline_colors( implode( '', wp_styles()->get_data( 'skye/login', 'after' ) ?: array() ) );
	branding_check( branding_readable( $branding_dark_tokens['text'], '#111111' ) && branding_readable( $branding_dark_tokens['link'], '#111111' ) && branding_readable( $branding_dark_tokens['control-text'], '#ffffff' ) && branding_readable( $branding_dark_tokens['control-link'], '#ffffff' ), 'A dark website background and light accent must retain readable text on both page and native white form surfaces.' );

	foreach ( array( '', 'https://example.test/logo(1).png?first=A&second=B' ) as $branding_logo ) {
		$branding_service = branding_response();
		$branding_filtered = $branding_palette;
		$branding_logo_override = $branding_logo;
		wp_enqueue_style( 'login' );
		do_action( 'login_enqueue_scripts' );
		$branding_css = implode( '', wp_styles()->get_data( 'skye/login', 'after' ) ?: array() );
		$branding_classes = apply_filters( 'login_body_class', array( 'login-action-lostpassword', 'wp-core-ui' ) );
		branding_check( wp_style_is( 'skye/login', 'enqueued' ) && wp_styles()->registered['skye/login']->deps === array( 'login' ), 'The real login hook must retain the native login stylesheet as dependency.' );
		branding_check( in_array( 'login-action-lostpassword', $branding_classes, true ) && in_array( 'skye-branding', $branding_classes, true ) && in_array( $branding_logo === '' ? 'skye-login-text-logo' : 'skye-login-has-logo', $branding_classes, true ), 'Native login-action classes must survive alongside the actual image/text identity state.' );
		branding_check( apply_filters( 'login_headerurl', 'https://wordpress.org/' ) === home_url( '/' ) && apply_filters( 'login_headertext', 'Powered by WordPress' ) === wp_strip_all_tags( get_bloginfo( 'name' ) ), 'The existing native identity link must use the actual site homepage and accessible name.' );
		branding_check( $branding_logo === '' ? ! str_contains( $branding_css, '--skye-login-logo' ) : str_contains( $branding_css, '--skye-login-logo:url("' . $branding_logo . '")' ), 'Logo URL serialization must preserve query ampersands and parentheses without introducing CSS-active text.' );
		$branding_service->login_styles();
		branding_check( implode( '', wp_styles()->get_data( 'skye/login', 'after' ) ) === $branding_css, 'Repeated native login hook execution must retain one inline token block.' );
	}
	$branding_service = branding_response();
	$branding_filtered = false;
	$branding_logo_override = '';
	$branding_service->login_styles();
	$branding_classes = $branding_service->login_classes( array( 'login' ) );
	branding_check( in_array( 'skye-login-text-logo', $branding_classes, true ) && ! in_array( 'skye-branding', $branding_classes, true ) && ! wp_styles()->get_data( 'skye/login', 'after' ), 'Login without palette or logo must still show the site-name identity while retaining native colors.' );
	branding_check( Branding::asset_uri( '../foreign.css' ) === '' && Branding::asset_uri( 'main.css' ) === '', 'The package asset resolver must reject unrelated or traversing asset entry names.' );

	$branding_parent_backend = Branding::asset_uri( 'backend.css' );
	$branding_fixture_root = $branding_runtime . '/branding-owned-' . bin2hex( random_bytes( 6 ) );
	if ( ! mkdir( $branding_fixture_root, 0700 ) ) {
		throw new RuntimeException( 'Cannot create the owned temporary deployment fixture.' );
	}
	$branding_fixture_owned = true;
	$branding_filtered = null;
	add_filter( 'stylesheet_directory', 'branding_child_directory' );
	foreach ( array( array( 'version' => 1, 'colors' => $branding_palette ), array( 'version' => 2, 'colors' => $branding_palette ), array( 'version' => 1, 'colors' => array( 'primary' => '#123456' ) ) ) as $branding_index => $branding_record ) {
		$branding_child_root = $branding_fixture_root . '/child-' . $branding_index;
		mkdir( $branding_child_root . '/dist', 0700, true );
		file_put_contents( $branding_child_root . '/dist/assets.json', wp_json_encode( array( '_branding' => $branding_record ) ) );
		branding_check( $branding_index === 0 ? Branding::colors() === array_map( 'strtolower', $branding_palette ) : Branding::colors() === array(), 'The selected child build owns its palette, with native fallback for unknown versions and incomplete records.' );
		branding_check( Branding::asset_uri( 'backend.css' ) === $branding_parent_backend, 'A child-only palette manifest must not redirect parent package styles into nonexistent child files.' );
	}
	$branding_child_root = '';
	remove_filter( 'stylesheet_directory', 'branding_child_directory' );
	add_filter( 'template_directory', 'branding_parent_directory' );
	foreach ( array( '../foreign.css', 'styles/backend-0123456789abcdef.css', 'styles/backend-not-a-hash.css', 'https://example.test/foreign.css' ) as $branding_index => $branding_path ) {
		$branding_package_root = $branding_fixture_root . '/package-' . $branding_index;
		mkdir( $branding_package_root . '/dist', 0700, true );
		file_put_contents( $branding_package_root . '/dist/assets.json', wp_json_encode( array( 'backend.css' => $branding_path ) ) );
		branding_check( Branding::asset_uri( 'backend.css' ) === '', 'Traversal, foreign or missing package assets must never create speculative public stylesheet requests.' );
	}
	$branding_package_root = $branding_fixture_root . '/package-valid';
	mkdir( $branding_package_root . '/dist/styles', 0700, true );
	$branding_asset = $branding_package_root . '/dist/styles/backend-0123456789abcdef.css';
	file_put_contents( $branding_asset, '/* Owned native branding fixture. */' );
	file_put_contents( $branding_package_root . '/dist/assets.json', '{"backend.css":"styles/backend-0123456789abcdef.css"}' );
	branding_check( str_ends_with( Branding::asset_uri( 'backend.css' ), '/dist/styles/backend-0123456789abcdef.css' ), 'A complete owned immutable package entry must resolve from the native parent URI.' );
	unlink( $branding_asset );
	branding_check( Branding::asset_uri( 'backend.css' ) === '', 'A manifest snapshot must not cache a positive file-presence decision after deletion.' );
	file_put_contents( $branding_fixture_root . '/outside.css', '/* Outside this package deployment. */' );
	file_put_contents( $branding_asset, '/* Recreated owned native branding fixture. */' );
	branding_check( Branding::asset_uri( 'backend.css' ) !== '', 'A recreated owned file must resolve before an external-process replacement.' );
	$branding_replace = proc_open( array( PHP_BINARY, '-r', 'exit(unlink($argv[1]) && symlink($argv[2], $argv[1]) ? 0 : 2);', $branding_asset, $branding_fixture_root . '/outside.css' ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $branding_pipes );
	if ( ! is_resource( $branding_replace ) ) {
		throw new RuntimeException( 'Cannot start the isolated native file-replacement probe.' );
	}
	foreach ( $branding_pipes as $branding_pipe ) {
		fclose( $branding_pipe );
	}
	branding_check( proc_close( $branding_replace ) === 0, 'The isolated replacement process must complete without a shell or package mutation.' );
	branding_check( Branding::asset_uri( 'backend.css' ) === '', 'A symlink replaced by another process must fail despite a previously warmed PHP realpath cache.' );
	$branding_package_root = $branding_fixture_root . '/package-parent-swap';
	mkdir( $branding_package_root . '/dist/styles', 0700, true );
	$branding_name = 'backend-0123456789abcdef.css';
	file_put_contents( $branding_package_root . '/dist/styles/' . $branding_name, '/* Owned package parent. */' );
	file_put_contents( $branding_package_root . '/dist/assets.json', wp_json_encode( array( 'backend.css' => 'styles/' . $branding_name ) ) );
	$branding_outside = $branding_fixture_root . '/outside-parent';
	mkdir( $branding_outside, 0700 );
	file_put_contents( $branding_outside . '/' . $branding_name, '/* Outside the package. */' );
	branding_check( Branding::asset_uri( 'backend.css' ) !== '', 'The physical package parent is warm before its external replacement.' );
	branding_external_replace( $branding_package_root . '/dist/styles', $branding_outside );
	branding_check( Branding::asset_uri( 'backend.css' ) === '', 'A separately replaced intermediate package directory must not reuse a stale inside canonical spelling.' );
	foreach ( array( 'old', 'new' ) as $branding_generation ) {
		$branding_generation_root = $branding_fixture_root . '/package-generation-' . $branding_generation . '/theme';
		mkdir( $branding_generation_root . '/dist/styles', 0700, true );
		file_put_contents( $branding_generation_root . '/dist/assets.json', wp_json_encode( array( 'backend.css' => 'styles/' . $branding_name ) ) );
		if ( 'old' === $branding_generation ) {
			file_put_contents( $branding_generation_root . '/dist/styles/' . $branding_name, '/* Original complete generation. */' );
		}
	}
	$branding_current = $branding_fixture_root . '/package-generation-current';
	symlink( $branding_fixture_root . '/package-generation-old', $branding_current );
	$branding_package_root = $branding_current . '/theme';
	branding_check( Branding::asset_uri( 'backend.css' ) !== '', 'An intentional alias above the native parent root resolves a complete package.' );
	branding_external_replace( $branding_current, $branding_fixture_root . '/package-generation-new', true );
	branding_check( Branding::asset_uri( 'backend.css' ) === '', 'An external root-ancestor swap to an incomplete generation cannot keep a stale positive package decision.' );
	branding_external_replace( $branding_current, $branding_fixture_root . '/package-generation-old', true );
	branding_check( Branding::asset_uri( 'backend.css' ) !== '', 'Returning to a complete native generation restores package delivery without a cached negative decision.' );
	remove_filter( 'template_directory', 'branding_parent_directory' );
	branding_check( $branding_writes === 0, 'Automatic branding must not write options, user color preferences, roles, attachment records or other SQL data.' );
	branding_check( $branding_http === 0, 'Branding and logo resolution must make no native HTTP requests.' );
} finally {
	remove_filter( 'skye_branding_colors', 'branding_palette_filter' );
	remove_filter( 'skye_branding_logo', 'branding_logo_filter' );
	remove_filter( 'theme_mod_custom_logo', 'branding_native_logo' );
	remove_filter( 'wp_get_attachment_image_src', 'branding_attachment_image', 10 );
	remove_filter( 'pre_option_theme_options_theme_logo', 'branding_legacy_option' );
	remove_filter( 'query', 'branding_observe_query' );
	remove_filter( 'pre_http_request', 'branding_block_http', PHP_INT_MAX );
	remove_filter( 'stylesheet_directory', 'branding_child_directory' );
	remove_filter( 'template_directory', 'branding_parent_directory' );
	if ( $branding_fixture_owned && is_dir( $branding_fixture_root ) ) {
		branding_remove_fixture( $branding_fixture_root );
	}
	$GLOBALS['current_screen'] = null;
}

echo wp_json_encode( array( 'status' => 'pass', 'assertions' => $branding_checks, 'wordpress' => $wp_version, 'php' => PHP_VERSION, 'persistent_writes' => $branding_writes, 'http_requests' => $branding_http, 'native_login_and_admin_hooks' => true ) ) . "\n";
