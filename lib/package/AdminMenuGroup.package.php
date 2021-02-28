<?php
namespace Cubetech\Skye\Packages;

/**
 * Creates some admin page for grouping CPT.
 *
 * @author Christoph S. Ackermann <christoph.ackermann@cubetech.ch>
 * @version 1.0
 */
class AdminMenuGroup
{
	/**
	 * Hooks the init action if ACF is installed.
	 * Hooks admin_notices and displays warning if ACF is not installed.
	 *
	 * @return void
	 */
	public function __construct()
	{
		add_action( 'admin_menu', [$this, 'createMenuPage'] );
	}

	public function createMenuPage()
	{
		// cubetech related menu page
/*
		add_menu_page(
			'cubetech Demo',
			'» cubetech Demo',
			'edit_posts',
			'edit.php?post_type=ct-demo',
			'',
			'dashicons-megaphone',
			30
		);
*/
		
	}

}
