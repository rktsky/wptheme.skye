<?php
/** Verify the integrated theme against a disposable, fully bootstrapped WordPress site. */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}
$site = realpath( getenv( 'SKYE_SITE_ROOT' ) ?: '' );
if ( ! $site || ! is_file( $site . '/wp-load.php' ) || ! str_contains( $site, '/.git/runtime/' ) ) {
	throw new RuntimeException( 'SKYE_SITE_ROOT must be an isolated checkout under .git/runtime/.' );
}
require $site . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
wp_set_current_user( 1 );
if ( in_array( '--activate-classic-plugin', $argv, true ) ) {
	$error = activate_plugin( 'classic-editor/classic-editor.php', '', false, true );
	if ( is_wp_error( $error ) ) {
		throw new RuntimeException( $error->get_error_message() );
	}
	update_option( 'classic-editor-replace', 'block' );
	update_option( 'classic-editor-allow-users', 'allow' );
	echo "Original Classic Editor activated with conflicting fixture preferences.\n";
	exit;
}
$checks = 0;

/**
 * Fail a native integration contract immediately rather than hiding diagnostics.
 *
 * @since 3.0.0
 * @param bool $condition Actual result from integrated Core and theme code.
 * @param string $message Expected behavior.
 * @return void
 */
function skye_site_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	$GLOBALS['checks']++;
}

