<?php

	add_filter( 'gform_address_display_format', 'address_format' );

	function address_format( $format ) {
		return 'zip_before_city';
	}

	add_filter( 'gform_countries', function () {
	    $countries = GF_Fields::get( 'address' )->get_default_countries();
	    asort( $countries );
	 
	    return $countries;
	} );

	// Force Gravity Forms to init scripts in the footer and ensure that the DOM is loaded before scripts are executed.
	add_filter( 'gform_init_scripts_footer', '__return_true' );
	add_filter( 'gform_cdata_open', 'wrap_gform_cdata_open', 1 );
	add_filter( 'gform_cdata_close', 'wrap_gform_cdata_close', 99 );
	
	function wrap_gform_cdata_open( $content = '' ) {
		if ( ! do_wrap_gform_cdata() ) {
			return $content;
		}
		$content = 'document.addEventListener( "DOMContentLoaded", function() { ' . $content;
		return $content;
	}
	
	function wrap_gform_cdata_close( $content = '' ) {
		if ( ! do_wrap_gform_cdata() ) {
			return $content;
		}
		$content .= ' }, false );';
		return $content;
	}
	
	function do_wrap_gform_cdata() {
		if (
			is_admin()
			|| ( defined( 'DOING_AJAX' ) && DOING_AJAX )
			|| isset( $_POST['gform_ajax'] )
			|| isset( $_GET['gf_page'] ) // Admin page (eg. form preview).
			|| doing_action( 'wp_footer' )
			|| did_action( 'wp_footer' )
		) {
			return false;
		}
		return true;
	}

