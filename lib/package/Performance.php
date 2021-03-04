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
		add_filter( 'override_load_textdomain',array( $this, 'a_faster_load_textdomain'), 1, 3 );

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

	public function a_faster_load_textdomain( $retval, $domain, $mofile ) {

		global $l10n;

		if( !is_readable( $mofile ) ) return false;

		$data = get_transient( md5( $mofile ) );
		$mtime = filemtime( $mofile );

		$mo = new \MO();
		if(!$data || !isset( $data[ 'mtime' ] ) || $mtime > $data[ 'mtime' ]) {
			if ( !$mo->import_from_file( $mofile ) ) return false;
			$data = array(
				'mtime' => $mtime,
				'entries' => $mo->entries,
				'headers' => $mo->headers
			);
			set_transient( md5( $mofile ), $data );
		} else {
			$mo->entries = $data[ 'entries' ];
			$mo->headers = $data[ 'headers' ];
		}

		if( isset( $l10n[ $domain ] ) ) {
			$mo->merge_with( $l10n[ $domain ] );
		}

		$l10n[ $domain ] = &$mo;

		return true;

	}

}
