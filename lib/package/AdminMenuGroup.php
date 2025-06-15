<?php
namespace Rocketsky\Skye\Packages;

/**
 * Creates some admin page for grouping CPT.
 *
 * @author  Christoph Ackermann <acki@rocketsky.ch>
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
		// Rocketsky related menu page
/*
		add_menu_page(
			'Rocketsky Demo',
			'» Rocketsky Demo',
			'edit_posts',
			'edit.php?post_type=rs-demo',
			'',
			'dashicons-megaphone',
			30
		);
*/
		
	}

}
