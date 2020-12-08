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
