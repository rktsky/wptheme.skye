<?php

namespace Rocketsky\Skye\Setup;

use Rocketsky\Skye\Assets;

/**
 * Theme setup
 */
function setup() {

	// Make theme available for translation
	load_theme_textdomain( 'skye', get_template_directory() . '/lang' );

	// Enable plugins to manage the document title
	// http://codex.wordpress.org/Function_Reference/add_theme_support#Title_Tag
	add_theme_support('title-tag');

	// Register wp_nav_menu() menus
	// http://codex.wordpress.org/Function_Reference/register_nav_menus
	register_nav_menus([
		'primary_navigation' => __('Primary Navigation', 'skye')
	]);

	// Enable post thumbnails
	// http://codex.wordpress.org/Post_Thumbnails
	// http://codex.wordpress.org/Function_Reference/set_post_thumbnail_size
	// http://codex.wordpress.org/Function_Reference/add_image_size
	add_theme_support('post-thumbnails');

	// Enable post formats
	// http://codex.wordpress.org/Post_Formats
	add_theme_support('post-formats', ['aside', 'gallery', 'link', 'image', 'quote', 'video', 'audio']);

	// Enable HTML5 markup support
	// http://codex.wordpress.org/Function_Reference/add_theme_support#HTML5
	add_theme_support('html5', ['caption', 'comment-form', 'comment-list', 'gallery', 'search-form']);

	// Use main stylesheet for visual editor
	// To add custom styles edit /assets/styles/layouts/_tinymce.scss
	add_editor_style(Assets\asset_path('styles/main.css'));
}
add_action('after_setup_theme', __NAMESPACE__ . '\\setup');

/**
 * Register sidebars
 */
function widgets_init() {

	register_sidebar([
		'name'			=> __('Footer', 'skye'),
		'id'			=> 'sidebar-footer',
		'before_widget' => '<section class="widget %1$s %2$s">',
		'after_widget'	=> '</section>',
		'before_title'	=> '<h3>',
		'after_title'	=> '</h3>'
	]);

}
//add_action('widgets_init', __NAMESPACE__ . '\\widgets_init');

/**
 * Theme assets
 */
function assets() {
	//wp_enqueue_style('skye/css', Assets\asset_path('styles/main.css'), false, null);
	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'wp-block-library-theme' );
	wp_dequeue_style( 'wc-block-style' );

	if (is_single() && comments_open() && get_option('thread_comments')) {
		wp_enqueue_script('comment-reply');
	}

	//wp_enqueue_script('skye/js', Assets\asset_path('scripts/main.js'), ['jquery'], null, true);
}
add_action('wp_enqueue_scripts', __NAMESPACE__ . '\\assets', 100);


function print_assets() {

	$file = 'styles/main.css';
	if( file_exists( Assets\dist_path( $file ) ) ) {
		//  deepcode ignore XSS: no external input
		echo '<style type="text/css" name=\'' . $file . '\'>' . str_replace( '../', parse_url( get_template_directory_uri(), PHP_URL_PATH ) . '/dist/', file_get_contents( Assets\dist_path( $file ) ) ) . '</style>';
	}

	// Example font loading
	//echo '<link rel="preload" href="' . Assets\dist_path( 'fonts/font.woff2' ) . '" as="font" type="font/woff2" crossorigin>';

}
add_action('wp_head', __NAMESPACE__ . '\\print_assets' );

function print_late_assets() {

	$files = [
		[ 'direct', 'wp-includes/js/wp-embed.min.js' ],
		[ 'direct', 'wp-includes/js/jquery/jquery.min.js' ],
		[ 'direct', 'wp-includes/js/jquery/jquery-migrate.min.js' ],
		[ 'dist', 'scripts/main.js' ],
	];

	foreach( $files as $file ) {

		switch ( $file[ 0 ] ) {
			case 'direct':
				if( file_exists( ABSPATH . $file[ 1 ] ) ) {
					//  deepcode ignore XSS: no external input
					echo '<script type=\'text/javascript\' name=\'' . $file[ 1 ] . '\'>' . file_get_contents( ABSPATH . $file[ 1 ] ) . '</script>';
				}
				break;
			case 'dist':
				if( file_exists( Assets\dist_path( $file[ 1 ] ) ) ) {
					//  deepcode ignore XSS: no external input
					echo '<script type=\'text/javascript\' name=\'' . $file[ 1 ] . '\'>' . file_get_contents( Assets\dist_path( $file[ 1 ] ) ) . '</script>';
				}
				break;
			case 'asset':
				if( file_exists( Assets\asset_path( $file[ 1 ] ) ) ) {
					//  deepcode ignore XSS: no external input
					echo '<script type=\'text/javascript\' name=\'' . $file[ 1 ] . '\'>' . file_get_contents( Assets\asset_path( $file[ 1 ] ) ) . '</script>';
				}
				break;
		}

	}

}
add_action('wp_footer', __NAMESPACE__ . '\\print_late_assets' );

function remove_embed() {
	wp_dequeue_script( 'wp-embed' );
	wp_dequeue_script( 'jquery' );
	wp_dequeue_script( 'jquery-migrate' );
}
add_action( 'wp_head', __NAMESPACE__ . '\\remove_embed' );

function ct_login_style() {
    wp_enqueue_style( 'ct-login', Assets\asset_uri('styles/login.css') );
}
add_action( 'login_enqueue_scripts', __NAMESPACE__ . '\\ct_login_style' );
