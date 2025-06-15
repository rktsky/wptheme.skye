<?php
	
	require_once( 'ACFAddKey.php' );
	require_once( 'AdminAddTaxToMenu.php' );
	require_once( 'AdminCss.php' );
	require_once( 'AdminMenuGroup.php' );
	require_once( 'ClassicEditor.php' );
	require_once( 'Dashboard.php' );
	require_once( 'Images.php' );
	require_once( 'MenuCache.php' );
	require_once( 'NotFound.php' );
	require_once( 'Performance.php' );
	require_once( 'Register.php' );
	require_once( 'Templates.php' );

	new Rocketsky\Skye\Packages\ACFAddKey();
	//new Rocketsky\Skye\Packages\AdminAddTaxToMenu();
	new Rocketsky\Skye\Packages\AdminCss();
	//new Rocketsky\Skye\Packages\AdminMenuGroup();
	new Rocketsky\Skye\Packages\ClassicEditor();
	//new Rocketsky\Skye\Packages\Dashboard();
	new Rocketsky\Skye\Packages\Images();
	new Rocketsky\Skye\Packages\Performance();
	new Rocketsky\Skye\Packages\Register();

	$rocketsky_menu_cache = new Rocketsky\Skye\Packages\MenuCache( false );
	add_action( 'wp_update_nav_menu', 'Rocketsky\Skye\Packages\MenuCache::purge' );