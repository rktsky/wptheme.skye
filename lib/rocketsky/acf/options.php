<?php

/**
 * Register options page.
 */
function register_options() {

	// Theme options
	$page = array(
		'page_title' => 'cubetech<br>Einstellungen',
		'menu_title' => '',
		'menu_slug' => 'theme-options-parent',
		'capability' => 'edit_posts',
		'position' => false,
		'parent_slug' => '',
		'icon_url' => false,
		'redirect' => true,
		'post_id' => 'theme_options',
		'autoload' => false,
	);

	acf_add_options_page( $page );

	// Theme options
	$page = array(
		'page_title' => 'Grundeinstellungen',
		'menu_title' => '',
		'menu_slug' => 'theme-options',
		'capability' => 'edit_posts',
		'position' => false,
		'parent_slug' => 'theme-options-parent',
		'icon_url' => false,
		'redirect' => true,
		'post_id' => 'theme_options',
		'autoload' => false,
	);

	acf_add_options_page( $page );

	// Theme options
	$page = array(
		'page_title' => 'APIs',
		'menu_title' => '',
		'menu_slug' => 'theme-options-api',
		'capability' => 'edit_posts',
		'position' => false,
		'parent_slug' => 'theme-options-parent',
		'icon_url' => false,
		'redirect' => true,
		'post_id' => 'theme_options',
		'autoload' => false,
	);

	acf_add_options_page( $page );

}

// Set warning in backend if acf is not installed
function set_theme_options_warning() {

	echo '
	<div class="notice notice-error">
		<p><strong>' . __( 'Fehler', 'skye' ) . ':</strong> ' . __( 'Für das aktive WordPress-Theme wird das Plugin Advanced Custom Fields PRO benötigt.', 'skye' ) . '</p>
	</div>';

}

if( function_exists( 'acf_add_options_page' ) ) {
	add_action( 'init', 'register_options' );
} else {
	add_action( 'admin_notices', 'set_theme_options_warning' );
}

// set theme options for global access
function get_theme_options() {

	global $theme_options;

	$theme_options = array();
	$cache = get_transient( 'ct_theme_options' );

	if( !empty( $cache ) ) {

		$theme_options = $cache;

	} elseif( function_exists( 'get_field' ) ) {
		
		// define fields
		$fields = array(
			'theme_logo',
			'favicon',
			'gmakey',
		);
	
		// loop defined fields and set them in theme options array
		foreach( $fields as $field ) {
	
			$value = get_field( $field, 'theme_options' );
	
			if( $value !== NULL && $value !== false ){
				$theme_options[$field] = $value;
			} else {
				$theme_options[$field] = false;
			}
	
		}

		set_transient( 'ct_theme_options', $theme_options );

	}

	return $theme_options;

}

if( function_exists( 'get_field' ) && !is_admin() ) {
	add_action( 'init', 'get_theme_options' );
}

function unset_theme_options() {

	$screen = get_current_screen();

	if( is_int( strpos( $screen->id, 'theme-options' ) ) ) {
		$favicon_id = get_option( 'theme_options_favicon' );
		update_option( 'site_icon', $favicon_id );
		delete_transient( 'ct_theme_options' );
	}

}

add_action( 'acf/save_post', 'unset_theme_options', 20 );
