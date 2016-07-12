<?php

	/**
	 * cubetech registered images checker
	 *
	 * Checks for available image sizes in WordPress. Error if there are only
	 * four sizes (default size count in WP) and additional warning if no sizes
	 * with ct- Prefix found.
	 *
	 * Registering image sizes in lib/cubetech/image-sizes.php
	 *
	 * @author		Christoph S. Ackermann <christoph.ackermann@cubetech.ch>
	 * @since		12.7.2016
	 * @return		string		HTML Markup with warnings
	 */

	function ct_check_images() {

		global $ini_array;

		$image_sizes = get_intermediate_image_sizes();
		$found_ct = false;
		$sizes = false;

		if( count( $image_sizes ) === 4 ):
			echo '
			<div class="notice notice-error">
				<p><strong>Fehler:</strong> Für diese WordPress-Installation wurden noch keine angepassten Bildgrössen registriert.</p>
			</div>';
		endif;

		foreach( $image_sizes as $img ):

			if( strpos( $img, 'ct-' ) !== false ):
				$found_ct = true;
			endif;

			if( $sizes === false ):
				$sizes = $img;
			else:
				$sizes = $sizes . ', ' . $img;
			endif;

		endforeach;

		if( $found_ct === false ):
			echo '
			<div class="notice notice-warning">
				<p><strong>Warnung:</strong> Für diese WordPress-Installation wurden noch keine angepassten cubetech-Bildgrössen (ct- Prefix) registriert. Aktuell verfügbare Grössen: ' . $sizes . '</p>
			</div>';
		endif;

		echo '';

	}
	add_action('admin_notices', 'ct_check_images');