<?php
	
	function ct_acf_gmaps() {

		$theme_options = get_theme_options();

		$apikey = $theme_options[ 'gmakey' ];

		if( !empty( $apikey ) ) {
			acf_update_setting( 'google_api_key', $apikey );
		}
	}
	
	add_action('acf/init', 'ct_acf_gmaps');