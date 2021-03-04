<?php
namespace Cubetech\Skye\Packages;

/**
 * Creates some admin page for grouping CPT.
 *
 * @author Christoph S. Ackermann <christoph.ackermann@cubetech.ch>
 * @version 1.0
 */
class AdminCss
{
	/**
	 * Hooks the init action if ACF is installed.
	 * Hooks admin_notices and displays warning if ACF is not installed.
	 *
	 * @return void
	 */
	public function __construct()
	{
		add_action( 'admin_enqueue_scripts', [$this, 'adminThemeStyle'] );
	}

	public function adminThemeStyle()
	{
		// cubetech related menu page
		if( file_exists ( get_template_directory() . '/assets/styles/admin.css' ) ) {
			wp_enqueue_style('ct-admin-style', get_template_directory_uri() . '/assets/styles/admin.css');
		}
	}

}
