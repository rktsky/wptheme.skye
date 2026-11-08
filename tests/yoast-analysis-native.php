<?php
/** Verify explicit Yoast projections with native Core hooks, public post guards and script dependencies on a disposable site. */

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit;
}
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$analysis_site = realpath( getenv( 'SKYE_SITE_ROOT' ) ?: '' );
if ( ! $analysis_site || ! is_file( $analysis_site . '/wp-load.php' ) ) {
	throw new RuntimeException( 'Set SKYE_SITE_ROOT to a disposable local WordPress fixture.' );
}
require $analysis_site . '/wp-load.php';
$analysis_database = defined( 'DB_PATH' ) ? realpath( DB_PATH ) : false;
$analysis_fixture = realpath( dirname( $analysis_site ) );
if ( wp_get_environment_type() !== 'local' || ! defined( 'DB_ENGINE' ) || DB_ENGINE !== 'sqlite' || ! $wpdb instanceof WP_SQLite_DB ||
	! $analysis_database || ! $analysis_fixture || ! str_starts_with( $analysis_database, $analysis_fixture . '/' ) ||
	! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( '127.0.0.1', 'localhost' ), true ) ) {
	throw new RuntimeException( 'Yoast tests require the native local SQLite fixture on loopback.' );
}
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
require_once dirname( __DIR__ ) . '/lib/package/YoastAnalysis.php';

use Rocketsky\Skye\Packages\YoastAnalysis;

new YoastAnalysis();
$analysis_checks = 0;
$analysis_project_result = null;
$analysis_project_calls = 0;
$analysis_editor_config = null;
$analysis_throw = false;
$analysis_http = 0;

/**
 * Fail on an actual native contract mismatch without exposing fixture data.
 * @since 3.5.0
 * @param bool $condition Required observed result.
 * @param string $reason Specific invariant.
 * @return void
 */
function analysis_check( $condition, $reason ) {
	if ( ! $condition ) {
		throw new RuntimeException( $reason );
	}
	$GLOBALS['analysis_checks']++;
}

/**
 * Supply only controlled project results through the actual public saved-content filter.
 * @since 3.5.0
 * @param mixed $previous Earlier explicit project result, initially null.
 * @param WP_Post $post Exact native caller-selected post.
 * @param string $content Incoming provider content.
 * @return mixed Controlled semantic HTML or an unsupported fallback value.
 */
function analysis_project( $previous, $post, $content ) {
	$GLOBALS['analysis_project_calls']++;
	$GLOBALS['analysis_project_post'] = $post;
	$GLOBALS['analysis_project_input'] = $content;
	if ( $GLOBALS['analysis_throw'] ) {
		throw new RuntimeException( 'Synthetic unavailable project source.' );
	}
	return $GLOBALS['analysis_project_result'];
}

/**
 * Opt into one native editor without querying a public relationship catalog.
 * @since 3.5.0
 * @param mixed $previous Earlier editor contract, initially null.
 * @param WP_Post $post Exact authorized native editor document.
 * @return mixed Controlled small script contract or an unavailable mapping.
 */
function analysis_editor( $previous, $post ) {
	$GLOBALS['analysis_editor_post'] = $post;
	return $GLOBALS['analysis_editor_config'];
}

/**
 * Point the package to this source checkout through Core's public path filter.
 * @since 3.5.0
 * @param string $directory Original theme directory.
 * @return string Owned candidate source whose immutable build is being verified.
 */
function analysis_source_directory( $directory ) {
	return dirname( __DIR__ );
}

/**
 * Count and stop any unexpected native transport; these tests never need HTTP.
 * @since 3.5.0
 * @param mixed $previous Earlier native request preemption.
 * @return WP_Error Explicit local test refusal.
 */
function analysis_no_http( $previous ) {
	$GLOBALS['analysis_http']++;
	return new WP_Error( 'analysis_no_http', 'No analysis test may perform transport.' );
}

/**
 * Register the actual original addon identity only when that provider is installed in this fixture.
 * @since 3.5.0
 * @return void Pure Core fixtures keep the documented no-addon path.
 */
