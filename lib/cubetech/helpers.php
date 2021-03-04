<?php


	function get_component( $name, $second = false ) {

		if( !empty( $second ) )
			$name = $name . '/' . $second;

		$template = locate_template( 'components/' . $name . '.php', false, false );

		if( ( !$template || empty( $template ) ) && empty( $second ) )
			$template = locate_template( 'components/' . $name . '/' . $name . '.php', false, false );

		if( !$template || empty( $template ) )
			echo '<!-- ERROR get_component(): Component ' . $name . ' not found! -->';
		else
			include( $template );

	}
	
	// get field function for ACF without using ACF and generating additional queries
	function get( $selector, $post_id = false, $format_value = true ) {

		if( function_exists( 'get_post_meta' ) && function_exists( 'acf_get_valid_post_id' ) ) {
			$result = get_post_meta( acf_get_valid_post_id( $post_id ), $selector, $format_value );
			if( empty( $result ) )
				$result = get_user_meta( acf_get_valid_post_id( $post_id ), $selector, $format_value );
			if( empty( $result ) )
				$result = get_option( $selector );
			return $result;
		} else {
			echo '<!-- ERROR get(): ACF not found. Please install. -->';
		}
	
	}	

	function get_image( $id, $size = 'full' ) {

		if( empty( (int) $id ) )
			return false;

		$cacheid = 'ct_image_cache_' . $id;
		$imageurl = get_transient( $cacheid );

		if( empty( $imageurl ) || empty( $imageurl[ $size ] ) ) {
			if( $size === 'full' ) {
				$imageurl[ $size ] = wp_get_attachment_url( $id );
			} else {
				$imageurl[ $size ] = wp_get_attachment_image_src( $id, $size )[0];
			}
			set_transient( $cacheid, $imageurl );
		}

		return $imageurl[ $size ];

	}

	function get_image_reset( $value, $post_id, $field ) {

		$cacheid = 'ct_image_cache_' . get( $field['name'], $post_id );
		delete_transient( $cacheid );

		return $value;

	}
	add_filter('acf/update_value/type=image', 'get_image_reset', 10, 3);

	// get primary taxonomy id (works only with YOAST installed)
	if ( ! function_exists( 'get_primary_taxonomy_id' ) ) {
		function get_primary_taxonomy_id( $post_id, $taxonomy ) {
		    $prm_term = '';
		    if (class_exists('WPSEO_Primary_Term')) {
		        $wpseo_primary_term = new WPSEO_Primary_Term( $taxonomy, $post_id );
		        $prm_term = $wpseo_primary_term->get_primary_term();
		    }
		    if ( !is_object($wpseo_primary_term) && empty( $prm_term ) ) {
		        $term = wp_get_post_terms( $post_id, $taxonomy );
		        if (isset( $term ) && !empty( $term ) ) {
		            return wp_get_post_terms( $post_id, $taxonomy )[0]->term_id;
		        } else {
		            return '';
		        }
		    }
		    return $wpseo_primary_term->get_primary_term();
		}
	}

	/**
	 * custom dump function with pre tag and parameter to interrupt script.
	 * 
	 * @access public
	 * @param mixed $var
	 * @param bool $die (default: false)
	 * @return void
	 */
	function dump($var, $die = false) {
	    echo '<pre>' . print_r($var, 1) . '</pre>';
	    if ($die) {
	        die();
	    }
	}

	/**
	 * telephone_url function.
	 * 
	 * @access public
	 * @param mixed $number
	 * @return void
	 */
	function telephone_url($number) {
	    $nationalprefix = '+41';
	    $protocol       = 'tel:';

	    $formattedNumber = $number;
	    if ($formattedNumber !== '') {
	        // add national dialing code prefix to tel: link if it's not already set
	        if (strpos($formattedNumber, '00') !== 0 && strpos($formattedNumber, '0800') !== 0 && strpos($formattedNumber, '+') !== 0 && strpos($formattedNumber, $nationalprefix) !== 0) {
	            $formattedNumber = preg_replace('/^0/', $nationalprefix, $formattedNumber);
	        }
	    }

	    $formattedNumber = str_replace('(0)', '', $formattedNumber);
	    $formattedNumber = preg_replace('~[^0-9\+]~', '', $formattedNumber);
	    $formattedNumber = trim($formattedNumber);

	    return $protocol . $formattedNumber;
	}

	function get_excerpt( $id, $length = 25 ) {

		$excerpt = get_the_excerpt( (int) $id );

		if( empty( $excerpt ) )
			return false;

		$ea = explode( ' ', $excerpt );
		$excerpt = '';
		$amount = count( $ea );
		if( $amount > $length )
			$amount = $length;

		for( $i = 0; $i < $amount; $i++ ) {
			$excerpt .= $ea[ $i ] . ' ';
		}

		if( $amount === $length )
			$excerpt .= '…';

		return $excerpt;

	}