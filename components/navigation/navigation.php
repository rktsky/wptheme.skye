<?php

	// prepare data
	global $theme_options, $data;

	if( empty( $data ) )
		$data = (object) [];

	$data->ulclass = 'nav';

?>

<div class="menu" aria-hidden="true">
	<div class="menu-container container-md">
		<div class="row mainrow">
			<div class="col-12 col-md-9">
				<nav class="navbar" role="navigation">
					<?php get_component( 'navigation', 'nav' ); ?>
				</nav>
			</div>
		</div>
	</div>
</div>