function analysis_native_collector() {
	if ( defined( 'AC_SEO_ACF_ANALYSIS_PLUGIN_FILE' ) ) {
		wp_register_script( 'yoast-acf-analysis-post', plugins_url( '/js/yoast-acf-analysis.js', AC_SEO_ACF_ANALYSIS_PLUGIN_FILE ), array( 'jquery', 'underscore' ), '3.2', true );
	}
}

$analysis_original_post = $GLOBALS['post'] ?? null;
$analysis_original_scripts = $GLOBALS['wp_scripts'] ?? null;
$analysis_original_screen = $GLOBALS['current_screen'] ?? null;
$analysis_original_user = get_current_user_id();
$analysis_posts = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => 1, 'has_password' => false ) );
$analysis_admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( ! $analysis_posts || ! $analysis_admins ) {
	throw new RuntimeException( 'The disposable fixture requires a native published Sample Page and its installation administrator.' );
}
$analysis_post = $analysis_posts[0];
add_filter( 'pre_http_request', 'analysis_no_http', PHP_INT_MAX );
add_filter( 'template_directory', 'analysis_source_directory' );

try {
	$analysis_before = $wpdb->num_queries;
	analysis_check( apply_filters( 'wpseo_pre_analysis_post_content', 'Native source.', $analysis_post ) === 'Native source.', 'No project mapping preserves native content.' );
	analysis_check( $wpdb->num_queries === $analysis_before, 'Dormant saved analysis performs no native query.' );
	$GLOBALS['wp_scripts'] = new WP_Scripts();
	YoastAnalysis::enqueue( 'post.php' );
	analysis_check( ! wp_script_is( 'skye-yoast-analysis', 'registered' ), 'No editor mapping registers or enqueues assets.' );
	analysis_check( has_filter( 'Yoast\\WP\\ACF\\blacklist_name', array( YoastAnalysis::class, 'content' ) ) === false, 'The base never changes original-addon leaf exclusions.' );
	add_filter( 'skye_yoast_analysis_content', 'analysis_project', 10, 3 );
	$analysis_project_result = '<h1>Actual saved project content.</h1><img src="https://example.test/actual-image.jpg" alt="Actual attachment description">';
	$GLOBALS['post'] = new WP_Post( (object) array( 'ID' => 999999999, 'post_type' => 'post', 'post_status' => 'private', 'post_password' => '' ) );
	analysis_check( apply_filters( 'wpseo_pre_analysis_post_content', 'Original.', $analysis_post ) === $analysis_project_result, 'The exact two-argument provider filter accepts the explicit public projection.' );
	analysis_check( $analysis_project_post === $analysis_post && $analysis_project_input === 'Original.', 'Projection receives the exact supplied post object and content, never the global loop post.' );
	foreach ( array( null, false, 23, array( 'not' => 'html' ), new stdClass() ) as $analysis_project_result ) {
		analysis_check( apply_filters( 'wpseo_pre_analysis_post_content', 'Original.', $analysis_post ) === 'Original.', 'Unsupported saved projection values remain native.' );
	}
	$analysis_throw = true;
	analysis_check( apply_filters( 'wpseo_pre_analysis_post_content', 'Original.', $analysis_post ) === 'Original.', 'A failing owned projection keeps the incoming content.' );
	$analysis_throw = false;
	$analysis_project_result = '';
	analysis_check( apply_filters( 'wpseo_pre_analysis_post_content', 'Original.', $analysis_post ) === '', 'A complete empty source is distinct from an unavailable source.' );
	$analysis_project_calls = 0;
	foreach ( array( 'private', 'draft', 'trash' ) as $status ) {
		$restricted = clone $analysis_post;
		$restricted->post_status = $status;
		analysis_check( apply_filters( 'wpseo_pre_analysis_post_content', 'Original.', $restricted ) === 'Original.', 'Unpublished or private posts keep the provider input: ' . $status );
	}
	$restricted = clone $analysis_post;
	$restricted->post_password = 'synthetic-password';
	analysis_check( apply_filters( 'wpseo_pre_analysis_post_content', 'Original.', $restricted ) === 'Original.', 'Password-protected data never enters the saved projection.' );
	$restricted = clone $analysis_post;
	$restricted->post_type = 'revision';
	analysis_check( apply_filters( 'wpseo_pre_analysis_post_content', 'Original.', $restricted ) === 'Original.', 'Nonpublic post types keep native content.' );
	$restricted = clone $analysis_post;
	$restricted->ID = 0;
	analysis_check( apply_filters( 'wpseo_pre_analysis_post_content', 'Original.', $restricted ) === 'Original.', 'A missing native post identity cannot select a projection.' );
	analysis_check( apply_filters( 'wpseo_pre_analysis_post_content', 'Original.', null ) === 'Original.', 'Unknown native post shapes remain unchanged.' );
	analysis_check( $analysis_project_calls === 0, 'No protected or unknown case calls the project reader.' );
	$analysis_project_result = '<h1>Visible heading.</h1><img src="https://example.test/actual-image.jpg" alt="Actual attachment description">';
	if ( class_exists( 'WPSEO_Content_Images' ) ) {
		$images = new WPSEO_Content_Images();
		analysis_check( $images->get_images( $analysis_post->ID, $analysis_post ) === array( 'https://example.test/actual-image.jpg' ), 'The unchanged original Yoast image finder consumes the saved projection.' );
	}
	remove_filter( 'skye_yoast_analysis_content', 'analysis_project', 10 );
	if ( ! defined( 'WPSEO_VERSION' ) ) {
		define( 'WPSEO_VERSION', 'isolated-availability-fixture' );
	}
	wp_set_current_user( (int) $analysis_admins[0] );
	set_current_screen( 'page' );
	$GLOBALS['post'] = $analysis_post;
	$GLOBALS['wp_scripts'] = new WP_Scripts();
	wp_register_script( 'fixture-project-analysis', '/fixture-owned-project.js', array( 'skye-yoast-analysis' ), null, true );
	analysis_native_collector();
	add_filter( 'skye_yoast_analysis_editor', 'analysis_editor', 10, 2 );
	$analysis_editor_config = array( 'id' => 'fixture', 'script' => 'fixture-project-analysis' );
	YoastAnalysis::enqueue( 'post.php' );
	analysis_check( wp_script_is( 'fixture-project-analysis', 'enqueued' ), 'The authorized explicit editor mapping enqueues its registered project adapter.' );
	analysis_check( $analysis_editor_post === $analysis_post, 'The editor mapping receives the exact authorized current native document.' );
	$bridge = wp_scripts()->registered['skye-yoast-analysis'];
	analysis_check( $bridge->deps === ( defined( 'AC_SEO_ACF_ANALYSIS_PLUGIN_FILE' ) ? array( 'jquery', 'yoast-acf-analysis-post' ) : array( 'jquery' ) ), 'The bridge depends on the actual registered original collector when present.' );
	analysis_check( $bridge->ver === null && str_contains( $bridge->src, '/scripts/yoast-analysis-' ), 'The own bridge uses its immutable built asset.' );
	analysis_check( wp_scripts()->get_data( 'skye-yoast-analysis', 'data' ) === 'var SkyeYoastAnalysisConfig = {"id":"fixture"};', 'Localized data contains only the bounded project identity.' );
	$GLOBALS['wp_scripts'] = new WP_Scripts();
	wp_register_script( 'fixture-project-analysis', '/fixture-owned-project.js', array( 'skye-yoast-analysis' ), null, true );
	wp_register_script( 'skye-yoast-analysis', '/foreign-source.js', array(), 'foreign', true );
	$foreign = wp_scripts()->registered['skye-yoast-analysis'];
	YoastAnalysis::enqueue( 'post.php' );
	analysis_check( wp_scripts()->registered['skye-yoast-analysis'] === $foreign && wp_scripts()->get_data( 'skye-yoast-analysis', 'data' ) === false && ! wp_script_is( 'fixture-project-analysis', 'enqueued' ), 'A pre-existing foreign bridge handle receives neither project data nor enqueue.' );
	foreach ( array( '3.3', 'unknown', null ) as $collector_version ) {
		$GLOBALS['wp_scripts'] = new WP_Scripts();
		wp_register_script( 'fixture-project-analysis', '/fixture-owned-project.js', array( 'skye-yoast-analysis' ), null, true );
		wp_register_script( 'yoast-acf-analysis-post', '/unknown-collector.js', array( 'jquery', 'underscore' ), $collector_version, true );
		YoastAnalysis::enqueue( 'post.php' );
		analysis_check( ! wp_script_is( 'skye-yoast-analysis', 'registered' ) && ! wp_script_is( 'fixture-project-analysis', 'enqueued' ), 'An unknown collector version or source retains complete native analysis.' );
	}
	if ( defined( 'AC_SEO_ACF_ANALYSIS_PLUGIN_FILE' ) ) {
		$GLOBALS['wp_scripts'] = new WP_Scripts();
		wp_register_script( 'fixture-project-analysis', '/fixture-owned-project.js', array( 'skye-yoast-analysis' ), null, true );
		wp_register_script( 'yoast-acf-analysis-post', plugins_url( '/js/yoast-acf-analysis.js', AC_SEO_ACF_ANALYSIS_PLUGIN_FILE ), array( 'jquery' ), '3.2', true );
		YoastAnalysis::enqueue( 'post.php' );
		analysis_check( ! wp_script_is( 'skye-yoast-analysis', 'registered' ), 'Changed collector dependencies require renewed review even under the known version.' );
	}
	foreach ( array( null, array( 'id' => 'fixture', 'script' => 'missing' ), array( 'id' => '<invalid>', 'script' => 'fixture-project-analysis' ), array( 'id' => 'fixture', 'script' => 'skye-yoast-analysis' ) ) as $analysis_editor_config ) {
		$GLOBALS['wp_scripts'] = new WP_Scripts();
		wp_register_script( 'fixture-project-analysis', '/fixture-owned-project.js', array( 'skye-yoast-analysis' ), null, true );
		YoastAnalysis::enqueue( 'post.php' );
		analysis_check( ! wp_script_is( 'skye-yoast-analysis', 'registered' ), 'Unknown editor contracts preserve native script delivery.' );
	}
	$analysis_editor_config = array( 'id' => 'fixture', 'script' => 'fixture-project-analysis' );
	$GLOBALS['wp_scripts'] = new WP_Scripts();
	wp_register_script( 'fixture-project-analysis', '/fixture-owned-project.js', array(), null, true );
	YoastAnalysis::enqueue( 'post.php' );
	analysis_check( ! wp_script_is( 'skye-yoast-analysis', 'registered' ), 'A project script must explicitly depend on the bridge.' );
	wp_deregister_script( 'fixture-project-analysis' );
	wp_register_script( 'fixture-project-analysis', '/fixture-owned-project.js', array( 'skye-yoast-analysis' ), null, true );
	wp_set_current_user( 0 );
	YoastAnalysis::enqueue( 'post.php' );
	analysis_check( ! wp_script_is( 'skye-yoast-analysis', 'registered' ), 'An unauthorized editor receives no project adapter.' );
	wp_set_current_user( (int) $analysis_admins[0] );
	YoastAnalysis::enqueue( 'index.php' );
	analysis_check( ! wp_script_is( 'skye-yoast-analysis', 'registered' ), 'Other administration screens receive no bridge.' );
	$GLOBALS['post'] = clone $analysis_post;
	$GLOBALS['post']->post_type = 'post';
	YoastAnalysis::enqueue( 'post.php' );
	analysis_check( ! wp_script_is( 'skye-yoast-analysis', 'registered' ), 'A mismatched screen/post identity cannot select an editor adapter.' );
	analysis_check( $analysis_http === 0, 'No analysis callback or enqueue performs HTTP.' );
	echo 'PASS ' . $analysis_checks . " native Yoast scope, identity, fallback and script-contract assertions; no CMS writes or transport.\n";
} finally {
	remove_filter( 'skye_yoast_analysis_content', 'analysis_project', 10 );
	remove_filter( 'skye_yoast_analysis_editor', 'analysis_editor', 10 );
	remove_filter( 'pre_http_request', 'analysis_no_http', PHP_INT_MAX );
	remove_filter( 'template_directory', 'analysis_source_directory' );
	$GLOBALS['post'] = $analysis_original_post;
	$GLOBALS['wp_scripts'] = $analysis_original_scripts;
	$GLOBALS['current_screen'] = $analysis_original_screen;
	wp_set_current_user( $analysis_original_user );
}
