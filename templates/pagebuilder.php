<?php
/**
 * Template Name: Rocketsky Page Builder
 * Template Post Type: page
 *
 * Compose the optional pagebuilder through its protected library renderer.
 */
get_component( 'page', 'header', array( 'header' => skye_page_header_model() ) );
?>
<div class="container py-4">
	<?php while ( have_posts() ) : the_post(); ?>
		<?php skye_render_pagebuilder(); ?>
	<?php endwhile; ?>
</div>
