<?php

namespace Cubetech\Theme\Packages;

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
		add_filter( 'xmlrpc_enabled', '__return_false' );

		if(!is_admin()){
			remove_action('wp_head', 'wp_print_scripts');
			remove_action('wp_head', 'wp_print_head_scripts', 9);
			remove_action('wp_head', 'wp_enqueue_scripts', 1);
			
			add_action('wp_footer', 'wp_print_scripts', 5);
			add_action('wp_footer', 'wp_enqueue_scripts', 5);
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

}
