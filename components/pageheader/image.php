<?php

	global $data;

	if( !empty( $data->lead ) )
		$data->headerlead = $data->lead;

?>

<?php get_component( 'pageheader', 'pageimage' ); ?>
