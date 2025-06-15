<?php

namespace Rocketsky\Skye\Packages;

/**
 * NotFound Class
 *
 * @author  Christoph Ackermann <acki@rocketsky.ch>
 * @version 1.0
 */
class NotFound {

	private $similar = [];

	/**
	 *
	 * @return void
	 */
	public function __construct() {

		return;

	}

	public function get_similar_posts( $with = true ) {

		$this->similar_posts();

		if( empty( $this->similar ) )
			return false;

		if( $with === false )
			return $this->similar;

		$return = '<ul class="similar">';

		foreach( $this->similar as $s ) {

			$return .= '<li><a href="' . get_permalink( $s->ID ) . '">';
			$return .= $s->post_title;
			$return .= '</a></li>';

		}

		$return .= '</ul>';

		return $return;

	}

	private function similar_posts() {

		$path = parse_url( $_SERVER[ 'REQUEST_URI' ], PHP_URL_PATH );
		$path = explode( '/', $path );

		$this->similar = [];

		foreach( $path as $p ) {

			if( !empty( $p ) && count( $this->similar ) <= 5 ) {

				$args = [
					's' => $p,
				    'numberposts' => 10,
				    'post_types'  => [ 'post', 'page', 'ct-team', 'ct-success', 'ct-jobs' ],
				];
				$query = new \WP_Query();
				$query->query( $args );

				foreach( $query->posts as $q )
					$this->similar[ $q->ID ] = $q;

				if( empty( $similar ) || count( $this->similar ) < 5 ) {
			
					$split = preg_split('(-|_|%20)', $p);
		
					foreach( $split as $s ) {

						$args = [
							's' => $s,
						    'numberposts' => 10,
						    'post_types'  => [ 'post', 'page', 'ct-team', 'ct-success', 'ct-jobs' ],
						];
						$innerquery = new \WP_Query();
						$innerquery->query( $args );

						foreach( $innerquery->posts as $q )
							$this->similar[ $q->ID ] = $q;

					}

				}

			}

		}

		if( empty( $this->similar ) ) {

			$pageid = get_option( 'page_on_front' );
			if( empty( $pageid ) )
				$pageid = get_option( 'page_for_posts' );

			if( !empty( $pageid ) ) {
				$fallback = get_post( $pageid );
				$this->similar[ $fallback->ID ] = $fallback;
			}	

		}

	}

}