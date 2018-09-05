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
