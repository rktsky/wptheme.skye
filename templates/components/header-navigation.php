<?php

	// prepare data
	global $theme_options;

?>

<div class="row">
	<div class="col-12" style="display: flex;">
		<nav class="navbar navbar-expand-md my-auto" role="navigation">

		    <!-- Brand and toggle get grouped for better mobile display -->
			<button class="navbar-toggler" type="button" data-toggle="navbar-collapse" data-target="#navbar-collapse-1" aria-controls="navbar-collapse-1" aria-expanded="false" aria-label="Toggle navigation">
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
			</button>
			<div class="container nav-area">
		
			        <?php
			        wp_nav_menu( array(
			            'theme_location'    => 'primary_navigation',
			            'depth'             => -1,
			            'container'         => 'div',
			            'container_class'   => 'navbar-collapse',
			            'container_id'      => 'navbar-collapse-1',
			            'menu_class'        => 'nav navbar-nav',
			            'fallback_cb'       => 'WP_Bootstrap_Navwalker::fallback',
			            'walker'            => new WP_Bootstrap_Navwalker()
					) );
			        ?>

		    </div>
		</nav>
	</div>
</div>