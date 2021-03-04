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

	new Cubetech\Skye\Packages\ACFAddKey();
	//new Cubetech\Skye\Packages\AdminAddTaxToMenu();
	new Cubetech\Skye\Packages\AdminCss();
	//new Cubetech\Skye\Packages\AdminMenuGroup();
	new Cubetech\Skye\Packages\ClassicEditor();
	//new Cubetech\Skye\Packages\Dashboard();
	new Cubetech\Skye\Packages\Images();
	new Cubetech\Skye\Packages\Performance();
	new Cubetech\Skye\Packages\Register();

	$cubetech_menu_cache = new Cubetech\Skye\Packages\MenuCache( false );
	add_action( 'wp_update_nav_menu', 'Cubetech\Skye\Packages\MenuCache::purge' );