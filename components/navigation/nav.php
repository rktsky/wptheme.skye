<?php

	global $postid, $polylang;

	$location = 'primary_navigation';

	new Cubetech\Skye\Packages\MenuCache( array(
	    'theme_location'    => $location,
	    'depth'             => -1,
	    'menu_class'        => 'nav',
	    'container'			=> false,
	    'fallback_cb'       => 'WP_Bootstrap_Navwalker::fallback',
	    'walker'            => new WP_Bootstrap\Navwalker\Walker(),
	) );

?>