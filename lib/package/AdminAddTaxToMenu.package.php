<?php
namespace Cubetech\Theme\Packages;

/**
 * Creates some admin page for grouping CPT.
 *
 * @author Christoph S. Ackermann <christoph.ackermann@cubetech.ch>
 * @version 1.0
 */
class AdminAddTaxToMenu
{
	/**
	 * Hooks the init action if ACF is installed.
	 * Hooks admin_notices and displays warning if ACF is not installed.
	 *
	 * @return void
	 */
	public function __construct()
	{
		add_action( 'admin_menu', [$this, 'registerSubMenu'] );
	}

	public function registerSubMenu()
	{

		// ****** TYPE DEMO ******
		// add_submenu_page( 'edit.php?post_type=ct-demo', 'wp-menu-separator', '', 'read', '11' );
		// Category pages
		// add_submenu_page( 'edit.php?post_type=ct-demo', 'Demo', 'Demo', 'edit_posts', 'edit-tags.php?taxonomy=ct-demo' );

	}

}
