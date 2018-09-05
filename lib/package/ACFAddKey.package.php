<?php

namespace Cubetech\Theme\Packages;

/**
 * Activate ACF License Key if there is none (so even after search-replace it reactivates itself)
 * Snippet found on GitHub Gist, thanks @teolaz !
 *
 * @author  Christoph S. Ackermann <christoph.ackermann@cubetech.ch>
 * @version 1.0
 */
class ACFAddKey {
		
	const ACTIVATION_KEY = '';
	
	/**
	 * AutoActivator constructor.
	 * This will update the license field option on acf
	 * Works only on backend to not attack performance on frontend
	 */
	public function __construct() {
		if (
			function_exists( 'acf' ) &&
		    is_admin() &&
			!acf_pro_get_license_key()
		) {
			acf_pro_update_license(self::ACTIVATION_KEY);
		}
	}
	
}
