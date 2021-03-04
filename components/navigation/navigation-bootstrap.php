<?php

	// prepare data
	global $theme_options, $data;

	if( empty( $data ) )
		$data = (object) [];

	$data->ulclass = 'navbar-nav';

?>

<nav class="navbar navbar-expand-lg navbar-light fixed-top">
	<div class="container-fluid">
		<?php get_component( 'header', 'brand' ); ?>
		<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
			<span class="navbar-toggler-icon"></span>
		</button>
		<div class="collapse navbar-collapse" id="navbarNav">
			<?php get_component( 'navigation', 'nav' ); ?>
		</div>
	</div>
</nav>
