<?php

namespace Cubetech\Skye\Packages;

/**
 * AJAX API
 *
 * @author  Christoph S. Ackermann <christoph.ackermann@cubetech.ch>
 * @version 1.0
 */
class MenuCache {
	
	private $args;
	private $caching_key;
	private $menu_location;
	private $expired;
	private $debug = false;
	public $data;

	public function __construct ( $args = array() ) {

		if( $args !== false ) {
			$this->init( $args );
		}

	}

	private function init( $args = array() ) {

		$defaults = array( 'menu' => '', 'container' => 'div', 'container_class' => '', 'container_id' => '', 'menu_class' => 'menu', 'menu_id' => '',
		'echo' => false, 'fallback_cb' => 'wp_page_menu', 'before' => '', 'after' => '', 'link_before' => '', 'link_after' => '', 'items_wrap' => '<ul id="%1$s" class="%2$s">%3$s</ul>',
		'depth' => 0, 'walker' => '', 'theme_location' => '', 'active' => true );

		$this->args = wp_parse_args( $args, $defaults );

		if( has_nav_menu( $this->args['theme_location'] ) ) {

			$this->menu_location = $this->args['theme_location'];
			$this->set_caching_key();
			$this->delete();

			$this->expired = 0;
			$this->get();

			if(  $this->data === false || empty($this->data) ) {

				$this->data = wp_nav_menu( $this->args );

				if( !isset( $debug ) || $debug == false ) {

					$this->set();

				}

			}

			echo $this->data;

		} else {

			if(WP_DEBUG === true) {

				$error = new \WP_Error( 'broke', '<i>Debug: No menu set for theme location "' . $this->args['theme_location'] . '" or no theme_location set.</i>' );
				echo $error->get_error_message();

			} else {

				return false;

			}

		}

	}

	private function set_caching_key() {

		global $sitepress;

		$this->caching_key = 'cubetech-menu-cache-' . $this->menu_location;
		if( isset($sitepress) && $sitepress !== NULL ):
			$this->caching_key .= '-' . $sitepress->get_current_language();
		elseif( !empty( $polylang->curlang->slug ) ):
			global $polylang;
			$this->caching_key .= '-' . $polylang->curlang->slug;
		endif;

	}

	private function set() {

		set_transient( $this->caching_key, $this->data, $this->expired );
		$this->active();

	}

	private function get() {

		$this->data = get_transient( $this->caching_key );
		$this->active();
		return $this->data;

	}

	private function get_menu( $args ) {

		return wp_nav_menu( $args );

	}

	private function active() {

		if( $this->args['active'] === true && $this->data !== false ) {

			global $post;

			if( empty( $post->ancestors ) ) :
				$section_ids = array( $post->ID );
			else :
				$section_ids = $post->ancestors;
				array_push( $section_ids, $post->ID );
			endif;

			$this->data = str_replace('class="active ', 'class="', $this->data);

			if( isset( $section_id ) && $section_id !== NULL ) :

				foreach( $section_ids as $sid ) :

					$slug = basename( get_permalink( $sid ) );
					$this->data = str_replace('menu-' . $slug, 'active menu-' . $slug, $this->data);

				endforeach;

			else:

				return;

			endif;

		}

	}

	private function delete() {

		if( isset( $_GET['ctmenu'] ) && $_GET['ctmenu'] == 'delete' ) {

			delete_transient( $this->caching_key );
			$this->data = '';

		}

	}

	static function purge() {

		global $sitepress, $polylang;
		$menus = get_registered_nav_menus();

		foreach ( $menus as $location => $description ) {

			if( isset( $sitepress ) && $sitepress !== NULL && function_exists( 'icl_get_languages' ) ) {
				
				$languages = icl_get_languages();
				
				foreach( $languages as $lang => $value ) {
					
					delete_transient( 'cubetech-menu-cache-' . $location . '-' . $lang );
					
				}
				
			} elseif( isset( $polylang ) && $polylang !== NULL && function_exists( 'pll_the_languages' ) ) {

				$languages = get_terms( 'language', array( 'hide_empty' => false, 'orderby' => 'term_group' ) );
				foreach( $languages as $lang => $value ) {
					
					delete_transient( 'cubetech-menu-cache-' . $location . '-' . $value->slug );
					
				}					
				
			} else {
				
				delete_transient( 'cubetech-menu-cache-' . $location );
				
			}

		}
		
	}
	
}