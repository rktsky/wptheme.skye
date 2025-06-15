<?php

	// prepare data
	global $theme_options, $data, $Rocketsky_user;

	if( empty( $data ) )
		$data = (object) [];

	$data->ulclass = 'navbar-nav';

?>

<nav class="navbar navbar-expand-lg navbar-light">
	<?php get_component( 'header', 'brand' ); ?>
	<div class="navbar-collapse collapse" id="navbarNav">
		<?php get_component( 'navigation', 'nav' ); ?>
	</div>
	<button class="menu-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
		<span class="icon-bar one"></span>
		<span class="icon-bar two"></span>
		<span class="icon-bar three"></span>
	</button>


</nav>
