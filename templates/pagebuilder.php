<?php
/*
Template Name: Rocketsky Seitenbaukasten
Template Post Type: page
*/

	get_component( 'page', 'header' );

	if( have_rows( 'pagebuilder' ) ) {

		while( have_rows( 'pagebuilder' ) ) {

			the_row();

			get_component( 'pagebuilder', get_row_layout() );

		}

	}

?>

