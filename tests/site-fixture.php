<?php
/** Install disposable WordPress fixture content without contacting external services. */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}
$site = realpath( getenv( 'SKYE_SITE_ROOT' ) ?: '' );
if ( ! $site || ! is_file( $site . '/wp-load.php' ) || ! str_contains( $site, '/.git/runtime/' ) ) {
	throw new RuntimeException( 'SKYE_SITE_ROOT must be an isolated checkout under .git/runtime/.' );
}
define( 'WP_INSTALLING', true );
require $site . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
if ( ! is_blog_installed() ) {
	wp_install( 'Skye Fixture', 'skye_fixture', 'fixture@example.invalid', false, '', 'isolated-fixture-password' );
}
wp_set_current_user( 1 );
update_option( 'blogname', 'Skye Fixture' );
update_option( 'blogdescription', 'A classic starter theme with Bootstrap 6' );
update_option( 'permalink_structure', '' );

/**
 * Create or reuse fixture content so browser checks are repeatable.
 *
 * Only the disposable installation selected above is modified. External HTTP,
 * mail and automatic Cron are blocked by its dedicated test configuration.
 *
 * @since 3.0.0
 * @param string $slug Stable native content slug.
 * @param array $values Native wp_insert_post() values.
 * @return int Created or existing native post identity.
 */
function skye_fixture_post( $slug, $values ) {
	$parent = empty( $values['post_parent'] ) ? '' : get_page_uri( $values['post_parent'] ) . '/';
	$existing = get_page_by_path( $parent . $slug, OBJECT, $values['post_type'] );
	$id = wp_insert_post( array_merge( array( 'post_status' => 'publish', 'post_name' => $slug, 'ID' => $existing ? $existing->ID : 0 ), $values ), true );
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( $id->get_error_message() );
	}
	return $id;
}
$home = skye_fixture_post( 'fixture-home', array( 'post_type' => 'page', 'post_title' => 'A clear starting point', 'post_content' => '<p>Native WordPress content remains readable without ACF, Redis or a JavaScript page builder.</p><h2>Simple content</h2><p>Keyboard navigation and responsive layouts are part of the base theme.</p>' ) );
$about = skye_fixture_post( 'fixture-about', array( 'post_type' => 'page', 'post_title' => 'About the fixture', 'post_content' => '<p>This page exercises native navigation and content.</p>' ) );
$child = skye_fixture_post( 'fixture-child', array( 'post_type' => 'page', 'post_parent' => $about, 'post_title' => 'A child page', 'post_content' => '<p>Nested navigation keeps its native page state.</p>' ) );
$protected = skye_fixture_post( 'fixture-protected', array( 'post_type' => 'page', 'post_title' => 'Protected page', 'post_password' => 'fixture-only', 'post_content' => 'PRIVATE_FIXTURE_MARKER' ) );
$article = skye_fixture_post( 'fixture-post', array( 'post_type' => 'post', 'post_title' => 'A native article', 'post_content' => '<p>A real native article with comments and pagination.</p>' ) );
$blocks = skye_fixture_post( 'fixture-blocks', array( 'post_type' => 'post', 'post_title' => 'Existing block content', 'post_content' => '<!-- wp:paragraph --><p>Previously saved block content still renders.</p><!-- /wp:paragraph -->' ) );
$longtext = skye_fixture_post( 'fixture-long', array( 'post_type' => 'page', 'post_title' => str_repeat( 'A longer readable heading ', 5 ), 'post_content' => '<p>Reference: ' . str_repeat( 'LongReference0123456789', 12 ) . '</p><p>Long content must wrap within a narrow layout.</p>' ) );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $home );
$menu = wp_get_nav_menu_object( 'Fixture navigation' );
$menu_id = $menu ? $menu->term_id : wp_create_nav_menu( 'Fixture navigation' );
$items = wp_get_nav_menu_items( $menu_id );
if ( ! $items ) {
	foreach ( array( $home, $about, $protected ) as $id ) {
		$item = wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-object-id' => $id, 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
		if ( $id === $about ) {
			wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-object-id' => $child, 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish', 'menu-item-parent-id' => $item ) );
		}
	}
}
echo wp_json_encode( array( 'home' => $home, 'about' => $about, 'child' => $child, 'protected' => $protected, 'article' => $article, 'blocks' => $blocks, 'longtext' => $longtext, 'menu' => $menu_id, 'wordpress' => $wp_version, 'php' => PHP_VERSION ) ) . "\n";
