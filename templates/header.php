<?php

	global $theme_options;
	
	// get name of website
	$blog_name = get_bloginfo( 'name' );
  
?>

<header class="banner navbar navbar-default navbar-static-top" role="banner">
	<div class="container">
		<div class="navbar-header">
			<button type="button" class="nav-toggle visible-xs">
				<span class="sr-only"><?= __( 'Toggle navigation', THEME_TEXT_DOMAIN ); ?></span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
			</button>
			<a class="navbar-brand" title="<?php echo $blog_name; ?>" href="<?= esc_url( home_url( '/' ) ); ?>">
				<?php if( isset( $theme_options['theme_logo'] ) && $theme_options['theme_logo'] !== false ) : ?>
					<img src="<?php echo $theme_options['theme_logo']; ?>">
				<?php else : ?>
					<?php echo $blog_name; ?>
				<?php endif; ?>
			</a>
		</div>
		<nav class="primary-navigation pull-right" role="navigation">
			<?php
				if (has_nav_menu('primary_navigation')) :
					wp_nav_menu(['theme_location' => 'primary_navigation', 'walker' => new wp_bootstrap_navwalker(), 'menu_class' => 'nav navbar-nav']);
				endif;
			?>
		</nav>
	</div>
</header>