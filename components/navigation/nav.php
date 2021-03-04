<?php

	global $postid, $polylang, $data;

	$location = 'primary_navigation';

	new Cubetech\Skye\Packages\MenuCache( array(
	    'theme_location'    => $location,
	    'depth'             => -1,
	    'menu_class'        => $data->ulclass,
	    'container'			=> false,
	    'fallback_cb'       => 'WP_Bootstrap_Navwalker::fallback',
	    'walker'            => new WP_Bootstrap\Navwalker\Walker(),
	) );

?>