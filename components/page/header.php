<?php

	global $data;

	if( empty( $data ) && function_exists( 'get_field' ) ) {
		$data = (object) get_field( 'pageheader' );
	}

	if( empty( $data ) )
		$data = (object) [];

	if( empty( $data->type ) )
		$data->type = 'simple';

	get_component( 'pageheader', $data->type );
