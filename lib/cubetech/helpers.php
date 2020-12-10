<?php


	function get_component( $name ) {
		$template = locate_template( 'templates/components/' . $name . '.php', false, false );
		if( !$template || empty( $template ) ) {
			echo '<!-- ERROR get_component(): Component ' . $name . ' not found! -->';
		} else {
			include( $template );
		}
	}
	
	// get field function for ACF without using ACF and generating additional queries
	function get( $selector, $post_id = false, $format_value = true ) {
	
		if( function_exists( 'get_post_meta' ) && function_exists( 'acf_get_valid_post_id' ) ) {
			$result = get_post_meta( acf_get_valid_post_id( $post_id ), $selector, $format_value );
			if( empty( $result ) )
				$result = get_user_meta( acf_get_valid_post_id( $post_id ), $selector, $format_value );
			return $result;
		} else {
			echo '<!-- ERROR get(): ACF not found. Please install. -->';
		}
	
	}	

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
	 * get_image_url_by_size function.
	 * 
	 * @access public
	 * @param bool $post_ID (default: false)
	 * @param string $size (default: '')
	 * @return void
	 */
	function get_image_url_by_size($post_ID = false, $size = '') {
	    if (empty($post_ID)) {
	        return false;
	    }
	    if ($size === '') {
	        $size = 'full';
	    }
	    $image = wp_get_attachment_image_src($post_ID, $size);
	    $image = $image[0];
	
	    return $image;
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
