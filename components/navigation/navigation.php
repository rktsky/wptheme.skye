<?php

	// prepare data
	global $theme_options;

?>

<div class="menu" aria-hidden="true">
	<div class="menu-container container-md">
		<div class="row justify-content-md-center">
			<div class="col-md-auto d-none d-md-block navbar-language">
				<?php get_component( 'navigation', 'language' ); ?>
			</div>
		</div>
		<div class="row mainrow">
			<div class="col-12 col-md-9">
				<nav class="navbar" role="navigation">
					<?php get_component( 'navigation', 'nav' ); ?>
				</nav>
				<nav class="navbar-small" role="navigation">
					<?php get_component( 'navigation', 'nav-small' ); ?>
				</nav>
			</div>
<!--
			<div class="col-12 col-md-3">
				...
			</div>
-->
		</div>
		<nav class="navbar-right content" role="navigation">
			<?php get_component( 'navigation', 'nav-social' ); ?>
		</nav>
	</div>
</div>