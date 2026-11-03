<?php
/** Register classic theme support and native immutable asset dependencies. */

namespace Rocketsky\Skye\Setup;

use Rocketsky\Skye\Assets;
use Rocketsky\Skye\Packages\Icons;

/**
 * Describe theme capabilities while leaving content and site policy under Core control.
 *
 * Native image, title, logo and HTML5 support keep plugins and child themes compatible.
 *
 * @since 2015-11-11
 * @return void
 */
function setup() {
	load_theme_textdomain( 'skye', get_template_directory() . '/lang' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo' );
	add_theme_support( 'html5', array( 'caption', 'comment-form', 'comment-list', 'gallery', 'search-form', 'style', 'script' ) );
	register_nav_menus( array( 'primary_navigation' => __( 'Primary Navigation', 'skye' ) ) );
	add_editor_style( Assets\asset_uri( 'styles/main.css' ) );
}
add_action( 'after_setup_theme', __NAMESPACE__ . '\\setup' );

/**
 * Register the existing footer extension point using the native widget renderer.
 *
 * Wrappers are WordPress API format templates rather than an alternative page renderer.
 *
 * @since 2015-11-11
 * @return void
 */
function widgets_init() {
	register_sidebar( array(
		'name' => __( 'Footer', 'skye' ), 'id' => 'sidebar-footer',
		'before_widget' => '<section class="widget %1$s %2$s">', 'after_widget' => '</section>',
		'before_title' => '<h3>', 'after_title' => '</h3>',
	) );
}
add_action( 'widgets_init', __NAMESPACE__ . '\\widgets_init' );

/**
 * Enqueue complete viewport peers and only the starter's native JavaScript dependencies.
 *
 * Core owns ordering, inline data and script strategy. Block content retains its
 * native assets even though editing is classic. Content hashes provide cache busting.
 *
 * @since 2015-11-11
 * @return void
 */
function assets() {
	foreach ( Assets\stylesheet_variants() as $handle => $style ) {
		wp_enqueue_style( $handle, Assets\asset_uri( $style['file'] ), array(), null, $style['media'] );
	}
	wp_register_style( 'skye/fontawesome', Assets\asset_uri( 'styles/fontawesome.css' ), array_keys( Assets\stylesheet_variants() ), null );
	if ( ! class_exists( Icons::class ) || Icons::needs_styles() ) {
		wp_enqueue_style( 'skye/fontawesome' );
	}
	wp_enqueue_script( 'skye/js', Assets\asset_uri( 'scripts/main.js' ), array(), null, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\assets', 100 );

/**
 * Add configured critical hints using Core's preload API, preserving unrelated providers.
 *
 * Responsive image candidates omit a fallback URL to prevent duplicate downloads
 * in older preload parsers. Callers own content eligibility and accurate sizes.
 *
 * @since 3.0.0
 * @param array $resources Existing native preload descriptors.
 * @return array Original descriptors followed by valid configured own hints.
 */
function preload_resources( $resources ) {
	$resources = is_array( $resources ) ? $resources : array();
	$hints = array_merge( Assets\font_preloads(), apply_filters( 'skye_preload_resources', array() ) );
	foreach ( $hints as $hint ) {
		if ( ! is_array( $hint ) || ! in_array( $hint['as'] ?? '', array( 'font', 'image', 'style' ), true ) ) {
			continue;
		}
		if ( ( $hint['as'] ?? '' ) === 'image' && ! empty( $hint['imagesrcset'] ) ) {
			unset( $hint['href'] );
		}
		if ( ( isset( $hint['href'] ) && ! is_string( $hint['href'] ) ) || ( empty( $hint['href'] ) && empty( $hint['imagesrcset'] ) ) ) {
			continue;
		}
		if ( ! in_array( $hint, $resources, true ) ) {
			$resources[] = $hint;
		}
	}
	return $resources;
}
add_filter( 'wp_preload_resources', __NAMESPACE__ . '\\preload_resources' );

/**
 * Enqueue the separately built login skin without replacing WordPress form behavior.
 *
 * @since 2020-12-10
 * @return void
 */
function ct_login_style() {
	wp_enqueue_style( 'skye/login', Assets\asset_uri( 'styles/login.css' ), array(), null );
}
add_action( 'login_enqueue_scripts', __NAMESPACE__ . '\\ct_login_style' );
