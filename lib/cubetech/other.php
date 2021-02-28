<?php

function ct_allow_webp( $mime ) {

	$mime[ 'webp' ] = 'image/webp';
	return $mime;

}
add_filter( 'mime_types', 'ct_allow_webp' );