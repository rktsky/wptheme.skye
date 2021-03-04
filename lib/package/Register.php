<?php

namespace Cubetech\Skye\Packages;

/**
 * Register Post Types and Taxonomies
 *
 * @author  Christoph S. Ackermann <christoph.ackermann@cubetech.ch>
 * @version 1.0
 */
class Register {

	/**
	 * Hooks the removeMetaBox action.
	 *
	 * @return void
	 */

	public $post_types_url = '/lib/config/post_types.php';
	public $taxonomies_url = '/lib/config/taxonomies.php';

	public function __construct() {

		add_action('init', array($this, 'post_types'), 0);
		add_action('init', array($this, 'taxonomies'));

		$this->post_types_url = get_template_directory() . $this->post_types_url;
		$this->taxonomies_url = get_template_directory() . $this->taxonomies_url;

  }

	public function post_types() {

		if( file_exists( $this->post_types_url ) ) {

			require_once( $this->post_types_url );
	
			foreach( $ct_post_types as $key => $value ) {
				register_post_type( $key, $value['args'] );
			}

		}

	}

	public function taxonomies()	{
	
		if( file_exists( $this->taxonomies_url ) ) {

			require_once( $this->taxonomies_url );
			
			foreach( $ct_taxonomies as $key => $value ) {
				register_taxonomy( $key, $value['post_types'], $value['args'] );
			}

		}
	
	}

}
