<?php
	
	require_once( 'ACFAddKey.package.php' );
	require_once( 'AdminAddTaxToMenu.package.php' );
	require_once( 'AdminCss.package.php' );
	require_once( 'AdminMenuGroup.package.php' );
	require_once( 'ClassicEditor.package.php' );
	require_once( 'Dashboard.package.php' );
	require_once( 'Images.package.php' );
	require_once( 'Performance.package.php' );
	require_once( 'Register.package.php' );

	new Cubetech\Theme\Packages\ACFAddKey();
	//new Cubetech\Theme\Packages\AdminAddTaxToMenu();
	new Cubetech\Theme\Packages\AdminCss();
	//new Cubetech\Theme\Packages\AdminMenuGroup();
	new Cubetech\Theme\Packages\ClassicEditor();
	//new Cubetech\Theme\Packages\Dashboard();
	new Cubetech\Theme\Packages\Images();
	new Cubetech\Theme\Packages\Performance();
	new Cubetech\Theme\Packages\Register();
	