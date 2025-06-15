<?php

namespace Cubetech\Skye\Packages;

/**
 * Register image sizes and alert function
 *
 * @author  Christoph S. Ackermann <christoph.ackermann@cubetech.ch>
 * @version 1.0
 */
class Images {

	/**
	 * Hooks the removeMetaBox action.
	 *
	 * @return void
	 */
	public function __construct()
	{
		add_action( 'admin_notices', array( $this, 'check_images' ) );
		add_action( 'init', array( $this, 'images' ) );
	}

	public function images() {

		require_once( get_template_directory() . '/lib/config/image_sizes.php' );

		foreach( $rs_image_sizes as $key => $value ) {
			add_image_size( $key, $value['width'], $value['height'], $value['crop'] );
		}

	}

	public function check_images() {

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

			if( strpos( $img, 'rs-' ) !== false ):
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
				<p><strong>Warnung:</strong> Für diese WordPress-Installation wurden noch keine angepassten Rocketsky-Bildgrössen (rs- Prefix) registriert. Aktuell verfügbare Grössen: ' . $sizes . '</p>
			</div>';
		endif;

		echo '';

	}

}