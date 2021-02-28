<?php

use Roots\Sage\Setup;
use Roots\Sage\Wrapper;
use Cubetech\Theme\Packages\Templates;

global $theme_options, $wp_query;

$body_class = [];

?>
	
<!doctype html>
<html <?php language_attributes(); ?>>

	<head>
		<meta charset="utf-8">
		<meta http-equiv="x-ua-compatible" content="ie=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<?php wp_head(); ?>
	</head>

	<body <?php body_class( $body_class ); ?>>
		<!--[if IE]>
			<div class="alert alert-warning">
				<?php _e('You are using an <strong>outdated</strong> browser. Please <a href="http://browsehappy.com/">upgrade your browser</a> to improve your experience.', 'sage'); ?>
			</div>
		<![endif]-->
		<?php
			do_action('get_header');
			get_component( 'header' );
		?>
		<main class="main" role="document">
			<?php
			
				if( empty( $templates ) || !is_object( $templates ) )
					$templates = new Templates();
			
				include( $templates->template() );
			
			?>
		</main><!-- /.main -->
		<?php
			do_action('get_footer');
			get_component( 'footer' );
			wp_footer();
		?>
		<div class="ct-loader"></div>
	</body>
</html>