skye_site_check( 'skye' === get_template(), 'The actual source theme is active' );
skye_site_check( ! function_exists( 'get_field' ), 'ACF absent path is exercised without an emulated provider' );
skye_site_check( ! wp_using_ext_object_cache(), 'Core remains functional without Redis' );
register_post_type( 'skye_fixture_type', array( 'public' => false, 'show_ui' => true, 'show_in_rest' => true, 'supports' => array( 'title', 'editor' ) ) );
foreach ( array( 'post', 'page', 'skye_fixture_type' ) as $type ) {
	skye_site_check( false === use_block_editor_for_post_type( $type ), 'Native editor decision is classic for ' . $type );
}
$article = get_page_by_path( 'fixture-post', OBJECT, 'post' );
skye_site_check( false === use_block_editor_for_post( $article ), 'Existing native post uses classic editing' );
skye_site_check( false === wp_use_widgets_block_editor(), 'Native widget manager remains classic' );
if ( class_exists( 'Classic_Editor' ) ) {
	$editors = apply_filters( 'classic_editor_enabled_editors_for_post', array( 'classic_editor' => true, 'block_editor' => true ), $article );
	skye_site_check( true === $editors['classic_editor'] && false === $editors['block_editor'], 'Separately updated original plugin offers no block-editor switch' );
}
$mimes = get_allowed_mime_types();
skye_site_check( ! isset( $mimes['svg'] ), 'Theme does not enable unsafe SVG uploads' );
skye_site_check( has_filter( 'override_load_textdomain' ) === false, 'Core translation loading is not replaced' );
skye_site_check( has_filter( 'option_active_plugins' ) === false, 'Theme does not filter installed plugin activation' );
skye_site_check( current_user_can( 'customize' ), 'Native administrative Customizer capability survives' );
$native = Rocketsky\Skye\Assets\asset_uri( 'styles/main.css' );
skye_site_check( str_contains( $native, '/dist/styles/' ) && preg_match( '/-[a-f0-9]+\.css$/', $native ) === 1, 'Native theme URL uses a deployed content fingerprint' );
$temporary = tempnam( sys_get_temp_dir(), 'skye-json-' );
file_put_contents( $temporary, '{invalid' );
$manifest = new Rocketsky\Skye\Assets\JsonManifest( $temporary );
skye_site_check( array() === $manifest->get(), 'Malformed deployment manifest returns a safe array' );
unlink( $temporary );
foreach ( array( '../secret', '/etc/passwd', 'https://example.invalid/x', 'scripts//main.js' ) as $invalid ) {
	skye_site_check( '' === Rocketsky\Skye\Assets\relative_path( $invalid ), 'Foreign or ambiguous asset path is refused' );
}
$external = tempnam( sys_get_temp_dir(), 'skye-external-template-' );
skye_site_check( $external === rs_base( $external ), 'Independent plugin templates retain their own response boundary' );
unlink( $external );
if ( metadata_exists( 'post', $article->ID, 'custom_teasertext' ) ) {
	skye_site_check( 'UNMIGRATED_FIXTURE_VALUE' === get_post_meta( $article->ID, 'custom_teasertext', true ), 'Actual HTTP rendering does not migrate or delete stored legacy metadata' );
}
$batch_options = array( 'theme_options_theme_logo' => '0', 'theme_options_favicon' => false, 'theme_options_gmakey' => '' );
foreach ( $batch_options as $name => $value ) {
	$missing = new stdClass();
	skye_site_check( $missing === get_option( $name, $missing ), 'The isolated batch fixture owns a previously absent option: ' . $name );
}
try {
	foreach ( $batch_options as $name => $value ) {
		skye_site_check( add_option( $name, $value, '', false ), 'Create only an owned non-autoloaded native fixture option.' );
		wp_cache_delete( $name, 'options' );
	}
	wp_cache_delete( 'notoptions', 'options' );
	$GLOBALS['theme_options'] = null;
	$queries_before_batch = $wpdb->num_queries;
	$batch_result = get_theme_options();
	$batch_queries = $wpdb->num_queries - $queries_before_batch;
	skye_site_check( array( 'theme_logo' => '0', 'favicon' => '', 'gmakey' => '' ) === $batch_result, 'Cold native option reads preserve zero and empty values, including Core storing scalar false as an empty string.' );
	skye_site_check( 1 === $batch_queries, 'Three cold non-autoloaded settings require one actual native query.' );
	get_theme_options();
	skye_site_check( $wpdb->num_queries === $queries_before_batch + 1, 'A warm read adds no native query.' );
	update_option( 'theme_options_gmakey', 'NATIVE_BATCH_UPDATE' );
	skye_site_check( 'NATIVE_BATCH_UPDATE' === get_theme_options()['gmakey'], 'A native update invalidates the primed request snapshot.' );
} finally {
	foreach ( $batch_options as $name => $value ) {
		delete_option( $name );
	}
}
skye_site_check( array( 'theme_logo' => false, 'favicon' => false, 'gmakey' => false ) === get_theme_options(), 'Native deletion restores the original absence of every owned option.' );
$option_name = 'theme_options_gmakey';
$missing_option = new stdClass();
$saved_option = get_option( $option_name, $missing_option );
$icon_before_option_writes = get_option( 'site_icon', false );
try {
	get_theme_options();
	update_option( $option_name, 'SKYE_NATIVE_OPTION_CACHE_FIXTURE' );
	skye_site_check( 'SKYE_NATIVE_OPTION_CACHE_FIXTURE' === get_theme_options()['gmakey'], 'A native option write refreshes the already-loaded theme cache' );
	update_option( $option_name, '0' );
	skye_site_check( '0' === get_theme_options()['gmakey'], 'A native option update preserves the stored zero string' );
	delete_option( $option_name );
	skye_site_check( false === get_theme_options()['gmakey'], 'A native option deletion invalidates the cached value' );
	add_option( $option_name, '' );
	skye_site_check( '' === get_theme_options()['gmakey'], 'A native option addition preserves an intentionally empty value' );
	skye_site_check( $icon_before_option_writes === get_option( 'site_icon', false ), 'Native option cache invalidation does not synchronize or modify the favicon' );
} finally {
	if ( $saved_option === $missing_option ) {
		delete_option( $option_name );
	} else {
		update_option( $option_name, $saved_option );
	}
}
skye_site_check( $saved_option === get_option( $option_name, $missing_option ), 'The isolated native option fixture restores the original value or absence' );
$site_icon = get_option( 'site_icon', 0 );
update_option( 'site_icon', 321 );
delete_option( 'theme_options_favicon' );
unset_theme_options( 'theme_options' );
skye_site_check( 321 === (int) get_option( 'site_icon' ), 'Absent legacy favicon preserves native site-icon configuration' );
update_option( 'site_icon', $site_icon );
update_post_meta( $article->ID, 'custom_teasertext', 'UNMIGRATED_FIXTURE_VALUE' );
skye_site_check( 'UNMIGRATED_FIXTURE_VALUE' === get_post_meta( $article->ID, 'custom_teasertext', true ), 'Render-migration marker is present for browser read-only verification' );
echo wp_json_encode( array( 'status' => 'pass', 'assertions' => $checks, 'wordpress' => $wp_version, 'php' => PHP_VERSION, 'classic_plugin' => class_exists( 'Classic_Editor' ), 'database' => 'isolated SQLite3.1.3', 'external_transports' => 'blocked' ) ) . "\n";
