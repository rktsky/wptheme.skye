<?php

namespace Cubetech\Skye\Packages;

/**
 * Templates Engine
 *
 * @author  Christoph S. Ackermann <christoph.ackermann@cubetech.ch>
 * @version 1.0
 */
class Templates {

	private $base = 'views/';
	private $default = 'default';
	private $type = false;
	private $ID = 0;

	/**
	 * Hooks the removeMetaBox action.
	 *
	 * @return void
	 */
	public function __construct() {

		global $wp_query;

		$this->ID = get_queried_object_id();
		$this->type = get_post_type( $this->ID );

	}

	public function template() {

		global $wp_query;

		if( is_404() )
			return $this->get_404();

		if( is_archive() )
			return $this->get_archive();

		if( is_front_page() )
			return $this->get_page();

		if( is_home() )
			return $this->get_archive();

		if( is_page() )
			return $this->get_page();

		if( is_search() )
			return $this->get_search();

		if( is_single() )
			return $this->get_single();

		return $this->get_default();

	}

	public function get_archive_site( $posttype ) {

		$return = false;

		$ct_archive = get_posts( [
			'post_type' => 'ct-archive',
			'meta_query' => [
				[
					'key' => 'archive_posttype',
					'value' => $posttype,
				],
			],
		] );

		if( !empty( $ct_archive ) ) {
			$return = $ct_archive[0];
		}

		return $return;

	}

	private function locate( $name = 'default' ) {

		return locate_template( $name . '.php' );

	}

	private function get_404( $name = '404' ) {

		return $this->find( $name, '404' );

	}

	private function get_archive( $name = 'archive' ) {

		$archive = $this->get_archive_site( $this->type );

		if( !empty( $archive->ID ) ) {

			global $post;
			$post = $archive;

			if( !empty( get_page_template_slug( $archive->ID ) ) )
				return $this->find( get_page_template_slug( $archive->ID ), 'page' );

		}

		if( $this->type !== 'post' && $this->type !== 'page' ) {
			$name = $this->type . '/' . $name;
		}

		return $this->find( $name, 'archive' );

	}

	private function get_default() {

		return $this->find( 'default' );

	}

	private function get_page( $name = 'page' ) {

		$path = pathinfo( get_page_template_slug( $this->ID ) );
		$template = '';

		if( !empty( $path[ 'dirname' ] ) )
			$template .= $path[ 'dirname' ] . '/';

		if( !empty( $path[ 'filename' ] ) )
			$template .= $path[ 'filename' ];

		if( !empty( $template ) )
			$name = $template;

		return $this->find( $name, 'page' );

	}

	private function get_single( $name = 'single' ) {

		if( $this->type !== 'post' && $this->type !== 'page' ) {
			$name = $this->type . '/' . $name;
		}

		return $this->find( $name, 'single' );

	}

	private function get_search( $name = 'search' ) {

		return $this->find( $name, 'search' );

	}

	private function find( $name, $fallback = false ) {

		$name = str_replace( '.php', '', $name );

		// Try to get our views template file
		$locate = $this->locate( $this->base . $name );

		// Try to get basic template if the view doesn't exists
		if( empty( $locate ) )
			$locate = $this->locate( $name );

		// Try to fall back to WordPress default if another name is given
		if( empty( $locate ) && $fallback !== false  )
			$locate = $this->locate( $fallback );

		// Try to fall back to views
		if( empty( $locate ) && $fallback !== false )
			$locate = $this->locate( $this->base . $fallback );

		// Try to fall back to views default
		if( empty( $locate ) )
			$locate = $this->locate( $this->base . $this->default );

		return $locate;

	}

}