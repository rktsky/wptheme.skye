<?php

	global $postid, $polylang;

	$location = 'primary_navigation';

/*
	if( !empty( $postid ) && function_exists( 'pll_get_post_language' ) ) {

		$language = PLL()->model->get_language( pll_get_post_language( $postid ) );

		if( !empty( $language ) ) {
			//$location = PLL()->nav_menu->combine_location( $location, $language );
		}

	}
*/

	new Cubetech\Theme\Packages\MenuCache( array(
	    'theme_location'    => $location,
	    'depth'             => -1,
	    'menu_class'        => 'nav',
	    'container'			=> false,
	    'fallback_cb'       => 'WP_Bootstrap_Navwalker::fallback',
	    'walker'            => new WP_Bootstrap_Navwalker(),
	) );

?>