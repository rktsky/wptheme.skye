<?php

namespace Cubetech\Skye\Packages;

/**
 * Performance class - some crazy shit :-)
 *
 * @author  Christoph S. Ackermann <christoph.ackermann@cubetech.ch>
 * @version 1.0
 */
class Performance {

	/**
	 * Hooks the removeMetaBox action.
	 *
	 * @return void
	 */
	public function __construct()
	{
		add_filter( 'option_active_plugins', array( $this, 'disable_acf' ), 1 );
		add_action( 'wp_before_admin_bar_render', array( $this, 'disable_customizer_link' ) ); 
		add_filter( 'xmlrpc_enabled', '__return_false' );
		remove_action( 'plugins_loaded', '_wp_customize_include', 10);
		remove_action( 'admin_enqueue_scripts', '_wp_customize_loader_settings', 11);
		add_filter( 'map_meta_cap', array( $this, 'filter_to_remove_customize_capability'), 10, 4 );

		if(!is_admin()){
			remove_action('wp_head', 'wp_print_scripts');
			remove_action('wp_head', 'wp_print_head_scripts', 9);
			
			add_action('wp_footer', 'wp_print_scripts', 5);
			add_action('wp_footer', 'wp_print_head_scripts', 5);
		}

	}

	public function disable_acf($plugins) {

		if( is_admin() )
			return $plugins;
		
		foreach( $plugins as $i => $plugin ) {
			if( $plugin == 'advanced-custom-fields-pro/acf.php' ) {
				unset( $plugins[$i] );
			}
		}
		
		return $plugins;

	}

	public function disable_customizer_link() {

		global $wp_admin_bar;		
		$wp_admin_bar->remove_menu('customize');

	}

	public function filter_to_remove_customize_capability( $caps = array(), $cap = '', $user_id = 0, $args = array() ) {
		
		if( $cap === 'customize' ) {
			return array('nope');
		}
		
		return $caps;

	}

}
