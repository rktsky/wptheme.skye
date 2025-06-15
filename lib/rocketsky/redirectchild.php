<?php
// If the page template template-redirectchild.php is selected in the backend, 
// redirect to the first child page (in the menu, or in the page structure).

add_action('wp', function() {
	if(is_page_template('template-redirectchild.php')) {
		global $post;
		// searches for the last "active" and gets the first link in its sub-menu (greedy-modifier is VERY important here)
		$linkregex = '/current-menu-item(?!.*current-menu-item).*sub-menu.*href="(.*)"/Us';
		
		$args = array(
			'post_parent' => $post->ID,
			'post_type'   => 'page', 
			'posts_per_page' => 1,
			'order' => 'ASC',
			'orderby' => 'menu_order',
			'post_status' => 'publish'
		);
		
		$children_array = get_children( $args );
		$children_array = current($children_array);

		if(!empty($children_array)) {
			$defaults = array(
				'theme_location'  => 'primary',
				'menu'            => '',
				'container'       => 'div',
				'container_class' => '',
				'container_id'    => '',
				'menu_class'      => 'menu',
				'menu_id'         => '',
				'echo'            => false,
				'fallback_cb'     => 'wp_page_menu',
				'before'          => '',
				'after'           => '',
				'link_before'     => '',
				'link_after'      => '',
				'items_wrap'      => '<ul id="%1$s" class="%2$s">%3$s</ul>',
				'depth'           => 0,
				'walker'          => ''
			);
			
			$t = wp_nav_menu( $defaults );
			$k = preg_match_all($linkregex, $t, $matches);
			$match = end($matches);
			$link = end($match);

			if($link) {
				wp_redirect($link, 301 );
			} else {
				$defaults = array(
					'theme_location'  => 'top_navigation',
					'menu'            => '',
					'container'       => 'div',
					'container_class' => '',
					'container_id'    => '',
					'menu_class'      => 'menu',
					'menu_id'         => '',
					'echo'            => false,
					'fallback_cb'     => 'wp_page_menu',
					'before'          => '',
					'after'           => '',
					'link_before'     => '',
					'link_after'      => '',
					'items_wrap'      => '<ul id="%1$s" class="%2$s">%3$s</ul>',
					'depth'           => 0,
					'walker'          => ''
				);
				
				$t = wp_nav_menu( $defaults );
				$k = preg_match_all($linkregex, $t, $matches);
		
				$match = end($matches);
				$link = end($match);
	
				if($link) {
					wp_redirect($link, 301 );
				}
			}
		}
	}
});
