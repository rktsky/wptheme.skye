<?php

/**
 * Class Name: wp_bootstrap_navwalker
 * GitHub URI: https://github.com/twittem/wp-bootstrap-navwalker
 * Description: A custom WordPress nav walker class to implement the Bootstrap 3 navigation style in a custom theme using the WordPress built in menu manager.
 * Version: 2.0.4
 * Author: Edward McIntyre - @twittem
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

final class wp_bootstrap_navwalker extends Walker_Nav_Menu {

	/**
	 * @var
	 */
	private $activeMenuItem;

	private $ancestors;

	/**
	 * @see Walker::start_lvl()
	 * @since 3.0.0
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param int $depth Depth of page. Used for padding.
	 */
	public function start_lvl( &$output, $depth = 0, $args = array() ) {
		$indent = str_repeat( "\t", $depth );
		$output .= "\n$indent<ul role=\"menu\" class=\" dropdown-menu\">\n";
	}

	/**
	 * @see Walker::start_el()
	 * @since 3.0.0
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param object $item Menu item data object.
	 * @param int $depth Depth of menu item. Used for padding.
	 * @param int $current_page Menu item ID.
	 * @param object $args
	 */
	public function start_el( &$output, $item, $depth = 0, $args = array(), $id = 0 ) {
		$indent = ( $depth ) ? str_repeat( "\t", $depth ) : '';

		/**
		 * Dividers, Headers or Disabled
		 * =============================
		 * Determine whether the item is a Divider, Header, Disabled or regular
		 * menu item. To prevent errors we use the strcasecmp() function to so a
		 * comparison that is not case sensitive. The strcasecmp() function returns
		 * a 0 if the strings are equal.
		 */
		if ( strcasecmp( $item->attr_title, 'divider' ) == 0 && $depth === 1 ) {
			$output .= $indent . '<li role="presentation" class="divider">';
		} else if ( strcasecmp( $item->title, 'divider') == 0 && $depth === 1 ) {
			$output .= $indent . '<li role="presentation" class="divider">';
		} else if ( strcasecmp( $item->attr_title, 'dropdown-header') == 0 && $depth === 1 ) {
			$output .= $indent . '<li role="presentation" class="dropdown-header">' . esc_attr( $item->title );
		} else if ( strcasecmp($item->attr_title, 'disabled' ) == 0 ) {
			$output .= $indent . '<li role="presentation" class="disabled"><a href="#">' . esc_attr( $item->title ) . '</a>';
		} else {

			$class_names = $value = '';

			$classes = empty( $item->classes ) ? array() : (array) $item->classes;
			$classes[] = 'menu-item-' . $item->ID;

			$class_names = join( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args ) );

			if ( $args->has_children && $args->depth === 0 )
				$class_names .= ' dropdown';

			if ( in_array( 'current-menu-item', $classes ) )
				$class_names .= ' active';

			$class_names = $class_names ? ' class="' . esc_attr( $class_names ) . '"' : '';

			$id = apply_filters( 'nav_menu_item_id', 'menu-item-'. $item->ID, $item, $args );
			$id = $id ? ' id="' . esc_attr( $id ) . '"' : '';

			$output .= $indent . '<li' . $id . $value . $class_names .'>';

			$atts = array();
			$atts['title']  = ! empty( $item->title )	? $item->title	: '';
			$atts['target'] = ! empty( $item->target )	? $item->target	: '';
			$atts['rel']    = ! empty( $item->xfn )		? $item->xfn	: '';

			// If item has_children add atts to a.
			if ( $args->has_children && $depth === 0 && $args->depth === 0 ) {
				$atts['href']   		= '#';
				$atts['data-toggle']	= 'dropdown';
				$atts['class']			= 'dropdown-toggle';
				$atts['aria-haspopup']	= 'true';
			} else {
				$atts['href'] = ! empty( $item->url ) ? $item->url : '';
			}

			$atts = apply_filters( 'nav_menu_link_attributes', $atts, $item, $args );

			$attributes = '';
			foreach ( $atts as $attr => $value ) {
				if ( ! empty( $value ) ) {
					$value = ( 'href' === $attr ) ? esc_url( $value ) : esc_attr( $value );
					$attributes .= ' ' . $attr . '="' . $value . '"';
				}
			}

			$item_output = $args->before;

			/*
			 * Glyphicons
			 * ===========
			 * Since the the menu item is NOT a Divider or Header we check the see
			 * if there is a value in the attr_title property. If the attr_title
			 * property is NOT null we apply it as the class name for the glyphicon.
			 */
			if ( ! empty( $item->attr_title ) )
				$item_output .= '<a'. $attributes .'><span class="glyphicon ' . esc_attr( $item->attr_title ) . '"></span>&nbsp;';
			else
				$item_output .= '<a'. $attributes .'>';

			$item_output .= $args->link_before . apply_filters( 'the_title', $item->title, $item->ID ) . $args->link_after;
			$item_output .= ( $args->has_children && 0 === $depth && $args->depth === 0 ) ? ' <span class="caret"></span></a>' : '</a>';
			$item_output .= $args->after;

			$output .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );
		}
	}

	/**
	 * Traverse elements to create list from elements.
	 *
	 * Display one element if the element doesn't have any children otherwise,
	 * display the element and its children. Will only traverse up to the max
	 * depth and no ignore elements under that depth.
	 *
	 * This method shouldn't be called directly, use the walk() method instead.
	 *
	 * @see Walker::start_el()
	 * @since 2.5.0
	 *
	 * @param object $element Data object
	 * @param array $children_elements List of elements to continue traversing.
	 * @param int $max_depth Max depth to traverse.
	 * @param int $depth Depth of current element.
	 * @param array $args
	 * @param string $output Passed by reference. Used to append additional content.
	 * @return null Null on failure with no changes to parameters.
	 */
	public function display_element( $element, &$children_elements, $max_depth, $depth, $args, &$output ) {
		if ( ! $element )
			return;

		$id_field = $this->db_fields['id'];

		// Display this element.
		if ( is_object( $args[0] ) )
			$args[0]->has_children = ! empty( $children_elements[ $element->$id_field ] );

		parent::display_element( $element, $children_elements, $max_depth, $depth, $args, $output );
	}

	/**
	 * Menu Fallback
	 * =============
	 * If this function is assigned to the wp_nav_menu's fallback_cb variable
	 * and a manu has not been assigned to the theme location in the WordPress
	 * menu manager the function with display nothing to a non-logged in user,
	 * and will add a link to the WordPress menu manager if logged in as an admin.
	 *
	 * @param array $args passed from the wp_nav_menu function.
	 *
	 */
	public static function fallback( $args ) {
		if ( current_user_can( 'manage_options' ) ) {

			extract( $args );

			$fb_output = null;

			if ( $container ) {
				$fb_output = '<' . $container;

				if ( $container_id )
					$fb_output .= ' id="' . $container_id . '"';

				if ( $container_class )
					$fb_output .= ' class="' . $container_class . '"';

				$fb_output .= '>';
			}

			$fb_output .= '<ul';

			if ( $menu_id )
				$fb_output .= ' id="' . $menu_id . '"';

			if ( $menu_class )
				$fb_output .= ' class="' . $menu_class . '"';

			$fb_output .= '>';
			$fb_output .= '<li><a href="' . admin_url( 'nav-menus.php' ) . '">Add a menu</a></li>';
			$fb_output .= '</ul>';

			if ( $container )
				$fb_output .= '</' . $container . '>';

			echo $fb_output;
		}
	}


	public function walk( $elements, $max_depth ) {
		// Get active menu element
		$activeElement = array_filter( $elements, function( $ele ) {
			return $ele->current;
		});

		$current_menu_item_top_ancestor = array_filter( $elements, function( $ele ) {
			return $ele->current_item_ancestor && $ele->menu_item_parent == 0;
		});


		$ancestors = array_filter( $elements, function( $ele ) {
			return $ele->current_item_parent;
		});

		if( !empty( $activeElement ) ) {
			$this->activeMenuItem = reset( $activeElement );
		}

		if( !empty( $ancestors ) ) {
			$this->ancestors = array_values( $ancestors );
		}

		if( !empty( $ancestors ) ) {
			$this->current_menu_item_top_ancestor = array_values( $current_menu_item_top_ancestor );
		}

		$args = array_slice(func_get_args(), 2);

		$output = '';

		//invalid parameter or nothing to walk
		if ( $max_depth < -1 || empty( $elements ) ) {
			return $output;
		}

		$parent_field = $this->db_fields['parent'];

		// flat display
		if ( -1 == $max_depth ) {
			$empty_array = array();
			foreach ( $elements as $e )
				$this->display_element( $e, $empty_array, 1, 0, $args, $output );
			return $output;
		}

		/*
		 * Need to display in hierarchical order.
		 * Separate elements into two buckets: top level and children elements.
		 * Children_elements is two dimensional array, eg.
		 * Children_elements[10][] contains all sub-elements whose parent is 10.
		 */
		$top_level_elements = array();
		$children_elements  = array();

		$has_active_post_children = false;
		$post_children_top_level = false;
		$post_children_higher_level = false;
		// fill news post item with posts, set them active so navigation is correct
		foreach ( $elements as &$e ) {

			if( in_array( 'post-overview', $e->classes ) ) {

				$posts_menu_item_id = $e->db_id;

				$children_elements[ $e->db_id ] = $this->fill_with_posts( $e->db_id );

				$e = $this->set_active_state_of_filled_posts( $e, $children_elements[ $e->db_id ] );


				if( $e->current_item_ancestor === true ) {

					$e->classes[] = 'current-menu-item';
					$e->classes[] = 'current_page_item';
					$e->classes[] = 'page_item';

					$post_children_higher_level = (int) $e->menu_item_parent;
					$has_active_post_children = true;

				}
			}

		}
		// make sure the reference is not used in other foreach loops
		unset($e);

		// set higher menu item active (not the highest level)

		foreach ( $elements as &$e ){

			if( $post_children_higher_level && $e->db_id === $post_children_higher_level && $e->menu_item_parent != 0){
				$e = $this->set_active_state_of_filled_posts( $e, $children_elements[ $posts_menu_item_id ] );

				$post_children_top_level = (int) $e->menu_item_parent;
			}


		}
		// make sure the reference is not used in other foreach loops
		unset($e);

		foreach ( $elements as $e) {
			if ( empty( $e->$parent_field ) )
				$top_level_elements[] = $e;
			else
				$children_elements[ $e->$parent_field ][] = $e;

			// assign correct classes if we are on a posts menu item
			if( $post_children_top_level && ( $e->db_id === (int) $post_children_top_level ) && $has_active_post_children == true )	{
				$e->current_item_ancestor = true;
				$e->classes[] = 'current-page-ancestor';
				$e->classes[] = 'current_page_ancestor';
				$e->classes[] = 'menu-item-has-children';
				$e->classes[] = 'current-menu-ancestor';

				$this->current_menu_item_top_ancestor = array($e);
				$this->activeMenuItem = $e;

			}

		}

		/*
		 * When none of the elements is top level.
		 * Assume the first one must be root of the sub elements.
		 */
		if ( empty($top_level_elements) ) {

			$first = array_slice( $elements, 0, 1 );
			$root = $first[0];

			$top_level_elements = array();
			$children_elements  = array();
			foreach ( $elements as $e) {
				if ( $root->$parent_field == $e->$parent_field )
					$top_level_elements[] = $e;
				else
					$children_elements[ $e->$parent_field ][] = $e;
			}
		}

		if( !isset( $args[0]->level ) || $args[0]->level === 0 ) {
			foreach ( $top_level_elements as $e ) {
				$this->display_element( $e, $children_elements, $max_depth, 0, $args, $output );
			}
		}

		// check if second level navigation case
		if( isset( $args[0]->level ) && $args[0]->level === 2 ) {
			// check if active menu item parent is zero (top level) and check if there are child elements
			if( isset($this->activeMenuItem) && $this->activeMenuItem->menu_item_parent == 0 ) {
				if( isset( $children_elements[ $this->activeMenuItem->db_id ] ) ) {
					$children_elements = array( $children_elements[ $this->activeMenuItem->db_id ] );
				} else {
					$children_elements = array();
				}
			}
			else {

				if( isset($this->current_menu_item_top_ancestor) && isset( $children_elements[ $this->current_menu_item_top_ancestor[0]->db_id ] ) ) {
					$children_elements = array( $children_elements[ $this->current_menu_item_top_ancestor[0]->db_id ] );
				} else {
					$children_elements = array();
				}
			}

			// check if other level navigation case
		} elseif( isset( $args[0]->level ) && $args[0]->level !== 0  ) {
			if( !empty( $this->current_menu_item_top_ancestor ) ) {
				$children_elements = $this->find_children_from_parent( $children_elements, $this->current_menu_item_top_ancestor[0]->db_id, $args, 1 );
			}
			else {
				$children_elements = array();
			}
		}

		/*
		 * If we are displaying all levels, and remaining children_elements is not empty,
		 * then we got orphans, which should be displayed regardless.
		 */
		if ( ( $max_depth == 0 ) && count( $children_elements ) > 0 ) {
			$empty_array = array();
			foreach ( $children_elements as $orphans ) {
				if( !empty( $orphans ) ) {
					foreach ( $orphans as $op ) {
						$this->display_element( $op, $empty_array, 1, 0, $args, $output );
					}
				}
			}
		}

		return $output;
	}


	/**
	 * This method checks all children element and gets the actual one
	 *
	 * @param $children_elements
	 * @param $parent_id
	 * @param $args
	 * @param $current_recursion
	 *
	 * @return array
	 */
	public function find_children_from_parent( $children_elements, $parent_id, $args, $current_recursion ) {
		$current_children = $children_elements[ $parent_id ];

		// loop actual child elements
		foreach( $current_children as $child ) {

			// check if child element is ancestor
			if( $child->current_item_parent || $child->current_item_ancestor ) {
				// if level reached return child elements, if not recall function and check next level
				if( $current_recursion === $args[0]->level ) {
					return array( $children_elements[$child->db_id] );
				} else {
					$current_recursion++;
					return $this->find_children_from_parent( $children_elements, $child->db_id, $args, $current_recursion );
				}

			}
			elseif( $child->current ) { // check if child element is current menu point
				// check if child elements exist
				if( isset( $children_elements[$child->db_id] ) ) {
					return array( $children_elements[$child->db_id] );
				} elseif( isset( $children_elements[$parent_id] ) ) {
					return array( $children_elements[$parent_id] );
				} else {
					return array();
				}
			}

		}

	}

	public function fill_with_posts( $parent_id ) {

		global $post;

		$posts = get_posts( array(
			'post_type' => 'post',
			'post_status' => 'publish',
			'posts_per_page' => -1
		) );

		foreach( $posts as &$single_post ) {

			// add needed classes
			$single_post->classes = array();

			$single_post->classes[] = '';
			$single_post->classes[] = 'menu-item';
			$single_post->classes[] = 'menu-item-type-post_type';
			$single_post->classes[] = 'menu-item-object-page';

			$single_post->menu_item_parent = $parent_id;

			if( $post->ID === $single_post->ID ) {

				$single_post->current = true;

			} else {
				$single_post->current = false;
			}


		}

		return $posts;

	}

	public function set_active_state_of_filled_posts( $element, $children_elements ) {

		global $post;


		if( !empty( $children_elements ) ) {

			$element->current_item_ancestor = false;
			$element->current_item_parent = false;

			foreach( $children_elements as $children_element ) {

				if( $post->ID === $children_element->ID ) {

					$element->current_item_ancestor = true;
					$element->current_item_parent = true;

					if( !is_array( $element->classes ) )
						$element->classes = array();


					$element->classes[] = 'current-menu-ancestor';
					$element->classes[] = 'current-page-ancestor';
					$element->classes[] = 'current_page_ancestor';
					$element->classes[] = 'menu-item-has-children';

				}

			}
		}

		return $element;

	}
}